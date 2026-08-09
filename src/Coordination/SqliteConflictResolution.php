<?php

declare(strict_types=1);

namespace SquirrelForge\Coordination;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Knowledge\KnowledgeManager;

/**
 * Detects, classifies, and resolves disagreements between agents
 * against escalation criteria a collaboration structure has already
 * defined, while preserving project quality, correctness, and forward
 * progress, per 17_COORDINATION/CONFLICT-RESOLUTION.md -- the seventh
 * and final real component in 17_COORDINATION, closing out this
 * layer's roster.
 *
 * "This document does not invent escalation criteria case by case; it
 * applies them" (Purpose): `AGENT-COLLABORATION.md` has no code, so
 * whether a specific conflict requires escalation arrives as a
 * caller-supplied `escalation_required` flag -- the same "reference,
 * don't recompute" boundary this codebase applies to every uncoded
 * authority. What this class genuinely computes is the Resolution
 * Priority tie-break itself: given the caller-supplied set of
 * applicable rules (each already owned by its own real layer -- this
 * class never redefines what any of them says), it selects whichever
 * one sits highest in the spec's own fixed eight-tier order. That
 * selection *is* this component's real, load-bearing contribution --
 * "determines which already-owned rule takes precedence when two
 * apply in conflict" (Purpose), not a fabricated judgment.
 *
 * A conflict with no applicable rule supplied at all cannot honestly
 * be resolved "using documented project rules" (Rule), so it escalates
 * rather than silently picking nothing -- the same fail-closed
 * discipline `SqliteFailureRecovery` already applies when no safe
 * strategy exists for a failure type.
 *
 * "Prevent repeated conflicts by recognizing recurrence against prior
 * Conflict Records" mirrors `SqliteFailureRecovery`'s own recurrence
 * mechanism exactly: a second consecutive conflict of the same type
 * for the same task escalates automatically, even without an explicit
 * `escalation_required` flag, since a conflict that keeps recurring
 * was evidently never really resolved.
 *
 * "Clear the block... without itself re-executing work" is a
 * caller-supplied `$clearBlock` closure, the same `STATE-MANAGER.md`
 * stand-in pattern `ExecutionEngine`/`SqliteFailureRecovery` already
 * established -- and it is called only on a genuine `Resolved`
 * outcome, never on `Escalated`: an escalated conflict hands off to a
 * higher authority, the paused component stays paused rather than
 * being told to resume against an unresolved disagreement.
 *
 * "Forward validated, reusable resolutions to `KNOWLEDGE-MANAGER.md`"
 * composes the already-real `KnowledgeManager::coordinate('register', ...)`
 * directly, but only when the caller explicitly supplies
 * `reusable_guidance` -- whether a specific resolution generalizes
 * beyond its own task is a judgment this class cannot make on its own,
 * the same "don't invent what the caller didn't establish" reasoning
 * behind every optional forwarding step in this codebase.
 *
 * SQLite-backed for the Conflict Record and recurrence detection --
 * both genuinely need state across separate `resolve()` calls, the
 * same reasoning `SqliteFailureRecovery`/`SqliteHandoffProtocol`
 * already established for this shape of requirement.
 */
final class SqliteConflictResolution
{
    private const CONFLICT_TYPES = ['Technical', 'Security', 'Performance', 'Documentation', 'Validation', 'Workflow'];

    /** The spec's own fixed Resolution Priority order, lower number wins. */
    private const RESOLUTION_PRIORITY = [
        'Project Rules' => 1,
        'Security Requirements' => 2,
        'Validation Rules' => 3,
        'Architecture Decisions' => 4,
        'Active Workflow' => 5,
        'Coding Standards' => 6,
        'Performance Considerations' => 7,
        'Documentation Standards' => 8,
    ];

    private const RECURRENCE_THRESHOLD = 2;

    private PDO $database;

