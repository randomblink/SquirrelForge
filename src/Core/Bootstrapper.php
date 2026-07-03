<?php

declare(strict_types=1);

namespace SquirrelForge\Core;

use SquirrelForge\Contracts\ServiceProviderInterface;

final class Bootstrapper
{
    /**
     * @var array<int, ServiceProviderInterface>
     */
    private array $providers = [];

    public function addProvider(ServiceProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return array<int, ServiceProviderInterface>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    public function register(Application $app): void
    {
        foreach ($this->providers as $provider) {
            $app->register($provider);
        }
    }
}