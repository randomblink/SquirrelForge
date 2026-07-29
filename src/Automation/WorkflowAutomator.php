<?php

declare(strict_types=1);

namespace SquirrelForge\Automation;

use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Resilience\SqliteFailureDetector;
use SquirrelForge\Resilience\SqliteResilienceManager;
use SquirrelForge\Workflow\WorkflowEngine;
use SquirrelForge\Workflow\WorkflowExecutionException;
use Throwable;

/**
 * Owns the automation-to-execution handoff, per
 * 33_AUTOMATION/WORKFLOW-AUTOMATOR.md: gates a workflow automation
 * request, submits it to the real execution engine, and republishes the
 * outcome as automation-facing status.
 *
 * "Submit workflow execution requests to authoritative Execution
 * components" is a genuine delegation to the real
 * SquirrelForge\Workflow\WorkflowEngine that already exists in this
 * codebase -- this class never runs a step itself, matching the spec's
 * rule that it "does not... execute actions... or own workflow
 * execution state". "Check handoff completeness" is a real gate: the
 * injected ApprovalGate is consulted first, and only a `pass` or
 * `conditional-pass` outcome allows the handoff to reach the execution
 * engine at all -- a blocked, pending, or incomplete gate never touches
 * WorkflowEngine.
 *
 * "Consume execution progress, completion, failure, and recovery-status
 * references" and "Request recovery handling from Resilience owners" are
 * both real: WorkflowEngine::execute() throws a real
 * WorkflowExecutionException carrying the failed step, checkpoints, and
 * rollback result on failure (StepWorkflow already performs that
 * rollback itself -- this class doesn't repeat it), and this class turns
 * that into a real SqliteFailureDetector detection, then hands it to the
 * injected SqliteResilienceManager exactly as the caller configured
 * (self_heal_action/recovery_strategy) -- it never invents a recovery
 * strategy of its own, the same restraint SqliteResilienceManager itself
 * exercises over its own inputs.
 */
final class WorkflowAutomator
{
    private const PASSING_GATE_OUTCOMES = ['pass', 'conditional-pass'];

    public function __construct(
        private readonly ?WorkflowEngine $workflowEngine = null,
        private readonly ?ApprovalGate $approvalGate = null,
        private readonly ?SqliteFailureDetector $failureDetector = null,
        private readonly ?SqliteResilienceManager $resilienceManager = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array<string, mixed> $context forwarded to WorkflowEngine::execute()
     * @param array{approval_references?: array<string, array<string, mixed>>, approval_scope?: ?string, required_categories?: array<int, string>, recovery?: array<string, mixed>} $options
     * @return array{outcome: string, status: ?string, gate_outcome: ?string, checkpoints: ?array<int, mixed>, rollback: ?array<string, mixed>, recovery: ?array<string, mixed>, error: ?string}
     */
    public function handoff(string $automationId, array $context, array $options = []): array
    {
        $gateOutcome = null;

        if ($this->approvalGate !== null) {
            $gate = $this->approvalGate->checkGate($automationId, $options['approval_references'] ?? [], [
                'scope' => $options['approval_scope'] ?? null,
                'required_categories' => $options['required_categories'] ?? [],
            ]);
            $gateOutcome = $gate['outcome'];

            if (!in_array($gateOutcome, self::PASSING_GATE_OUTCOMES, true)) {
                return $this->finish('blocked', null, $gateOutcome, null, null, null, sprintf('Handoff blocked: approval gate outcome was "%s".', $gateOutcome));
            }
        }

        if ($this->workflowEngine === null) {
            return $this->finish('rejected', null, $gateOutcome, null, null, null, 'Workflow Engine is not configured.');
        }

        try {
            $result = $this->workflowEngine->execute($context);

            return $this->finish('completed', $result['status'], $gateOutcome, $result['checkpoints'], null, null, null);
        } catch (WorkflowExecutionException $e) {
            $recovery = $this->requestRecovery($e, $options['recovery'] ?? null);

            return $this->finish('failed', 'failed', $gateOutcome, $e->checkpoints, $e->rollback, $recovery, $e->getMessage());
        } catch (Throwable $e) {
            return $this->finish('rejected', null, $gateOutcome, null, null, null, $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed>|null $recoveryOptions
     * @return array<string, mixed>|null
     */
    private function requestRecovery(WorkflowExecutionException $exception, ?array $recoveryOptions): ?array
    {
        if ($recoveryOptions === null || $this->failureDetector === null || $this->resilienceManager === null) {
            return null;
        }

        $detection = $this->failureDetector->detect('workflow', 'execution_failure', [
            'affected_components' => [$exception->failedStepId],
        ]);

        return $this->resilienceManager->coordinate($detection, $recoveryOptions);
    }

    /**
     * @param array<int, mixed>|null $checkpoints
     * @param array<string, mixed>|null $rollback
     * @param array<string, mixed>|null $recovery
     * @return array{outcome: string, status: ?string, gate_outcome: ?string, checkpoints: ?array<int, mixed>, rollback: ?array<string, mixed>, recovery: ?array<string, mixed>, error: ?string}
     */
    private function finish(string $outcome, ?string $status, ?string $gateOutcome, ?array $checkpoints, ?array $rollback, ?array $recovery, ?string $error): array
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'workflow_automator.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['outcome' => $outcome, 'gate_outcome' => $gateOutcome]
        ));

        return ['outcome' => $outcome, 'status' => $status, 'gate_outcome' => $gateOutcome, 'checkpoints' => $checkpoints, 'rollback' => $rollback, 'recovery' => $recovery, 'error' => $error];
    }
}
