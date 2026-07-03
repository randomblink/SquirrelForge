<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface ServiceProviderInterface
{
    /**
     * Register services with the container.
     */
    public function register(ContainerInterface $container): void;

    /**
     * Boot services after all providers have been registered.
     */
    public function boot(ContainerInterface $container): void;
}
