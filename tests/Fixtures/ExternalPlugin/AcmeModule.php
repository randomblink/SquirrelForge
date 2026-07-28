<?php

declare(strict_types=1);

// Deliberately NOT under the SquirrelForge\ or SquirrelForge\Tests\ prefixes
// composer.json already maps -- this namespace has no static PSR-4 mapping
// at all, so this fixture is only resolvable once something dynamically
// registers it (Kernel/PluginPathResolver's addPsr4() call), proving real
// external-plugin loading rather than an accidentally-already-mapped path.
namespace Acme\Plugin;

use SquirrelForge\Modules\AbstractModule;

final class AcmeModule extends AbstractModule
{
    public function getId(): string
    {
        return 'acme-plugin';
    }

    public function getName(): string
    {
        return 'Acme Plugin';
    }
}
