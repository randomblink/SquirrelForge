<?php

declare(strict_types=1);

namespace SquirrelForge\Memory;

use DateTimeImmutable;
use RuntimeException;
use SquirrelForge\Contracts\MemoryStoreInterface;

/**
 * A MemoryStoreInterface implementation backed by a single JSON file, so
 * memory records survive across separate process invocations -- unlike
 * InMemoryStore, which is lost the moment the process exits.
 *
 * Every mutating call re-reads the file, applies the change, and writes
 * the whole file back (with LOCK_EX). This is deliberately simple rather
 * than fast: it keeps every instance pointed at the same path in sync
 * without an in-process cache that could go stale, which matters more
 * here than raw throughput for a role-agent pipeline's memory of past
 * runs.
 */
final class FileMemoryStore implements MemoryStoreInterface
{
    private bool $booted = false;

    public function __construct(
        private readonly string $path
    ) {
    }

    public function boot(): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create memory store directory "%s".', $directory));
        }

        if (!file_exists($this->path)) {
            $this->writeAll([]);
        }

        $this->booted = true;
    }

    public function isHealthy(): bool
    {
        return $this->booted && is_readable($this->path) && is_writable($this->path);
    }

    public function health(): array
    {
        return [
            'status' => $this->isHealthy() ? 'healthy' : 'unhealthy',
            'component' => self::class,
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            'details' => [
                'path' => $this->path,
                'count' => count($this->readAll()),
            ],
        ];
    }

    public function store(array $memory): string
    {
        $memories = $this->readAll();
        $id = $memory['id'] ?? uniqid('mem_', true);

        $memories[$id] = array_merge($memory, [
            'id' => $id,
            'created_at' => $memory['created_at'] ?? (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        $this->writeAll($memories);

        return $id;
    }

    public function retrieve(string $id): ?array
    {
        return $this->readAll()[$id] ?? null;
    }

    public function search(array $criteria = []): array
    {
        $memories = $this->readAll();

        if ($criteria === []) {
            return array_values($memories);
        }

        return array_values(array_filter(
            $memories,
            fn (array $memory): bool => $this->matches($memory, $criteria)
        ));
    }

    public function update(string $id, array $memory): bool
    {
        $memories = $this->readAll();

        if (!isset($memories[$id])) {
            return false;
        }

        $memories[$id] = array_merge($memories[$id], $memory, [
            'id' => $id,
            'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        $this->writeAll($memories);

        return true;
    }

    public function delete(string $id): bool
    {
        $memories = $this->readAll();

        if (!isset($memories[$id])) {
            return false;
        }

        unset($memories[$id]);
        $this->writeAll($memories);

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

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readAll(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array<string, mixed>> $memories
     */
    private function writeAll(array $memories): void
    {
        $encoded = json_encode($memories, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new RuntimeException('Unable to encode memory store contents.');
        }

        if (file_put_contents($this->path, $encoded, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Unable to write memory store "%s".', $this->path));
        }
    }
}
