<?php

declare(strict_types=1);

namespace SquirrelForge\Core;

use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\ServiceProviderInterface;

final class CoreRuntimeServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(HealthManager::class, HealthManager::class);
        $container->singleton(LifecycleManager::class, LifecycleManager::class);
    }

    public function boot(ContainerInterface $container): void
    {
        /** @var LifecycleManager $lifecycle */
        $lifecycle = $container->make(LifecycleManager::class);

        $lifecycle->start();
    }
}