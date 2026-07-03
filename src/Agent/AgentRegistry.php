<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use SquirrelForge\Contracts\AgentInterface;

final class AgentRegistry
{
    /**
     * @var array<string, AgentInterface>
     */
    private array $agents = [];

    public function register(AgentInterface $agent): void
    {
        $this->agents[$agent->getId()] = $agent;
    }

    public function get(string $id): ?AgentInterface
    {
        return $this->agents[$id] ?? null;
    }

    public function findFor(array $context): ?AgentInterface
    {
        foreach ($this->agents as $agent) {
            if ($agent->supports($context)) {
                return $agent;
            }
        }

        return null;
    }

    public function all(): array
    {
        return array_values($this->agents);
    }
}