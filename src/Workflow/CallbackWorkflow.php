<?php

declare(strict_types=1);

namespace SquirrelForge\Workflow;

use Closure;
use SquirrelForge\Contracts\WorkflowInterface;

final class CallbackWorkflow implements WorkflowInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly Closure $supports,
        private readonly Closure $execute
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

    public function supports(array $context): bool
    {
        return ($this->supports)($context);
    }

    public function execute(array $context): array
    {
        return ($this->execute)($context);
    }
}
