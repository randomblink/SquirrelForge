<?php

declare(strict_types=1);

namespace SquirrelForge\Coordination;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Engine\TaskRouter;

/**
 * Detects and classifies failures reported by other coordination and
 * engine components, attempts a safe recovery strategy, and escalates
 * when recovery is not possible, per
 * 17_COORDINATION/FAILURE-RECOVERY.md -- the first real component in
 * 17_COORDINATION, and the real decision-maker four already-built
 * `20_EXECUTION` components (`FailureHandler`, `RollbackManager`,
 * `WorkflowExecutor`, `ExecutionEngine`) have been standing a
 * caller-supplied `$recoveryRequest` closure in for all session.
 *
 * `decide()`'s return shape is deliberately identical to what
 * `FailureHandler::forward()`'s own `$recoveryRequest` closure expects
 * (`authorized`/`operation`/`target_component`/`recovery_record_ref`/
 * `reason`) -- this class is meant to be wired in directly as that
 * closure (`fn(array $r) => $failureRecovery->decide($r)`), not merely
 * shaped similarly by coincidence. It also accepts
 * `FailureHandler`'s own Execution Failure Record verbatim as input.
 *
 * This spec's own seven Failure Types (Validation/Dependency/Workflow/
 * Agent/Communication/Resource/Unknown) are a genuinely different,
 * real taxonomy from `FAILURE-HANDLER.md`'s own seven
 * (Prerequisite/Dispatch/Execution/Validation/Timeout/Dependency/Rule)
 * -- different owners, different purposes (execution-layer routing vs.
 * recovery-strategy selection). `classify()` maps between them
 * explicitly rather than assuming they're interchangeable: `Rule
 * Failure` maps to `Validation Failure` (the same "rule compliance is
 * a validation-adjacent gate" reasoning `WorkflowExecutor`'s own
 * checkpoint-failure classification already established), `Timeout
 * Failure`/`Execution Failure` map to `Agent Failure` ("assigned agent
 * unable to finish"), `Prerequisite Failure` maps to `Resource Failure`
 * ("missing file, service, or tool"), and `Dispatch Failure` maps to
 * `Communication Failure` ("could not be routed"). A caller may also
 * supply one of this spec's own seven types directly (for a
 * `MESSAGE-BUS.md`/`HANDOFF-PROTOCOL.md` report neither of which has
 * code yet); anything unrecognized is `Unknown Failure`, never guessed.
 *
 * "Prevent repeated failure loops by recognizing recurrence against
 * prior Recovery Records" requires real state across separate `decide()`
 * calls, so this class is SQLite-backed, matching
 * `SqliteResultCollector`'s own "genuinely needs cross-call
 * correlation" reasoning. The Escalation Rules are checked before any
 * strategy is attempted, fail-closed: a task/failure-type combination
 * that has already been attempted `max_retries` times (default 3)
 * escalates rather than retrying again, and "validation repeatedly
 * fails" is this same generic recurrence mechanism, not a separate
 * rule to invent.
 *
 * "Request reassignment through the Task Router rather than selecting
 * a new owner directly" is genuine composition of the already-real
 * `TaskRouter::reroute()` -- and specifically `reroute()`, never
 * `route()`: `route()` selects a new agent, which this spec's own
 * Boundary explicitly forbids this class from doing; `reroute()` only
 * marks `REROUTE_REQUIRED` and preserves the superseded route,
 * leaving the actual new-owner selection to `TaskRouter`'s own
 * subsequent `route()` call outside this class.
 *
 * "Request the State Manager record `RECOVERY_REQUIRED`... or `BLOCKED`"
 * is a caller-supplied `$applyState` closure standing in for the
 * uncoded `STATE-MANAGER.md`, the same pattern `ExecutionEngine`
 * already established for that same uncoded authority -- called with
 * `RECOVERY_REQUIRED` at the start of every `decide()` call, and again
 * with `BLOCKED` when no safe automated strategy applies.
 */
final class SqliteFailureRecovery
{
    private const FAILURE_TYPES = [
        'Validation Failure', 'Dependency Failure', 'Workflow Failure',
        'Agent Failure', 'Communication Failure', 'Resource Failure', 'Unknown Failure',
    ];

