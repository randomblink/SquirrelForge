<?php

declare(strict_types=1);

namespace SquirrelForge\Execution;

use Closure;

/**
 * Carries out the active workflow's approved steps, in the order and
 * with the dependencies the execution plan already defines, handing
 * each step to `ActionDispatcher` for actual execution and to
 * `SqliteCheckpointManager` for required checkpoint validation, per
 * 20_EXECUTION/WORKFLOW-EXECUTOR.md -- the sixth real component in
 * 20_EXECUTION, and the layer's first genuine top-level sequencer,
 * composing four of this layer's five already-built components at
 * once.
 *
 * "Receive the execution plan from `EXECUTION-ENGINE.md`" is, like
 * every other "reports to" relationship this layer's Depends On
 * fields already describe, an input this class consumes rather than a
 * component it calls -- `execute()` takes `ExecutionPlanner::plan()`'s
 * own `steps` array directly, the same reasoning that made
 * `ExecutionLogger`/`ExecutionMonitor`/`ActionDispatcher` buildable
 * before their own "used by" components existed. This class never
 * re-derives sequencing, parallel grouping, or blocking itself
 * (Boundary: "does not decide dependency order, already fixed by
 * `EXECUTION-PLANNER.md`") -- a step the planner already marked
 * `blocked` is recorded `Skipped` using the plan's own `stop_conditions`
 * as the justification reference, never a fabricated one, and
 * execution continues to the next step rather than treating a single
 * blocked task as a whole-workflow halt.
 *
 * Each non-blocked step is handed to a real `ActionDispatcher::dispatch()`
 * call. Because `ActionDispatcher` already reports its own
 * Prerequisite/Dispatch/Execution failures to `FailureHandler` (this
 * layer's established composition chain), this class does not
 * re-report the same fact a second time -- doing so would fabricate a
 * duplicate failure record for one real event. What this class *does*
 * report to `FailureHandler` itself is a checkpoint failure, which
 * `ActionDispatcher` has no way to know about: `SqliteCheckpointManager::confirm()`
 * already distinguishes which of `EngineValidation` or `RuleEvaluator`
 * failed (its own `validation_decision`/`rule_outcome` fields), so this
 * class reports the real, correct `Validation Failure` or `Rule
 * Failure` type with that field as the required
 * `source_classification_ref` -- never guessing which one failed.
 *
 * The Workflow Step Model's own status vocabulary (`Pending`/`Running`/
 * `Passed`/`Failed`/`Skipped`) is distinct from `ActionDispatcher`'s
 * own (`Dispatched`/`Running`/`Complete`/`Failed`) -- this class maps
 * between them explicitly (`Complete` -> `Passed`) rather than
 * conflating two different specs' vocabularies.
 *
 * "Its step-level activity is observed in detail by `EXECUTION-MONITOR.md`"
 * is genuine composition, not merely documentation: every dispatched
 * step's outcome is translated into `ExecutionMonitor::track()`'s own
 * signal shape (`completion_signal`/`error`), so the monitor's real
 * history captures each step as it happens.
 *
 * Retry is explicitly out of scope for a single `execute()` call, the
 * same "pure function over one attempt, caller re-invokes across
 * attempts" boundary `EngineValidation` already draws around itself: a
 * `Failed` step halts this pass at `Halted` unless the
 * `$recoveryRequest`/`$route` closures authorize a `Skip` for that
 * specific step, in which case execution continues past it.
 */
final class WorkflowExecutor
{
    private const DISPATCH_TO_STEP_STATUS = ['Complete' => 'Passed', 'Running' => 'Running', 'Dispatched' => 'Running', 'Failed' => 'Failed'];

    public function __construct(
        private readonly ?ActionDispatcher $actionDispatcher = null,
        private readonly ?SqliteCheckpointManager $checkpointManager = null,
        private readonly ?FailureHandler $failureHandler = null,
        private readonly ?ExecutionMonitor $executionMonitor = null,
        private readonly ?SqliteExecutionLogger $logger = null
    ) {
    }

