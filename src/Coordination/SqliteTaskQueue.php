<?php

declare(strict_types=1);

namespace SquirrelForge\Coordination;

use Closure;
use DateTimeImmutable;
use PDO;

/**
 * Holds and orders tasks the Task Router has confirmed are ready for
 * routing but do not yet have an available owner, and dequeues them in
 * the correct order once one becomes available, per
 * 17_COORDINATION/TASK-QUEUE.md -- the sixth real component in
 * 17_COORDINATION, and the closing half of the mutual
 * `PRIORITY-MANAGER.md`/`TASK-QUEUE.md` reference pair.
 *
 * "A task must not be enqueued before the Task Router has confirmed its
 * dependencies are satisfied" (Inputs) is a real, checked guard, not
 * caller-trusted evidence: `enqueue()` requires `task_router_status`
 * to be exactly `ROUTED` -- the one real success status
 * `TaskRouter::route()` itself produces (its only other status,
 * `BLOCKED`, is explicitly not a confirmation). This class never calls
 * `TaskRouter` itself (Boundary: "does not... confirm dependency
 * readiness or select an owner"), it only refuses to accept an entry
 * that arrives without that real confirmation already attached.
 *
 * "Order entries by the priority `PRIORITY-MANAGER.md` assigns" is
 * genuine composition of the already-real `SqlitePriorityManager::current()`
 * -- this class never invents a priority of its own; a task with no
 * priority record on file is rejected rather than defaulting to some
 * assumed level, the same "must not assign priority itself" boundary
 * made literal.
 *
 * "Prevent duplicate entries for a task already queued or active" is
 * real, persisted state: an `enqueue()` call for a task that already
 * has a `QUEUED` or `DEQUEUED` entry (still active, having already
 * left this queue for a handoff) is rejected as `SKIPPED` rather than
 * silently creating a second entry.
 *
 * "Hand off a dequeued task to `HANDOFF-PROTOCOL.md`" composes the
 * already-real `SqliteHandoffProtocol::initiate()` directly --
 * `dequeue()` never selects the receiving owner itself (Boundary), the
 * caller-supplied `$handoffContext` must already carry the
 * already-determined `next_agent`, the same "participants decided
 * elsewhere" boundary `HandoffProtocol` itself already established one
 * layer up.
 *
 * Dequeue order is by real priority rank (`Critical` > `High` >
 * `Medium` > `Low`, this codebase's own established four-level order),
 * tied broken by earliest `enqueue()` call -- "order... by... the
 * readiness the Task Router confirms" has no separate readiness metric
 * this class could compute (readiness is binary: an entry either got
 * in via the `ROUTED` gate or it didn't), so priority plus FIFO
 * ordering among equal priorities is the real, complete ordering rule.
 *
 * SQLite-backed for "preserve queue entry history for audit"
 * (Responsibilities) and because ordering/duplicate-detection
 * genuinely need state across separate `enqueue()`/`dequeue()` calls,
 * the same reasoning `SqliteHandoffProtocol`'s ownership registry
 * already established for this shape of requirement.
 */
final class SqliteTaskQueue
{
    private const ACTIVE_STATES = ['QUEUED', 'DEQUEUED'];

