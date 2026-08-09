<?php

declare(strict_types=1);

namespace SquirrelForge\Coordination;

use Closure;
use DateTimeImmutable;
use PDO;

/**
 * Executes the actual transfer of task ownership from one agent to the
 * next, preserving execution context, current validation status, and
 * progress, and confirming the receiving agent explicitly accepts
 * before ownership changes, per 17_COORDINATION/HANDOFF-PROTOCOL.md --
 * the fourth real component in 17_COORDINATION.
 *
 * "The Protocol executes a handoff whose participants are already
 * decided elsewhere... it does not decide who the next owner is"
 * (Purpose) is upheld by `initiate()` never selecting `next_agent`
 * itself -- it arrives caller-supplied, already determined by
 * `TASK-ROUTER.md`, `AGENT-DELEGATION.md`, or a stage's own
 * `next_stage`. "Does not independently define validation status"
 * gets one real, checked guard: a supplied `validation_status` must be
 * one of `EngineValidation`'s actual seven decision values, the same
 * cross-check `ExecutionReporter` already applies for the identical
 * reason -- this class carries that status forward, it never computes
 * one, so an unverifiable value is refused rather than silently
 * carried as if genuine.
 *
 * "Prevent duplicate work by confirming only one agent owns the task
 * at a time" is real, checked state, not a documentation-only promise:
 * a persisted ownership record tracks each task's current owner, and
 * `initiate()` refuses to proceed when the caller's declared
 * `current_agent` doesn't match who this class last recorded as
 * owner -- the same "never proceed against state you haven't
 * confirmed" discipline `SqliteCheckpointManager`'s prerequisite check
 * already established for this codebase.
 *
 * "Send the handoff through `MESSAGE-BUS.md`" composes the already-real
 * `SqliteMessageBus` directly, as a real `Task Assignment` message
 * (transferring task ownership is exactly what that Message Type
 * describes) carrying task status, validation status, artifacts, and
 * notes in its payload. The receiving agent's actual accept/reject
 * decision is a caller-supplied `Closure` (`$requestAcceptance`) --
 * this class has no way to reach into an agent's own reasoning, the
 * same "one fixed protocol would fabricate a choice this spec never
 * makes" reasoning behind every agent-facing closure in this codebase.
 * Omitting it is a real dry run landing at `Sent`, never fabricating
 * an acceptance.
 *
 * "If rejection recurs without resolution, escalate to
 * `FAILURE-RECOVERY.md`" is genuine composition of the already-real
 * `SqliteFailureRecovery::decide()`, reported as a `Workflow Failure`
 * -- the handoff mechanism itself is stuck, not one identifiable
 * agent malfunctioning, so `Agent Failure` would misattribute the
 * cause. "Recurs" is a real, counted threshold (a second consecutive
 * rejection for the same task), not merely "on any rejection."
 *
 * SQLite-backed for the ownership registry and "record the completed
 * or rejected handoff" (Responsibilities) -- both genuinely need state
 * across separate `initiate()` calls, matching
 * `SqliteCheckpointManager`'s own reasoning for the same shape of
 * requirement.
 */
final class SqliteHandoffProtocol
{
    private const VALIDATION_DECISIONS = [
        'ACCEPTED', 'ACCEPTED_WITH_LIMITATIONS', 'REPAIR_REQUIRED', 'BLOCKED', 'RECOVERY_REQUIRED', 'CLARIFICATION_REQUIRED', 'REJECTED',
    ];