    /**
     * @param array<int, array{step_id: string, task_id?: ?string, target_ref?: ?string, workflow?: ?string, checkpoint?: ?string, stop_conditions?: array<int, string>, status?: ?string}> $steps ExecutionPlanner::plan()'s own `steps`, already ordered and dependency-resolved.
     * @param ?Closure $dispatchTarget forwarded to ActionDispatcher::dispatch() for each step's real technical dispatch.
     * @param ?Closure $checkpointEvidence (array $step): array{validation_items?: array<int, array<string, mixed>>, validation_options?: array<string, mixed>, rules?: array<int, array<string, mixed>>, rule_context?: array<string, mixed>, rule_options?: array<string, mixed>} supplies the real evidence for a step's checkpoint confirmation. Omitting it confirms against empty evidence, matching SqliteCheckpointManager::confirm()'s own "nothing required" default.
     * @param ?Closure $recoveryRequest forwarded to FailureHandler::forward() for both dispatch and checkpoint failures.
     * @param ?Closure $route forwarded to FailureHandler::forward().
     * @return array{workflow_status: string, steps: array<int, array<string, mixed>>, error: ?string}
     */
    public function execute(
        string $workflowRef,
        array $steps,
        ?Closure $dispatchTarget = null,
        ?Closure $checkpointEvidence = null,
        ?Closure $recoveryRequest = null,
        ?Closure $route = null
    ): array {
        $stepRecords = [];
        $halted = false;

        foreach ($steps as $step) {
            $stepId = $step['step_id'] ?? null;

            if (!is_string($stepId) || $stepId === '') {
                continue;
            }

            if (($step['status'] ?? null) === 'blocked') {
                $stepRecords[] = $this->recordStep($workflowRef, $stepId, 'Skipped', implode('; ', $step['stop_conditions'] ?? []));

                continue;
            }

            $dispatchResult = $this->dispatchStep($workflowRef, $step, $stepId, $dispatchTarget, $recoveryRequest, $route);
            $stepStatus = self::DISPATCH_TO_STEP_STATUS[$dispatchResult['status']] ?? 'Failed';

            $this->trackStep($workflowRef, $stepId, $dispatchResult);

            if ($stepStatus === 'Failed') {
                $skippedByRecovery = ($dispatchResult['recovery']['outcome'] ?? null) === 'routed' && ($dispatchResult['recovery']['operation'] ?? null) === 'Skip';

                if (!$skippedByRecovery) {
                    $stepRecords[] = $this->recordStep($workflowRef, $stepId, 'Failed', $dispatchResult['error']);
                    $halted = true;

                    break;
                }

                $stepRecords[] = $this->recordStep($workflowRef, $stepId, 'Skipped', 'Authorized skip after failure: ' . ($dispatchResult['error'] ?? ''));

                continue;
            }

            $checkpointOutcome = $this->confirmCheckpointIfRequired($workflowRef, $step, $stepId, $checkpointEvidence, $recoveryRequest, $route);

            if ($checkpointOutcome !== null && $checkpointOutcome['status'] === 'Failed') {
                $stepRecords[] = $this->recordStep($workflowRef, $stepId, 'Failed', $checkpointOutcome['error']);
                $halted = true;

                break;
            }

            $stepRecords[] = $this->recordStep($workflowRef, $stepId, $stepStatus, null);
        }

        $workflowStatus = $halted ? 'Halted' : 'Completed';

        $this->logger?->record([
            'execution_id' => $workflowRef,
            'actor' => 'workflow_executor',
            'action_type' => 'workflow_completion',
            'outcome' => $workflowStatus,
        ]);

        return ['workflow_status' => $workflowStatus, 'steps' => $stepRecords, 'error' => $halted ? 'Workflow halted on an unrecovered step failure.' : null];
    }

    /**
     * @param array<string, mixed> $step
     * @return array{status: string, action_id: string, workflow_ref: ?string, target_ref: ?string, result: mixed, error: ?string, recovery: ?array<string, mixed>}
     */
    private function dispatchStep(string $workflowRef, array $step, string $stepId, ?Closure $dispatchTarget, ?Closure $recoveryRequest, ?Closure $route): array
    {
        if ($this->actionDispatcher === null) {
            return ['status' => 'Dispatched', 'action_id' => $stepId, 'workflow_ref' => $workflowRef, 'target_ref' => null, 'result' => null, 'error' => null, 'recovery' => null];
        }

        return $this->actionDispatcher->dispatch(
            [
                'action_id' => $stepId,
                'workflow_ref' => $workflowRef,
                'action_type' => $step['workflow'] ?? 'unspecified',
                'target_ref' => $step['target_ref'] ?? $step['task_id'] ?? $stepId,
            ],
            $dispatchTarget,
            $recoveryRequest,
            $route
        );
    }

