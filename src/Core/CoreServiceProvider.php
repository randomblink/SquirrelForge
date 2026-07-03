<?php

declare(strict_types=1);

namespace SquirrelForge\Core;

use SquirrelForge\Contracts\ConfigurationInterface;
use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\ServiceProviderInterface;

final class CoreServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(ConfigurationInterface::class, Configuration::class);
    }

    public function boot(ContainerInterface $container): void
    {
        // Core services are ready after registration.
    }
}