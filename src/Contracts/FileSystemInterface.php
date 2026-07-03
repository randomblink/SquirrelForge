<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

/**
 * A minimal, path-contained file system contract. Implementations must
 * refuse any path that resolves outside their configured root -- this is
 * the containment boundary that makes it safe to hand write access to an
 * agent.
 */
interface FileSystemInterface
{
    /**
     * @throws \RuntimeException if the path is unsafe or cannot be read.
     */
    public function read(string $relativePath): string;

    /**
     * Writes a file, creating parent directories as needed.
     *
     * @throws \RuntimeException if the path is unsafe or cannot be written.
     */
    public function write(string $relativePath, string $contents): void;

    /**
     * Deletes a file if it exists. Deleting a path that doesn't exist is a
     * no-op, not an error.
     *
     * @throws \RuntimeException if the path is unsafe or cannot be deleted.
     */
    public function delete(string $relativePath): void;

    public function exists(string $relativePath): bool;
}
