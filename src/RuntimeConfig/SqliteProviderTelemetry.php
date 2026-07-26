<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use PDO;
use SquirrelForge\Contracts\ProviderTelemetryInterface;

final class SqliteProviderTelemetry implements ProviderTelemetryInterface
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
            'CREATE TABLE IF NOT EXISTS provider_telemetry (
                telemetry_ref TEXT PRIMARY KEY,
                event_type TEXT NOT NULL,
                metadata_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    public function record(string $eventType, array $metadata = []): void
    {
        $safeMetadata = array_intersect_key($metadata, array_flip([
            'attempt',
            'status',
            'circuit_state',
            'operation_class',
        ]));
        $statement = $this->database->prepare(
            'INSERT INTO provider_telemetry (
                telemetry_ref, event_type, metadata_json, created_at
            ) VALUES (
                :telemetry_ref, :event_type, :metadata_json, :created_at
            )'
        );
        $statement->execute([
            'telemetry_ref' => 'provider_telemetry_' . bin2hex(random_bytes(12)),
            'event_type' => $eventType,
            'metadata_json' => json_encode($safeMetadata, JSON_THROW_ON_ERROR),
            'created_at' => gmdate(DATE_RFC3339),
        ]);
    }

    public function snapshot(): array
    {
        $rows = $this->database->query(
            'SELECT event_type, COUNT(*) AS event_count
             FROM provider_telemetry GROUP BY event_type'
        )->fetchAll();
        $counters = [];

        foreach ($rows as $row) {
            $counters[$row['event_type']] = (int) $row['event_count'];
        }

        $latest = $this->database->query(
            'SELECT event_type, metadata_json, created_at
             FROM provider_telemetry ORDER BY created_at DESC, telemetry_ref DESC LIMIT 1'
        )->fetch();
        $circuitState = 'CLOSED';

        if (is_array($latest)) {
            $metadata = json_decode($latest['metadata_json'], true, 512, JSON_THROW_ON_ERROR);
            $circuitState = is_string($metadata['circuit_state'] ?? null)
                ? $metadata['circuit_state']
                : ($latest['event_type'] === 'provider.circuit.opened' ? 'OPEN' : 'CLOSED');
        }

        return [
            'counters' => $counters,
            'circuit_state' => $circuitState,
            'last_event_at' => is_array($latest) ? $latest['created_at'] : null,
        ];
    }
}
