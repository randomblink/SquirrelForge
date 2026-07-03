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
 *
 * write() and delete() additionally realpath()-verify containment (of the
 * nearest existing ancestor directory, since the target itself may not
 * exist yet), the same way read() already did -- this closes a narrow gap
 * where a pre-existing symlink inside the root pointing outside it could
 * otherwise redirect a write or delete past the textual ".." check above.
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
        $this->assertAncestryIsContained($relativePath, $path);

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

        $realRoot = realpath($this->root);
        $real = realpath($path);

        if ($real === false || $realRoot === false || !$this->isWithin($real, $realRoot)) {
            throw new RuntimeException(sprintf('Refusing to delete path outside project root: "%s".', $relativePath));
        }

        if (!is_file($real)) {
            throw new RuntimeException(sprintf('Refusing to delete non-file path "%s".', $relativePath));
        }

        if (!unlink($real)) {
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

    /**
     * Verifies that the nearest *existing* ancestor directory of $path --
     * the deepest directory component that's actually on disk today -- is
     * really inside the root once symlinks are resolved. The target itself
     * (and any not-yet-created intermediate directories) may not exist, so
     * realpath() can't be used on $path directly; but any symlink that
     * could redirect the eventual write must already exist as a directory
     * somewhere along the path, and this walk is guaranteed to find it
     * (bounded by $this->root, which the constructor already confirmed
     * exists).
     */
    private function assertAncestryIsContained(string $relativePath, string $path): void
    {
        $realRoot = realpath($this->root);

        if ($realRoot === false) {
            throw new RuntimeException('LocalFileSystem root could not be resolved.');
        }

        $ancestor = dirname($path);

        while (!is_dir($ancestor)) {
            $parent = dirname($ancestor);

            if ($parent === $ancestor) {
                throw new RuntimeException(
                    sprintf('Unable to resolve an existing ancestor directory for "%s".', $relativePath)
                );
            }

            $ancestor = $parent;
        }

        $realAncestor = realpath($ancestor);

        if ($realAncestor === false || !$this->isWithin($realAncestor, $realRoot)) {
            throw new RuntimeException(
                sprintf('Refusing path whose ancestor escapes the project root via a symlink: "%s".', $relativePath)
            );
        }
    }
}
