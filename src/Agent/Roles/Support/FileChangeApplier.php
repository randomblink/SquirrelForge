<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles\Support;

use InvalidArgumentException;
use SquirrelForge\Contracts\FileSystemInterface;
use Throwable;

/**
 * Applies a batch of LLM-proposed file changes through a `FileSystemInterface`,
 * extracted out of `DeveloperAgent`. Each change is applied independently --
 * one failure doesn't stop the rest from being attempted -- and every
 * outcome (success or failure, with the error message) is reported back so
 * the caller can decide how to reflect it in task status.
 */
final class FileChangeApplier
{
    public function __construct(
        private readonly FileSystemInterface $fileSystem
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $changes
     * @return array<int, array<string, mixed>>
     */
    public function applyAll(array $changes): array
    {
        $applied = [];

        foreach ($changes as $change) {
            $applied[] = $this->apply($change);
        }

        return $applied;
    }

    /**
     * @param array<string, mixed> $change
     * @return array<string, mixed>
     */
    private function apply(array $change): array
    {
        $path = $change['path'] ?? null;
        $action = $change['action'] ?? null;

        if (!is_string($path) || $path === '') {
            return ['path' => $path, 'action' => $action, 'applied' => false, 'error' => 'Missing or invalid path.'];
        }

        try {
            match ($action) {
                'create', 'update' => $this->fileSystem->write($path, (string) ($change['content'] ?? '')),
                'delete' => $this->fileSystem->delete($path),
                default => throw new InvalidArgumentException(
                    sprintf('Unknown file change action "%s".', is_string($action) ? $action : 'null')
                ),
            };

            return ['path' => $path, 'action' => $action, 'applied' => true];
        } catch (Throwable $e) {
            return ['path' => $path, 'action' => $action, 'applied' => false, 'error' => $e->getMessage()];
        }
    }
}
