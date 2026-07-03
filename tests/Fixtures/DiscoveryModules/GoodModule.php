<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Fixtures\DiscoveryModules;

use SquirrelForge\Modules\AbstractModule;

/**
 * A well-formed, discoverable module fixture: concrete, implements
 * ModuleInterface (via AbstractModule), and has no constructor.
 */
final class GoodModule extends AbstractModule
{
    public function getId(): string
    {
        return 'good-module';
    }

    public function getName(): string
    {
        return 'Good Module';
    }
}
