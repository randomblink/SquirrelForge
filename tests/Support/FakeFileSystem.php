<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Support;

use RuntimeException;
use SquirrelForge\Contracts\FileSystemInterface;

/**
 * An in-memory FileSystemInterface test double. Never touches real disk,
 * so tests exercising agent tool-use logic don't need a temp directory.
 */
final class FakeFileSystem implements FileSystemInterface
{
    /**
     * @var array<string, string>
     */
    public array $files;

    /**
     * @var array<int, array{op: string, path: string}>
     */
    public array $calls = [];

    /**
     * @var array<int, string>
     */
    private array $failOnWrite = [];

    /**
     * @param array<string, string> $initialFiles
     */
    public function __construct(array $initialFiles = [])
    {
        $this->files = $initialFiles;
    }

    public function failOnWrite(string $relativePath): void
    {
        $this->failOnWrite[] = $relativePath;
    }

    public function read(string $relativePath): string
    {
        $this->calls[] = ['op' => 'read', 'path' => $relativePath];

        if (!array_key_exists($relativePath, $this->files)) {
            throw new RuntimeException(sprintf('No such fake file: "%s".', $relativePath));
        }

        return $this->files[$relativePath];
    }

    public function write(string $relativePath, string $contents): void
    {
        $this->calls[] = ['op' => 'write', 'path' => $relativePath];

        if (in_array($relativePath, $this->failOnWrite, true)) {
            throw new RuntimeException(sprintf('Simulated write failure: "%s".', $relativePath));
        }

        $this->files[$relativePath] = $contents;
    }

    public function delete(string $relativePath): void
    {
        $this->calls[] = ['op' => 'delete', 'path' => $relativePath];

        unset($this->files[$relativePath]);
    }

    public function exists(string $relativePath): bool
    {
        return array_key_exists($relativePath, $this->files);
    }
}
