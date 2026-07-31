<?php

declare(strict_types=1);

namespace SquirrelForge\Observability;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Owns alert records, alert rule evaluation, alert status, duplicate
 * suppression, and escalation/notification requests, per
 * 27_OBSERVABILITY/ALERT-MANAGER.md.
 *
 * Unlike Log/Metrics/Trace Manager, Alert Manager genuinely owns
 * stateful data of its own -- an alert's lifecycle status is exactly
 * the kind of authoritative record those classes explicitly delegate
 * elsewhere -- so, alone among 27_OBSERVABILITY's producers so far,
 * this class owns a real SQLite database (`Sqlite` prefix, matching
 * every other stateful governance-shaped component in this codebase).
 * Alert transitions are appended to a history table, never rewritten,
 * the same append-only pattern SqliteObservabilityGovernance uses for
 * its own review history.
 *
 * evaluate() implements exactly two real rule types --
 * `metric_threshold` (against MetricsManager::thresholdEvidence()) and
 * `failure_path` (against DiagnosticsEngine::findFailurePath()) --
 * rather than a fabricated general rule DSL like RuleEngine's five
 * condition types. Those are the only two real evidence sources that
 * exist in this codebase today; inventing more would mean fabricating
 * an evidence type nothing produces yet.
 *
 * "Correlate related alerts and suppress duplicates" is a real,
 * structural check: create() looks for any existing alert with the
 * same (source, category) that is not yet Resolved and, if found,
 * inserts the new alert directly as `Suppressed` with a `duplicate_of`
 * reference instead of a fabricated time-windowed dedup policy the
 * spec doesn't define.
 *
 * Rule 1 ("may request escalation... but must not execute response
 * actions") is upheld literally: requestEscalation() only transitions
 * state and dispatches an event -- it has no delivery mechanism of any
 * kind. Rule 2 (severity is operational-only) is upheld by storing the
 * caller's severity verbatim, never reinterpreting it. Rule 3 (must not
 * close alerts without supplied resolution evidence) is an enforced
 * gate on resolve(): empty evidence is rejected outright.
 */
