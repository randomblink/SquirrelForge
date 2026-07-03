<?php

declare(strict_types=1);

// Legacy integration script — superseded by KernelBootTest.php
// Kept for direct CLI use: php tests/BootTest.php

require_once __DIR__ . '/../vendor/autoload.php';

use SquirrelForge\Core\Kernel;
use SquirrelForge\Contracts\ConfigurationInterface;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Contracts\MemoryStoreInterface;
use SquirrelForge\Workflow\WorkflowEngine;
use SquirrelForge\Agent\AgentRegistry;
use SquirrelForge\Tools\ToolRegistry;
use SquirrelForge\Modules\ModuleRegistry;

$kernel = new Kernel();
$app = $kernel->boot();
$container = $app->container();

$checks = [
    ConfigurationInterface::class,
    EventBusInterface::class,
    MemoryStoreInterface::class,
    WorkflowEngine::class,
    AgentRegistry::class,
    ToolRegistry::class,
    ModuleRegistry::class,
];

foreach ($checks as $service) {
    if (!$container->has($service)) {
        throw new RuntimeException("Missing service: {$service}");
    }
    $container->make($service);
}

echo "SquirrelForge boot test passed.\n";