    /**
     * @param array<string, mixed> $dispatchResult
     */
    private function trackStep(string $workflowRef, string $stepId, array $dispatchResult): void
    {
        $this->executionMonitor?->track($workflowRef, $stepId, [
            'completion_signal' => $dispatchResult['status'] === 'Complete',
            'error' => $dispatchResult['error'],
        ]);
    }

    /**
     * Requests checkpoint validation only when the plan names one for
     * this step (`step['checkpoint']`), confirming it with
     * caller-supplied evidence and reporting a real, correctly
     * classified failure through FailureHandler when it does not pass.
     *
     * @param array<string, mixed> $step
     * @return array{status: string, error: ?string}|null null when the plan requires no checkpoint here.
     */
    private function confirmCheckpointIfRequired(
        string $workflowRef,
        array $step,
        string $stepId,
        ?Closure $checkpointEvidence,
        ?Closure $recoveryRequest,
        ?Closure $route
    ): ?array {
        $checkpointStage = $step['checkpoint'] ?? null;

        if (!is_string($checkpointStage) || $checkpointStage === '' || $this->checkpointManager === null) {
            return null;
        }

        $created = $this->checkpointManager->create(['workflow_ref' => $workflowRef, 'stage' => $checkpointStage]);
        $evidence = $checkpointEvidence !== null ? $checkpointEvidence($step) : [];

        $confirmed = $this->checkpointManager->confirm(
            $created['checkpoint_id'],
            $evidence['validation_items'] ?? [],
            $evidence['validation_options'] ?? [],
            $evidence['rules'] ?? [],
            $evidence['rule_context'] ?? [],
            $evidence['rule_options'] ?? []
        );

        $record = $confirmed['record'];

        if ($record === null || $record['status'] !== 'Complete') {
            $failureType = $this->checkpointFailureType($record);
            $reason = sprintf('Checkpoint "%s" for step "%s" did not confirm as Complete (status: %s).', $checkpointStage, $stepId, $record['status'] ?? $confirmed['outcome']);

            if ($this->failureHandler !== null) {
                $received = $this->failureHandler->receive([
                    'execution_ref' => $workflowRef,
                    'action_ref' => $stepId,
                    'checkpoint_ref' => $created['checkpoint_id'],
                    'reporting_component' => 'workflow_executor',
                    'failure_type' => $failureType['type'],
                    'observed_condition' => $reason,
                    'source_classification_ref' => $failureType['reference'],
                ]);

                if ($received['outcome'] === 'normalized' && $recoveryRequest !== null) {
                    $this->failureHandler->forward($received['failure_record'], $recoveryRequest, $route);
                }
            }

            return ['status' => 'Failed', 'error' => $reason];
        }

        return ['status' => 'Passed', 'error' => null];
    }

    /**
     * @param ?array<string, mixed> $record
     * @return array{type: string, reference: ?string}
     */
    private function checkpointFailureType(?array $record): array
    {
        $validationDecision = $record['validation_decision'] ?? null;

        if ($validationDecision !== null && !in_array($validationDecision, ['ACCEPTED', 'ACCEPTED_WITH_LIMITATIONS'], true)) {
            return ['type' => 'Validation Failure', 'reference' => $validationDecision];
        }

        $ruleOutcome = $record['rule_outcome'] ?? null;

        if ($ruleOutcome !== null && $ruleOutcome !== 'Passed') {
            return ['type' => 'Rule Failure', 'reference' => $ruleOutcome];
        }

        return ['type' => 'Execution Failure', 'reference' => $record['status'] ?? 'unknown'];
    }

    /**
     * @return array{step_id: string, status: string, error: ?string}
     */
    private function recordStep(string $workflowRef, string $stepId, string $status, ?string $note): array
    {
        $this->logger?->record([
            'execution_id' => $workflowRef,
            'task_id' => $stepId,
            'actor' => 'workflow_executor',
            'action_type' => 'step_status',
            'outcome' => $status,
            'error_category' => $status === 'Failed' ? 'step_failed' : null,
        ]);

        return ['step_id' => $stepId, 'status' => $status, 'error' => $status === 'Failed' ? $note : ($note === '' ? null : $note)];
    }
}