final class SqliteAlertManager
{
    private const OPEN_STATUSES = ['Open', 'Acknowledged', 'Escalation Requested'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?MetricsManager $metrics = null,
        private readonly ?DiagnosticsEngine $diagnostics = null,
        private readonly ?SqliteObservabilityGovernance $governance = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS alerts (
                alert_id TEXT PRIMARY KEY,
                source TEXT NOT NULL,
                category TEXT NOT NULL,
                severity TEXT NOT NULL,
                status TEXT NOT NULL,
                evidence_json TEXT NOT NULL,
                duplicate_of TEXT,
                resolution_evidence TEXT,
                retention_days INTEGER,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS alert_transitions (
                transition_ref TEXT PRIMARY KEY,
                alert_id TEXT NOT NULL,
                from_status TEXT,
                to_status TEXT NOT NULL,
                note TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{id: string, type: string, source?: string, category?: string, severity?: string, metric?: string, operator?: string, threshold?: float, aggregate_type?: string, trace_id?: string} $rule
     * @return array{rule_id: ?string, condition_met: bool, alert: ?array<string, mixed>, outcome: string, error: ?string}
     */
    public function evaluate(array $rule): array
    {
        $ruleId = $rule['id'] ?? null;

        if (!is_string($ruleId) || $ruleId === '') {
            return ['rule_id' => null, 'condition_met' => false, 'alert' => null, 'outcome' => 'invalid_rule', 'error' => 'Rule requires a non-empty "id".'];
        }

        return match ($rule['type'] ?? null) {
            'metric_threshold' => $this->evaluateMetricThresholdRule($rule),
            'failure_path' => $this->evaluateFailurePathRule($rule),
            default => ['rule_id' => $ruleId, 'condition_met' => false, 'alert' => null, 'outcome' => 'invalid_rule', 'error' => sprintf('Unknown or missing rule type "%s".', $rule['type'] ?? '')],
        };
    }

    /**
     * @param array<string, mixed> $evidence
     * @return array{alert_id: string, status: string, duplicate_of: ?string, outcome: string, error: ?string}
     */
    public function create(string $source, string $category, string $severity, array $evidence): array
    {
        $existing = $this->findOpenDuplicate($source, $category);
        $alertId = 'alert_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);
        $status = $existing !== null ? 'Suppressed' : 'Open';
        $retentionDays = $this->governance?->getStandard('alert')['retention_days'] ?? null;

        $insert = $this->database->prepare(
            'INSERT INTO alerts (
                alert_id, source, category, severity, status, evidence_json,
                duplicate_of, resolution_evidence, retention_days, created_at, updated_at
            ) VALUES (
                :alert_id, :source, :category, :severity, :status, :evidence_json,
                :duplicate_of, NULL, :retention_days, :created_at, :updated_at
            )'
        );
        $insert->execute([
            'alert_id' => $alertId,
            'source' => $source,
            'category' => $category,
            'severity' => $severity,
            'status' => $status,
            'evidence_json' => json_encode($evidence, JSON_THROW_ON_ERROR),
            'duplicate_of' => $existing['alert_id'] ?? null,
            'retention_days' => $retentionDays,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $note = $existing !== null ? sprintf('Duplicate of open alert "%s".', $existing['alert_id']) : 'Created.';
        $this->recordTransition($alertId, null, $status, $note);
        $this->publish($alertId, $status, $existing !== null ? 'alert.duplicate_suppressed' : 'alert.created');

        return ['alert_id' => $alertId, 'status' => $status, 'duplicate_of' => $existing['alert_id'] ?? null, 'outcome' => $existing !== null ? 'duplicate_suppressed' : 'created', 'error' => null];
    }

    /**
     * @return array{alert_id: string, status: string, outcome: string, error: ?string}
     */
    public function acknowledge(string $alertId, string $acknowledgedBy): array
    {
        if ($acknowledgedBy === '') {
            return ['alert_id' => $alertId, 'status' => '', 'outcome' => 'invalid_request', 'error' => '"acknowledgedBy" is required.'];
        }

        return $this->transition($alertId, ['Open'], 'Acknowledged', sprintf('Acknowledged by "%s".', $acknowledgedBy));
    }

    /**
     * @return array{alert_id: string, status: string, outcome: string, error: ?string}
     */
    public function suppress(string $alertId, string $reason): array
    {
        if ($reason === '') {
            return ['alert_id' => $alertId, 'status' => '', 'outcome' => 'invalid_request', 'error' => '"reason" is required.'];
        }

        return $this->transition($alertId, ['Open', 'Acknowledged'], 'Suppressed', $reason);
    }

    /**
     * Rule 1: transitions state and dispatches an event only. There is
     * no delivery mechanism here -- the owning communication or response
     * component executes the actual notification.
     *
     * @return array{alert_id: string, status: string, outcome: string, error: ?string}
     */
    public function requestEscalation(string $alertId, string $reason): array
    {
        if ($reason === '') {
            return ['alert_id' => $alertId, 'status' => '', 'outcome' => 'invalid_request', 'error' => '"reason" is required.'];
        }

        return $this->transition($alertId, ['Open', 'Acknowledged'], 'Escalation Requested', $reason);
    }

    /**
     * Rule 3: refuses to close an alert without non-empty resolution
     * evidence from the responsible owner.
     *
     * @return array{alert_id: string, status: string, outcome: string, error: ?string}
     */
    public function resolve(string $alertId, string $resolutionEvidence): array
    {
        if ($resolutionEvidence === '') {
            return ['alert_id' => $alertId, 'status' => '', 'outcome' => 'invalid_request', 'error' => 'Resolving an alert requires non-empty resolution evidence.'];
        }

        $result = $this->transition($alertId, ['Open', 'Acknowledged', 'Suppressed', 'Escalation Requested'], 'Resolved', $resolutionEvidence);

        if ($result['outcome'] === 'transitioned') {
            $update = $this->database->prepare('UPDATE alerts SET resolution_evidence = :resolution_evidence WHERE alert_id = :alert_id');
            $update->execute(['resolution_evidence' => $resolutionEvidence, 'alert_id' => $alertId]);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $alertId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM alerts WHERE alert_id = :alert_id');
        $statement->execute(['alert_id' => $alertId]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $alertId): array
    {
        $statement = $this->database->prepare('SELECT * FROM alert_transitions WHERE alert_id = :alert_id ORDER BY rowid ASC');
        $statement->execute(['alert_id' => $alertId]);

        return $statement->fetchAll();
    }

    /**
     * Every not-yet-resolved alert for a source. A retrofit: consumers
     * like Health Reporter genuinely need to ask "what's currently open
     * for this component", and until now the only lookup was by a
     * single known alert_id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function openFor(string $source): array
    {
        $placeholders = implode(',', array_fill(0, count(self::OPEN_STATUSES), '?'));
        $statement = $this->database->prepare(
            "SELECT * FROM alerts WHERE source = ? AND status IN ({$placeholders}) ORDER BY rowid DESC"
        );
        $statement->execute([$source, ...self::OPEN_STATUSES]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array{id: string, source?: string, category?: string, severity?: string, metric?: string, operator?: string, threshold?: float, aggregate_type?: string} $rule
     * @return array{rule_id: string, condition_met: bool, alert: ?array<string, mixed>, outcome: string, error: ?string}
     */
    private function evaluateMetricThresholdRule(array $rule): array
    {
        $ruleId = $rule['id'];

        if ($this->metrics === null) {
            return ['rule_id' => $ruleId, 'condition_met' => false, 'alert' => null, 'outcome' => 'not_configured', 'error' => 'Metrics Manager is not configured.'];
        }

        foreach (['source', 'category', 'severity', 'metric', 'operator', 'threshold'] as $field) {
            if (!isset($rule[$field])) {
                return ['rule_id' => $ruleId, 'condition_met' => false, 'alert' => null, 'outcome' => 'invalid_rule', 'error' => sprintf('Metric threshold rule requires "%s".', $field)];
            }
        }

        $evidence = $this->metrics->thresholdEvidence($rule['metric'], $rule['operator'], (float) $rule['threshold'], $rule['aggregate_type'] ?? 'mean');

        if ($evidence['satisfied'] !== true) {
            return ['rule_id' => $ruleId, 'condition_met' => false, 'alert' => null, 'outcome' => 'condition_not_met', 'error' => null];
        }

        $alert = $this->create($rule['source'], $rule['category'], $rule['severity'], $evidence);

        return ['rule_id' => $ruleId, 'condition_met' => true, 'alert' => $alert, 'outcome' => $alert['outcome'], 'error' => null];
    }

    /**
     * @param array{id: string, source?: string, category?: string, severity?: string, trace_id?: string} $rule
     * @return array{rule_id: string, condition_met: bool, alert: ?array<string, mixed>, outcome: string, error: ?string}
     */
    private function evaluateFailurePathRule(array $rule): array
    {
        $ruleId = $rule['id'];

        if ($this->diagnostics === null) {
            return ['rule_id' => $ruleId, 'condition_met' => false, 'alert' => null, 'outcome' => 'not_configured', 'error' => 'Diagnostics Engine is not configured.'];
        }

        foreach (['source', 'category', 'severity', 'trace_id'] as $field) {
            if (!isset($rule[$field])) {
                return ['rule_id' => $ruleId, 'condition_met' => false, 'alert' => null, 'outcome' => 'invalid_rule', 'error' => sprintf('Failure path rule requires "%s".', $field)];
            }
        }

        $findings = $this->diagnostics->findFailurePath($rule['trace_id']);

        if ($findings['observed_failed_spans'] === []) {
            return ['rule_id' => $ruleId, 'condition_met' => false, 'alert' => null, 'outcome' => 'condition_not_met', 'error' => null];
        }

        $alert = $this->create($rule['source'], $rule['category'], $rule['severity'], $findings);

        return ['rule_id' => $ruleId, 'condition_met' => true, 'alert' => $alert, 'outcome' => $alert['outcome'], 'error' => null];
    }

    /**
     * @param array<int, string> $allowedFromStatuses
     * @return array{alert_id: string, status: string, outcome: string, error: ?string}
     */
    private function transition(string $alertId, array $allowedFromStatuses, string $toStatus, string $note): array
    {
        $alert = $this->get($alertId);

        if ($alert === null) {
            return ['alert_id' => $alertId, 'status' => '', 'outcome' => 'not_found', 'error' => sprintf('Unknown alert "%s".', $alertId)];
        }

        if (!in_array($alert['status'], $allowedFromStatuses, true)) {
            return ['alert_id' => $alertId, 'status' => $alert['status'], 'outcome' => 'invalid_transition', 'error' => sprintf('Cannot move alert from "%s" to "%s".', $alert['status'], $toStatus)];
        }

        $update = $this->database->prepare('UPDATE alerts SET status = :status, updated_at = :updated_at WHERE alert_id = :alert_id');
        $update->execute(['status' => $toStatus, 'updated_at' => gmdate(DATE_RFC3339), 'alert_id' => $alertId]);

        $this->recordTransition($alertId, $alert['status'], $toStatus, $note);
        $this->publish($alertId, $toStatus, 'alert.' . str_replace(' ', '_', strtolower($toStatus)));

        return ['alert_id' => $alertId, 'status' => $toStatus, 'outcome' => 'transitioned', 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOpenDuplicate(string $source, string $category): ?array
    {
        $placeholders = implode(',', array_fill(0, count(self::OPEN_STATUSES), '?'));
        $statement = $this->database->prepare(
            "SELECT * FROM alerts WHERE source = ? AND category = ? AND status IN ({$placeholders}) ORDER BY rowid DESC LIMIT 1"
        );
        $statement->execute([$source, $category, ...self::OPEN_STATUSES]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function recordTransition(string $alertId, ?string $fromStatus, string $toStatus, string $note): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO alert_transitions (transition_ref, alert_id, from_status, to_status, note, created_at)
             VALUES (:transition_ref, :alert_id, :from_status, :to_status, :note, :created_at)'
        );
        $statement->execute([
            'transition_ref' => 'alert_transition_' . bin2hex(random_bytes(12)),
            'alert_id' => $alertId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'created_at' => gmdate(DATE_RFC3339),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['evidence'] = json_decode((string) $row['evidence_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['evidence_json']);
        $row['retention_days'] = $row['retention_days'] !== null ? (int) $row['retention_days'] : null;

        return $row;
    }

    private function publish(string $alertId, string $status, string $eventName): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            $eventName,
            new DateTimeImmutable(),
            self::class,
            ['alert_id' => $alertId, 'status' => $status]
        ));
    }
}
