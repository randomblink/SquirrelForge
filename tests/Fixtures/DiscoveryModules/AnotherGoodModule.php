<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Fixtures\DiscoveryModules;

use SquirrelForge\Modules\AbstractModule;

/**
 * A second well-formed, discoverable module fixture, so discovery tests
 * can confirm more than one candidate is found.
 */
final class AnotherGoodModule extends AbstractModule
{
    public function getId(): string
    {
        return 'another-good-module';
    }

    public function getName(): string
    {
        return 'Another Good Module';
    }
}
