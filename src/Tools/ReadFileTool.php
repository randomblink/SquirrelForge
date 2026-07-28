<?php

declare(strict_types=1);

namespace SquirrelForge\Tools;

use DateTimeImmutable;
use SquirrelForge\Contracts\FileSystemInterface;
use SquirrelForge\Contracts\ToolInterface;
use Throwable;

final class ReadFileTool implements ToolInterface
{
    private bool $booted = false;

    public function __construct(
        private readonly FileSystemInterface $fileSystem
    ) {
    }

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
            'details' => ['id' => $this->getId()],
        ];
    }

    public function getId(): string
    {
        return 'read_file';
    }

    public function getName(): string
    {
        return 'Read File';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Reads the contents of a file relative to the project root.';
    }

    public function supports(string $operation): bool
    {
        return $operation === $this->getId();
    }

    public function execute(string $operation, array $parameters = []): array
    {
        if (!isset($parameters['path']) || !is_string($parameters['path']) || trim($parameters['path']) === '') {
            return ['error' => 'A non-empty string "path" parameter is required.'];
        }

        try {
            return ['content' => $this->fileSystem->read($parameters['path'])];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function metadata(): array
    {
        return [];
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Path relative to the project root.',
                ],
            ],
            'required' => ['path'],
        ];
    }
}
