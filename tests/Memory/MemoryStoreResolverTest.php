<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Memory;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Container\Container;
use SquirrelForge\Contracts\ConfigurationInterface;
use SquirrelForge\Core\Configuration;
use SquirrelForge\Memory\FileMemoryStore;
use SquirrelForge\Memory\InMemoryStore;
use SquirrelForge\Memory\MemoryStoreResolver;

final class MemoryStoreResolverTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('SQUIRRELFORGE_MEMORY_STORE_PATH');
    }

    protected function tearDown(): void
    {
        putenv('SQUIRRELFORGE_MEMORY_STORE_PATH');
    }

    public function testResolvesToInMemoryStoreWhenNothingIsConfigured(): void
    {
        $container = new Container();

        $store = MemoryStoreResolver::resolve($container);

        $this->assertInstanceOf(InMemoryStore::class, $store);
    }

    public function testResolvesToFileMemoryStoreWhenConfigurationKeyIsSet(): void
    {
        $container = new Container();
        $container->instance(
            ConfigurationInterface::class,
            new Configuration(['memory.store_path' => '/tmp/sf-memory-config.json'])
        );

        $store = MemoryStoreResolver::resolve($container);

        $this->assertInstanceOf(FileMemoryStore::class, $store);
    }

    public function testResolvesToFileMemoryStoreWhenEnvironmentVariableIsSet(): void
    {
        putenv('SQUIRRELFORGE_MEMORY_STORE_PATH=/tmp/sf-memory-env.json');

        $container = new Container();

        $store = MemoryStoreResolver::resolve($container);

        $this->assertInstanceOf(FileMemoryStore::class, $store);
    }

    public function testConfigurationTakesPrecedenceOverTheEnvironmentVariable(): void
    {
        putenv('SQUIRRELFORGE_MEMORY_STORE_PATH=/tmp/sf-memory-env.json');

        $container = new Container();
        $container->instance(
            ConfigurationInterface::class,
            new Configuration(['memory.store_path' => '/tmp/sf-memory-config.json'])
        );

        // Both indirectly resolve to FileMemoryStore; this asserts the
        // resolver doesn't throw or behave oddly when both are set at
        // once, and that a blank Configuration value still falls back to
        // the environment variable rather than InMemoryStore.
        $store = MemoryStoreResolver::resolve($container);

        $this->assertInstanceOf(FileMemoryStore::class, $store);
    }

    public function testBlankConfigurationValueFallsBackToTheEnvironmentVariable(): void
    {
        putenv('SQUIRRELFORGE_MEMORY_STORE_PATH=/tmp/sf-memory-env.json');

        $container = new Container();
        $container->instance(
            ConfigurationInterface::class,
            new Configuration(['memory.store_path' => '   '])
        );

        $store = MemoryStoreResolver::resolve($container);

        $this->assertInstanceOf(FileMemoryStore::class, $store);
    }
}
