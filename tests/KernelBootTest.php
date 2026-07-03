<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

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
}
