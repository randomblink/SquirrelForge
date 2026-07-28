<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use PDO;
use SquirrelForge\Contracts\SecurityEventSinkInterface;
use SquirrelForge\Contracts\ProviderReadinessInterface;

final class SqliteSecurityEventSink implements SecurityEventSinkInterface, ProviderReadinessInterface
{
    private PDO $database;

    public function __construct(string $databasePath)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS security_events (
                event_ref TEXT PRIMARY KEY,
                event_type TEXT NOT NULL,
                outcome TEXT NOT NULL,
                identity_ref TEXT,
                correlation_id TEXT NOT NULL,
                metadata_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    public function providerRef(): string
    {
        return 'security-events:sqlite-local';
    }

    public function isProductionReady(): bool
    {
        return false;
    }

    public function record(
        string $eventType,
        string $outcome,
        ?string $identityRef,
        string $correlationId,
        array $metadata = []
    ): string {
        $eventRef = 'security_event_' . bin2hex(random_bytes(12));
        $statement = $this->database->prepare(
            'INSERT INTO security_events (
                event_ref, event_type, outcome, identity_ref,
                correlation_id, metadata_json, created_at
            ) VALUES (
                :event_ref, :event_type, :outcome, :identity_ref,
                :correlation_id, :metadata_json, :created_at
            )'
        );
        $statement->execute([
            'event_ref' => $eventRef,
            'event_type' => $eventType,
            'outcome' => $outcome,
            'identity_ref' => $identityRef,
            'correlation_id' => $correlationId,
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        return $eventRef;
    }

    public function events(string $identityRef): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM security_events WHERE identity_ref = :identity_ref ORDER BY created_at, event_ref'
        );
        $statement->execute(['identity_ref' => $identityRef]);

        return $statement->fetchAll();
    }

    /**
     * Most recent events across all identities, excluding metadata_json.
     *
     * @return array<int, array{event_ref: string, event_type: string, outcome: string, identity_ref: ?string, correlation_id: string, created_at: string}>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT event_ref, event_type, outcome, identity_ref, correlation_id, created_at
             FROM security_events
             ORDER BY created_at DESC, event_ref DESC
             LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
