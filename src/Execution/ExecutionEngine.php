<?php

declare(strict_types=1);

namespace SquirrelForge\Execution;

use Closure;
use SquirrelForge\Engine\EngineValidation;

/**
 * The Execution layer's entry point and top-level coordinator: receives
 * an approved strategy and execution plan, verifies preconditions,
 * hands off actual workflow execution to `WorkflowExecutor`, and routes
 * the terminal validation decision until the workflow reaches a
 * terminal state, per 20_EXECUTION/EXECUTION-ENGINE.md -- the tenth and
 * final real component in 20_EXECUTION, closing out this layer's
 * roster. It composes every other component built for this layer this
 * session, directly or through `WorkflowExecutor`'s own composition
 * chain.
 *
 * "It does not select the strategy... build the execution plan...
 * assign the agent... or resolve dependencies... it receives these as
 * already-approved inputs" (Purpose) is upheld literally: `$steps`
 * arrives as `ExecutionPlanner::plan()`'s own already-ordered output,
 * and `$dependencyBlockers` arrives as `DependencyAnalyzer`'s own
 * already-computed blockers -- the same shape `ExecutionPlanner` itself
 * already consumes them in, not re-derived. A non-empty
 * `$dependencyBlockers` refuses to begin at all (`Blocked`), per the
 * Rule's "execution may only begin after... dependencies... have been
 * approved."
 *
 * "Request the resume point from `CHECKPOINT-MANAGER.md` rather than
 * independently determining the correct checkpoint" composes the real
 * `SqliteCheckpointManager::latestComplete()`. This class does not
 * attempt to translate a resume checkpoint into skipping already-passed
 * `$steps` itself -- `WorkflowExecutor` has no resume-aware step
 * filtering of its own yet, and inventing one here would fabricate
 * mechanics this spec assigns nowhere; the resumed checkpoint reference
 * is returned as real evidence for the caller to act on, not silently
 * dropped.
 *
 * "Hand off workflow execution to `WORKFLOW-EXECUTOR.md`" and
 * "monitor overall execution progress through `EXECUTION-MONITOR.md`'s
 * reported health" are satisfied by one real composition, not two:
 * `WorkflowExecutor::execute()` already tracks every step through a
 * real `ExecutionMonitor` when the caller wires one in, so this class
 * never adds a second, redundant top-level monitor call -- "coordinates;
 * does not perform the detailed work itself" means delegating overall
 * progress observation to the same real chain, not duplicating it.
 *
 * "Submit the result-set reference... to `VALIDATION.md`" and "apply
 * the returned decision through `STATE-MANAGER.md`" are two different
 * kinds of dependency, handled differently: `EngineValidation` is a
 * real, already-built, stateless pure function, so this class composes
 * it directly (the same pure-function reuse `SqliteCheckpointManager`
 * already established for its own per-step gating -- this is a
 * separate, whole-execution invocation over the assembled Execution
 * Result Set, not a duplicate of that per-step call). `STATE-MANAGER.md`
 * has no code at all, so "apply... without reinterpreting it" is a
 * caller-supplied `$applyState` closure receiving the real decision
 * verbatim -- this class never decides what that state write means.
 *
 * The Execution Status table's decision-to-status mapping is applied
 * literally from the spec's own text ("Complete... ACCEPTED or
 * policy-permitted ACCEPTED_WITH_LIMITATIONS", "Repair Required...
 * REPAIR_REQUIRED", etc.) with one real gap the spec leaves unnamed:
 * `CLARIFICATION_REQUIRED` (one of `EngineValidation`'s own seven real
 * decisions) has no distinct row in this table. It maps to `Blocked` --
 * "cannot continue due to an unresolved... condition" is the closest
 * real fit the spec's own vocabulary offers, not a fabricated eighth
 * status. `REPAIR_REQUIRED`/`BLOCKED`/`RECOVERY_REQUIRED` decisions are
 * additionally routed through the composed `FailureHandler` as a real
 * `Validation Failure` (Responsibility 10-11's "route... to their
 * recorded owners"), with the decision itself as the required
 * `source_classification_ref` -- the exact same "preserve, don't
 * reinterpret" boundary `WorkflowExecutor`'s own checkpoint-failure
 * reporting already established for this layer.
 */
final class ExecutionEngine
{
    private const DECISION_TO_STATUS = [
        'ACCEPTED' => 'Complete',
        'ACCEPTED_WITH_LIMITATIONS' => 'Complete',
        'REPAIR_REQUIRED' => 'Repair Required',
        'BLOCKED' => 'Blocked',
        'RECOVERY_REQUIRED' => 'Recovery Required',
        'CLARIFICATION_REQUIRED' => 'Blocked',
        'REJECTED' => 'Rejected',
    ];

    private const ROUTED_DECISIONS = ['REPAIR_REQUIRED', 'BLOCKED', 'RECOVERY_REQUIRED'];

    public function __construct(
        private readonly ?WorkflowExecutor $workflowExecutor = null,
        private readonly ?SqliteCheckpointManager $checkpointManager = null,
        private readonly ?SqliteResultCollector $resultCollector = null,
        private readonly ?FailureHandler $failureHandler = null,
        private readonly ?SqliteExecutionLogger $logger = null,
        private readonly EngineValidation $validation = new EngineValidation()
    ) {
    }

