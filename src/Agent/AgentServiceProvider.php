<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use SquirrelForge\Agent\Roles\ArchitectAgent;
use SquirrelForge\Agent\Roles\DeveloperAgent;
use SquirrelForge\Agent\Roles\DocumentationAgent;
use SquirrelForge\Agent\Roles\PerformanceAgent;
use SquirrelForge\Agent\Roles\PlannerAgent;
use SquirrelForge\Agent\Roles\ReleaseAgent;
use SquirrelForge\Agent\Roles\ReviewerAgent;
use SquirrelForge\Agent\Roles\SecurityAgent;
use SquirrelForge\Contracts\AgentInterface;
use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\ServiceProviderInterface;

final class AgentServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(AgentRegistry::class, AgentRegistry::class);

        $container->singleton(
            AgentOrchestrator::class,
            static fn (ContainerInterface $container): AgentOrchestrator => new AgentOrchestrator(
                $container->make(AgentRegistry::class)
            )
        );
    }

    public function boot(ContainerInterface $container): void
    {
        /** @var AgentRegistry $registry */
        $registry = $container->make(AgentRegistry::class);

        foreach ($this->pipelineAgents() as $agent) {
            $agent->boot();
            $registry->register($agent);
        }

        /** @var AgentOrchestrator $orchestrator */
        $orchestrator = $container->make(AgentOrchestrator::class);
        $orchestrator->boot();
        $registry->register($orchestrator);
    }

    /**
     * The Architect -> Planner -> Developer -> Reviewer -> Security ->
     * Performance -> Documentation -> Release handoff sequence defined in
     * `16_AGENTS/README.md`.
     *
     * @return array<int, AgentInterface>
     */
    private function pipelineAgents(): array
    {
        return [
            new ArchitectAgent(),
            new PlannerAgent(),
            new DeveloperAgent(),
            new ReviewerAgent(),
            new SecurityAgent(),
            new PerformanceAgent(),
            new DocumentationAgent(),
            new ReleaseAgent(),
        ];
    }
}