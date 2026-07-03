<?php

declare(strict_types=1);

namespace SquirrelForge\Modules;

use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\ServiceProviderInterface;

final class ModuleServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(ModuleRegistry::class, ModuleRegistry::class);

        $container->singleton(ModuleLoader::class, function (ContainerInterface $container): ModuleLoader {
            return new ModuleLoader(
                $container->make(ModuleRegistry::class)
            );
        });
    }

    public function boot(ContainerInterface $container): void
    {
        // Modules are discovered and registered during application startup.
    }
}