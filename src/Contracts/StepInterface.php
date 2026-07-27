<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface StepInterface
{
    public function getId(): string;

    public function getName(): string;

    /**
     * Perform this step's work and return data to merge into the workflow context.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function run(array $context): array;

    /**
     * Reverse this step's effects. Called only after this step already completed
     * successfully and a later step in the same workflow run failed.
     *
     * @param array<string, mixed> $context the context as it stood immediately after this step ran
     */
    public function rollback(array $context): void;
}
