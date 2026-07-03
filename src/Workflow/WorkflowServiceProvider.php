<?php

declare(strict_types=1);

namespace SquirrelForge\Workflow;

use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\ServiceProviderInterface;

final class WorkflowServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(WorkflowEngine::class, WorkflowEngine::class);
    }

    public function boot(ContainerInterface $container): void
    {
        // Workflows are registered by modules after the engine exists.
    }
}