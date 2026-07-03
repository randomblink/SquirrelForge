<?php

declare(strict_types=1);

namespace SquirrelForge\Core;

use RuntimeException;
use SquirrelForge\Container\Container;
use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Contracts\ServiceProviderInterface;
use SquirrelForge\Events\EventBus;

final class Application
{
    /**
     * @var array<int, ServiceProviderInterface>
     */
    private array $providers = [];

    private bool $booted = false;

    public function __construct(
        private readonly ContainerInterface $container = new Container()
    ) {
        $this->container->instance(ContainerInterface::class, $this->container);
        $this->container->singleton(EventBusInterface::class, EventBus::class);
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

    public function register(ServiceProviderInterface $provider): void
    {
        if ($this->booted) {
            throw new RuntimeException('Cannot register provider after application has booted.');
        }

        $provider->register($this->container);
        $this->providers[] = $provider;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->boot($this->container);
        }

        $this->booted = true;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function events(): EventBusInterface
    {
        return $this->container->make(EventBusInterface::class);
    }
}