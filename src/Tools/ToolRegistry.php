<?php

declare(strict_types=1);

namespace SquirrelForge\Tools;

use SquirrelForge\Contracts\ToolInterface;

final class ToolRegistry
{
    /**
     * @var array<string, ToolInterface>
     */
    private array $tools = [];

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->getId()] = $tool;
    }

    public function get(string $id): ?ToolInterface
    {
        return $this->tools[$id] ?? null;
    }

    public function findFor(string $operation): ?ToolInterface
    {
        foreach ($this->tools as $tool) {
            if ($tool->supports($operation)) {
                return $tool;
            }
        }

        return null;
    }

    public function all(): array
    {
        return array_values($this->tools);
    }
}
