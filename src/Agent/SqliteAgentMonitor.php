<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Observability\HealthReporter;
use SquirrelForge\Observability\SqliteAlertManager;

/**
 * Interprets agent-specific health signals surfaced by
 * `27_OBSERVABILITY` and keeps each agent's operational health status
 * current, per 16_AGENTS/AGENT-MONITOR.md -- the seventh real
 * component in 16_AGENTS's governance/coordination gap, and the first
 * half of the mutual `AGENT-MANAGER.md`/`AGENT-MONITOR.md` reference
 * pair (`AGENT-MANAGER.md` names this spec as a dependency; this spec
 * names `AGENT-MANAGER.md` right back).
 *
 * That mutual reference is resolved the same way `20_EXECUTION`'s
 * `SqliteCheckpointManager`/`SqliteResultCollector` pair and
 * `17_COORDINATION`'s `SqlitePriorityManager`/`SqliteTaskQueue` pair
 * already were: a spec's "Depends On" often names who it *reports to*
 * (an output relationship), not code it must call. "Update the agent's
 * health status in the Agent Manager" (Responsibilities) is honored by
 * this class owning the authoritative current-health record itself and
 * exposing `currentHealth()` -- the same "leaf authority publishes,
 * consumers read" shape `SqliteAgentLifecycle::currentState()` already
 * established -- so the not-yet-built Agent Manager can compose this
 * class directly once it exists, rather than this class reaching
 * forward into a component that doesn't yet exist.
 *
 * "The Monitor interprets and reports. It does not collect telemetry,
 * compute metrics, or fire alerts itself" (Purpose) is upheld by
 * genuine composition rather than reimplementation: health
 * classification is entirely the already-real `HealthReporter::componentHealth()`,
 * which itself only reasons from `SqliteAlertManager`/`MetricsManager`/
 * `DiagnosticsEngine` evidence the caller opts into -- this class never
 * invents a threshold or an anomaly rule of its own, satisfying
 * "monitoring must not proceed against undefined or missing thresholds
 * by inventing its own" (Inputs) simply by never composing a threshold
 * path outside what `HealthReporter`'s own caller-supplied `options`
 * already carry.
 *
 * `HealthReporter`'s own real four-value state vocabulary
 * (`Healthy`/`Degraded`/`Unhealthy`/`Unknown`) maps directly onto this
 * spec's own four-value Health Status (`NORMAL`/`DEGRADED`/`CRITICAL`/
 * `UNKNOWN`) -- a clean one-to-one translation between two genuinely
 * different real vocabularies, the same "different owners, different
 * real vocabularies" treatment already established by `SqliteMessageBus`
 * and `AgentCommunication`.
 *
 * "Request an alert from `27_OBSERVABILITY`'s Alert Manager when a
 * breach is detected, rather than notifying directly" composes the
 * already-real `SqliteAlertManager::create()` directly -- fired only on
 * `CRITICAL`, the one Health Status value the spec's own table defines
 * as "a critical threshold is breached," never on `DEGRADED` (defined
 * as remaining "eligible for work"), so this class never over-alerts
 * past what the spec's own words actually describe as a breach.
 *
 * SQLite-backed for "record monitoring events for historical trend
 * analysis and audit" (Responsibilities) and the explicit Monitor
 * Record table.
 */