    public function __construct(string $databasePath, private readonly ?KnowledgeManager $knowledgeManager = null)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS conflict_records (
                conflict_id TEXT PRIMARY KEY,
                task_id TEXT NOT NULL,
                agents_involved TEXT NOT NULL,
                conflict_type TEXT NOT NULL,
                resolution TEXT,
                decision_source TEXT,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Resolution Process steps 1-8.
     *
     * @param array{
     *     task_id?: ?string,
     *     agents_involved?: array<int, string>,
     *     conflict_type?: ?string,
     *     escalation_required?: bool,
     *     applicable_rules?: array<int, array{source: string, recommendation: string}>,
     *     reusable_guidance?: ?string
     * } $conflict
     * @param ?Closure $clearBlock (string $taskId, string $resolution): mixed the real hand-off to the paused queue/handoff/execution component. Called only on a Resolved outcome.
     * @return array{outcome: string, conflict_id: ?string, task_id: ?string, resolution: ?string, decision_source: ?string, error: ?string}
     */
    public function resolve(array $conflict, ?Closure $clearBlock = null): array
    {
        $taskId = $conflict['task_id'] ?? null;
        $conflictType = $conflict['conflict_type'] ?? null;
        $agentsInvolved = $conflict['agents_involved'] ?? [];

        if (!is_string($taskId) || $taskId === '' || $agentsInvolved === []) {
            return $this->outcome('Invalid', null, $taskId, null, null, 'A conflict requires a non-empty task_id and at least one agent involved.');
        }

        if (!is_string($conflictType) || !in_array($conflictType, self::CONFLICT_TYPES, true)) {
            return $this->outcome('Invalid', null, $taskId, null, null, sprintf('"%s" is not one of this spec\'s named Conflict Types.', (string) ($conflictType ?? '')));
        }

        $recurrence = $this->recurrenceCount($taskId, $conflictType) + 1;
        $escalationRequired = ($conflict['escalation_required'] ?? false) === true || $recurrence >= self::RECURRENCE_THRESHOLD;

        if ($escalationRequired) {
            $conflictId = $this->record($taskId, $agentsInvolved, $conflictType, null, null, 'Escalated');

            return $this->outcome('Escalated', $conflictId, $taskId, null, null, $recurrence >= self::RECURRENCE_THRESHOLD ? sprintf('This is the %d%s occurrence of a "%s" conflict on this task without resolution.', $recurrence, $this->ordinalSuffix($recurrence), $conflictType) : 'Escalation was required by the collaboration structure\'s own criteria.');
        }

        $applicableRules = $conflict['applicable_rules'] ?? [];
        $selected = $this->selectByPriority($applicableRules);

        if ($selected === null) {
            $conflictId = $this->record($taskId, $agentsInvolved, $conflictType, null, null, 'Escalated');

            return $this->outcome('Escalated', $conflictId, $taskId, null, null, 'No applicable rule was supplied; this conflict cannot be resolved using documented project rules.');
        }

        $conflictId = $this->record($taskId, $agentsInvolved, $conflictType, $selected['recommendation'], $selected['source'], 'Resolved');

        if ($clearBlock !== null) {
            $clearBlock($taskId, $selected['recommendation']);
        }

        $reusableGuidance = $conflict['reusable_guidance'] ?? null;

        if (is_string($reusableGuidance) && $reusableGuidance !== '' && $this->knowledgeManager !== null) {
            $this->knowledgeManager->coordinate('register', ['title' => sprintf('Conflict resolution: %s (%s)', $conflictType, $taskId), 'type' => 'conflict_resolution'], ['content' => $reusableGuidance]);
        }

        return $this->outcome('Resolved', $conflictId, $taskId, $selected['recommendation'], $selected['source'], null);
    }

    /**
     * @param array<int, array{source: string, recommendation: string}> $applicableRules
     * @return ?array{source: string, recommendation: string}
     */
    private function selectByPriority(array $applicableRules): ?array
    {
        $ranked = array_filter($applicableRules, static fn(array $rule): bool => isset(self::RESOLUTION_PRIORITY[$rule['source'] ?? '']));

        if ($ranked === []) {
            return null;
        }

        usort($ranked, static fn(array $a, array $b): int => self::RESOLUTION_PRIORITY[$a['source']] <=> self::RESOLUTION_PRIORITY[$b['source']]);

        return $ranked[array_key_first($ranked)];
    }

    private function recurrenceCount(string $taskId, string $conflictType): int
    {
        $statement = $this->database->prepare('SELECT COUNT(*) AS total FROM conflict_records WHERE task_id = :task_id AND conflict_type = :conflict_type');
        $statement->execute(['task_id' => $taskId, 'conflict_type' => $conflictType]);

        return (int) $statement->fetch()['total'];
    }

    /**
     * @param array<int, string> $agentsInvolved
     */
    private function record(string $taskId, array $agentsInvolved, string $conflictType, ?string $resolution, ?string $decisionSource, string $status): string
    {
        $conflictId = 'conflict_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO conflict_records (conflict_id, task_id, agents_involved, conflict_type, resolution, decision_source, status, created_at)
             VALUES (:conflict_id, :task_id, :agents_involved, :conflict_type, :resolution, :decision_source, :status, :created_at)'
        );
        $statement->execute([
            'conflict_id' => $conflictId,
            'task_id' => $taskId,
            'agents_involved' => implode(',', $agentsInvolved),
            'conflict_type' => $conflictType,
            'resolution' => $resolution,
            'decision_source' => $decisionSource,
            'status' => $status,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $conflictId;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $conflictId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM conflict_records WHERE conflict_id = :conflict_id');
        $statement->execute(['conflict_id' => $conflictId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $taskId): array
    {
        $statement = $this->database->prepare('SELECT * FROM conflict_records WHERE task_id = :task_id ORDER BY rowid ASC');
        $statement->execute(['task_id' => $taskId]);

        return $statement->fetchAll();
    }

    private function ordinalSuffix(int $n): string
    {
        return match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };
    }

    /**
     * @return array{outcome: string, conflict_id: ?string, task_id: ?string, resolution: ?string, decision_source: ?string, error: ?string}
     */
    private function outcome(string $outcome, ?string $conflictId, ?string $taskId, ?string $resolution, ?string $decisionSource, ?string $error): array
    {
        return [
            'outcome' => $outcome,
            'conflict_id' => $conflictId,
            'task_id' => $taskId,
            'resolution' => $resolution,
            'decision_source' => $decisionSource,
            'error' => $error,
        ];
    }
}
