<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Fixtures\DiscoveryModules;

/**
 * Matches the "*Module.php" naming convention but does NOT implement
 * ModuleInterface. Discovery must skip it.
 */
final class PlainClassModule
{
    public function getId(): string
    {
        return 'plain-class-module';
    }
}
