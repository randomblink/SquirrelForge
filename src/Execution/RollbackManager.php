<?php

declare(strict_types=1);

namespace SquirrelForge\Execution;

use Closure;
use Throwable;

/**
 * Executes an authorized rollback by restoring the workflow to a target
 * checkpoint, reversing completed actions within the authorized scope,
 * and confirming the restored state's integrity, per
 * 20_EXECUTION/ROLLBACK-MANAGER.md -- the seventh real component in
 * 20_EXECUTION.
 *
 * Unlike every other component built this session, this class does not
 * take `FailureHandler` as a constructor dependency: `FAILURE-HANDLER.md`
 * is this spec's own "Used By" -- `RollbackManager` is what
 * `FailureHandler::forward()`'s `$route` closure calls into for a
 * `Rollback` operation, not something this class calls outward. "Return
 * control to `FAILURE-HANDLER.md`" (Responsibilities) is satisfied
 * structurally by `rollback()` simply returning its Rollback Record to
 * whichever caller (the `$route` closure) invoked it.
 *
 * Rule ("every rollback must be authorized before it begins") is a real,
 * checked precondition: `rollback()` refuses outright without a
 * non-empty `authorization_ref` -- this class never infers an
 * authorization `17_COORDINATION/FAILURE-RECOVERY.md` was never
 * actually given, the same discipline `SqliteCheckpointManager::skip()`
 * already established for this layer.
 *
 * "Confirm restored-state integrity through `CHECKPOINT-MANAGER.md`"
 * (Responsibilities) is genuine composition, and a deliberate choice
 * about *what* gets confirmed: re-confirming the checkpoint being
 * restored *to* would be meaningless (`SqliteCheckpointManager::confirm()`
 * already refuses to re-run on an already-`Complete` checkpoint, and it
 * passed once already) -- what this class actually verifies is that the
 * state *produced by the rollback itself* is coherent, by creating and
 * confirming a fresh checkpoint at the restored point
 * (`rollback_restored:{scope}`). This is also this spec's hardest
 * requirement, not an optional nicety: when a `SqliteCheckpointManager`
 * is configured, that fresh confirmation not reaching `Complete` makes
 * the whole rollback `Failed` regardless of how many actions were
 * successfully reversed -- "must restore... to a checkpoint whose
 * integrity is confirmed" admits no partial credit on that specific
 * point.
 *
 * "Reverse completed actions within that scope when possible"
 * acknowledges some actions genuinely cannot be reversed -- an action
 * explicitly marked `reversible: false` is recorded as such and never
 * counted as a failure; only a real, attempted, failed reversal (the
 * caller-supplied `$reverseAction` closure returning `reversed: false`
 * or throwing) produces `Partial` rather than `Successful`. Actual
 * reversal mechanics are that caller-supplied closure -- reversing a
 * file write, an API call, and a database transaction share no common
 * protocol, the same reasoning behind every provider-facing `Closure`
 * already established across this codebase.
 */
final class RollbackManager
{
    private const SCOPES = ['Action', 'Stage', 'Workflow', 'Session'];

    public function __construct(
        private readonly ?SqliteCheckpointManager $checkpointManager = null,
        private readonly ?SqliteExecutionLogger $logger = null
    ) {
    }

    /**
     * Rollback Process steps 1-8.
     *
     * @param array{workflow_ref?: ?string, authorization_ref?: ?string, scope?: ?string, target_checkpoint_id?: ?string} $request
     * @param array<int, array{action_id: string, reversible?: bool}> $completedActions actions within the authorized scope that may need reversing.
     * @param ?Closure $reverseAction (array $action): array{reversed: bool, error?: ?string} the real, domain-specific reversal mechanics for one completed, reversible action.
     * @param ?Closure $checkpointEvidence (string $scope): array{validation_items?: array<int, array<string, mixed>>, validation_options?: array<string, mixed>, rules?: array<int, array<string, mixed>>, rule_context?: array<string, mixed>, rule_options?: array<string, mixed>} supplies the real evidence confirming the restored state's integrity.
     * @return array{
     *     rollback_id: ?string,
     *     status: string,
     *     workflow_ref: ?string,
     *     scope: ?string,
     *     checkpoint_ref: ?string,
     *     reversed_actions: array<int, string>,
     *     failed_actions: array<int, string>,
     *     not_reversible_actions: array<int, string>,
     *     error: ?string
     * }
     */
    public function rollback(array $request, array $completedActions = [], ?Closure $reverseAction = null, ?Closure $checkpointEvidence = null): array
    {
        $workflowRef = $request['workflow_ref'] ?? null;
        $authorizationRef = $request['authorization_ref'] ?? null;
        $scope = $request['scope'] ?? null;

        if (!$this->presentAndNonEmpty($workflowRef) || !$this->presentAndNonEmpty($authorizationRef)) {
            return $this->result(null, 'Failed', $workflowRef, $scope, null, [], [], [], 'A rollback requires a non-empty workflow_ref and authorization_ref; it must be authorized before it begins.');
        }

        if (!is_string($scope) || !in_array($scope, self::SCOPES, true)) {
            return $this->result(null, 'Failed', $workflowRef, $scope, null, [], [], [], sprintf('"%s" is not one of this spec\'s named Rollback Levels.', (string) ($scope ?? '')));
        }

        $checkpointRef = $request['target_checkpoint_id'] ?? $this->checkpointManager?->latestComplete($workflowRef)['checkpoint_id'] ?? null;

        if ($this->checkpointManager !== null && $checkpointRef === null) {
            return $this->result(null, 'Failed', $workflowRef, $scope, null, [], [], [], sprintf('No Complete checkpoint exists for workflow "%s" to restore to.', $workflowRef));
        }

        [$reversed, $failed, $notReversible] = $this->reverseActions($completedActions, $reverseAction);

        $integrityConfirmed = $this->confirmRestoredIntegrity($workflowRef, $scope, $checkpointEvidence);

        if ($integrityConfirmed === false) {
            $rollbackId = $this->record($workflowRef, 'Failed', $scope, $checkpointRef, $authorizationRef);

            return $this->result($rollbackId, 'Failed', $workflowRef, $scope, $checkpointRef, $reversed, $failed, $notReversible, 'Restored-state integrity was not confirmed through the Checkpoint Manager.');
        }

        $status = $failed === [] ? 'Successful' : 'Partial';
        $rollbackId = $this->record($workflowRef, $status, $scope, $checkpointRef, $authorizationRef);

        return $this->result($rollbackId, $status, $workflowRef, $scope, $checkpointRef, $reversed, $failed, $notReversible, null);
    }

