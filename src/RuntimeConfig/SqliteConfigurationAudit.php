<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Observability\AuditTrail;

/**
 * Owns configuration-domain change history -- version references,
 * change records, actor references, approval references, prior-state
 * and new-state references, and configuration audit evidence
 * references -- per 28_RUNTIME-CONFIG/CONFIGURATION-AUDIT.md.
 *
 * This is the first of `28_RUNTIME-CONFIG`'s seven real gap
 * components, picked first because its own "Depends On" -- the
 * already-real `27_OBSERVABILITY/AUDIT-TRAIL.md` and `37_STORAGE`
 * -- resolves entirely against existing code, with zero uncoded
 * `28_RUNTIME-CONFIG` siblings, the same "build the leaf first"
 * pattern `SqliteCheckpointManager` and `SqliteAgentLifecycle`
 * already established for their own layers.
 *
 * "It does not own general audit infrastructure, immutable audit
 * storage... or runtime workflow state" (Purpose) is upheld by real,
 * two-actor composition rather than reimplementation: this class owns
 * its own `configuration_history` table for the configuration-domain
 * fields the spec names (version, actor, approval, reason, prior/new
 * state, rollback references) -- evidence `27_OBSERVABILITY/AUDIT-TRAIL.md`
 * has no reason to model -- while genuinely composing the already-real
 * `AuditTrail::recordEvent()` to "emit audit evidence references to
 * `27_OBSERVABILITY/AUDIT-TRAIL.md`" (Responsibilities), the same "two
 * different actors recording two different aspects of one event" shape
 * `ActionDispatcher`/`FailureHandler` already established in
 * `20_EXECUTION`.
 *
 * The Audited Configuration Events table's nine event names are a
 * real, closed vocabulary -- an unrecognized event is rejected, never
 * silently accepted as free text.
 *
 * "Preserve rollback-request and rollback-result references without
 * executing rollback" (Responsibilities, Rule 3) is upheld structurally:
 * `recordRollback()` only ever writes a reference; this class exposes
 * no method that could execute or trigger a rollback itself.
 *
 * SQLite-backed for "provide configuration history references to
 * Runtime Configuration components" (Responsibilities) -- the query
 * surface every other `28_RUNTIME-CONFIG` component built after this
 * one will read from.
 */
