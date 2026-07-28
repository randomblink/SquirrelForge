<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Modules;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Container\Container;
use SquirrelForge\Contracts\ConfigurationInterface;
use SquirrelForge\Core\Configuration;
use SquirrelForge\Modules\PluginPathResolver;

final class PluginPathResolverTest extends TestCase
{
    private const ENV_KEYS = ['SQUIRRELFORGE_PLUGINS_PATH', 'SQUIRRELFORGE_PLUGINS_NAMESPACE'];

    protected function tearDown(): void
    {
        foreach (self::ENV_KEYS as $key) {
            putenv($key);
        }
    }

    public function testConfiguredEntriesAreUsedWhenPresent(): void
    {
        $container = new Container();
        $container->singleton(ConfigurationInterface::class, new Configuration([
            'modules.plugins' => [
                ['namespace' => 'Acme\\Plugin', 'path' => '/plugins/acme'],
            ],
        ]));

        $this->assertSame(
            [['namespace' => 'Acme\\Plugin', 'path' => '/plugins/acme']],
            PluginPathResolver::resolve($container)
        );
    }

    public function testMalformedConfiguredEntriesAreSkippedNotThrown(): void
    {
        $container = new Container();
        $container->singleton(ConfigurationInterface::class, new Configuration([
            'modules.plugins' => [
                ['namespace' => 'Acme\\Plugin'],
                'not-an-array',
                ['namespace' => '', 'path' => '/plugins/empty-namespace'],
                ['namespace' => 'Acme\\Good', 'path' => '/plugins/good'],
            ],
        ]));

        $this->assertSame(
            [['namespace' => 'Acme\\Good', 'path' => '/plugins/good']],
            PluginPathResolver::resolve($container)
        );
    }

    public function testEnvironmentFallbackIsUsedWhenNoConfigEntryExists(): void
    {
        putenv('SQUIRRELFORGE_PLUGINS_PATH=/plugins/acme');
        putenv('SQUIRRELFORGE_PLUGINS_NAMESPACE=Acme\\Plugin');

        $container = new Container();
        $container->singleton(ConfigurationInterface::class, new Configuration());

        $this->assertSame(
            [['namespace' => 'Acme\\Plugin', 'path' => '/plugins/acme']],
            PluginPathResolver::resolve($container)
        );
    }

    public function testReturnsEmptyArrayWhenNothingIsConfigured(): void
    {
        $container = new Container();
        $container->singleton(ConfigurationInterface::class, new Configuration());

        $this->assertSame([], PluginPathResolver::resolve($container));
    }

    public function testReturnsEmptyArrayWhenNoConfigurationServiceIsRegistered(): void
    {
        $container = new Container();

        $this->assertSame([], PluginPathResolver::resolve($container));
    }
}
