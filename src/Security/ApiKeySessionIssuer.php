<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use DateInterval;
use DateTimeImmutable;
use PDO;
use PDOException;
use SquirrelForge\Contracts\IdentityManagerInterface;
use SquirrelForge\Contracts\MfaVerifierInterface;
use SquirrelForge\Contracts\SecurityEventSinkInterface;
use SquirrelForge\Contracts\SecretsManagerInterface;

final class ApiKeySessionIssuer
{
    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly IdentityManagerInterface $identities,
        private readonly SecretsManagerInterface $secrets,
        private readonly SqliteAuthenticationManager $sessions,
        private readonly int $maximumFailures = 5,
        private readonly int $failureWindowSeconds = 300,
        private readonly int $lockoutSeconds = 900,
        private readonly MfaVerifierInterface $mfa = new DenyHumanMfaVerifier(),
        private readonly SecurityEventSinkInterface $events = new NullSecurityEventSink()
    ) {
        if ($maximumFailures < 1 || $failureWindowSeconds < 1 || $lockoutSeconds < 1) {
            throw new PDOException('Authentication throttle values must be positive.');
        }

        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->migrate();
    }

    public function issue(
        string $identityRef,
        string $apiKey,
        string $correlationId,
        string $sourceRef,
        ?string $mfaProof = null
    ): array {
        if ($this->isLocked($identityRef, $sourceRef)) {
            $this->events->record('AUTHENTICATION', 'LOCKED', $identityRef, $correlationId, [
                'source_ref' => $sourceRef,
            ]);
            return $this->failure(
                'AUTHENTICATION_LOCKED',
                'Authentication is temporarily unavailable.',
                false
            );
        }

        $verification = $this->secrets->verifyApiKey($identityRef, $apiKey);
        $verified = ($verification['verified'] ?? false) === true
            && $this->identities->isActive($identityRef);

        if (!$verified) {
            $this->recordFailure($identityRef, $sourceRef);
            $this->events->record('CREDENTIAL_VERIFICATION', 'FAILURE', $identityRef, $correlationId, [
                'source_ref' => $sourceRef,
            ]);

            return $this->failure(
                $this->isLocked($identityRef, $sourceRef)
                    ? 'AUTHENTICATION_LOCKED'
                    : 'INVALID_CREDENTIALS',
                'The credentials could not be verified.',
                false
            );
        }

        $identity = $this->identities->identity($identityRef);

        if (($identity['identity_type'] ?? null) === 'USER') {
            $mfa = $this->mfa->verify($identityRef, $mfaProof, $correlationId);

            if (($mfa['verified'] ?? false) !== true) {
                $this->recordFailure($identityRef, $sourceRef);
                $this->events->record('MFA_VERIFICATION', 'FAILURE', $identityRef, $correlationId, [
                    'source_ref' => $sourceRef,
                ]);

                return $this->failure('MFA_REQUIRED', 'Human identities require verified MFA.', false);
            }

            $this->events->record('MFA_VERIFICATION', 'SUCCESS', $identityRef, $correlationId, [
                'verification_ref' => $mfa['verification_ref'] ?? null,
            ]);
        }

        $this->clearFailures($identityRef, $sourceRef);
        $session = $this->sessions->issueSession(
            $identityRef,
            (string) $verification['verification_ref'],
            new DateInterval('PT15M')
        );
        $this->events->record('SESSION_ISSUED', 'SUCCESS', $identityRef, $correlationId, [
            'session_ref' => $session['session_ref'],
            'source_ref' => $sourceRef,
        ]);

        return [
            'authenticated' => true,
            'authentication_ref' => (string) $verification['verification_ref'],
            'identity_ref' => $identityRef,
            'session_ref' => $session['session_ref'],
            'access_token' => $session['access_token'],
            'expires_at' => $session['expires_at'],
            'correlation_id' => $correlationId,
        ];
    }

    private function isLocked(string $identityRef, string $sourceRef): bool
    {
        $record = $this->failureRecord($identityRef, $sourceRef);

        return $record !== null
            && $record['locked_until'] !== null
            && new DateTimeImmutable($record['locked_until']) > new DateTimeImmutable();
    }

    private function recordFailure(string $identityRef, string $sourceRef): void
    {
        $record = $this->failureRecord($identityRef, $sourceRef);
        $now = new DateTimeImmutable();
        $windowStart = $record !== null
            ? new DateTimeImmutable($record['window_started_at'])
            : $now;
        $withinWindow = $record !== null
            && $windowStart->modify('+' . $this->failureWindowSeconds . ' seconds') > $now;
        $count = $withinWindow ? ((int) $record['failure_count'] + 1) : 1;
        $windowStart = $withinWindow ? $windowStart : $now;
        $lockedUntil = $count >= $this->maximumFailures
            ? $now->modify('+' . $this->lockoutSeconds . ' seconds')->format(DATE_RFC3339)
            : null;
        $statement = $this->database->prepare(
            'INSERT INTO authentication_throttles (
                identity_ref, source_ref, window_started_at, failure_count, locked_until, updated_at
            ) VALUES (
                :identity_ref, :source_ref, :window_started_at, :failure_count, :locked_until, :updated_at
            )
            ON CONFLICT(identity_ref, source_ref) DO UPDATE SET
                window_started_at = excluded.window_started_at,
                failure_count = excluded.failure_count,
                locked_until = excluded.locked_until,
                updated_at = excluded.updated_at'
        );
        $statement->execute([
            'identity_ref' => $identityRef,
            'source_ref' => $sourceRef,
            'window_started_at' => $windowStart->format(DATE_RFC3339),
            'failure_count' => $count,
            'locked_until' => $lockedUntil,
            'updated_at' => $now->format(DATE_RFC3339),
        ]);
    }

    private function clearFailures(string $identityRef, string $sourceRef): void
    {
        $statement = $this->database->prepare(
            'DELETE FROM authentication_throttles
             WHERE identity_ref = :identity_ref AND source_ref = :source_ref'
        );
        $statement->execute([
            'identity_ref' => $identityRef,
            'source_ref' => $sourceRef,
        ]);
    }

    private function failureRecord(string $identityRef, string $sourceRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM authentication_throttles
             WHERE identity_ref = :identity_ref AND source_ref = :source_ref'
        );
        $statement->execute([
            'identity_ref' => $identityRef,
            'source_ref' => $sourceRef,
        ]);
        $record = $statement->fetch();

        return is_array($record) ? $record : null;
    }

    private function failure(string $type, string $message, bool $retryable): array
    {
        return [
            'authenticated' => false,
            'error' => [
                'type' => $type,
                'message' => $message,
                'retryable' => $retryable,
            ],
        ];
    }

    private function migrate(): void
    {
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS authentication_throttles (
                identity_ref TEXT NOT NULL,
                source_ref TEXT NOT NULL,
                window_started_at TEXT NOT NULL,
                failure_count INTEGER NOT NULL,
                locked_until TEXT,
                updated_at TEXT NOT NULL,
                PRIMARY KEY(identity_ref, source_ref)
            )'
        );
    }
}
