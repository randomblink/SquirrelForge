<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\AuthenticationManagerInterface;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Validates a message's structure and, when wired to the Security layer,
 * its source authentication and destination authorization, per
 * 36_COMMUNICATION/MESSAGE-VALIDATOR.md.
 *
 * This is a deliberately scoped subset of that spec: identity verification
 * delegates to real, existing components (AuthenticationManagerInterface /
 * AuthorizationManagerInterface from 24_SECURITY) rather than reimplementing
 * them, and is genuinely skipped -- recorded as `not_applicable`, not
 * silently treated as passing -- when the caller doesn't inject one.
 * Malware/exploit detection, replay protection, and rate-limit
 * verification are not modeled: no real detector, replay store, or rate
 * limiter exists to back them, and fabricating one here would violate the
 * spec's own rule against approving messages while pretending a check ran.
 * For the same reason, only three of the seven listed validation states
 * are produced -- `valid`, `rejected` (structural/schema failure), and
 * `blocked` (authentication or authorization failure) -- since
 * "pending review", "expired", and "quarantined" all need a review queue,
 * expiry policy, or quarantine store this framework doesn't have.
 *
 * Unlike SqliteRecoveryManager/SqliteSelfHealingEngine, which refuse to
 * even persist a record for a request they won't act on, every call here
 * is persisted regardless of outcome: producing the audit record *is* this
 * component's job, per the spec's "Message Rejection Report" and "Message
 * validation audit records" outputs.
 */
final class SqliteMessageValidator
{
    private const PRIORITIES = ['emergency', 'critical', 'high', 'normal', 'low', 'informational'];
    private const REQUIRED_STRING_FIELDS = ['source', 'destination', 'operation'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?AuthenticationManagerInterface $authentication = null,
        private readonly ?AuthorizationManagerInterface $authorization = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS message_validations (
                validation_ref TEXT PRIMARY KEY,
                message_id TEXT,
                source TEXT,
                destination TEXT,
                status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                authentication_status TEXT NOT NULL,
                authorization_status TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{message_id?: string, source?: string, destination?: string, operation?: string, payload?: array<string, mixed>, priority?: string} $message
     * @param array{access_token?: ?string, permission_ref?: ?string, correlation_id?: string} $context
     * @return array{validation_ref: string, message_id: ?string, source: ?string, destination: ?string, status: string, governance_status: string, authentication_status: string, authorization_status: string, error: ?string}
     */
    public function validate(array $message, array $context = []): array
    {
        $messageId = $message['message_id'] ?? null;
        $source = $message['source'] ?? null;
        $destination = $message['destination'] ?? null;

        $structuralError = $this->checkStructure($message);

        if ($structuralError !== null) {
            return $this->persist($messageId, $source, $destination, 'rejected', 'not_applicable', 'not_applicable', $structuralError);
        }

        $correlationId = $context['correlation_id'] ?? ('correlation_' . bin2hex(random_bytes(8)));
        $authenticationStatus = 'not_applicable';
        $identityRef = $source;

        if ($this->authentication !== null) {
            $result = $this->authentication->authenticate($context['access_token'] ?? null, $source, $correlationId);

            if (!$result['authenticated']) {
                return $this->persist($messageId, $source, $destination, 'blocked', 'failed', 'not_applicable', 'Message source failed authentication.');
            }

            $authenticationStatus = 'verified';
            $identityRef = $result['identity_ref'] ?? $source;
        }

        $authorizationStatus = 'not_applicable';

        if ($this->authorization !== null) {
            $result = $this->authorization->authorize(
                $identityRef,
                $context['permission_ref'] ?? $message['operation'],
                $message['operation'],
                $destination,
                $correlationId
            );

            if (!$result['authorized']) {
                return $this->persist($messageId, $source, $destination, 'blocked', $authenticationStatus, 'denied', 'Destination authorization was denied.');
            }

            $authorizationStatus = 'granted';
        }

        return $this->persist($messageId, $source, $destination, 'valid', $authenticationStatus, $authorizationStatus, null);
    }

    /**
     * @param array<string, mixed> $message
     */
    private function checkStructure(array $message): ?string
    {
        foreach (self::REQUIRED_STRING_FIELDS as $field) {
            if (!isset($message[$field]) || !is_string($message[$field]) || $message[$field] === '') {
                return sprintf('Missing or invalid required field "%s".', $field);
            }
        }

        if (!isset($message['payload']) || !is_array($message['payload'])) {
            return 'Missing or invalid required field "payload".';
        }

        if (isset($message['priority']) && !in_array($message['priority'], self::PRIORITIES, true)) {
            return sprintf('Unknown priority "%s".', $message['priority']);
        }

        return null;
    }

    /**
     * @return array{validation_ref: string, message_id: ?string, source: ?string, destination: ?string, status: string, governance_status: string, authentication_status: string, authorization_status: string, error: ?string}
     */
    private function persist(
        ?string $messageId,
        ?string $source,
        ?string $destination,
        string $status,
        string $authenticationStatus,
        string $authorizationStatus,
        ?string $error
    ): array {
        $validationRef = 'message_validation_' . bin2hex(random_bytes(12));
        $createdAt = gmdate(DATE_RFC3339);
        $governanceStatus = 'ungoverned';

        $statement = $this->database->prepare(
            'INSERT INTO message_validations (
                validation_ref, message_id, source, destination, status, governance_status,
                authentication_status, authorization_status, error, created_at
            ) VALUES (
                :validation_ref, :message_id, :source, :destination, :status, :governance_status,
                :authentication_status, :authorization_status, :error, :created_at
            )'
        );
        $statement->execute([
            'validation_ref' => $validationRef,
            'message_id' => $messageId,
            'source' => $source,
            'destination' => $destination,
            'status' => $status,
            'governance_status' => $governanceStatus,
            'authentication_status' => $authenticationStatus,
            'authorization_status' => $authorizationStatus,
            'error' => $error,
            'created_at' => $createdAt,
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'message.validated',
            new DateTimeImmutable(),
            self::class,
            ['validation_ref' => $validationRef, 'status' => $status]
        ));

        return [
            'validation_ref' => $validationRef,
            'message_id' => $messageId,
            'source' => $source,
            'destination' => $destination,
            'status' => $status,
            'governance_status' => $governanceStatus,
            'authentication_status' => $authenticationStatus,
            'authorization_status' => $authorizationStatus,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validation(string $validationRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM message_validations WHERE validation_ref = :validation_ref'
        );
        $statement->execute(['validation_ref' => $validationRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM message_validations ORDER BY rowid DESC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
