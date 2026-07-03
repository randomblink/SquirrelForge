<?php

declare(strict_types=1);

namespace SquirrelForge\Tools;

use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\ServiceProviderInterface;

final class ToolServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(ToolRegistry::class, ToolRegistry::class);
    }

    public function boot(ContainerInterface $container): void
    {
        // Tools are registered by modules after the registry exists.
    }
}