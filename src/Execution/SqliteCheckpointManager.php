<?php

declare(strict_types=1);

namespace SquirrelForge\Execution;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use SquirrelForge\Engine\EngineValidation;
use SquirrelForge\Reasoning\RuleEvaluator;

/**
 * Records execution-milestone checkpoints, confirms the validation and
 * rule-compliance evidence an execution plan requires actually exists
 * and passed, and gates whether a workflow may continue past that
 * point, per 20_EXECUTION/CHECKPOINT-MANAGER.md -- the first real
 * component in 20_EXECUTION, chosen because its own Depends On list is
 * the only one in this ten-component layer resolvable entirely against
 * already-built code (`14_ENGINE/VALIDATION.md` -> `EngineValidation`,
 * `19_REASONING/RULE-EVALUATOR.md` -> `RuleEvaluator`,
 * `14_ENGINE/EXECUTION-PLANNER.md` -> `ExecutionPlanner`); every other
 * component in this layer needs at least one uncoded 20_EXECUTION
 * sibling or the wholly uncoded `17_COORDINATION` layer.
 *
 * "Confirm that the validation and rule-compliance evidence... actually
 * exists and passed" (Purpose) and "does not independently decide that
 * an output is correct or that a rule was satisfied" (Purpose) are
 * upheld by genuine composition, not by consuming a caller-supplied
 * decision string the way most gating components in this codebase do:
 * `confirm()` calls the real, already-built `EngineValidation::evaluate()`
 * and `RuleEvaluator::evaluate()` directly against caller-supplied items
 * and rules, the same "delegate to the real, already-built component"
 * pattern `IntegrationManager` established for this codebase. This class
 * never re-implements what counts as a passing validation item or a
 * satisfied rule -- it only decides what counts as a passing *decision*
 * from each: `EngineValidation`'s own seven-state decision vocabulary
 * buckets `ACCEPTED` and `ACCEPTED_WITH_LIMITATIONS` under its own
 * "Acceptance" rules (the latter is even named for acceptance), so both
 * count as passed for gating; every other decision (`REJECTED`,
 * `REPAIR_REQUIRED`, `BLOCKED`, `RECOVERY_REQUIRED`,
 * `CLARIFICATION_REQUIRED`) does not. `RuleEvaluator`'s own three-state
 * vocabulary (`Passed`/`Warning`/`Failed`) plus its architecture-approved
 * fourth outcome `Escalate` draws a cleaner line: only `Passed` counts,
 * since "must both be confirmed and passed" (the spec's own Rule) reads
 * as strict, and `Warning`/`Escalate` are real, load-bearing distinct
 * outcomes those components chose deliberately, not degenerate cases to
 * fold into a pass.
 *
 * The Checkpoint Lifecycle's six steps map onto two real operations
 * rather than one: `create()` performs steps 1-2 (register the
 * checkpoint at a point `14_ENGINE/EXECUTION-PLANNER.md` already
 * scheduled, record the workflow-state snapshot) and lands at `Pending`;
 * `confirm()` performs steps 3-6 (run both evaluations, decide
 * `Complete`/`Failed`, and that decision *is* the continuation gate).
 * Splitting them keeps `create()` honest about "which points require a
 * checkpoint" staying `EXECUTION-PLANNER.md`'s decision (this class
 * never infers it) while still letting evaluation evidence arrive
 * later, matching the spec's own resume/interruption scenario. A
 * checkpoint already `Complete` or `Skipped` is immutable: `confirm()`
 * refuses to re-run on it, so a settled pass can never silently flip to
 * `Failed` later.
 *
 * The remaining two named statuses are given real meaning rather than
 * left unreachable: `confirm()` writes `Active` immediately before
 * running the two evaluations and only then `Complete`/`Failed` --
 * "currently being evaluated" is real and observable if the process
 * crashes mid-confirmation, not merely decorative. `Blocked`
 * ("waiting on prerequisite") is a genuine ordering check this class
 * can honestly make without inventing workflow semantics: a checkpoint
 * may only be confirmed once every earlier checkpoint for the same
 * `workflow_ref` has reached `Complete` or `Skipped`; an earlier one
 * still `Pending`/`Active`/`Failed`/`Blocked` blocks this one from
 * being evaluated at all, matching "resume from the next incomplete
 * step" -- a workflow's checkpoints resolve in order.
 *
 * "Prevent a workflow stage from being skipped without explicit
 * authorization" is upheld by `skip()` requiring a non-empty
 * `authorized_by` string -- omitting it is refused outright, not
 * defaulted to some anonymous placeholder.
 *
 * Persisted in SQLite, matching this codebase's established
 * `Sqlite*Manager` shape (`SqliteConnectorManager`,
 * `SqliteServiceDiscovery`) for exactly the reason those share:
 * "support workflow resume after interruption" and "record checkpoint
 * history" both require state that survives a process restart, unlike
 * this session's pure in-memory coordinators.
 */
