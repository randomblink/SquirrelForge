<?php

declare(strict_types=1);

namespace SquirrelForge\Modules;

use SquirrelForge\Contracts\ContainerInterface;

abstract class AbstractModule implements ModuleInterface
{
    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return '';
    }

    public function register(ContainerInterface $container): void
    {
        // Optional override.
    }

    public function boot(ContainerInterface $container): void
    {
        // Optional override.
    }
}