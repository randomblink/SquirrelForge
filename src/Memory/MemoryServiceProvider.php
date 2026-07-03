<?php

declare(strict_types=1);

namespace SquirrelForge\Memory;

use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\MemoryStoreInterface;
use SquirrelForge\Contracts\ServiceProviderInterface;

final class MemoryServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(MemoryStoreInterface::class, InMemoryStore::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $memory = $container->make(MemoryStoreInterface::class);
        $memory->boot();
    }
}