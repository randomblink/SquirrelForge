<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Modules;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Modules\ModuleDiscovery;

/**
 * Exercises ModuleDiscovery against a controlled fixture directory
 * (tests/Fixtures/DiscoveryModules/) rather than the real src/ tree, so
 * this suite doesn't need to change every time a module is added to the
 * application.
 */
final class ModuleDiscoveryTest extends TestCase
{
    private const FIXTURE_NAMESPACE = 'SquirrelForge\\Tests\\Fixtures\\DiscoveryModules';

    public function testDiscoversOnlyConcreteModulesWithNoRequiredConstructorArguments(): void
    {
        $discovery = new ModuleDiscovery();

        $modules = $discovery->discover($this->fixtureDirectory(), self::FIXTURE_NAMESPACE);

        $ids = array_map(static fn ($module): string => $module->getId(), $modules);
        sort($ids);

        // GoodModule and AnotherGoodModule only: PlainClassModule doesn't
        // implement ModuleInterface, AbstractExampleModule is abstract, and
        // RequiresArgsModule needs a constructor argument discovery can't
        // supply.
        $this->assertSame(['another-good-module', 'good-module'], $ids);
        $this->assertSame([], $discovery->errors());
    }

    public function testReturnsEmptyArrayForNonExistentDirectory(): void
    {
        $discovery = new ModuleDiscovery();

        $modules = $discovery->discover(
            $this->fixtureDirectory() . '/does-not-exist',
            self::FIXTURE_NAMESPACE
        );

        $this->assertSame([], $modules);
    }

    public function testExcludeListPreventsInstantiationEvenIfDiscoverable(): void
    {
        $discovery = new ModuleDiscovery();

        $modules = $discovery->discover(
            $this->fixtureDirectory(),
            self::FIXTURE_NAMESPACE,
            [self::FIXTURE_NAMESPACE . '\\GoodModule']
        );

        $ids = array_map(static fn ($module): string => $module->getId(), $modules);

        $this->assertNotContains('good-module', $ids);
        $this->assertContains('another-good-module', $ids);
    }

    private function fixtureDirectory(): string
    {
        return __DIR__ . '/../Fixtures/DiscoveryModules';
    }
}