final class SqliteConfigurationAudit
{
    /** The Audited Configuration Events table, reproduced verbatim as a real closed vocabulary. */
    private const EVENTS = [
        'Configuration Registered', 'Configuration Updated', 'Configuration Deprecated', 'Configuration Archived',
        'Validation Recorded', 'Secret Lifecycle Changed', 'Feature Flag Changed', 'Policy Configuration Changed',
        'Environment Overlay Changed',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?AuditTrail $auditTrail = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS configuration_history (
                history_ref TEXT PRIMARY KEY,
                event TEXT NOT NULL,
                configuration_ref TEXT NOT NULL,
                version_ref TEXT,
                actor_ref TEXT NOT NULL,
                approval_ref TEXT,
                reason TEXT,
                prior_state_json TEXT,
                new_state_json TEXT,
                audit_evidence_ref TEXT,
                created_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS configuration_rollbacks (
                rollback_ref TEXT PRIMARY KEY,
                configuration_ref TEXT NOT NULL,
                rollback_request_ref TEXT NOT NULL,
                rollback_result_ref TEXT,
                actor_ref TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Rule 1: "Every configuration-domain change must create a
     * configuration history record before the new configuration is
     * considered active" -- the caller is expected to call this before
     * applying the change, not after.
     *
     * @param array{
     *     event?: ?string,
     *     configuration_ref?: ?string,
     *     actor_ref?: ?string,
     *     version_ref?: ?string,
     *     approval_ref?: ?string,
     *     reason?: ?string,
     *     prior_state?: ?array<string, mixed>,
     *     new_state?: ?array<string, mixed>,
     *     timestamp?: ?string
     * } $entry
     * @return array{outcome: string, history_ref: ?string, audit_evidence_ref: ?string, error: ?string}
     */
    public function record(array $entry): array
    {
        $event = $entry['event'] ?? null;
        $configurationRef = $entry['configuration_ref'] ?? null;
        $actorRef = $entry['actor_ref'] ?? null;

        if (!is_string($event) || !in_array($event, self::EVENTS, true)) {
            return $this->envelope('invalid', null, null, sprintf('"%s" is not one of this spec\'s named Audited Configuration Events.', (string) ($event ?? '')));
        }

        if (!$this->present($configurationRef) || !$this->present($actorRef)) {
            return $this->envelope('invalid', null, null, 'A configuration history record requires a non-empty configuration_ref and actor_ref.');
        }

        $historyRef = 'config_history_' . bin2hex(random_bytes(12));
        $auditEvidenceRef = null;

        if ($this->auditTrail !== null) {
            $auditResult = $this->auditTrail->recordEvent([
                'actor_ref' => $actorRef,
                'action' => $event,
                'resource_ref' => $configurationRef,
                'outcome' => 'recorded',
                'reason' => $entry['reason'] ?? null,
                'evidence' => [
                    'version_ref' => $entry['version_ref'] ?? null,
                    'approval_ref' => $entry['approval_ref'] ?? null,
                    'prior_state' => $entry['prior_state'] ?? null,
                    'new_state' => $entry['new_state'] ?? null,
                ],
                'timestamp' => $entry['timestamp'] ?? null,
            ]);

            $auditEvidenceRef = $auditResult['outcome'] === 'recorded' ? $auditResult['audit_ref'] : null;
        }

        $statement = $this->database->prepare(
            'INSERT INTO configuration_history (
                history_ref, event, configuration_ref, version_ref, actor_ref, approval_ref, reason,
                prior_state_json, new_state_json, audit_evidence_ref, created_at
            ) VALUES (
                :history_ref, :event, :configuration_ref, :version_ref, :actor_ref, :approval_ref, :reason,
                :prior_state_json, :new_state_json, :audit_evidence_ref, :created_at
            )'
        );
        $statement->execute([
            'history_ref' => $historyRef,
            'event' => $event,
            'configuration_ref' => $configurationRef,
            'version_ref' => $entry['version_ref'] ?? null,
            'actor_ref' => $actorRef,
            'approval_ref' => $entry['approval_ref'] ?? null,
            'reason' => $entry['reason'] ?? null,
            'prior_state_json' => isset($entry['prior_state']) ? json_encode($entry['prior_state'], JSON_THROW_ON_ERROR) : null,
            'new_state_json' => isset($entry['new_state']) ? json_encode($entry['new_state'], JSON_THROW_ON_ERROR) : null,
            'audit_evidence_ref' => $auditEvidenceRef,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $this->envelope('recorded', $historyRef, $auditEvidenceRef, null);
    }

    /**
     * "Preserve rollback-request and rollback-result references
     * without executing rollback" -- a pure reference record, never an
     * action.
     *
     * @return array{outcome: string, rollback_ref: ?string, error: ?string}
     */
    public function recordRollback(string $configurationRef, string $rollbackRequestRef, string $actorRef, ?string $rollbackResultRef = null): array
    {
        if (!$this->present($configurationRef) || !$this->present($rollbackRequestRef) || !$this->present($actorRef)) {
            return ['outcome' => 'invalid', 'rollback_ref' => null, 'error' => 'A rollback record requires a non-empty configuration_ref, rollback_request_ref, and actor_ref.'];
        }

        $rollbackRef = 'config_rollback_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO configuration_rollbacks (rollback_ref, configuration_ref, rollback_request_ref, rollback_result_ref, actor_ref, created_at)
             VALUES (:rollback_ref, :configuration_ref, :rollback_request_ref, :rollback_result_ref, :actor_ref, :created_at)'
        );
        $statement->execute([
            'rollback_ref' => $rollbackRef,
            'configuration_ref' => $configurationRef,
            'rollback_request_ref' => $rollbackRequestRef,
            'rollback_result_ref' => $rollbackResultRef,
            'actor_ref' => $actorRef,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return ['outcome' => 'recorded', 'rollback_ref' => $rollbackRef, 'error' => null];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $historyRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM configuration_history WHERE history_ref = :history_ref');
        $statement->execute(['history_ref' => $historyRef]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * "Provide configuration history references to Runtime
     * Configuration components" -- every recorded change for a
     * configuration item, in the order it happened.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $configurationRef): array
    {
        $statement = $this->database->prepare('SELECT * FROM configuration_history WHERE configuration_ref = :configuration_ref ORDER BY rowid ASC');
        $statement->execute(['configuration_ref' => $configurationRef]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rollbackHistory(string $configurationRef): array
    {
        $statement = $this->database->prepare('SELECT * FROM configuration_rollbacks WHERE configuration_ref = :configuration_ref ORDER BY rowid ASC');
        $statement->execute(['configuration_ref' => $configurationRef]);

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['prior_state'] = $row['prior_state_json'] !== null ? json_decode((string) $row['prior_state_json'], true, flags: JSON_THROW_ON_ERROR) : null;
        $row['new_state'] = $row['new_state_json'] !== null ? json_decode((string) $row['new_state_json'], true, flags: JSON_THROW_ON_ERROR) : null;
        unset($row['prior_state_json'], $row['new_state_json']);

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{outcome: string, history_ref: ?string, audit_evidence_ref: ?string, error: ?string}
     */
    private function envelope(string $outcome, ?string $historyRef, ?string $auditEvidenceRef, ?string $error): array
    {
        return ['outcome' => $outcome, 'history_ref' => $historyRef, 'audit_evidence_ref' => $auditEvidenceRef, 'error' => $error];
    }
}