    private const REJECTION_RECURRENCE_THRESHOLD = 2;

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteMessageBus $messageBus = null,
        private readonly ?SqliteFailureRecovery $failureRecovery = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS handoffs (
                handoff_id TEXT PRIMARY KEY,
                task_id TEXT NOT NULL,
                current_agent TEXT NOT NULL,
                next_agent TEXT NOT NULL,
                task_status TEXT,
                validation_status TEXT,
                outcome TEXT NOT NULL,
                rejection_reason TEXT,
                created_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS task_ownership (
                task_id TEXT PRIMARY KEY,
                current_owner TEXT NOT NULL,
                consecutive_rejections INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Handoff Process steps 1-6, and step 7's rejection handling.
     *
     * @param array{task_id?: ?string, current_agent?: ?string, next_agent?: ?string, task_status?: ?string, validation_status?: ?string, artifacts?: array<int, string>, notes?: ?string} $handoff
     * @param ?Closure $requestAcceptance (array $handoffPayload): array{accepted: bool, reason?: ?string} the real hand-off to the next agent's own accept/reject decision. Omitting it leaves the result at `Sent` without fabricating an acceptance.
     * @param array{max_retries?: int, critical_dependency_unresolved?: bool, security_or_integrity_at_risk?: bool, human_approval_required?: bool} $recoveryOptions forwarded to SqliteFailureRecovery::decide() when rejection recurs.
     * @return array{
     *     outcome: string,
     *     handoff_id: ?string,
     *     task_id: ?string,
     *     current_agent: ?string,
     *     next_agent: ?string,
     *     rejection_reason: ?string,
     *     recovery: ?array<string, mixed>,
     *     error: ?string
     * }
     */
    public function initiate(array $handoff, ?Closure $requestAcceptance = null, array $recoveryOptions = []): array
    {
        $taskId = $handoff['task_id'] ?? null;
        $currentAgent = $handoff['current_agent'] ?? null;
        $nextAgent = $handoff['next_agent'] ?? null;
        $validationStatus = $handoff['validation_status'] ?? null;

        if (!$this->present($taskId) || !$this->present($currentAgent) || !$this->present($nextAgent)) {
            return $this->result('Invalid', null, $taskId, $currentAgent, $nextAgent, null, null, 'A handoff requires a non-empty task_id, current_agent, and already-determined next_agent.');
        }

        if ($validationStatus !== null && !in_array($validationStatus, self::VALIDATION_DECISIONS, true)) {
            return $this->result('Invalid', null, $taskId, $currentAgent, $nextAgent, null, null, sprintf('"%s" is not one of EngineValidation\'s real decision values; it cannot be carried forward without redefining it.', $validationStatus));
        }

        $recordedOwner = $this->currentOwner($taskId);

        if ($recordedOwner !== null && $recordedOwner !== $currentAgent) {
            return $this->result('Blocked', null, $taskId, $currentAgent, $nextAgent, null, null, sprintf('Task "%s" is currently owned by "%s", not the declared current_agent "%s".', $taskId, $recordedOwner, $currentAgent));
        }

        $this->messageBus?->send([
            'sender' => $currentAgent,
            'recipient' => $nextAgent,
            'message_type' => 'Task Assignment',
            'task_id' => $taskId,
            'priority' => 'High',
            'payload' => [
                'task_status' => $handoff['task_status'] ?? null,
                'validation_status' => $validationStatus,
                'artifacts' => $handoff['artifacts'] ?? [],
                'notes' => $handoff['notes'] ?? null,
            ],
        ]);

        if ($requestAcceptance === null) {
            $handoffId = $this->record($taskId, $currentAgent, $nextAgent, $handoff['task_status'] ?? null, $validationStatus, 'Sent', null);

            return $this->result('Sent', $handoffId, $taskId, $currentAgent, $nextAgent, null, null, null);
        }

        $decision = $requestAcceptance([
            'task_id' => $taskId,
            'current_agent' => $currentAgent,
            'next_agent' => $nextAgent,
            'task_status' => $handoff['task_status'] ?? null,
            'validation_status' => $validationStatus,
            'artifacts' => $handoff['artifacts'] ?? [],
            'notes' => $handoff['notes'] ?? null,
        ]);

        if (($decision['accepted'] ?? false) === true) {
            $this->setOwner($taskId, $nextAgent);
            $handoffId = $this->record($taskId, $currentAgent, $nextAgent, $handoff['task_status'] ?? null, $validationStatus, 'Accepted', null);

            return $this->result('Accepted', $handoffId, $taskId, $currentAgent, $nextAgent, null, null, null);
        }

        $reason = $decision['reason'] ?? 'The receiving agent rejected the handoff without a stated reason.';
        $rejectionCount = $this->recordRejection($taskId, $currentAgent);
        $handoffId = $this->record($taskId, $currentAgent, $nextAgent, $handoff['task_status'] ?? null, $validationStatus, 'Rejected', $reason);

        $recovery = null;

        if ($rejectionCount >= self::REJECTION_RECURRENCE_THRESHOLD && $this->failureRecovery !== null) {
            $recovery = $this->failureRecovery->decide(
                [
                    'action_ref' => $taskId,
                    'failure_type' => 'Workflow Failure',
                    'observed_condition' => sprintf('Handoff of task "%s" to "%s" was rejected %d times without resolution: %s', $taskId, $nextAgent, $rejectionCount, $reason),
                ],
                $recoveryOptions
            );
        }

        return $this->result('Rejected', $handoffId, $taskId, $currentAgent, $nextAgent, $reason, $recovery, null);
    }

    public function currentOwner(string $taskId): ?string
    {
        $statement = $this->database->prepare('SELECT current_owner FROM task_ownership WHERE task_id = :task_id');
        $statement->execute(['task_id' => $taskId]);
        $row = $statement->fetch();

        return $row === false ? null : $row['current_owner'];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $handoffId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM handoffs WHERE handoff_id = :handoff_id');
        $statement->execute(['handoff_id' => $handoffId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * "Record the completed or rejected handoff" -- every handoff for
     * a task, in the order it happened.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $taskId): array
    {
        $statement = $this->database->prepare('SELECT * FROM handoffs WHERE task_id = :task_id ORDER BY rowid ASC');
        $statement->execute(['task_id' => $taskId]);

        return $statement->fetchAll();
    }

    private function setOwner(string $taskId, string $owner): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO task_ownership (task_id, current_owner, consecutive_rejections, updated_at)
             VALUES (:task_id, :owner, 0, :updated_at)
             ON CONFLICT(task_id) DO UPDATE SET current_owner = :owner, consecutive_rejections = 0, updated_at = :updated_at'
        );
        $statement->execute(['task_id' => $taskId, 'owner' => $owner, 'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM)]);
    }

    /**
     * Returns ownership to `$currentAgent` and increments the
     * consecutive-rejection counter, atomically in one statement --
     * deliberately never resetting the counter, unlike `setOwner()`,
     * since "returning" ownership after a rejection is not the same
     * event as a genuine new owner accepting one.
     */
    private function recordRejection(string $taskId, string $currentAgent): int
    {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $statement = $this->database->prepare(
            'INSERT INTO task_ownership (task_id, current_owner, consecutive_rejections, updated_at)
             VALUES (:task_id, :owner, 1, :updated_at)
             ON CONFLICT(task_id) DO UPDATE SET
                current_owner = :owner,
                consecutive_rejections = consecutive_rejections + 1,
                updated_at = :updated_at'
        );
        $statement->execute(['task_id' => $taskId, 'owner' => $currentAgent, 'updated_at' => $now]);

        $select = $this->database->prepare('SELECT consecutive_rejections FROM task_ownership WHERE task_id = :task_id');
        $select->execute(['task_id' => $taskId]);

        return (int) $select->fetch()['consecutive_rejections'];
    }

    private function record(string $taskId, string $currentAgent, string $nextAgent, ?string $taskStatus, ?string $validationStatus, string $outcome, ?string $rejectionReason): string
    {
        $handoffId = 'handoff_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO handoffs (handoff_id, task_id, current_agent, next_agent, task_status, validation_status, outcome, rejection_reason, created_at)
             VALUES (:handoff_id, :task_id, :current_agent, :next_agent, :task_status, :validation_status, :outcome, :rejection_reason, :created_at)'
        );
        $statement->execute([
            'handoff_id' => $handoffId,
            'task_id' => $taskId,
            'current_agent' => $currentAgent,
            'next_agent' => $nextAgent,
            'task_status' => $taskStatus,
            'validation_status' => $validationStatus,
            'outcome' => $outcome,
            'rejection_reason' => $rejectionReason,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $handoffId;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{
     *     outcome: string,
     *     handoff_id: ?string,
     *     task_id: ?string,
     *     current_agent: ?string,
     *     next_agent: ?string,
     *     rejection_reason: ?string,
     *     recovery: ?array<string, mixed>,
     *     error: ?string
     * }
     */
    private function result(
        string $outcome,
        ?string $handoffId,
        mixed $taskId,
        mixed $currentAgent,
        mixed $nextAgent,
        ?string $rejectionReason,
        ?array $recovery,
        ?string $error
    ): array {
        return [
            'outcome' => $outcome,
            'handoff_id' => $handoffId,
            'task_id' => is_string($taskId) ? $taskId : null,
            'current_agent' => is_string($currentAgent) ? $currentAgent : null,
            'next_agent' => is_string($nextAgent) ? $nextAgent : null,
            'rejection_reason' => $rejectionReason,
            'recovery' => $recovery,
            'error' => $error,
        ];
    }
}