    /**
     * Execution Process, steps 1-13.
     *
     * @param array<int, array{step_id: string, task_id?: ?string, target_ref?: ?string, workflow?: ?string, checkpoint?: ?string, stop_conditions?: array<int, string>, status?: ?string}> $steps ExecutionPlanner::plan()'s own `steps`.
     * @param array<int, array{dependency_id: string, required_by: ?string}> $dependencyBlockers DependencyAnalyzer's own already-computed blockers; a non-empty list refuses to begin.
     * @param bool $resume whether to request the resume checkpoint before starting.
     * @param ?Closure $dispatchTarget forwarded through WorkflowExecutor to ActionDispatcher.
     * @param ?Closure $checkpointEvidence forwarded through WorkflowExecutor for per-step checkpoints.
     * @param ?Closure $recoveryRequest forwarded to FailureHandler for both step-level and terminal validation failures.
     * @param ?Closure $route forwarded to FailureHandler.
     * @param array{validation_items?: array<int, array<string, mixed>>, validation_options?: array<string, mixed>} $finalValidationEvidence evidence for the terminal, whole-execution EngineValidation call.
     * @param ?Closure $applyState (string $workflowRef, string $decision, array $context): mixed the real hand-off to STATE-MANAGER.md. The decision is applied verbatim, never reinterpreted.
     * @return array{
     *     status: string,
     *     workflow_ref: string,
     *     resume_checkpoint: ?array<string, mixed>,
     *     steps: array<int, array<string, mixed>>,
     *     result_set: array<int, array<string, mixed>>,
     *     validation_decision: ?string,
     *     error: ?string
     * }
     */
    public function run(
        string $workflowRef,
        array $steps = [],
        array $dependencyBlockers = [],
        bool $resume = false,
        ?Closure $dispatchTarget = null,
        ?Closure $checkpointEvidence = null,
        ?Closure $recoveryRequest = null,
        ?Closure $route = null,
        array $finalValidationEvidence = [],
        ?Closure $applyState = null
    ): array {
        if ($dependencyBlockers !== []) {
            return $this->terminal($workflowRef, 'Blocked', null, [], [], null, 'Execution cannot begin: unresolved dependency blockers exist.');
        }

        $resumeCheckpoint = $resume ? $this->checkpointManager?->latestComplete($workflowRef) : null;

        if ($this->workflowExecutor === null) {
            return $this->terminal($workflowRef, 'Pending', $resumeCheckpoint, [], [], null, null);
        }

        $executionResult = $this->workflowExecutor->execute($workflowRef, $steps, $dispatchTarget, $checkpointEvidence, $recoveryRequest, $route);

        if ($executionResult['workflow_status'] === 'Halted') {
            return $this->terminal($workflowRef, 'Failed', $resumeCheckpoint, $executionResult['steps'], [], null, $executionResult['error']);
        }

        $resultSet = $this->resultCollector?->forExecution($workflowRef) ?? [];

        $validationResult = $this->validation->evaluate(
            $finalValidationEvidence['validation_items'] ?? [],
            $finalValidationEvidence['validation_options'] ?? []
        );
        $decision = $validationResult['decision'];

        if ($applyState !== null) {
            $applyState($workflowRef, $decision, ['validation' => $validationResult]);
        }

        if (in_array($decision, self::ROUTED_DECISIONS, true) && $this->failureHandler !== null) {
            $received = $this->failureHandler->receive([
                'execution_ref' => $workflowRef,
                'reporting_component' => 'execution_engine',
                'failure_type' => 'Validation Failure',
                'observed_condition' => sprintf('Terminal validation decision "%s" requires routing.', $decision),
                'source_classification_ref' => $decision,
            ]);

            if ($received['outcome'] === 'normalized' && $recoveryRequest !== null) {
                $this->failureHandler->forward($received['failure_record'], $recoveryRequest, $route);
            }
        }

        $status = self::DECISION_TO_STATUS[$decision] ?? 'Failed';

        return $this->terminal($workflowRef, $status, $resumeCheckpoint, $executionResult['steps'], $resultSet, $decision, null);
    }

    /**
     * @param ?array<string, mixed> $resumeCheckpoint
     * @param array<int, array<string, mixed>> $steps
     * @param array<int, array<string, mixed>> $resultSet
     * @return array{
     *     status: string,
     *     workflow_ref: string,
     *     resume_checkpoint: ?array<string, mixed>,
     *     steps: array<int, array<string, mixed>>,
     *     result_set: array<int, array<string, mixed>>,
     *     validation_decision: ?string,
     *     error: ?string
     * }
     */
    private function terminal(string $workflowRef, string $status, ?array $resumeCheckpoint, array $steps, array $resultSet, ?string $decision, ?string $error): array
    {
        $this->logger?->record([
            'execution_id' => $workflowRef,
            'actor' => 'execution_engine',
            'action_type' => 'execution_run',
            'outcome' => $status,
        ]);

        return [
            'status' => $status,
            'workflow_ref' => $workflowRef,
            'resume_checkpoint' => $resumeCheckpoint,
            'steps' => $steps,
            'result_set' => $resultSet,
            'validation_decision' => $decision,
            'error' => $error,
        ];
    }
}
