<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Top-level coordinator for the Communication Layer, per
 * 36_COMMUNICATION/COMMUNICATION-MANAGER.md, mirroring how
 * SqliteResilienceManager coordinates the Resilience Layer.
 *
 * Of the spec's ten "Communication Types", five route to real
 * components: `agent_message` (SqliteAgentCommunicator), `notification`
 * (SqliteNotificationManager), `event` (the real EventBus, which is
 * always available since it's how this class dispatches its own audit
 * event too), the generic `user_message`/`service_message`/
 * `workflow_message`/`integration_message` group (SqliteMessageBroker,
 * the general-purpose validated router), and `governance_message`
 * (SqliteCommunicationGovernance::review()) -- the same "coordinator
 * predates its own specialist" resolution this codebase's other
 * coordinators have already gone through (Communication Manager:
 * 2026-07-28; Communication Governance: 2026-08-04). `system_message`
 * and `audit_message` have no real destination -- no System Message
 * concept and no audit pipe distinct from what each component already
 * writes on its own -- so those, and any unrecognized type, are
 * escalated rather than silently dropped or routed somewhere invented,
 * per the spec's rule against routing unauthorized communication.
 *
 * `event` dispatch is fire-and-forget by the real EventBus's own design
 * (it has no per-listener success/failure signal), so its delivery
 * status is always `dispatched` -- that is the accurate outcome, not an
 * optimistic guess. Governance status is the fixed constant `ungoverned`
 * for every type except `governance_message` itself, which records the
 * real decision `SqliteCommunicationGovernance::review()` returned.
 */
final class SqliteCommunicationManager
{
    private const REQUIRED_FIELDS = [
        'agent_message' => ['source_agent', 'destination_agent', 'message_type'],
        'notification' => ['recipient', 'channel', 'priority'],
        'user_message' => ['source', 'destination', 'operation'],
        'service_message' => ['source', 'destination', 'operation'],
        'workflow_message' => ['source', 'destination', 'operation'],
        'integration_message' => ['source', 'destination', 'operation'],
        'event' => ['name'],
        'governance_message' => ['communication_component', 'policy_context', 'reviewer'],
    ];