    /** Maps 20_EXECUTION/FAILURE-HANDLER.md's own Failure Types onto this spec's real, distinct vocabulary. */
    private const EXECUTION_FAILURE_TYPE_MAP = [
        'Validation Failure' => 'Validation Failure',
        'Dependency Failure' => 'Dependency Failure',
        'Rule Failure' => 'Validation Failure',
        'Timeout Failure' => 'Agent Failure',
        'Execution Failure' => 'Agent Failure',
        'Prerequisite Failure' => 'Resource Failure',
        'Dispatch Failure' => 'Communication Failure',
    ];

    /** @var array<string, array{0: string, 1: ?string, 2: bool}> failure type => [strategy text, FailureHandler operation (null = no routable operation), needs Task Router reassignment] */
    private const STRATEGY_TABLE = [
        'Validation Failure' => ['Roll back to the previous validated state', 'Rollback', false],
        'Dependency Failure' => ['Reload dependencies', 'Retry', false],
        'Resource Failure' => ['Reload dependencies', 'Retry', false],
        'Communication Failure' => ['Retry the operation', 'Retry', false],
        'Agent Failure' => ['Request reassignment through the Task Router', 'Retry', true],
        'Workflow Failure' => ['Request the State Manager mark the task BLOCKED', null, false],
        'Unknown Failure' => ['Request the State Manager mark the task BLOCKED', null, false],
    ];

    private const DEFAULT_MAX_RETRIES = 3;

    private PDO $database;

