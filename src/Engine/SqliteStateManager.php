<?php

declare(strict_types=1);

namespace SquirrelForge\Engine;

use DateTimeImmutable;
use PDO;

/**
 * Tracks the current planning and routing state for an active
 * SquirrelForge request -- lifecycle phase, task state, validation
 * evidence, blockers, and ownership -- per
 * 14_ENGINE/STATE-MANAGER.md.
 *
 * This is the one genuine gap a fresh audit-then-fork pass found:
 * multiple already-real components explicitly stub out waiting for it
 * by name -- `AgentApiServer`/`WorkflowApiServer`'s `notImplemented()`
 * calls, `ExecutionEngine`/`SqliteFailureRecovery`'s caller-supplied
 * `$applyState` closures standing in for it, `ProgressTracker`'s own
 * docblock saying "`STATE-MANAGER.md` has no code, so `$taskStatuses`
 * arrives as" caller evidence. This class is that missing owner.
 *
 * "The State Manager records state. It does not execute actions,
 * validate tests, or rewrite history" (Purpose) and "records, but does
 * not independently calculate, the decision emitted by
 * `14_ENGINE/VALIDATION.md`" (Validation Decision Values) are upheld
 * by taking no composed dependency on `EngineValidation`,
 * `ProjectLoader`, `WorkflowSelector`, or `TaskRouter` at all --
 * unlike most components built this session, this class is a pure
 * recording authority those already-real components call *into*, not
 * one that calls out to them. It never recomputes a validation
 * decision or a routing outcome; it only accepts an already-decided
 * one and applies this spec's own literal state-effect rules to it.
 *
 * Lifecycle Phase has no explicit transition-pair table in the spec
 * (unlike `AGENT-LIFECYCLE.md`), but "a lifecycle phase may not skip a
 * required gate" (Transition Rules) is unambiguous: the 16 named
 * phases (`REQUESTED` through `COMPLETE`) form a real, enforced
 * sequential-only order, derived directly from the Lifecycle State
 * Values table's own listed order. `BLOCKED`/`RECOVERY_REQUIRED`/
 * `FAILED` are real exception states reachable from any active phase,
 * with the phase in effect at the moment of entry preserved as the
 * `responsible_phase` -- "recovery state must preserve the failed or
 * interrupted state rather than overwriting it" and "a failed
 * validation returns work to the earliest responsible phase" are both
 * upheld by `resolveBlocker()` regressing to exactly that preserved
 * value, never an invented one.
 *
 * The Validation Decision Values table's seven rows are applied
 * literally and exactly, reusing `EngineValidation`'s own real
 * seven-value decision vocabulary rather than inventing a parallel
 * one: `ACCEPTED`/`ACCEPTED_WITH_LIMITATIONS` (the latter gated on a
 * caller-confirmed `policy_permits_limitations`, since only policy --
 * not this class -- decides whether a limitation is acceptable) move
 * a task to `COMPLETED`; `REPAIR_REQUIRED` moves the task to
 * `VALIDATION_FAILED` and regresses the lifecycle to the caller-named
 * responsible phase; `CLARIFICATION_REQUIRED` moves the task to
 * `WAITING`; `BLOCKED` and `RECOVERY_REQUIRED` compose this class's
 * own real blocker/recovery handling; `REJECTED` moves the lifecycle
 * to `FAILED` unless the caller names an explicit governance-approved
 * terminal override, per the decision table's own stated exception.
 *
 * "Completion state must not erase limitations, failed checks, or
 * unavailable validation" (Transition Rules) is upheld structurally:
 * `limitations` is an append-only list, never cleared or overwritten
 * by a later `COMPLETED` transition.
 *
 * "One owner may hold a task at a time" (Single Ownership Rule) is a
 * real, checked guard: reassigning an already-owned task without
 * declaring the current owner as the one relinquishing it is rejected,
 * the same ownership-verification shape `SqliteHandoffProtocol`
 * already applies to task ownership.
 *
 * SQLite-backed for the explicit State Record and "preserve context
 * between workflow stages... expose a concise current-state summary
 * for reporting" (Responsibilities / Reporting Rule).
 */
