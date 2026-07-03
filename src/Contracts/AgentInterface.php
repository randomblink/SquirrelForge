<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface AgentInterface extends BootableInterface, HealthCheckInterface
{
    /**
     * Returns the unique agent identifier.
     */
    public function getId(): string;

    /**
     * Returns the human-readable agent name.
     */
    public function getName(): string;

    /**
     * Returns the agent version.
     */
    public function getVersion(): string;

    /**
     * Returns a short description of the agent.
     */
    public function getDescription(): string;

    /**
     * Determines whether the agent can process the supplied context.
     *
     * @param array<string, mixed> $context
     */
    public function supports(array $context): bool;

    /**
     * Executes the agent.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function execute(array $context): array;

    /**
     * Returns metadata describing the agent.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;
}
