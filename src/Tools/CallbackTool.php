<?php

declare(strict_types=1);

namespace SquirrelForge\Tools;

use Closure;
use DateTimeImmutable;
use SquirrelForge\Contracts\ToolInterface;

final class CallbackTool implements ToolInterface
{
    private bool $booted = false;

    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $description,
        private readonly Closure $supports,
        private readonly Closure $execute,
        private readonly string $version = '1.0.0',
        private readonly array $metadata = [],
        private readonly array $parameters = ['type' => 'object', 'properties' => new \stdClass()],
        private readonly array $capabilities = []
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
            'details' => [
                'id' => $this->id,
                'name' => $this->name,
            ],
        ];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function supports(string $operation): bool
    {
        return ($this->supports)($operation);
    }

    public function execute(string $operation, array $parameters = []): array
    {
        return ($this->execute)($operation, $parameters);
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function capabilities(): array
    {
        return $this->capabilities;
    }
}