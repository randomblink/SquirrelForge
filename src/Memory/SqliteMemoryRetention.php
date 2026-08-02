<?php

declare(strict_types=1);

namespace SquirrelForge\Memory;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Defines retention periods, archival eligibility, pruning eligibility,
 * and preservation rules for records held in Working, Episodic,
 * Semantic, and Project Memory, and evaluates records against those
 * rules, per 18_MEMORY/MEMORY-RETENTION.md.
 *
 * Owns its own database (`Sqlite` prefix): none of the four memory
 * types track a lifecycle state (Created/Active/Archived/Expired/
 * Removed) of their own -- MemoryStoreInterface has no concept of it --
 * so retention state is a genuinely new, distinct record this class
 * owns, matching SqliteVersionManager's and SqliteAlertManager's own
 * precedent for owning stateful lifecycle data.
 *
 * The Retention Policies table is applied literally per memory type,
 * never a single generic rule: evaluateWorking() prunes immediately on
 * task completion with no archival step at all ("Working Memory holds
 * no long-term copy of its own"), while evaluateEpisodic(),
 * evaluateSemantic(), and evaluateProject() each require the specific
 * evidence the spec names for that type (a governance-approved
 * exception, supersession/inactivity evidence, or a governance-approved
 * project closeout, respectively) -- none of these decisions are made
 * by this class itself; every trigger is caller-supplied evidence
 * already established elsewhere, upholding "it does not decide what
 * counts as validated or reusable knowledge... or project lifecycle
 * phase transitions."
 *
 * "No record may be pruned before its archival and preservation
 * requirements are satisfied" is a real, enforced gate in
 * applyDecision(): for every memory type except Working, a prune
 * decision on a record that isn't already `Archived` is downgraded to
 * a blocked recommendation rather than executed.
 *
 * "Request Memory Index update or remove the corresponding index
 * entry" is a genuine composition of the injected SqliteMemoryIndex:
 * an archived record's index entry is refreshed, a removed record's
 * index entry is deleted -- this class never alters a memory record's
 * own substantive content, only its own tracked lifecycle state and the
 * index's metadata about it.
 */
final class SqliteMemoryRetention
{
    private const MEMORY_TYPES = ['working', 'episodic', 'semantic', 'project'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteMemoryIndex $index = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS retention_records (
                retention_id TEXT PRIMARY KEY,
                memory_type TEXT NOT NULL,
                record_id TEXT NOT NULL,
                state TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (memory_type, record_id)
            )'
        );
    }

    /**
     * @return array{retention_id: ?string, outcome: string, error: ?string}
     */
    public function register(string $memoryType, string $recordId): array
    {
        if (!in_array($memoryType, self::MEMORY_TYPES, true)) {
            return ['retention_id' => null, 'outcome' => 'invalid_memory_type', 'error' => sprintf('"%s" is not a retained memory type.', $memoryType)];
        }

        $existing = $this->fetchRecord($memoryType, $recordId);

        if ($existing !== null) {
            return ['retention_id' => $existing['retention_id'], 'outcome' => 'already_registered', 'error' => null];
        }

        $retentionId = 'retention_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);
        $statement = $this->database->prepare(
            'INSERT INTO retention_records (retention_id, memory_type, record_id, state, created_at, updated_at)
             VALUES (:retention_id, :memory_type, :record_id, :state, :created_at, :updated_at)'
        );
        $statement->execute([
            'retention_id' => $retentionId,
            'memory_type' => $memoryType,
            'record_id' => $recordId,
            'state' => 'Active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['retention_id' => $retentionId, 'outcome' => 'registered', 'error' => null];
    }

    /**
     * @param array{task_completed?: bool, governance_approved?: bool, requested_action?: ?string, superseded?: bool, no_longer_referenced?: bool, days_since_last_reference?: ?int, inactivity_window_days?: ?int, project_lifecycle_phase?: ?string, closeout_approved?: bool} $evidence
     * @return array{memory_type: string, record_id: string, action: string, state: ?string, reason: ?string, outcome: string, error: ?string}
     */
    public function evaluate(string $memoryType, string $recordId, array $evidence = []): array
    {
        $record = $this->fetchRecord($memoryType, $recordId);

        if ($record === null) {
            return ['memory_type' => $memoryType, 'record_id' => $recordId, 'action' => 'none', 'state' => null, 'reason' => null, 'outcome' => 'not_registered', 'error' => sprintf('%s record "%s" is not registered for retention.', $memoryType, $recordId)];
        }

        if ($record['state'] === 'Removed') {
            return ['memory_type' => $memoryType, 'record_id' => $recordId, 'action' => 'none', 'state' => 'Removed', 'reason' => null, 'outcome' => 'already_removed', 'error' => null];
        }

        $decision = match ($memoryType) {
            'working' => $this->evaluateWorking($evidence),
            'episodic' => $this->evaluateEpisodic($evidence),
            'semantic' => $this->evaluateSemantic($record, $evidence),
            'project' => $this->evaluateProject($record, $evidence),
        };

        return $this->applyDecision($memoryType, $recordId, $record, $decision);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $memoryType, string $recordId): ?array
    {
        return $this->fetchRecord($memoryType, $recordId);
    }

    /**
     * @param array{task_completed?: bool} $evidence
     * @return array{action: string, reason: ?string}
     */
    private function evaluateWorking(array $evidence): array
    {
        if (($evidence['task_completed'] ?? false) === true) {
            return ['action' => 'prune', 'reason' => 'Task completed and handed off to Episodic Memory; Working Memory holds no long-term copy.'];
        }

        return ['action' => 'retain', 'reason' => null];
    }

    /**
     * @param array{governance_approved?: bool, requested_action?: ?string} $evidence
     * @return array{action: string, reason: ?string}
     */
    private function evaluateEpisodic(array $evidence): array
    {
        if (($evidence['governance_approved'] ?? false) === true && in_array($evidence['requested_action'] ?? null, ['archive', 'prune'], true)) {
            return ['action' => $evidence['requested_action'], 'reason' => 'Governance-approved retention exception.'];
        }

        return ['action' => 'retain', 'reason' => null];
    }

    /**
     * @param array{superseded?: bool, no_longer_referenced?: bool, days_since_last_reference?: ?int, inactivity_window_days?: ?int} $evidence
     * @return array{action: string, reason: ?string}
     */
    private function evaluateSemantic(array $record, array $evidence): array
    {
        if ($record['state'] === 'Archived') {
            $daysSinceReference = $evidence['days_since_last_reference'] ?? null;
            $inactivityWindow = $evidence['inactivity_window_days'] ?? null;

            if ($daysSinceReference !== null && $inactivityWindow !== null && $daysSinceReference >= $inactivityWindow) {
                return ['action' => 'prune', 'reason' => sprintf('No retrieval or reference in %d day(s), exceeding the %d-day governance-defined inactivity window.', $daysSinceReference, $inactivityWindow)];
            }

            return ['action' => 'retain', 'reason' => null];
        }

        if (($evidence['superseded'] ?? false) === true) {
            return ['action' => 'archive', 'reason' => 'Superseded by a newer validated pattern.'];
        }

        if (($evidence['no_longer_referenced'] ?? false) === true) {
            return ['action' => 'archive', 'reason' => 'No longer referenced by active workflows.'];
        }

        return ['action' => 'retain', 'reason' => null];
    }

    /**
     * @param array{project_lifecycle_phase?: ?string, governance_approved?: bool, closeout_approved?: bool} $evidence
     * @return array{action: string, reason: ?string}
     */
    private function evaluateProject(array $record, array $evidence): array
    {
        if ($record['state'] === 'Archived') {
            if (($evidence['closeout_approved'] ?? false) === true) {
                return ['action' => 'prune', 'reason' => 'Governance-approved project closeout.'];
            }

            return ['action' => 'retain', 'reason' => null];
        }

        if (($evidence['project_lifecycle_phase'] ?? null) === 'RETENTION') {
            if (($evidence['governance_approved'] ?? false) === true) {
                return ['action' => 'archive', 'reason' => 'Project reached the RETENTION lifecycle phase.'];
            }

            return ['action' => 'recommend', 'reason' => 'Project reached the RETENTION lifecycle phase; governance approval is required before archival.'];
        }

        return ['action' => 'retain', 'reason' => null];
    }

    /**
     * @param array{action: string, reason: ?string} $decision
     * @return array{memory_type: string, record_id: string, action: string, state: ?string, reason: ?string, outcome: string, error: ?string}
     */
    private function applyDecision(string $memoryType, string $recordId, array $record, array $decision): array
    {
        if ($decision['action'] === 'retain') {
            return ['memory_type' => $memoryType, 'record_id' => $recordId, 'action' => 'retain', 'state' => $record['state'], 'reason' => $decision['reason'], 'outcome' => 'evaluated', 'error' => null];
        }

        if ($decision['action'] === 'recommend') {
            return ['memory_type' => $memoryType, 'record_id' => $recordId, 'action' => 'recommend', 'state' => $record['state'], 'reason' => $decision['reason'], 'outcome' => 'recommended', 'error' => null];
        }

        if ($decision['action'] === 'prune' && $memoryType !== 'working' && $record['state'] !== 'Archived') {
            return ['memory_type' => $memoryType, 'record_id' => $recordId, 'action' => 'recommend', 'state' => $record['state'], 'reason' => 'Cannot prune before archival requirements are satisfied.', 'outcome' => 'blocked', 'error' => 'Record must be archived before it can be pruned.'];
        }

        $newState = $decision['action'] === 'archive' ? 'Archived' : 'Removed';

        $statement = $this->database->prepare('UPDATE retention_records SET state = :state, updated_at = :updated_at WHERE memory_type = :memory_type AND record_id = :record_id');
        $statement->execute(['state' => $newState, 'updated_at' => gmdate(DATE_RFC3339), 'memory_type' => $memoryType, 'record_id' => $recordId]);

        if ($newState === 'Removed') {
            $this->index?->removeIndexEntry($memoryType, $recordId);
        } else {
            $this->index?->indexRecord($memoryType, $recordId);
        }

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'memory_retention.' . strtolower($newState),
            new DateTimeImmutable(),
            self::class,
            ['memory_type' => $memoryType, 'record_id' => $recordId]
        ));

        return ['memory_type' => $memoryType, 'record_id' => $recordId, 'action' => $decision['action'], 'state' => $newState, 'reason' => $decision['reason'], 'outcome' => strtolower($newState), 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRecord(string $memoryType, string $recordId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM retention_records WHERE memory_type = :memory_type AND record_id = :record_id');
        $statement->execute(['memory_type' => $memoryType, 'record_id' => $recordId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }
}
