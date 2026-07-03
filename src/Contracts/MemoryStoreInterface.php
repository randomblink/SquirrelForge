<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface MemoryStoreInterface extends BootableInterface, HealthCheckInterface
{
    /**
     * Store a memory record.
     *
     * @param array<string, mixed> $memory
     */
    public function store(array $memory): string;

    /**
     * Retrieve a memory by ID.
     *
     * @return array<string, mixed>|null
     */
    public function retrieve(string $id): ?array;

    /**
     * Search memory records.
     *
     * @param array<string, mixed> $criteria
     * @return array<int, array<string, mixed>>
     */
    public function search(array $criteria = []): array;

    /**
     * Update a memory record.
     *
     * @param array<string, mixed> $memory
     */
    public function update(string $id, array $memory): bool;

    /**
     * Delete a memory record.
     */
    public function delete(string $id): bool;
}
