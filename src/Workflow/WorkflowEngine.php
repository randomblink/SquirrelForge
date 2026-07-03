<?php

declare(strict_types=1);

namespace SquirrelForge\Workflow;

use RuntimeException;
use SquirrelForge\Contracts\WorkflowInterface;

final class WorkflowEngine
{
    /**
     * @var array<int, WorkflowInterface>
     */
    private array $workflows = [];

    public function register(WorkflowInterface $workflow): void
    {
        $this->workflows[] = $workflow;
    }

    public function execute(array $context): array
    {
        foreach ($this->workflows as $workflow) {
            if ($workflow->supports($context)) {
                return $workflow->execute($context);
            }
        }

        throw new RuntimeException('No workflow supports the supplied context.');
    }

    public function all(): array
    {
        return $this->workflows;
    }
}
