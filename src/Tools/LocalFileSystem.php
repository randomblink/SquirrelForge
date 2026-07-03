<?php

declare(strict_types=1);

namespace SquirrelForge\Tools;

use RuntimeException;
use SquirrelForge\Contracts\FileSystemInterface;

/**
 * A FileSystemInterface implementation rooted at a fixed directory.
 *
 * Every path is validated before touching disk: absolute paths, empty
 * paths, and any path containing a ".." segment are rejected outright, so
 * a caller can never escape the configured root, regardless of what an
 * LLM-generated path string contains. This is what makes it safe to give a
 * role agent real write access -- the containment is enforced here, not by
 * trusting the caller.
 */
final class LocalFileSystem implements FileSystemInterface
{
    public function __construct(
        private readonly string $root
    ) {
        if (!is_dir($this->root)) {
            throw new RuntimeException(sprintf('LocalFileSystem root "%s" does not exist.', $this->root));
        }
    }

    public function read(string $relativePath): string
    {
        $path = $this->resolveExisting($relativePath);

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read "%s".', $relativePath));
        }

        return $contents;
    }

    public function write(string $relativePath, string $contents): void
    {
        $path = $this->resolveForWrite($relativePath);

        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create directory for "%s".', $relativePath));
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Unable to write "%s".', $relativePath));
        }
    }

    public function delete(string $relativePath): void
    {
        $path = $this->resolveForWrite($relativePath);

        if (!file_exists($path)) {
            return;
        }

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Refusing to delete non-file path "%s".', $relativePath));
        }

        if (!unlink($path)) {
            throw new RuntimeException(sprintf('Unable to delete "%s".', $relativePath));
        }
    }

    public function exists(string $relativePath): bool
    {
        try {
            return file_exists($this->resolveForWrite($relativePath));
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Validates and resolves a path that must already exist.
     */
    private function resolveExisting(string $relativePath): string
    {
        $candidate = $this->resolveForWrite($relativePath);
        $real = realpath($candidate);
        $realRoot = realpath($this->root);

        if ($real === false || $realRoot === false || !$this->isWithin($real, $realRoot)) {
            throw new RuntimeException(sprintf('Refusing path outside project root: "%s".', $relativePath));
        }

        return $real;
    }

    /**
     * Validates and resolves a path that may not exist yet (so realpath()
     * can't be used to confirm containment -- the traversal checks below
     * are what enforce it instead).
     */
    private function resolveForWrite(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', $relativePath);

        if (
            $normalized === ''
            || str_starts_with($normalized, '/')
            || preg_match('#(^|/)\.\.($|/)#', $normalized) === 1
        ) {
            throw new RuntimeException(sprintf('Refusing unsafe path "%s".', $relativePath));
        }

        return rtrim($this->root, '/') . '/' . $normalized;
    }

    private function isWithin(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, rtrim($root, '/') . '/');
    }
}
