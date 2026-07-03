<?php

declare(strict_types=1);

namespace SquirrelForge\Memory;

use DateTimeImmutable;
use SquirrelForge\Contracts\MemoryStoreInterface;

final class InMemoryStore implements MemoryStoreInterface
{
    private bool $booted = false;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $memories = [];

    public function boot(): void
    {
        $this->booted = true;
    }

    public function isHealthy(): bool
    {
        return $this->booted;
    }

    public function health(): array
    {
        return [
            'status' => $this->booted ? 'healthy' : 'unhealthy',
            'component' => self::class,
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            'details' => [
                'count' => count($this->memories),
            ],
        ];
    }

    public function store(array $memory): string
    {
        $id = $memory['id'] ?? uniqid('mem_', true);

        $this->memories[$id] = array_merge($memory, [
            'id' => $id,
            'created_at' => $memory['created_at'] ?? (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $id;
    }

    public function retrieve(string $id): ?array
    {
        return $this->memories[$id] ?? null;
    }

    public function search(array $criteria = []): array
    {
        if ($criteria === []) {
            return array_values($this->memories);
        }

        return array_values(array_filter(
            $this->memories,
            fn (array $memory): bool => $this->matches($memory, $criteria)
        ));
    }

    public function update(string $id, array $memory): bool
    {
        if (!isset($this->memories[$id])) {
            return false;
        }

        $this->memories[$id] = array_merge($this->memories[$id], $memory, [
            'id' => $id,
            'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return true;
    }

    public function delete(string $id): bool
    {
        if (!isset($this->memories[$id])) {
            return false;
        }

        unset($this->memories[$id]);

        return true;
    }

    private function matches(array $memory, array $criteria): bool
    {
        foreach ($criteria as $key => $value) {
            if (!array_key_exists($key, $memory)) {
                return false;
            }

            if ($memory[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}