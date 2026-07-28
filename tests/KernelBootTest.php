<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\ConfigurationInterface;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Contracts\LoggerInterface;
use SquirrelForge\Contracts\MemoryStoreInterface;
use SquirrelForge\Agent\AgentRegistry;
use SquirrelForge\Tools\ToolRegistry;
use SquirrelForge\Modules\ModuleRegistry;
use SquirrelForge\Workflow\WorkflowEngine;
use SquirrelForge\Core\Kernel;

final class KernelBootTest extends TestCase
{
    private const ENV_KEYS = ['SQUIRRELFORGE_PLUGINS_PATH', 'SQUIRRELFORGE_PLUGINS_NAMESPACE'];

    protected function tearDown(): void
    {
        foreach (self::ENV_KEYS as $key) {
            putenv($key);
        }
    }

    public function testKernelBoots(): void
    {
        $kernel = new Kernel();
        $app = $kernel->boot();

        $this->assertTrue($app->isBooted());
    }

    public function testCoreServicesAreResolvable(): void
    {
        $kernel = new Kernel();
        $app = $kernel->boot();
        $container = $app->container();

        $services = [
            ConfigurationInterface::class,
            EventBusInterface::class,
            MemoryStoreInterface::class,
            WorkflowEngine::class,
            AgentRegistry::class,
            ToolRegistry::class,
            ModuleRegistry::class,
        ];

        foreach ($services as $service) {
            $this->assertTrue(
                $container->has($service),
                "Container is missing: {$service}"
            );
            $this->assertIsObject($container->make($service));
        }
    }

    public function testExternalPluginIsDiscoveredAndBootedWhenAutoloaderIsSupplied(): void
    {
        putenv('SQUIRRELFORGE_PLUGINS_PATH=' . $this->externalPluginFixtureDirectory());
        putenv('SQUIRRELFORGE_PLUGINS_NAMESPACE=Acme\\Plugin');

        // A fresh ClassLoader only actually participates in autoloading once
        // registered -- exactly what Composer's real vendor/autoload.php
        // already does for the loader instance it returns. Unregistered
        // afterward so this doesn't linger on the SPL autoload stack for the
        // rest of the test run.
        $autoloader = new ClassLoader();
        $autoloader->register();

        try {
            $kernel = new Kernel(autoloader: $autoloader);
            $app = $kernel->boot();

            /** @var ModuleRegistry $registry */
            $registry = $app->container()->make(ModuleRegistry::class);

            $this->assertTrue($registry->has('acme-plugin'));
        } finally {
            $autoloader->unregister();
        }
    }

    public function testConfiguredPluginPathIsSkippedWithoutCrashingWhenNoAutoloaderIsSupplied(): void
    {
        putenv('SQUIRRELFORGE_PLUGINS_PATH=' . $this->externalPluginFixtureDirectory());
        putenv('SQUIRRELFORGE_PLUGINS_NAMESPACE=Acme\\Plugin');

        $kernel = new Kernel();
        $app = $kernel->boot();

        /** @var ModuleRegistry $registry */
        $registry = $app->container()->make(ModuleRegistry::class);

        $this->assertTrue($app->isBooted());
        $this->assertFalse($registry->has('acme-plugin'));
    }

    private function externalPluginFixtureDirectory(): string
    {
        return __DIR__ . '/Fixtures/ExternalPlugin';
    }
}