final class SqliteAgentMonitor
{
    /** HealthReporter's own real state vocabulary, mapped onto this spec's own Health Status. */
    private const STATE_TO_HEALTH_STATUS = [
        'Healthy' => 'NORMAL',
        'Degraded' => 'DEGRADED',
        'Unhealthy' => 'CRITICAL',
        'Unknown' => 'UNKNOWN',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?HealthReporter $healthReporter = null,
        private readonly ?SqliteAlertManager $alertManager = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS agent_monitor_events (
                monitor_event_id TEXT PRIMARY KEY,
                agent_id TEXT NOT NULL,
                status TEXT NOT NULL,
                observed_evidence_json TEXT NOT NULL,
                alert_requested INTEGER NOT NULL,
                alert_id TEXT,
                created_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS agent_monitor_current_health (
                agent_id TEXT PRIMARY KEY,
                status TEXT NOT NULL,
                monitor_event_id TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Monitoring Process steps 1-7.
     *
     * @param array{
     *     agent_id?: ?string,
     *     health_options?: array{metric_threshold?: array{metric: string, operator: string, threshold: float, aggregate_type?: string}, anomaly_metric?: string, trace_id?: string, staleness_seconds?: int}
     * } $request
     * @return array{
     *     outcome: string,
     *     monitor_event_id: ?string,
     *     agent_id: ?string,
     *     status: ?string,
     *     alert_requested: bool,
     *     alert_id: ?string,
     *     error: ?string
     * }
     */
    public function monitor(array $request): array
    {
        $agentId = $request['agent_id'] ?? null;

        if (!$this->present($agentId)) {
            return $this->envelope('invalid', null, null, null, false, null, 'Monitoring requires a non-empty agent_id.');
        }

        if ($this->healthReporter === null) {
            return $this->recordAndEnvelope($agentId, 'UNKNOWN', [], false, null, null);
        }

        $healthResult = $this->healthReporter->componentHealth($agentId, $request['health_options'] ?? []);
        $status = self::STATE_TO_HEALTH_STATUS[$healthResult['state']];

        $alertId = null;
        $alertRequested = false;

        if ($status === 'CRITICAL' && $this->alertManager !== null) {
            $alert = $this->alertManager->create($agentId, 'agent_health', 'critical', $healthResult['observed_evidence']);
            $alertRequested = true;
            $alertId = $alert['alert_id'];
        }

        return $this->recordAndEnvelope($agentId, $status, $healthResult['observed_evidence'], $alertRequested, $alertId, null);
    }

    /**
     * The current, authoritative health status for an agent -- what
     * `16_AGENTS/AGENT-MANAGER.md` will read once it exists, the same
     * "leaf authority publishes, consumers read" shape
     * `SqliteAgentLifecycle::currentState()` already established.
     *
     * @return ?array<string, mixed>
     */
    public function currentHealth(string $agentId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM agent_monitor_current_health WHERE agent_id = :agent_id');
        $statement->execute(['agent_id' => $agentId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * "Record monitoring events for historical trend analysis and
     * audit" -- every monitoring event for an agent, in the order it
     * was recorded.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $agentId): array
    {
        $statement = $this->database->prepare('SELECT * FROM agent_monitor_events WHERE agent_id = :agent_id ORDER BY rowid ASC');
        $statement->execute(['agent_id' => $agentId]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $observedEvidence
     * @return array{outcome: string, monitor_event_id: ?string, agent_id: ?string, status: ?string, alert_requested: bool, alert_id: ?string, error: ?string}
     */
    private function recordAndEnvelope(string $agentId, string $status, array $observedEvidence, bool $alertRequested, ?string $alertId, ?string $error): array
    {
        $monitorEventId = 'monitor_event_' . bin2hex(random_bytes(12));
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $insert = $this->database->prepare(
            'INSERT INTO agent_monitor_events (
                monitor_event_id, agent_id, status, observed_evidence_json, alert_requested, alert_id, created_at
            ) VALUES (
                :monitor_event_id, :agent_id, :status, :observed_evidence_json, :alert_requested, :alert_id, :created_at
            )'
        );
        $insert->execute([
            'monitor_event_id' => $monitorEventId,
            'agent_id' => $agentId,
            'status' => $status,
            'observed_evidence_json' => json_encode($observedEvidence, JSON_THROW_ON_ERROR),
            'alert_requested' => $alertRequested ? 1 : 0,
            'alert_id' => $alertId,
            'created_at' => $now,
        ]);

        $upsert = $this->database->prepare(
            'INSERT INTO agent_monitor_current_health (agent_id, status, monitor_event_id, updated_at)
             VALUES (:agent_id, :status, :monitor_event_id, :updated_at)
             ON CONFLICT(agent_id) DO UPDATE SET status = excluded.status, monitor_event_id = excluded.monitor_event_id, updated_at = excluded.updated_at'
        );
        $upsert->execute([
            'agent_id' => $agentId,
            'status' => $status,
            'monitor_event_id' => $monitorEventId,
            'updated_at' => $now,
        ]);

        return $this->envelope('monitored', $monitorEventId, $agentId, $status, $alertRequested, $alertId, $error);
    }

    /**
     * @return array{outcome: string, monitor_event_id: ?string, agent_id: ?string, status: ?string, alert_requested: bool, alert_id: ?string, error: ?string}
     */
    private function envelope(string $outcome, ?string $monitorEventId, ?string $agentId, ?string $status, bool $alertRequested, ?string $alertId, ?string $error): array
    {
        return [
            'outcome' => $outcome,
            'monitor_event_id' => $monitorEventId,
            'agent_id' => $agentId,
            'status' => $status,
            'alert_requested' => $alertRequested,
            'alert_id' => $alertId,
            'error' => $error,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['observed_evidence'] = json_decode((string) $row['observed_evidence_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['alert_requested'] = (bool) $row['alert_requested'];
        unset($row['observed_evidence_json']);

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
