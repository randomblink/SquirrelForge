<?php

declare(strict_types=1);

namespace SquirrelForge\Workflow;

use DateTimeImmutable;

/**
 * Records the per-step checkpoint history of a single StepWorkflow execution.
 *
 * A new instance belongs to exactly one workflow run; it is not a shared
 * singleton, so repeated or concurrent executions of the same registered
 * workflow never mix histories together.
 */
final class CheckpointManager
{
    /**
     * @var array<int, Checkpoint>
     */
    private array $history = [];

    /**
     * @param array<string, mixed> $context
     */
    public function record(string $stepId, string $status, array $context): Checkpoint
    {
        $checkpoint = new Checkpoint(
            'checkpoint_' . bin2hex(random_bytes(12)),
            $stepId,
            $status,
            $context,
            new DateTimeImmutable()
        );

        $this->history[] = $checkpoint;

        return $checkpoint;
    }

    /**
     * @return array<int, Checkpoint>
     */
    public function history(): array
    {
        return $this->history;
    }

    public function lastComplete(): ?Checkpoint
    {
        for ($i = count($this->history) - 1; $i >= 0; $i--) {
            if ($this->history[$i]->status === 'complete') {
                return $this->history[$i];
            }
        }

        return null;
    }
}
