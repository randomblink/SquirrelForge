<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

use Closure;

interface ContainerInterface
{
    /**
     * Register a shared service (singleton).
     *
     * @param Closure(self):object|object|string $concrete
     */
    public function singleton(string $abstract, object|string $concrete): void;

    /**
     * Register a transient service.
     *
     * @param Closure(self):object|object|string $concrete
     */
    public function bind(string $abstract, object|string $concrete): void;

    /**
     * Register an existing instance.
     */
    public function instance(string $abstract, object $instance): void;

    /**
     * Resolve a service from the container.
     *
     * @template T of object
     *
     * @param class-string<T>|string $abstract
     * @return T|object
     */
    public function make(string $abstract): object;

    /**
     * Determine if a service has been registered.
     */
    public function has(string $abstract): bool;

    /**
     * Remove a registered service.
     */
    public function forget(string $abstract): void;

    /**
     * Clear all registered services.
     */
    public function flush(): void;
}