    public function __construct(string $databasePath, private readonly ?TaskRouter $taskRouter = null)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS recovery_records (
                recovery_id TEXT PRIMARY KEY,
                task_id TEXT NOT NULL,
                failure_type TEXT NOT NULL,
                recovery_strategy TEXT NOT NULL,
                retry_count INTEGER NOT NULL,
                outcome TEXT NOT NULL,
                notes TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Recovery Process steps 1, 3-8. Shaped to be wired in directly as
     * `FailureHandler::forward()`'s `$recoveryRequest` closure.
     *
     * @param array<string, mixed> $failureRecord FailureHandler's own Execution Failure Record (or an equivalent shape carrying `failure_type`, `action_ref`/`execution_ref`).
     * @param array{max_retries?: int, critical_dependency_unresolved?: bool, security_or_integrity_at_risk?: bool, human_approval_required?: bool} $options
     * @param ?Closure $applyState (string $taskId, string $state): mixed the real hand-off to STATE-MANAGER.md, applied verbatim.
     * @return array{
     *     authorized: bool,
     *     operation: ?string,
     *     target_component: ?string,
     *     recovery_record_ref: ?string,
     *     reason: ?string,
     *     state_action: ?string,
     *     failure_type: string,
     *     strategy: string
     * }
     */
    public function decide(array $failureRecord, array $options = [], ?Closure $applyState = null): array
    {
        $taskId = $failureRecord['action_ref'] ?? ($failureRecord['execution_ref'] ?? 'unknown');
        $failureType = $this->classify($failureRecord);

        if ($applyState !== null) {
            $applyState($taskId, 'RECOVERY_REQUIRED');
        }

        $priorAttempts = $this->countPriorAttempts($taskId, $failureType);
        $maxRetries = is_int($options['max_retries'] ?? null) ? $options['max_retries'] : self::DEFAULT_MAX_RETRIES;

        $escalationReason = $this->escalationReason($priorAttempts, $maxRetries, $options);

        if ($escalationReason !== null) {
            $recoveryId = $this->record($taskId, $failureType, 'Request human intervention', $priorAttempts, 'Escalated', $escalationReason);

            return $this->result(true, 'Escalate', null, $recoveryId, $escalationReason, null, $failureType, 'Request human intervention');
        }

        [$strategy, $operation, $needsReassignment] = self::STRATEGY_TABLE[$failureType];

        if ($needsReassignment) {
            $this->taskRouter?->reroute(['task_id' => $taskId], sprintf('Recovery requested reassignment after a %s.', $failureType));
        }

        if ($operation === null) {
            if ($applyState !== null) {
                $applyState($taskId, 'BLOCKED');
            }

            $recoveryId = $this->record($taskId, $failureType, $strategy, $priorAttempts, 'Blocked', 'No safe automated recovery strategy applies to this failure type.');

            return $this->result(false, null, null, $recoveryId, 'No safe automated recovery strategy applies to this failure type.', 'BLOCKED', $failureType, $strategy);
        }

        $recoveryId = $this->record($taskId, $failureType, $strategy, $priorAttempts + 1, 'Recovered', null);

        return $this->result(true, $operation, null, $recoveryId, null, null, $failureType, $strategy);
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $recoveryId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM recovery_records WHERE recovery_id = :recovery_id');
        $statement->execute(['recovery_id' => $recoveryId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * "Record recovery actions for future learning" -- every recovery
     * record for a task, in the order they were made.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $taskId): array
    {
        $statement = $this->database->prepare('SELECT * FROM recovery_records WHERE task_id = :task_id ORDER BY rowid ASC');
        $statement->execute(['task_id' => $taskId]);

        return $statement->fetchAll();
    }

    /**
     * @param array<string, mixed> $failureRecord
     */
    private function classify(array $failureRecord): string
    {
        $type = $failureRecord['failure_type'] ?? null;

        if (is_string($type) && in_array($type, self::FAILURE_TYPES, true)) {
            return $type;
        }

        if (is_string($type) && isset(self::EXECUTION_FAILURE_TYPE_MAP[$type])) {
            return self::EXECUTION_FAILURE_TYPE_MAP[$type];
        }

        return 'Unknown Failure';
    }

    /**
     * Counts every prior Recovery Record for this task and failure
     * type, escalated ones included -- once a task/failure-type
     * combination has crossed the retry limit and escalated, it must
     * stay escalated on a later call rather than silently resetting to
     * try again, which is exactly the repeated-failure loop this class
     * exists to prevent.
     */
    private function countPriorAttempts(string $taskId, string $failureType): int
    {
        $statement = $this->database->prepare(
            'SELECT COUNT(*) AS attempts FROM recovery_records WHERE task_id = :task_id AND failure_type = :failure_type'
        );
        $statement->execute(['task_id' => $taskId, 'failure_type' => $failureType]);

        return (int) $statement->fetch()['attempts'];
    }

    /**
     * @param array{max_retries?: int, critical_dependency_unresolved?: bool, security_or_integrity_at_risk?: bool, human_approval_required?: bool} $options
     */
    private function escalationReason(int $priorAttempts, int $maxRetries, array $options): ?string
    {
        if ($priorAttempts >= $maxRetries) {
            return sprintf('Retry limit (%d) exceeded for this task and failure type.', $maxRetries);
        }

        if (($options['critical_dependency_unresolved'] ?? false) === true) {
            return 'A critical dependency cannot be resolved.';
        }

        if (($options['security_or_integrity_at_risk'] ?? false) === true) {
            return 'Security or data integrity is at risk.';
        }

        if (($options['human_approval_required'] ?? false) === true) {
            return 'Human approval is required.';
        }

        return null;
    }

    private function record(string $taskId, string $failureType, string $strategy, int $retryCount, string $outcome, ?string $notes): string
    {
        $recoveryId = 'recovery_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO recovery_records (recovery_id, task_id, failure_type, recovery_strategy, retry_count, outcome, notes, created_at)
             VALUES (:recovery_id, :task_id, :failure_type, :recovery_strategy, :retry_count, :outcome, :notes, :created_at)'
        );
        $statement->execute([
            'recovery_id' => $recoveryId,
            'task_id' => $taskId,
            'failure_type' => $failureType,
            'recovery_strategy' => $strategy,
            'retry_count' => $retryCount,
            'outcome' => $outcome,
            'notes' => $notes,
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $recoveryId;
    }

    /**
     * @return array{
     *     authorized: bool,
     *     operation: ?string,
     *     target_component: ?string,
     *     recovery_record_ref: ?string,
     *     reason: ?string,
     *     state_action: ?string,
     *     failure_type: string,
     *     strategy: string
     * }
     */
    private function result(
        bool $authorized,
        ?string $operation,
        ?string $targetComponent,
        ?string $recoveryRecordRef,
        ?string $reason,
        ?string $stateAction,
        string $failureType,
        string $strategy
    ): array {
        return [
            'authorized' => $authorized,
            'operation' => $operation,
            'target_component' => $targetComponent,
            'recovery_record_ref' => $recoveryRecordRef,
            'reason' => $reason,
            'state_action' => $stateAction,
            'failure_type' => $failureType,
            'strategy' => $strategy,
        ];
    }
}
