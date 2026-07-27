<?php

declare(strict_types=1);

namespace SquirrelForge\Workflow;

use DateTimeImmutable;

final class Checkpoint
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public readonly string $id,
        public readonly string $stepId,
        public readonly string $status,
        public readonly array $context,
        public readonly DateTimeImmutable $recordedAt
    ) {
    }
}
