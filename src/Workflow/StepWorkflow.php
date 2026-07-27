<?php

declare(strict_types=1);

namespace SquirrelForge\Workflow;

use Closure;
use SquirrelForge\Contracts\StepInterface;
use SquirrelForge\Contracts\WorkflowInterface;
use Throwable;

/**
 * A WorkflowInterface implementation that sequences an ordered list of
 * StepInterface steps, recording a checkpoint after each and rolling back
 * every already-completed step, in reverse order, if a later step fails.
 *
 * This is a scoped reference implementation of the mechanics described by
 * 20_EXECUTION/WORKFLOW-EXECUTOR.md, CHECKPOINT-MANAGER.md, and
 * ROLLBACK-MANAGER.md. It does not confirm external validation or
 * rule-compliance evidence (14_ENGINE/VALIDATION.md, 19_REASONING/RULE-EVALUATOR.md),
 * does not wait for rollback authorization from 17_COORDINATION/FAILURE-RECOVERY.md,
 * does not persist checkpoints for resume after a process restart, and always
 * rolls back everything completed so far rather than distinguishing
 * Action/Stage/Session rollback scope — none of those surrounding layers have
 * a src/ implementation yet.
 */
final class StepWorkflow implements WorkflowInterface
{
    /**
     * @param StepInterface[] $steps executed in order
     */
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly Closure $supports,
        private readonly array $steps
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return StepInterface[]
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    public function supports(array $context): bool
    {
        return ($this->supports)($context);
    }

    public function execute(array $context): array
    {
        $checkpoints = new CheckpointManager();
        $rollbackManager = new RollbackManager();

        /** @var array<int, array{step: StepInterface, context: array<string, mixed>}> $completed */
        $completed = [];

        foreach ($this->steps as $step) {
            try {
                $context = array_merge($context, $step->run($context));
            } catch (Throwable $e) {
                $checkpoints->record($step->getId(), 'failed', $context);

                $rollbackResult = $rollbackManager->rollback($completed);

                throw new WorkflowExecutionException(
                    sprintf(
                        'Workflow "%s" failed at step "%s": %s',
                        $this->name,
                        $step->getId(),
                        $e->getMessage()
                    ),
                    $step->getId(),
                    $checkpoints->history(),
                    $rollbackResult,
                    $e
                );
            }

            $checkpoints->record($step->getId(), 'complete', $context);
            $completed[] = ['step' => $step, 'context' => $context];
        }

        return [
            'status' => 'success',
            'context' => $context,
            'checkpoints' => $checkpoints->history(),
        ];
    }
}
