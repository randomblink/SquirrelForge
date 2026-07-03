<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface WorkflowInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * Determine whether this workflow can handle the given input.
     *
     * @param array<string, mixed> $context
     */
    public function supports(array $context): bool;

    /**
     * Execute the workflow.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function execute(array $context): array;
}
