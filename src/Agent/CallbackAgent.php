<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use Closure;
use SquirrelForge\Agent\Support\BootableHealthCheck;
use SquirrelForge\Contracts\AgentInterface;

final class CallbackAgent implements AgentInterface
{
    use BootableHealthCheck;

    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $description,
        private readonly Closure $supports,
        private readonly Closure $execute,
        private readonly string $version = '1.0.0',
        private readonly array $metadata = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    protected function healthDetails(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
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

    public function supports(array $context): bool
    {
        return ($this->supports)($context);
    }

    public function execute(array $context): array
    {
        return ($this->execute)($context);
    }

    public function metadata(): array
    {
        return $this->metadata;
    }
}
