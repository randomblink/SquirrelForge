<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface KnowledgeProviderInterface extends BootableInterface, HealthCheckInterface
{
    /**
     * Returns the unique provider identifier.
     */
    public function getId(): string;

    /**
     * Returns the provider name.
     */
    public function getName(): string;

    /**
     * Returns the provider version.
     */
    public function getVersion(): string;

    /**
     * Returns a short description of the provider.
     */
    public function getDescription(): string;

    /**
     * Determine whether this provider can satisfy the supplied query.
     *
     * @param array<string, mixed> $context
     */
    public function supports(string $query, array $context = []): bool;

    /**
     * Search the knowledge source.
     *
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, array $context = []): array;

    /**
     * Retrieve a single knowledge item by its identifier.
     *
     * @return array<string, mixed>|null
     */
    public function retrieve(string $id): ?array;

    /**
     * Returns provider metadata.
     *
     * @return array<string, mixed>
     */
    public function metadata(): array;
}
