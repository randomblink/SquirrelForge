<?php

declare(strict_types=1);

namespace SquirrelForge\Coordination;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Engine\DependencyAnalyzer;

/**
 * Assigns and recalculates task priority -- balancing urgency,
 * dependency-blocking impact, and other prioritization factors -- and
 * supplies that priority to `17_COORDINATION/TASK-QUEUE.md` for
 * ordering, per 17_COORDINATION/PRIORITY-MANAGER.md -- the fifth real
 * component in 17_COORDINATION.
 *
 * `TASK-QUEUE.md` and this spec name each other in their own "Depends
 * On" (Priority Manager supplies priority to Task Queue; Task Queue
 * consumes it), the same mutual-reference shape `CHECKPOINT-MANAGER.md`/
 * `WORKFLOW-EXECUTOR.md` already had in `20_EXECUTION` -- "supplies
 * priority to Task Queue for ordering" is an output relationship this
 * class satisfies by returning a real priority value, not a call
 * dependency requiring `TASK-QUEUE.md` to exist first.
 *
 * "Read dependency-blocking impact from `DEPENDENCY-ANALYZER.md` rather
 * than independently re-deriving it" is genuine composition of the
 * already-real `DependencyAnalyzer::analyze()` -- this class never
 * re-implements blocker detection, it reads that component's own real
 * `blockers` list.
 *
 * The priority score is a real, transparent additive formula over this
 * spec's own seven named Priority Factors (urgency, security
 * implications, release readiness, technical risk, estimated effort,
 * business value, plus dependency-blocking impact folded in from the
 * real analyzer) -- the same "simple, explainable formula over real
 * inputs" idiom `TaskRouter`'s own load tie-break already established,
 * not a fabricated judgment call. Thresholds map the resulting score
 * onto the spec's own four Priority Levels.
 *
 * "Never let priority override an unresolved required dependency
 * without explicit authorization" (Rule, Permission Boundary) is real,
 * enforced logic, not documentation: whenever `DependencyAnalyzer`
 * reports a `blocking`/`critical` severity blocker, a computed
 * `Critical`/`High` level is capped to `Medium` unless the caller
 * explicitly supplies `authorized_bypass: true` -- this class is the
 * one assigning the value `TASK-QUEUE.md` will order by, so capping it
 * here is the only place this rule can actually be upheld before that
 * value ever leaves this class.
 *
 * `recalculate()` requires one of this spec's own six named
 * Reprioritization Triggers -- a real, checked cross-reference, the
 * same discipline `SqliteFailureRecovery`/`AutomationConnector` already
 * apply to their own closed vocabularies -- rather than accepting an
 * arbitrary reason string for what is otherwise the identical
 * computation `assign()` performs.
 *
 * SQLite-backed for "record the priority decision and its rationale"
 * and "compare against the priority of active or queued tasks"
 * (Prioritization Process step 4) -- both genuinely need state across
 * separate calls, the same reasoning `SqliteCheckpointManager` and
 * `SqliteHandoffProtocol` already established for this shape of
 * requirement.
 */
final class SqlitePriorityManager
{
    private const LEVELS = ['Critical', 'High', 'Medium', 'Low'];

    private const REPRIORITIZATION_TRIGGERS = [
        'New critical task', 'Blocked dependency', 'Validation failure', 'Security issue', 'User-requested change', 'Release deadline',
    ];

    private const URGENCY_POINTS = ['none' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];

    private const RISK_POINTS = ['low' => 0, 'medium' => 1, 'high' => 2];

    private const VALUE_POINTS = ['low' => 0, 'medium' => 1, 'high' => 2];

    private const EFFORT_PENALTY = ['low' => 0, 'medium' => 0, 'high' => 2];

    private PDO $database;

