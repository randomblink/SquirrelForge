<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\ServiceProviderInterface;

/**
 * Registers the core Agent infrastructure -- the registry every agent gets
 * added to, and the orchestrator that runs the pipeline through it.
 *
 * This provider does not register any agents itself. The Architect..Release
 * role pipeline is registered by `AgentPipelineModule`, loaded through
 * `ModuleLoader` in `Kernel::boot()`, per `12_AGENT/BOOTSTRAP.md`:
 * capability registration is project initialization, not core service
 * wiring.
 */
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
        // Agents are discovered and registered by modules during application bootstrap.
    }
}
