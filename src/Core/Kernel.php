<?php

declare(strict_types=1);

namespace SquirrelForge\Core;

use SquirrelForge\Agent\AgentPipelineModule;
use SquirrelForge\Agent\AgentServiceProvider;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Memory\MemoryServiceProvider;
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
     * booted. This is the single place modules get plugged in -- explicit,
     * not filesystem auto-discovery -- so capability registration (like the
     * Agent role pipeline) doesn't have to be hardcoded inside a core
     * service provider's boot().
     */
    private function loadModules(): void
    {
        $container = $this->app->container();

        /** @var ModuleLoader $moduleLoader */
        $moduleLoader = $container->make(ModuleLoader::class);

        $moduleLoader->load([
            new AgentPipelineModule(),
        ], $container);
    }
}