    /**
     * @param array<int, array{action_id: string, reversible?: bool}> $completedActions
     * @return array{0: array<int, string>, 1: array<int, string>, 2: array<int, string>}
     */
    private function reverseActions(array $completedActions, ?Closure $reverseAction): array
    {
        $reversed = [];
        $failed = [];
        $notReversible = [];

        foreach ($completedActions as $action) {
            $actionId = $action['action_id'] ?? 'unknown';

            if (($action['reversible'] ?? true) === false) {
                $notReversible[] = $actionId;

                continue;
            }

            if ($reverseAction === null) {
                continue;
            }

            try {
                $outcome = $reverseAction($action);
            } catch (Throwable) {
                $failed[] = $actionId;

                continue;
            }

            if (($outcome['reversed'] ?? false) === true) {
                $reversed[] = $actionId;
            } else {
                $failed[] = $actionId;
            }
        }

        return [$reversed, $failed, $notReversible];
    }

    /**
     * @return ?bool null when no CheckpointManager is configured (dry run, nothing fabricated); true/false otherwise.
     */
    private function confirmRestoredIntegrity(?string $workflowRef, string $scope, ?Closure $checkpointEvidence): ?bool
    {
        if ($this->checkpointManager === null) {
            return null;
        }

        $created = $this->checkpointManager->create(['workflow_ref' => $workflowRef, 'stage' => 'rollback_restored:' . $scope]);
        $evidence = $checkpointEvidence !== null ? $checkpointEvidence($scope) : [];

        $confirmed = $this->checkpointManager->confirm(
            $created['checkpoint_id'],
            $evidence['validation_items'] ?? [],
            $evidence['validation_options'] ?? [],
            $evidence['rules'] ?? [],
            $evidence['rule_context'] ?? [],
            $evidence['rule_options'] ?? []
        );

        return ($confirmed['record']['status'] ?? null) === 'Complete';
    }

    private function record(?string $workflowRef, string $status, string $scope, ?string $checkpointRef, ?string $authorizationRef): ?string
    {
        $rollbackId = 'rollback_' . bin2hex(random_bytes(12));

        $this->logger?->record([
            'execution_id' => $workflowRef ?? 'unknown',
            'task_id' => $rollbackId,
            'actor' => 'rollback_manager',
            'action_type' => 'rollback',
            'inputs' => ['scope' => $scope, 'authorization_ref' => $authorizationRef, 'checkpoint_ref' => $checkpointRef],
            'outcome' => $status,
            'checkpoint_id' => $checkpointRef,
        ]);

        return $rollbackId;
    }

    private function presentAndNonEmpty(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @param array<int, string> $reversed
     * @param array<int, string> $failed
     * @param array<int, string> $notReversible
     * @return array{
     *     rollback_id: ?string,
     *     status: string,
     *     workflow_ref: ?string,
     *     scope: ?string,
     *     checkpoint_ref: ?string,
     *     reversed_actions: array<int, string>,
     *     failed_actions: array<int, string>,
     *     not_reversible_actions: array<int, string>,
     *     error: ?string
     * }
     */
    private function result(
        ?string $rollbackId,
        string $status,
        ?string $workflowRef,
        ?string $scope,
        ?string $checkpointRef,
        array $reversed,
        array $failed,
        array $notReversible,
        ?string $error
    ): array {
        return [
            'rollback_id' => $rollbackId,
            'status' => $status,
            'workflow_ref' => $workflowRef,
            'scope' => $scope,
            'checkpoint_ref' => $checkpointRef,
            'reversed_actions' => $reversed,
            'failed_actions' => $failed,
            'not_reversible_actions' => $notReversible,
            'error' => $error,
        ];
    }
}
