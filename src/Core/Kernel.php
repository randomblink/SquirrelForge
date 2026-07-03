<?php

declare(strict_types=1);

namespace SquirrelForge\Core;

use SquirrelForge\Agent\AgentServiceProvider;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Contracts\LoggerInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Memory\MemoryServiceProvider;
use SquirrelForge\Modules\ModuleDiscovery;
use SquirrelForge\Modules\ModuleLoader;
use SquirrelForge\Modules\ModuleServiceProvider;
use SquirrelForge\Core\CoreRuntimeServiceProvider;
use SquirrelForge\Observability\ObservabilityServiceProvider;
use SquirrelForge\Tools\ToolServiceProvider;
use SquirrelForge\Workflow\WorkflowServiceProvider;

final class Kernel
{
    public function __construct(
        private readonly Application $app = new Application(),
        private readonly Bootstrapper $bootstrapper = new Bootstrapper()
    ) {
    }

    public function app(): Application
    {
        return $this->app;
    }

    public function boot(): Application
    {
        $this->bootstrapper->addProvider(new CoreServiceProvider());
        $this->bootstrapper->addProvider(new CoreRuntimeServiceProvider());
        $this->bootstrapper->addProvider(new ObservabilityServiceProvider());
        $this->bootstrapper->addProvider(new MemoryServiceProvider());
        $this->bootstrapper->addProvider(new WorkflowServiceProvider());
        $this->bootstrapper->addProvider(new AgentServiceProvider());
        $this->bootstrapper->addProvider(new ToolServiceProvider());
        $this->bootstrapper->addProvider(new ModuleServiceProvider());

        $this->bootstrapper->register($this->app);

        $this->app->boot();

        $this->loadModules();

        $this->events()->dispatch(new Event(
            uniqid('evt_', true),
            'SystemBooted',
            new \DateTimeImmutable(),
            self::class,
            [],
            ['booted' => true]
        ));

        return $this->app;
    }

    public function events(): EventBusInterface
    {
        return $this->app->events();
    }

    /**
     * Load application modules once every core provider has registered and
     * booted.
     *
     * Modules are found by scanning `src/` for classes named `*Module.php`
     * that implement `ModuleInterface` (see `ModuleDiscovery`) -- real
     * filesystem auto-discovery, not a hardcoded list. Adding a new module
     * anywhere under `src/` is enough to have it loaded; nothing here needs
     * to change. This is how capability registration (like the Agent role
     * pipeline, via `AgentPipelineModule`) stays out of core service
     * providers' `boot()` methods.
     */
    private function loadModules(): void
    {
        $container = $this->app->container();

        /** @var ModuleLoader $moduleLoader */
        $moduleLoader = $container->make(ModuleLoader::class);

        $discovery = new ModuleDiscovery();
        $modules = $discovery->discover($this->sourceRoot(), 'SquirrelForge');

        $moduleLoader->load($modules, $container);

        if ($container->has(LoggerInterface::class)) {
            /** @var LoggerInterface $logger */
            $logger = $container->make(LoggerInterface::class);

            foreach ($discovery->errors() as $error) {
                $logger->warning('Module discovery skipped a candidate.', ['error' => $error]);
            }
        }
    }

    /**
     * Absolute path to the src/ directory (this file lives at
     * src/Core/Kernel.php), used as the root for module discovery. It maps
     * to the "SquirrelForge" PSR-4 namespace prefix in composer.json.
     */
    private function sourceRoot(): string
    {
        return dirname(__DIR__);
    }
}
