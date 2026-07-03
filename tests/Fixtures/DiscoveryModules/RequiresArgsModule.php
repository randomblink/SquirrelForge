<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Fixtures\DiscoveryModules;

use SquirrelForge\Modules\AbstractModule;

/**
 * A concrete ModuleInterface implementation whose constructor requires an
 * argument discovery has no way to supply. Discovery must skip it.
 */
final class RequiresArgsModule extends AbstractModule
{
    public function __construct(
        private readonly string $requiredArgument
    ) {
    }

    public function getId(): string
    {
        return 'requires-args-module';
    }

    public function getName(): string
    {
        return 'Requires Args Module';
    }
}