    private const UNSUPPORTED_TYPES = ['system_message', 'audit_message'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly SqliteMessageBroker $broker,
        private readonly SqliteNotificationManager $notifications,
        private readonly SqliteAgentCommunicator $agentCommunicator,
        private readonly EventBusInterface $events,
        private readonly ?SqliteCommunicationGovernance $governance = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS communication_operations (
                communication_ref TEXT PRIMARY KEY,
                type TEXT NOT NULL,
                source TEXT,
                destination TEXT,
                validation_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                delivery_status TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options forwarded to whichever component handles this type
     * @return array{communication_ref: string, type: string, source: ?string, destination: ?string, validation_status: string, governance_status: string, delivery_status: string, error: ?string}
     */
    public function coordinate(string $type, array $payload, array $options = []): array
    {
        if (in_array($type, self::UNSUPPORTED_TYPES, true)) {
            return $this->persist($type, null, null, 'not_applicable', 'escalated', sprintf('Communication type "%s" has no implemented destination component.', $type));
        }

        if (!isset(self::REQUIRED_FIELDS[$type])) {
            return $this->persist($type, null, null, 'invalid', 'rejected', sprintf('Unknown communication type "%s".', $type));
        }

        $missingField = $this->firstMissingField($type, $payload);

        if ($missingField !== null) {
            return $this->persist($type, null, null, 'invalid', 'rejected', sprintf('Missing required field "%s" for communication type "%s".', $missingField, $type));
        }

        return match ($type) {
            'agent_message' => $this->coordinateAgentMessage($payload, $options),
            'notification' => $this->coordinateNotification($payload, $options),
            'event' => $this->coordinateEvent($payload),
            'governance_message' => $this->coordinateGovernanceMessage($payload),
            default => $this->coordinateGenericMessage($type, $payload, $options),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function firstMissingField(string $type, array $payload): ?string
    {
        foreach (self::REQUIRED_FIELDS[$type] as $field) {
            if (!isset($payload[$field]) || $payload[$field] === '') {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateAgentMessage(array $payload, array $options): array
    {
        $result = $this->agentCommunicator->send(
            $payload['source_agent'],
            $payload['destination_agent'],
            $payload['message_type'],
            $payload['body'] ?? [],
            $options
        );

        return $this->persist('agent_message', $payload['source_agent'], $payload['destination_agent'], 'valid', $result['delivery_status'], $result['error']);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateNotification(array $payload, array $options): array
    {
        $result = $this->notifications->send(
            $payload['recipient'],
            $payload['channel'],
            $payload['priority'],
            $payload['body'] ?? [],
            $options['max_attempts'] ?? 3
        );

        return $this->persist('notification', null, $payload['recipient'], 'valid', $result['status'], $result['error']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function coordinateEvent(array $payload): array
    {
        $this->events->dispatch(new Event(
            uniqid('evt_', true),
            $payload['name'],
            new DateTimeImmutable(),
            $payload['source'] ?? self::class,
            $payload['body'] ?? []
        ));

        return $this->persist('event', $payload['source'] ?? null, null, 'valid', 'dispatched', null);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function coordinateGovernanceMessage(array $payload): array
    {
        if ($this->governance === null) {
            return $this->persist('governance_message', null, null, 'not_applicable', 'rejected', 'Communication Governance is not configured.');
        }

        $result = $this->governance->review($payload);

        return $this->persist('governance_message', null, $payload['communication_component'], 'valid', 'reviewed', $result['error'], $result['decision']);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateGenericMessage(string $type, array $payload, array $options): array
    {
        $result = $this->broker->route([
            'message_id' => $type . '_' . bin2hex(random_bytes(12)),
            'source' => $payload['source'],
            'destination' => $payload['destination'],
            'operation' => $payload['operation'],
            'payload' => $payload['body'] ?? [],
            ...isset($payload['priority']) ? ['priority' => $payload['priority']] : [],
        ], $options);

        return $this->persist($type, $payload['source'], $payload['destination'], 'valid', $result['delivery_status'], $result['error']);
    }

    /**
     * @return array{communication_ref: string, type: string, source: ?string, destination: ?string, validation_status: string, governance_status: string, delivery_status: string, error: ?string}
     */
    private function persist(
        string $type,
        ?string $source,
        ?string $destination,
        string $validationStatus,
        string $deliveryStatus,
        ?string $error,
        string $governanceStatus = 'ungoverned'
    ): array {
        $communicationRef = 'communication_' . bin2hex(random_bytes(12));
        $createdAt = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO communication_operations (
                communication_ref, type, source, destination, validation_status,
                governance_status, delivery_status, error, created_at
            ) VALUES (
                :communication_ref, :type, :source, :destination, :validation_status,
                :governance_status, :delivery_status, :error, :created_at
            )'
        );
        $statement->execute([
            'communication_ref' => $communicationRef,
            'type' => $type,
            'source' => $source,
            'destination' => $destination,
            'validation_status' => $validationStatus,
            'governance_status' => $governanceStatus,
            'delivery_status' => $deliveryStatus,
            'error' => $error,
            'created_at' => $createdAt,
        ]);

        $this->events->dispatch(new Event(
            uniqid('evt_', true),
            'communication.finished',
            new DateTimeImmutable(),
            self::class,
            ['communication_ref' => $communicationRef, 'type' => $type, 'delivery_status' => $deliveryStatus]
        ));

        return [
            'communication_ref' => $communicationRef,
            'type' => $type,
            'source' => $source,
            'destination' => $destination,
            'validation_status' => $validationStatus,
            'governance_status' => $governanceStatus,
            'delivery_status' => $deliveryStatus,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function operation(string $communicationRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM communication_operations WHERE communication_ref = :communication_ref'
        );
        $statement->execute(['communication_ref' => $communicationRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM communication_operations ORDER BY rowid DESC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