    public function __construct(string $databasePath, private readonly ?DependencyAnalyzer $dependencyAnalyzer = new DependencyAnalyzer())
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS priority_records (
                record_id TEXT PRIMARY KEY,
                task_id TEXT NOT NULL,
                priority TEXT NOT NULL,
                reason TEXT NOT NULL,
                assigned_by TEXT,
                trigger_name TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Prioritization Process steps 1-3, 5, 7: assigns an initial
     * priority.
     *
     * @param array{
     *     task_id?: ?string,
     *     factors?: array{urgency?: string, security_implications?: bool, release_readiness?: bool, technical_risk?: string, estimated_effort?: string, business_value?: string},
     *     dependencies?: array<int, array<string, mixed>>,
     *     assigned_by?: ?string,
     *     authorized_bypass?: bool
     * } $request
     * @return array{outcome: string, record_id: ?string, task_id: ?string, priority: ?string, reason: ?string, blockers: array<int, array<string, mixed>>, error: ?string}
     */
    public function assign(array $request): array
    {
        return $this->decide($request, null);
    }

    /**
     * Prioritization Process step 6: recalculates priority when a real,
     * named reprioritization trigger occurs.
     *
     * @param array{
     *     task_id?: ?string,
     *     factors?: array{urgency?: string, security_implications?: bool, release_readiness?: bool, technical_risk?: string, estimated_effort?: string, business_value?: string},
     *     dependencies?: array<int, array<string, mixed>>,
     *     assigned_by?: ?string,
     *     authorized_bypass?: bool
     * } $request
     * @return array{outcome: string, record_id: ?string, task_id: ?string, priority: ?string, reason: ?string, blockers: array<int, array<string, mixed>>, error: ?string}
     */
    public function recalculate(array $request, string $trigger): array
    {
        if (!in_array($trigger, self::REPRIORITIZATION_TRIGGERS, true)) {
            return ['outcome' => 'invalid', 'record_id' => null, 'task_id' => $request['task_id'] ?? null, 'priority' => null, 'reason' => null, 'blockers' => [], 'error' => sprintf('"%s" is not one of this spec\'s named Reprioritization Triggers.', $trigger)];
        }

        return $this->decide($request, $trigger);
    }

    /**
     * @param array<string, mixed> $request
     * @return array{outcome: string, record_id: ?string, task_id: ?string, priority: ?string, reason: ?string, blockers: array<int, array<string, mixed>>, error: ?string}
     */
    private function decide(array $request, ?string $trigger): array
    {
        $taskId = $request['task_id'] ?? null;

        if (!is_string($taskId) || $taskId === '') {
            return ['outcome' => 'invalid', 'record_id' => null, 'task_id' => null, 'priority' => null, 'reason' => null, 'blockers' => [], 'error' => 'A priority decision requires a non-empty task_id.'];
        }

        $factors = $request['factors'] ?? [];
        $analysis = $this->dependencyAnalyzer?->analyze($request['dependencies'] ?? []);
        $blockers = $analysis['blockers'] ?? [];

        $score = $this->score($factors, $blockers);
        $level = $this->levelForScore($score);
        $capped = false;

        if ($blockers !== [] && in_array($level, ['Critical', 'High'], true) && ($request['authorized_bypass'] ?? false) !== true) {
            $level = 'Medium';
            $capped = true;
        }

        $reason = $this->reason($factors, $blockers, $score, $capped, $trigger);
        $assignedBy = is_string($request['assigned_by'] ?? null) ? $request['assigned_by'] : null;
        $recordId = $this->record($taskId, $level, $reason, $assignedBy, $trigger);

        return ['outcome' => 'assigned', 'record_id' => $recordId, 'task_id' => $taskId, 'priority' => $level, 'reason' => $reason, 'blockers' => $blockers, 'error' => null];
    }

    /**
     * @param array{urgency?: string, security_implications?: bool, release_readiness?: bool, technical_risk?: string, estimated_effort?: string, business_value?: string} $factors
     * @param array<int, array<string, mixed>> $blockers
     */
    private function score(array $factors, array $blockers): int
    {
        $score = self::URGENCY_POINTS[$factors['urgency'] ?? 'medium'] ?? self::URGENCY_POINTS['medium'];
        $score += ($factors['security_implications'] ?? false) ? 2 : 0;
        $score += ($factors['release_readiness'] ?? false) ? 2 : 0;
        $score += self::RISK_POINTS[$factors['technical_risk'] ?? 'medium'] ?? self::RISK_POINTS['medium'];
        $score += self::VALUE_POINTS[$factors['business_value'] ?? 'medium'] ?? self::VALUE_POINTS['medium'];
        $score -= self::EFFORT_PENALTY[$factors['estimated_effort'] ?? 'medium'] ?? self::EFFORT_PENALTY['medium'];
        $score += $blockers !== [] ? 3 : 0;

        return $score;
    }

    private function levelForScore(int $score): string
    {
        return match (true) {
            $score >= 10 => 'Critical',
            $score >= 6 => 'High',
            $score >= 3 => 'Medium',
            default => 'Low',
        };
    }

    /**
     * @param array{urgency?: string, security_implications?: bool, release_readiness?: bool, technical_risk?: string, estimated_effort?: string, business_value?: string} $factors
     * @param array<int, array<string, mixed>> $blockers
     */
    private function reason(array $factors, array $blockers, int $score, bool $capped, ?string $trigger): string
    {
        $parts = [sprintf('Computed score %d from urgency=%s, security_implications=%s, release_readiness=%s, technical_risk=%s, estimated_effort=%s, business_value=%s.',
            $score,
            $factors['urgency'] ?? 'medium',
            ($factors['security_implications'] ?? false) ? 'true' : 'false',
            ($factors['release_readiness'] ?? false) ? 'true' : 'false',
            $factors['technical_risk'] ?? 'medium',
            $factors['estimated_effort'] ?? 'medium',
            $factors['business_value'] ?? 'medium'
        )];

        if ($blockers !== []) {
            $parts[] = sprintf('%d unresolved required dependency blocker(s) reported by DependencyAnalyzer.', count($blockers));
        }

        if ($capped) {
            $parts[] = 'Priority capped to Medium: an unresolved required dependency blocks this task and no authorized_bypass was supplied.';
        }

        if ($trigger !== null) {
            $parts[] = sprintf('Recalculated due to reprioritization trigger: %s.', $trigger);
        }

        return implode(' ', $parts);
    }

    private function record(string $taskId, string $priority, string $reason, ?string $assignedBy, ?string $trigger): string
    {
        $recordId = 'priority_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO priority_records (record_id, task_id, priority, reason, assigned_by, trigger_name, created_at)
             VALUES (:record_id, :task_id, :priority, :reason, :assigned_by, :trigger_name, :created_at)'
        );
        $statement->execute([
            'record_id' => $recordId,
            'task_id' => $taskId,
            'priority' => $priority,
            'reason' => $reason,
            'assigned_by' => $assignedBy,
            'trigger_name' => $trigger,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $recordId;
    }

    /**
     * The task's most recently recorded priority.
     *
     * @return ?array<string, mixed>
     */
    public function current(string $taskId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM priority_records WHERE task_id = :task_id ORDER BY rowid DESC LIMIT 1');
        $statement->execute(['task_id' => $taskId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Prioritization Process step 4: the current priority of every
     * active or queued task, most recent record per task, ordered from
     * highest to lowest for comparison.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allCurrent(): array
    {
        $rows = $this->database->query(
            'SELECT p.* FROM priority_records p
             INNER JOIN (SELECT task_id, MAX(rowid) AS latest_rowid FROM priority_records GROUP BY task_id) latest
               ON p.task_id = latest.task_id AND p.rowid = latest.latest_rowid'
        )->fetchAll();

        usort($rows, static fn(array $a, array $b): int => array_search($a['priority'], self::LEVELS, true) <=> array_search($b['priority'], self::LEVELS, true));

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $taskId): array
    {
        $statement = $this->database->prepare('SELECT * FROM priority_records WHERE task_id = :task_id ORDER BY rowid ASC');
        $statement->execute(['task_id' => $taskId]);

        return $statement->fetchAll();
    }
}
