<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Fixtures\DiscoveryModules;

use SquirrelForge\Modules\AbstractModule;

/**
 * Implements ModuleInterface (via AbstractModule) but is abstract.
 * Discovery must skip it -- it can't be instantiated.
 */
abstract class AbstractExampleModule extends AbstractModule
{
    public function getId(): string
    {
        return 'abstract-example-module';
    }

    public function getName(): string
    {
        return 'Abstract Example Module';
    }
}