final class SqliteCheckpointManager
{
    private const STATUSES = ['Pending', 'Active', 'Complete', 'Failed', 'Blocked', 'Skipped'];

    private const PASSING_VALIDATION_DECISIONS = ['ACCEPTED', 'ACCEPTED_WITH_LIMITATIONS'];

    private const PASSING_RULE_OUTCOMES = ['Passed'];

    private const RESOLVED_STATUSES = ['Complete', 'Skipped'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly EngineValidation $validation = new EngineValidation(),
        private readonly RuleEvaluator $ruleEvaluator = new RuleEvaluator()
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS checkpoints (
                checkpoint_id TEXT PRIMARY KEY,
                workflow_ref TEXT NOT NULL,
                stage TEXT NOT NULL,
                workflow_state_json TEXT NOT NULL,
                status TEXT NOT NULL,
                validation_decision TEXT,
                rule_outcome TEXT,
                authorized_by TEXT,
                notes TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Registers a checkpoint at a point the execution plan already
     * scheduled (Lifecycle steps 1-2). Lands at `Pending` -- no
     * validation or rule-compliance evidence has been confirmed yet.
     *
     * @param array{workflow_ref?: ?string, stage?: ?string, workflow_state?: array<string, mixed>, notes?: ?string} $definition
     * @return array{outcome: string, checkpoint_id: ?string, record: ?array<string, mixed>, error: ?string}
     */
    public function create(array $definition): array
    {
        $workflowRef = $definition['workflow_ref'] ?? null;
        $stage = $definition['stage'] ?? null;

        if (!is_string($workflowRef) || $workflowRef === '' || !is_string($stage) || $stage === '') {
            return ['outcome' => 'invalid', 'checkpoint_id' => null, 'record' => null, 'error' => 'Checkpoint creation requires a non-empty workflow_ref and stage.'];
        }

        $checkpointId = 'checkpoint_' . bin2hex(random_bytes(12));
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $workflowState = is_array($definition['workflow_state'] ?? null) ? $definition['workflow_state'] : [];

        $statement = $this->database->prepare(
            'INSERT INTO checkpoints (checkpoint_id, workflow_ref, stage, workflow_state_json, status, notes, created_at, updated_at)
             VALUES (:checkpoint_id, :workflow_ref, :stage, :workflow_state_json, :status, :notes, :created_at, :updated_at)'
        );
        $statement->execute([
            'checkpoint_id' => $checkpointId,
            'workflow_ref' => $workflowRef,
            'stage' => $stage,
            'workflow_state_json' => json_encode($workflowState, JSON_THROW_ON_ERROR),
            'status' => 'Pending',
            'notes' => $definition['notes'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['outcome' => 'created', 'checkpoint_id' => $checkpointId, 'record' => $this->get($checkpointId), 'error' => null];
    }

    /**
     * Runs the real validation and rule-compliance confirmation
     * (Lifecycle steps 3-6) and gates continuation on the result.
     *
     * @param array<int, array{item_id: string, stage: string, required: bool, blocking?: bool, status: string, waivable?: bool, repairable?: bool}> $validationItems forwarded verbatim to EngineValidation::evaluate().
     * @param array{remaining_attempts?: int, recovery_required?: bool, clarification_needed?: bool} $validationOptions
     * @param array<int, array{id: string, source: string, mandatory?: bool, condition: array<string, mixed>}> $rules forwarded verbatim to RuleEvaluator::evaluate().
     * @param array<string, mixed> $ruleContext
     * @param array{policy_request_id?: string, policy_context?: array<string, mixed>, policy_category?: ?string} $ruleOptions
     * @return array{outcome: string, checkpoint_id: string, record: ?array<string, mixed>, error: ?string}
     */
    public function confirm(
        string $checkpointId,
        array $validationItems = [],
        array $validationOptions = [],
        array $rules = [],
        array $ruleContext = [],
        array $ruleOptions = []
    ): array {
        $existing = $this->get($checkpointId);

        if ($existing === null) {
            return ['outcome' => 'not_found', 'checkpoint_id' => $checkpointId, 'record' => null, 'error' => sprintf('Checkpoint "%s" does not exist.', $checkpointId)];
        }

        if (in_array($existing['status'], self::RESOLVED_STATUSES, true)) {
            return ['outcome' => 'already_resolved', 'checkpoint_id' => $checkpointId, 'record' => $existing, 'error' => sprintf('Checkpoint "%s" is already "%s" and cannot be re-confirmed.', $checkpointId, $existing['status'])];
        }

        $blockingCheckpointId = $this->earliestUnresolvedPrerequisite($existing);

        if ($blockingCheckpointId !== null) {
            $this->setStatus($checkpointId, 'Blocked');

            return [
                'outcome' => 'blocked',
                'checkpoint_id' => $checkpointId,
                'record' => $this->get($checkpointId),
                'error' => sprintf('Checkpoint "%s" is waiting on an earlier unresolved checkpoint "%s" for the same workflow.', $checkpointId, $blockingCheckpointId),
            ];
        }

        $this->setStatus($checkpointId, 'Active');

        $validationResult = $this->validation->evaluate($validationItems, $validationOptions);
        $ruleResult = $this->ruleEvaluator->evaluate($rules, $ruleContext, $ruleOptions);

        $validationPassed = in_array($validationResult['decision'], self::PASSING_VALIDATION_DECISIONS, true);
        $rulePassed = in_array($ruleResult['outcome'], self::PASSING_RULE_OUTCOMES, true);
        $status = $validationPassed && $rulePassed ? 'Complete' : 'Failed';

        $statement = $this->database->prepare(
            'UPDATE checkpoints SET status = :status, validation_decision = :validation_decision, rule_outcome = :rule_outcome, updated_at = :updated_at WHERE checkpoint_id = :checkpoint_id'
        );
        $statement->execute([
            'status' => $status,
            'validation_decision' => $validationResult['decision'],
            'rule_outcome' => $ruleResult['outcome'],
            'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'checkpoint_id' => $checkpointId,
        ]);

        return ['outcome' => strtolower($status), 'checkpoint_id' => $checkpointId, 'record' => $this->get($checkpointId), 'error' => null];
    }

    /**
     * Finds the earliest checkpoint for the same workflow, created
     * before this one, that has not reached Complete or Skipped --
     * the real "prerequisite" `Blocked` is waiting on. Ordered by
     * SQLite's implicit `rowid` (strict insertion order) rather than
     * `created_at` alone, since same-second timestamps would otherwise
     * tie under fast, repeated creation.
     *
     * @param array<string, mixed> $checkpoint
     */
    private function earliestUnresolvedPrerequisite(array $checkpoint): ?string
    {
        $statement = $this->database->prepare(
            "SELECT checkpoint_id FROM checkpoints
             WHERE workflow_ref = :workflow_ref
               AND rowid < (SELECT rowid FROM checkpoints WHERE checkpoint_id = :checkpoint_id)
               AND status NOT IN ('Complete', 'Skipped')
             ORDER BY rowid ASC
             LIMIT 1"
        );
        $statement->execute(['workflow_ref' => $checkpoint['workflow_ref'], 'checkpoint_id' => $checkpoint['checkpoint_id']]);
        $row = $statement->fetch();

        return $row === false ? null : $row['checkpoint_id'];
    }

    private function setStatus(string $checkpointId, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException(sprintf('"%s" is not one of this spec\'s named Checkpoint Statuses.', $status));
        }

        $statement = $this->database->prepare('UPDATE checkpoints SET status = :status, updated_at = :updated_at WHERE checkpoint_id = :checkpoint_id');
        $statement->execute(['status' => $status, 'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM), 'checkpoint_id' => $checkpointId]);
    }

    /**
     * Marks a checkpoint Skipped -- refused outright without a
     * non-empty authorization reference, per "must not permit a
     * workflow stage to be skipped without explicit authorization."
     *
     * @return array{outcome: string, checkpoint_id: string, record: ?array<string, mixed>, error: ?string}
     */
    public function skip(string $checkpointId, string $authorizedBy, ?string $reason = null): array
    {
        if ($authorizedBy === '') {
            return ['outcome' => 'unauthorized', 'checkpoint_id' => $checkpointId, 'record' => null, 'error' => 'Skipping a checkpoint requires a non-empty authorized_by reference.'];
        }

        $existing = $this->get($checkpointId);

        if ($existing === null) {
            return ['outcome' => 'not_found', 'checkpoint_id' => $checkpointId, 'record' => null, 'error' => sprintf('Checkpoint "%s" does not exist.', $checkpointId)];
        }

        if (in_array($existing['status'], self::RESOLVED_STATUSES, true)) {
            return ['outcome' => 'already_resolved', 'checkpoint_id' => $checkpointId, 'record' => $existing, 'error' => sprintf('Checkpoint "%s" is already "%s" and cannot be skipped.', $checkpointId, $existing['status'])];
        }

        $statement = $this->database->prepare(
            'UPDATE checkpoints SET status = :status, authorized_by = :authorized_by, notes = :notes, updated_at = :updated_at WHERE checkpoint_id = :checkpoint_id'
        );
        $statement->execute([
            'status' => 'Skipped',
            'authorized_by' => $authorizedBy,
            'notes' => $reason,
            'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'checkpoint_id' => $checkpointId,
        ]);

        return ['outcome' => 'skipped', 'checkpoint_id' => $checkpointId, 'record' => $this->get($checkpointId), 'error' => null];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $checkpointId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM checkpoints WHERE checkpoint_id = :checkpoint_id');
        $statement->execute(['checkpoint_id' => $checkpointId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * "Record checkpoint history" -- every checkpoint for a workflow, in
     * creation order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $workflowRef): array
    {
        $statement = $this->database->prepare('SELECT * FROM checkpoints WHERE workflow_ref = :workflow_ref ORDER BY rowid ASC');
        $statement->execute(['workflow_ref' => $workflowRef]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * Resume Procedure step 1, "Load the last completed checkpoint" --
     * only `Complete` counts as completed for resume purposes;
     * `Skipped` is a distinct status recording that the underlying work
     * was authorized around, not verified, so resuming from it would
     * misrepresent what was actually confirmed.
     *
     * @return ?array<string, mixed>
     */
    public function latestComplete(string $workflowRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM checkpoints WHERE workflow_ref = :workflow_ref AND status = :status ORDER BY rowid DESC LIMIT 1'
        );
        $statement->execute(['workflow_ref' => $workflowRef, 'status' => 'Complete']);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * "Allow workflow continuation only if the checkpoint is Complete"
     * -- an authorized Skip is the one spec-named exception.
     */
    public function mayContinue(string $checkpointId): bool
    {
        $record = $this->get($checkpointId);

        return $record !== null && in_array($record['status'], self::RESOLVED_STATUSES, true);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['workflow_state'] = json_decode($row['workflow_state_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['workflow_state_json']);

        return $row;
    }
}
