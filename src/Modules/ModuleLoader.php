<?php

declare(strict_types=1);

namespace SquirrelForge\Modules;

use SquirrelForge\Contracts\ContainerInterface;

final class ModuleLoader
{
    public function __construct(
        private readonly ModuleRegistry $registry
    ) {
    }

    /**
     * Load and register module instances.
     *
     * @param array<int, ModuleInterface> $modules
     */
    public function load(array $modules, ContainerInterface $container): void
    {
        foreach ($modules as $module) {
            $this->registry->register($module);
            $module->register($container);
        }

        foreach ($modules as $module) {
            $module->boot($container);
        }
    }
}