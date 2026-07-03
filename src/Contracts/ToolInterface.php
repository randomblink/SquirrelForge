<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface ToolInterface extends BootableInterface, HealthCheckInterface
{
    /**
     * Returns the unique tool identifier.
     */
    public function getId(): string;

    /**
     * Returns the tool name.
     */
    public function getName(): string;

    /**
     * Returns the tool version.
     */
    public function getVersion(): string;

    /**
     * Returns a short description of the tool.
     */
    public function getDescription(): string;

    /**
     * Determine whether the tool can handle the supplied operation.
     */
    public function supports(string $operation): bool;

    /**
     * Execute a tool operation.
     *
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function execute(
        string $operation,
        array $parameters = []
    ): array;

    /**
     * Returns tool metadata.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;
}
