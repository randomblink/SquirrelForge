<?php

declare(strict_types=1);

namespace SquirrelForge\Workflow;

use RuntimeException;
use Throwable;

final class WorkflowExecutionException extends RuntimeException
{
    /**
     * @param array<int, Checkpoint> $checkpoints
     * @param array{status: string, reversed: array<int, string>, notes: array<int, string>} $rollback
     */
    public function __construct(
        string $message,
        public readonly string $failedStepId,
        public readonly array $checkpoints,
        public readonly array $rollback,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
