<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\ServiceProviderInterface;

final class AgentServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(AgentRegistry::class, AgentRegistry::class);
    }

    public function boot(ContainerInterface $container): void
    {
        // Agents are discovered and registered by modules during application bootstrap.
    }
}