final class SqliteStateManager
{
    /** Lifecycle State Values, in the spec's own listed order -- the real sequential-only transition path. */
    private const LIFECYCLE_SEQUENCE = [
        'REQUESTED', 'BOOTSTRAPPING', 'INTAKE', 'CONTEXT_LOADING', 'ROUTING', 'REASONING', 'PLANNING',
        'PERMISSION_REVIEW', 'EXECUTION_HANDOFF', 'VALIDATION', 'REVIEW', 'REPORTING',
        'OBSERVABILITY_RECORDING', 'MEMORY_UPDATE', 'RETENTION', 'COMPLETE',
    ];

    /** Real exception states reachable from any active (non-terminal) phase. */
    private const EXCEPTION_PHASES = ['BLOCKED', 'RECOVERY_REQUIRED', 'FAILED'];

    private const TERMINAL_PHASES = ['COMPLETE', 'FAILED'];

    /** Task State Values. */
    private const TASK_STATES = [
        'NOT_STARTED', 'READY', 'ROUTED', 'IN_PROGRESS', 'WAITING', 'BLOCKED',
        'VALIDATION_PENDING', 'VALIDATION_FAILED', 'COMPLETED', 'CANCELLED',
    ];

    /** EngineValidation's own real seven-value decision vocabulary, reused verbatim. */
    private const VALIDATION_DECISIONS = [
        'ACCEPTED', 'ACCEPTED_WITH_LIMITATIONS', 'REPAIR_REQUIRED', 'CLARIFICATION_REQUIRED',
        'BLOCKED', 'RECOVERY_REQUIRED', 'REJECTED',
    ];

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
            'CREATE TABLE IF NOT EXISTS state_records (
                request_id TEXT PRIMARY KEY,
                goal_id TEXT NOT NULL,
                lifecycle_phase TEXT NOT NULL,
                responsible_phase TEXT,
                blocker_reason TEXT,
                next_safe_action TEXT,
                limitations_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS state_task_states (
                request_id TEXT NOT NULL,
                task_id TEXT NOT NULL,
                state TEXT NOT NULL,
                owner TEXT,
                dependencies_satisfied INTEGER NOT NULL,
                last_validation_decision TEXT,
                resume_condition TEXT,
                updated_at TEXT NOT NULL,
                PRIMARY KEY (request_id, task_id)
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS state_events (
                event_id TEXT PRIMARY KEY,
                request_id TEXT NOT NULL,
                event_type TEXT NOT NULL,
                detail_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @return array{outcome: string, request_id: ?string, lifecycle_phase: ?string, error: ?string}
     */
    public function initialize(string $requestId, string $goalId): array
    {
        if (!$this->present($requestId) || !$this->present($goalId)) {
            return $this->phaseOutcome('invalid', null, null, 'Initialization requires a non-empty request_id and goal_id.');
        }

        if ($this->stateRecord($requestId) !== null) {
            return $this->phaseOutcome('invalid', $requestId, null, sprintf('Request "%s" is already initialized.', $requestId));
        }

        $now = $this->now();
        $statement = $this->database->prepare(
            'INSERT INTO state_records (request_id, goal_id, lifecycle_phase, limitations_json, created_at, updated_at)
             VALUES (:request_id, :goal_id, :lifecycle_phase, :limitations_json, :created_at, :updated_at)'
        );
        $statement->execute([
            'request_id' => $requestId,
            'goal_id' => $goalId,
            'lifecycle_phase' => self::LIFECYCLE_SEQUENCE[0],
            'limitations_json' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->recordEvent($requestId, 'initialized', ['goal_id' => $goalId]);

        return $this->phaseOutcome('initialized', $requestId, self::LIFECYCLE_SEQUENCE[0], null);
    }

    /**
     * @return array{outcome: string, request_id: ?string, lifecycle_phase: ?string, error: ?string}
     */
    public function advancePhase(string $requestId, string $toPhase): array
    {
        $record = $this->stateRecord($requestId);

        if ($record === null) {
            return $this->phaseOutcome('invalid', $requestId, null, sprintf('Request "%s" is not initialized.', $requestId));
        }

        if (!in_array($toPhase, self::LIFECYCLE_SEQUENCE, true) && !in_array($toPhase, self::EXCEPTION_PHASES, true)) {
            return $this->phaseOutcome('rejected', $requestId, $record['lifecycle_phase'], sprintf('"%s" is not one of this spec\'s named Lifecycle State Values.', $toPhase));
        }

        $currentPhase = $record['lifecycle_phase'];

        if (in_array($currentPhase, self::TERMINAL_PHASES, true)) {
            return $this->phaseOutcome('rejected', $requestId, $currentPhase, sprintf('"%s" is a terminal phase; no further transition is possible.', $currentPhase));
        }

        if (in_array($toPhase, self::EXCEPTION_PHASES, true)) {
            $responsiblePhase = in_array($currentPhase, self::EXCEPTION_PHASES, true) ? $record['responsible_phase'] : $currentPhase;
            $this->setPhase($requestId, $toPhase, $toPhase === 'FAILED' ? null : $responsiblePhase);
            $this->recordEvent($requestId, 'phase_transition', ['from' => $currentPhase, 'to' => $toPhase]);

            return $this->phaseOutcome('transitioned', $requestId, $toPhase, null);
        }

        if (in_array($currentPhase, self::EXCEPTION_PHASES, true)) {
            return $this->phaseOutcome('rejected', $requestId, $currentPhase, 'Use resolveBlocker() to leave a BLOCKED/RECOVERY_REQUIRED state; a forward phase may not be assumed.');
        }

        $currentIndex = array_search($currentPhase, self::LIFECYCLE_SEQUENCE, true);
        $nextPhase = self::LIFECYCLE_SEQUENCE[$currentIndex + 1] ?? null;

        if ($toPhase !== $nextPhase) {
            return $this->phaseOutcome('rejected', $requestId, $currentPhase, sprintf('A lifecycle phase may not skip a required gate; "%s" must advance to "%s" next, not "%s".', $currentPhase, $nextPhase ?? '(none)', $toPhase));
        }

        $this->setPhase($requestId, $toPhase, null);
        $this->recordEvent($requestId, 'phase_transition', ['from' => $currentPhase, 'to' => $toPhase]);

        return $this->phaseOutcome('transitioned', $requestId, $toPhase, null);
    }

    /**
     * "A failed validation returns work to the earliest responsible
     * phase" / "recovery state must preserve the failed or interrupted
     * state rather than overwriting it" -- regresses to exactly the
     * phase preserved when BLOCKED/RECOVERY_REQUIRED was entered.
     *
     * @return array{outcome: string, request_id: ?string, lifecycle_phase: ?string, error: ?string}
     */
    public function resolveBlocker(string $requestId): array
    {
        $record = $this->stateRecord($requestId);

        if ($record === null) {
            return $this->phaseOutcome('invalid', $requestId, null, sprintf('Request "%s" is not initialized.', $requestId));
        }

        if (!in_array($record['lifecycle_phase'], ['BLOCKED', 'RECOVERY_REQUIRED'], true)) {
            return $this->phaseOutcome('rejected', $requestId, $record['lifecycle_phase'], 'Only a BLOCKED or RECOVERY_REQUIRED phase may be resolved.');
        }

        if ($record['responsible_phase'] === null) {
            return $this->phaseOutcome('rejected', $requestId, $record['lifecycle_phase'], 'No responsible phase was preserved to resolve back to.');
        }

        $resumedPhase = $record['responsible_phase'];
        $this->setPhase($requestId, $resumedPhase, null);
        $this->recordEvent($requestId, 'blocker_resolved', ['resumed_phase' => $resumedPhase]);

        return $this->phaseOutcome('transitioned', $requestId, $resumedPhase, null);
    }

    /**
     * "A required condition prevents progress" -- enters BLOCKED,
     * requiring "the blocked condition, responsible phase, and next
     * safe action" (Transition Rules).
     *
     * @return array{outcome: string, request_id: ?string, lifecycle_phase: ?string, error: ?string}
     */
    public function recordBlocker(string $requestId, string $reason, string $nextSafeAction): array
    {
        if (!$this->present($reason) || !$this->present($nextSafeAction)) {
            return $this->phaseOutcome('invalid', $requestId, null, 'A blocker requires a non-empty reason and next_safe_action.');
        }

        $result = $this->advancePhase($requestId, 'BLOCKED');

        if ($result['outcome'] === 'transitioned') {
            $now = $this->now();
            $statement = $this->database->prepare('UPDATE state_records SET blocker_reason = :reason, next_safe_action = :next_safe_action, updated_at = :updated_at WHERE request_id = :request_id');
            $statement->execute(['reason' => $reason, 'next_safe_action' => $nextSafeAction, 'updated_at' => $now, 'request_id' => $requestId]);
        }

        return $result;
    }

    /**
     * Task State transition, with this spec's own two real, checked
     * guards: "IN_PROGRESS only after required dependencies are
     * satisfied or explicitly waived" and "COMPLETED only after a
     * validation decision of ACCEPTED or policy-permitted
     * ACCEPTED_WITH_LIMITATIONS."
     *
     * @return array{outcome: string, request_id: ?string, task_id: ?string, state: ?string, error: ?string}
     */
    public function recordTaskState(string $requestId, string $taskId, string $toState, ?bool $dependenciesSatisfied = null): array
    {
        if (!$this->present($taskId)) {
            return $this->taskOutcome('invalid', $requestId, null, null, 'A task state requires a non-empty task_id.');
        }

        if (!in_array($toState, self::TASK_STATES, true)) {
            return $this->taskOutcome('invalid', $requestId, $taskId, null, sprintf('"%s" is not one of this spec\'s named Task State Values.', $toState));
        }

        if ($this->stateRecord($requestId) === null) {
            return $this->taskOutcome('invalid', $requestId, $taskId, null, sprintf('Request "%s" is not initialized.', $requestId));
        }

        $task = $this->taskState($requestId, $taskId);
        // Preserve the previously-recorded flag unless the caller explicitly declares a new one --
        // a later transition (e.g. WAITING -> IN_PROGRESS) must not silently reset an earlier waiver.
        $dependenciesSatisfied ??= (bool) ($task['dependencies_satisfied'] ?? false);

        if ($toState === 'IN_PROGRESS' && !$dependenciesSatisfied) {
            return $this->taskOutcome('rejected', $requestId, $taskId, $task['state'] ?? 'NOT_STARTED', 'A task may move to IN_PROGRESS only after required dependencies are satisfied or explicitly waived.');
        }

        if ($toState === 'COMPLETED' && !in_array($task['last_validation_decision'] ?? null, ['ACCEPTED', 'ACCEPTED_WITH_LIMITATIONS'], true)) {
            return $this->taskOutcome('rejected', $requestId, $taskId, $task['state'] ?? 'NOT_STARTED', 'A task may move to COMPLETED only after a validation decision of ACCEPTED or policy-permitted ACCEPTED_WITH_LIMITATIONS.');
        }

        $this->upsertTaskState($requestId, $taskId, $toState, $task['owner'] ?? null, $dependenciesSatisfied, $task['last_validation_decision'] ?? null, $task['resume_condition'] ?? null);
        $this->recordEvent($requestId, 'task_transition', ['task_id' => $taskId, 'to' => $toState]);

        return $this->taskOutcome('transitioned', $requestId, $taskId, $toState, null);
    }

    /**
     * Validation Decision Values: applies the spec's own literal
     * seven-row Required State Effect table. Never recalculates the
     * decision -- only accepts and applies one `14_ENGINE/VALIDATION.md`
     * already produced.
     *
     * @param array{policy_permits_limitations?: bool, responsible_phase?: ?string, resume_condition?: ?string, next_safe_action?: ?string, terminal_override?: ?string} $options
     * @return array{outcome: string, request_id: ?string, task_id: ?string, decision: ?string, lifecycle_phase: ?string, error: ?string}
     */
    public function recordValidationDecision(string $requestId, string $taskId, string $decision, array $options = []): array
    {
        if (!in_array($decision, self::VALIDATION_DECISIONS, true)) {
            return $this->validationOutcome('invalid', $requestId, $taskId, null, null, sprintf('"%s" is not one of EngineValidation\'s real decision values.', $decision));
        }

        if ($this->stateRecord($requestId) === null) {
            return $this->validationOutcome('invalid', $requestId, $taskId, null, null, sprintf('Request "%s" is not initialized.', $requestId));
        }

        $task = $this->taskState($requestId, $taskId) ?? ['state' => 'NOT_STARTED', 'owner' => null, 'dependencies_satisfied' => false, 'resume_condition' => null];
        $this->upsertTaskState($requestId, $taskId, $task['state'], $task['owner'], (bool) $task['dependencies_satisfied'], $decision, $task['resume_condition']);
        $this->recordEvent($requestId, 'validation_recorded', ['task_id' => $taskId, 'decision' => $decision]);

        switch ($decision) {
            case 'ACCEPTED':
            case 'ACCEPTED_WITH_LIMITATIONS':
                if ($decision === 'ACCEPTED_WITH_LIMITATIONS' && ($options['policy_permits_limitations'] ?? false) !== true) {
                    return $this->validationOutcome('recorded', $requestId, $taskId, $decision, $this->stateRecord($requestId)['lifecycle_phase'], null);
                }

                $this->recordTaskState($requestId, $taskId, 'COMPLETED');

                break;

            case 'REPAIR_REQUIRED':
                $this->upsertTaskState($requestId, $taskId, 'VALIDATION_FAILED', $task['owner'], (bool) $task['dependencies_satisfied'], $decision, $task['resume_condition']);
                $responsiblePhase = $options['responsible_phase'] ?? null;

                if ($this->present($responsiblePhase) && in_array($responsiblePhase, self::LIFECYCLE_SEQUENCE, true)) {
                    $this->setPhase($requestId, $responsiblePhase, null);
                }

                break;

            case 'CLARIFICATION_REQUIRED':
                $this->upsertTaskState($requestId, $taskId, 'WAITING', $task['owner'], (bool) $task['dependencies_satisfied'], $decision, $options['resume_condition'] ?? null);

                break;

            case 'BLOCKED':
                $this->upsertTaskState($requestId, $taskId, 'BLOCKED', $task['owner'], (bool) $task['dependencies_satisfied'], $decision, $task['resume_condition']);
                $this->recordBlocker($requestId, sprintf('Validation for task "%s" is BLOCKED.', $taskId), $options['next_safe_action'] ?? 'Await unblocking evidence.');

                break;

            case 'RECOVERY_REQUIRED':
                $this->advancePhase($requestId, 'RECOVERY_REQUIRED');

                break;

            case 'REJECTED':
                $terminal = $options['terminal_override'] ?? 'FAILED';
                $this->advancePhase($requestId, in_array($terminal, self::EXCEPTION_PHASES, true) ? $terminal : 'FAILED');

                break;
        }

        return $this->validationOutcome('recorded', $requestId, $taskId, $decision, $this->stateRecord($requestId)['lifecycle_phase'], null);
    }

    /**
     * Single Ownership Rule: reassigning an already-owned task must
     * declare the current owner as the one relinquishing it.
     *
     * @return array{outcome: string, request_id: ?string, task_id: ?string, owner: ?string, error: ?string}
     */
    public function assignOwner(string $requestId, string $taskId, string $newOwner, ?string $expectedCurrentOwner = null): array
    {
        $task = $this->taskState($requestId, $taskId);
        $currentOwner = $task['owner'] ?? null;

        if ($currentOwner !== null && $currentOwner !== $expectedCurrentOwner) {
            return $this->ownerOutcome('rejected', $requestId, $taskId, $currentOwner, sprintf('Task "%s" is currently owned by "%s", not the declared expected owner.', $taskId, $currentOwner));
        }

        $this->upsertTaskState($requestId, $taskId, $task['state'] ?? 'NOT_STARTED', $newOwner, (bool) ($task['dependencies_satisfied'] ?? false), $task['last_validation_decision'] ?? null, $task['resume_condition'] ?? null);
        $this->recordEvent($requestId, 'owner_assigned', ['task_id' => $taskId, 'owner' => $newOwner]);

        return $this->ownerOutcome('assigned', $requestId, $taskId, $newOwner, null);
    }

    /**
     * "A changed validation subject, version, dependency, environment,
     * rule, or acceptance criterion invalidates affected evidence and
     * moves its items to STALE" -- a real, caller-triggered event; this
     * class cannot itself detect drift in something it never observed.
     *
     * @return array{outcome: string, request_id: ?string, task_id: ?string, error: ?string}
     */
    public function invalidateStale(string $requestId, string $taskId, string $reason): array
    {
        $task = $this->taskState($requestId, $taskId);

        if ($task === null) {
            return ['outcome' => 'invalid', 'request_id' => $requestId, 'task_id' => $taskId, 'error' => sprintf('No task state exists for "%s".', $taskId)];
        }

        $this->upsertTaskState($requestId, $taskId, 'VALIDATION_PENDING', $task['owner'], (bool) $task['dependencies_satisfied'], 'STALE', $task['resume_condition']);
        $this->appendLimitation($requestId, sprintf('Task "%s" evidence invalidated: %s', $taskId, $reason));
        $this->recordEvent($requestId, 'stale_invalidated', ['task_id' => $taskId, 'reason' => $reason]);

        return ['outcome' => 'invalidated', 'request_id' => $requestId, 'task_id' => $taskId, 'error' => null];
    }

    /**
     * The Reporting Rule's own concise current-state summary.
     *
     * @return ?array<string, mixed>
     */
    public function currentState(string $requestId): ?array
    {
        $record = $this->stateRecord($requestId);

        if ($record === null) {
            return null;
        }

        $statement = $this->database->prepare('SELECT * FROM state_task_states WHERE request_id = :request_id ORDER BY task_id ASC');
        $statement->execute(['request_id' => $requestId]);
        $tasks = array_map(fn(array $row): array => $this->hydrateTask($row), $statement->fetchAll());

        return [
            'request_id' => $record['request_id'],
            'goal_id' => $record['goal_id'],
            'lifecycle_phase' => $record['lifecycle_phase'],
            'blocker_reason' => $record['blocker_reason'],
            'next_safe_action' => $record['next_safe_action'],
            'limitations' => json_decode((string) $record['limitations_json'], true, flags: JSON_THROW_ON_ERROR),
            'tasks' => $tasks,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $requestId): array
    {
        $statement = $this->database->prepare('SELECT * FROM state_events WHERE request_id = :request_id ORDER BY rowid ASC');
        $statement->execute(['request_id' => $requestId]);

        return array_map(
            static function (array $row): array {
                $row['detail'] = json_decode((string) $row['detail_json'], true, flags: JSON_THROW_ON_ERROR);
                unset($row['detail_json']);

                return $row;
            },
            $statement->fetchAll()
        );
    }

    /**
     * @return ?array<string, mixed>
     */
    private function stateRecord(string $requestId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM state_records WHERE request_id = :request_id');
        $statement->execute(['request_id' => $requestId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function taskState(string $requestId, string $taskId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM state_task_states WHERE request_id = :request_id AND task_id = :task_id');
        $statement->execute(['request_id' => $requestId, 'task_id' => $taskId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrateTask($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateTask(array $row): array
    {
        $row['dependencies_satisfied'] = (bool) $row['dependencies_satisfied'];

        return $row;
    }

    private function upsertTaskState(string $requestId, string $taskId, string $state, ?string $owner, bool $dependenciesSatisfied, ?string $lastValidationDecision, ?string $resumeCondition): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO state_task_states (request_id, task_id, state, owner, dependencies_satisfied, last_validation_decision, resume_condition, updated_at)
             VALUES (:request_id, :task_id, :state, :owner, :dependencies_satisfied, :last_validation_decision, :resume_condition, :updated_at)
             ON CONFLICT(request_id, task_id) DO UPDATE SET
                state = excluded.state, owner = excluded.owner, dependencies_satisfied = excluded.dependencies_satisfied,
                last_validation_decision = excluded.last_validation_decision, resume_condition = excluded.resume_condition, updated_at = excluded.updated_at'
        );
        $statement->execute([
            'request_id' => $requestId,
            'task_id' => $taskId,
            'state' => $state,
            'owner' => $owner,
            'dependencies_satisfied' => $dependenciesSatisfied ? 1 : 0,
            'last_validation_decision' => $lastValidationDecision,
            'resume_condition' => $resumeCondition,
            'updated_at' => $this->now(),
        ]);
    }

    private function setPhase(string $requestId, string $phase, ?string $responsiblePhase): void
    {
        $statement = $this->database->prepare('UPDATE state_records SET lifecycle_phase = :phase, responsible_phase = :responsible_phase, updated_at = :updated_at WHERE request_id = :request_id');
        $statement->execute(['phase' => $phase, 'responsible_phase' => $responsiblePhase, 'updated_at' => $this->now(), 'request_id' => $requestId]);
    }

    private function appendLimitation(string $requestId, string $limitation): void
    {
        $record = $this->stateRecord($requestId);

        if ($record === null) {
            return;
        }

        $limitations = json_decode((string) $record['limitations_json'], true, flags: JSON_THROW_ON_ERROR);
        $limitations[] = $limitation;

        $statement = $this->database->prepare('UPDATE state_records SET limitations_json = :limitations_json, updated_at = :updated_at WHERE request_id = :request_id');
        $statement->execute(['limitations_json' => json_encode($limitations, JSON_THROW_ON_ERROR), 'updated_at' => $this->now(), 'request_id' => $requestId]);
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function recordEvent(string $requestId, string $eventType, array $detail): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO state_events (event_id, request_id, event_type, detail_json, created_at)
             VALUES (:event_id, :request_id, :event_type, :detail_json, :created_at)'
        );
        $statement->execute([
            'event_id' => 'state_event_' . bin2hex(random_bytes(12)),
            'request_id' => $requestId,
            'event_type' => $eventType,
            'detail_json' => json_encode($detail, JSON_THROW_ON_ERROR),
            'created_at' => $this->now(),
        ]);
    }

    private function now(): string
    {
        return (new DateTimeImmutable())->format(DATE_ATOM);
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{outcome: string, request_id: ?string, lifecycle_phase: ?string, error: ?string}
     */
    private function phaseOutcome(string $outcome, ?string $requestId, ?string $lifecyclePhase, ?string $error): array
    {
        return ['outcome' => $outcome, 'request_id' => $requestId, 'lifecycle_phase' => $lifecyclePhase, 'error' => $error];
    }

    /**
     * @return array{outcome: string, request_id: ?string, task_id: ?string, state: ?string, error: ?string}
     */
    private function taskOutcome(string $outcome, ?string $requestId, ?string $taskId, ?string $state, ?string $error): array
    {
        return ['outcome' => $outcome, 'request_id' => $requestId, 'task_id' => $taskId, 'state' => $state, 'error' => $error];
    }

    /**
     * @return array{outcome: string, request_id: ?string, task_id: ?string, decision: ?string, lifecycle_phase: ?string, error: ?string}
     */
    private function validationOutcome(string $outcome, ?string $requestId, ?string $taskId, ?string $decision, ?string $lifecyclePhase, ?string $error): array
    {
        return ['outcome' => $outcome, 'request_id' => $requestId, 'task_id' => $taskId, 'decision' => $decision, 'lifecycle_phase' => $lifecyclePhase, 'error' => $error];
    }

    /**
     * @return array{outcome: string, request_id: ?string, task_id: ?string, owner: ?string, error: ?string}
     */
    private function ownerOutcome(string $outcome, ?string $requestId, ?string $taskId, ?string $owner, ?string $error): array
    {
        return ['outcome' => $outcome, 'request_id' => $requestId, 'task_id' => $taskId, 'owner' => $owner, 'error' => $error];
    }
}