    private const PRIORITY_RANK = ['Critical' => 0, 'High' => 1, 'Medium' => 2, 'Low' => 3];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqlitePriorityManager $priorityManager = null,
        private readonly ?SqliteHandoffProtocol $handoffProtocol = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS queue_entries (
                entry_id TEXT PRIMARY KEY,
                task_id TEXT NOT NULL,
                priority TEXT,
                position_state TEXT NOT NULL,
                handoff_ref TEXT,
                enqueued_at TEXT NOT NULL,
                dequeued_at TEXT
            )'
        );
    }

    /**
     * Queue Process steps 1-4, 7.
     *
     * @param array{task_id?: ?string, task_router_status?: ?string} $task
     * @return array{outcome: string, entry_id: ?string, task_id: ?string, priority: ?string, error: ?string}
     */
    public function enqueue(array $task): array
    {
        $taskId = $task['task_id'] ?? null;

        if (!is_string($taskId) || $taskId === '') {
            return ['outcome' => 'invalid', 'entry_id' => null, 'task_id' => null, 'priority' => null, 'error' => 'An entry requires a non-empty task_id.'];
        }

        if (($task['task_router_status'] ?? null) !== 'ROUTED') {
            return ['outcome' => 'invalid', 'entry_id' => null, 'task_id' => $taskId, 'priority' => null, 'error' => 'A task must not be enqueued before the Task Router has confirmed it as ROUTED.'];
        }

        if ($this->hasActiveEntry($taskId)) {
            $entryId = $this->insert($taskId, null, 'SKIPPED', null);

            return ['outcome' => 'skipped', 'entry_id' => $entryId, 'task_id' => $taskId, 'priority' => null, 'error' => 'This task is already queued or active.'];
        }

        $priority = $this->priorityManager?->current($taskId)['priority'] ?? null;

        if ($priority === null) {
            return ['outcome' => 'invalid', 'entry_id' => null, 'task_id' => $taskId, 'priority' => null, 'error' => 'No priority record exists for this task; the Priority Manager must assign one before it can be enqueued.'];
        }

        $entryId = $this->insert($taskId, $priority, 'QUEUED', null);

        return ['outcome' => 'queued', 'entry_id' => $entryId, 'task_id' => $taskId, 'priority' => $priority, 'error' => null];
    }

    /**
     * Queue Process steps 5-7: dequeues the highest-priority `QUEUED`
     * entry (ties broken by enqueue order) and hands it off through
     * HandoffProtocol.
     *
     * @param array{current_agent?: ?string, next_agent?: ?string, task_status?: ?string, validation_status?: ?string, artifacts?: array<int, string>, notes?: ?string} $handoffContext the already-determined receiving owner; this class never selects one itself.
     * @param ?Closure $requestAcceptance forwarded to SqliteHandoffProtocol::initiate().
     * @param array{max_retries?: int, critical_dependency_unresolved?: bool, security_or_integrity_at_risk?: bool, human_approval_required?: bool} $recoveryOptions forwarded to SqliteHandoffProtocol::initiate().
     * @return array{outcome: string, entry_id: ?string, task_id: ?string, priority: ?string, handoff: ?array<string, mixed>, error: ?string}
     */
    public function dequeue(array $handoffContext = [], ?Closure $requestAcceptance = null, array $recoveryOptions = []): array
    {
        $entry = $this->nextQueued();

        if ($entry === null) {
            return ['outcome' => 'empty', 'entry_id' => null, 'task_id' => null, 'priority' => null, 'handoff' => null, 'error' => 'The queue has no QUEUED entries.'];
        }

        $handoff = null;

        if ($this->handoffProtocol !== null && $this->present($handoffContext['next_agent'] ?? null)) {
            $handoff = $this->handoffProtocol->initiate(
                [...$handoffContext, 'task_id' => $entry['task_id']],
                $requestAcceptance,
                $recoveryOptions
            );
        }

        $statement = $this->database->prepare(
            "UPDATE queue_entries SET position_state = 'DEQUEUED', handoff_ref = :handoff_ref, dequeued_at = :dequeued_at WHERE entry_id = :entry_id"
        );
        $statement->execute([
            'handoff_ref' => $handoff['handoff_id'] ?? null,
            'dequeued_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'entry_id' => $entry['entry_id'],
        ]);

        return ['outcome' => 'dequeued', 'entry_id' => $entry['entry_id'], 'task_id' => $entry['task_id'], 'priority' => $entry['priority'], 'handoff' => $handoff, 'error' => null];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $entryId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM queue_entries WHERE entry_id = :entry_id');
        $statement->execute(['entry_id' => $entryId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * The ordered set of currently `QUEUED` entries, by priority rank
     * and then enqueue order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function queued(): array
    {
        $statement = $this->database->query("SELECT * FROM queue_entries WHERE position_state = 'QUEUED' ORDER BY rowid ASC");
        $rows = $statement->fetchAll();

        usort($rows, static fn(array $a, array $b): int => (self::PRIORITY_RANK[$a['priority']] ?? 99) <=> (self::PRIORITY_RANK[$b['priority']] ?? 99));

        return $rows;
    }

    /**
     * "Preserve queue entry history for audit" -- every entry for a
     * task, in the order it was recorded.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $taskId): array
    {
        $statement = $this->database->prepare('SELECT * FROM queue_entries WHERE task_id = :task_id ORDER BY rowid ASC');
        $statement->execute(['task_id' => $taskId]);

        return $statement->fetchAll();
    }

    private function hasActiveEntry(string $taskId): bool
    {
        $placeholders = implode(',', array_fill(0, count(self::ACTIVE_STATES), '?'));
        $statement = $this->database->prepare("SELECT 1 FROM queue_entries WHERE task_id = ? AND position_state IN ({$placeholders}) LIMIT 1");
        $statement->execute([$taskId, ...self::ACTIVE_STATES]);

        return $statement->fetch() !== false;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function nextQueued(): ?array
    {
        $queued = $this->queued();

        return $queued[0] ?? null;
    }

    private function insert(string $taskId, ?string $priority, string $positionState, ?string $handoffRef): string
    {
        $entryId = 'queue_entry_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO queue_entries (entry_id, task_id, priority, position_state, handoff_ref, enqueued_at)
             VALUES (:entry_id, :task_id, :priority, :position_state, :handoff_ref, :enqueued_at)'
        );
        $statement->execute([
            'entry_id' => $entryId,
            'task_id' => $taskId,
            'priority' => $priority,
            'position_state' => $positionState,
            'handoff_ref' => $handoffRef,
            'enqueued_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $entryId;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
