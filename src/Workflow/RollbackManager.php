<?php

declare(strict_types=1);

namespace SquirrelForge\Workflow;

use SquirrelForge\Contracts\StepInterface;
use Throwable;

/**
 * Reverses the steps a StepWorkflow already completed, in LIFO order, after a
 * later step in the same run has failed.
 */
final class RollbackManager
{
    /**
     * @param array<int, array{step: StepInterface, context: array<string, mixed>}> $completedSteps
     *        steps already completed, in the order they ran
     * @return array{status: string, reversed: array<int, string>, notes: array<int, string>}
     */
    public function rollback(array $completedSteps): array
    {
        $reversed = [];
        $notes = [];

        foreach (array_reverse($completedSteps) as $entry) {
            try {
                $entry['step']->rollback($entry['context']);
                $reversed[] = $entry['step']->getId();
            } catch (Throwable $e) {
                $notes[] = sprintf(
                    'Failed to reverse step "%s": %s',
                    $entry['step']->getId(),
                    $e->getMessage()
                );
            }
        }

        if ($notes === []) {
            $status = 'successful';
        } elseif ($reversed !== []) {
            $status = 'partial';
        } else {
            $status = 'failed';
        }

        return [
            'status' => $status,
            'reversed' => $reversed,
            'notes' => $notes,
        ];
    }
}
