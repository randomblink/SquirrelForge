<?php

declare(strict_types=1);

namespace SquirrelForge\Observability;

use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\LoggerInterface;
use SquirrelForge\Contracts\ServiceProviderInterface;

final class ObservabilityServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(LoggerInterface::class, ArrayLogger::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $logger = $container->make(LoggerInterface::class);

        $logger->info('Observability services booted.', [
            'provider' => self::class,
        ]);
    }
}