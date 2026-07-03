<?php

declare(strict_types=1);

namespace SquirrelForge\Container;

use Closure;
use RuntimeException;
use SquirrelForge\Contracts\ContainerInterface;

final class Container implements ContainerInterface
{
    /**
     * @var array<string, Closure|object|string>
     */
    private array $bindings = [];

    /**
     * @var array<string, Closure|object|string>
     */
    private array $singletons = [];

    /**
     * @var array<string, object>
     */
    private array $instances = [];

    public function singleton(string $abstract, object|string $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
        unset($this->instances[$abstract]);
    }

    public function bind(string $abstract, object|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
        unset($this->instances[$abstract]);
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function make(string $abstract): object
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->singletons[$abstract])) {
            $object = $this->resolve($this->singletons[$abstract]);
            $this->instances[$abstract] = $object;

            return $object;
        }

        if (isset($this->bindings[$abstract])) {
            return $this->resolve($this->bindings[$abstract]);
        }

        if (class_exists($abstract)) {
            return new $abstract();
        }

        throw new RuntimeException("Service not found: {$abstract}");
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract])
            || isset($this->singletons[$abstract])
            || isset($this->bindings[$abstract])
            || class_exists($abstract);
    }

    public function forget(string $abstract): void
    {
        unset(
            $this->bindings[$abstract],
            $this->singletons[$abstract],
            $this->instances[$abstract]
        );
    }

    public function flush(): void
    {
        $this->bindings = [];
        $this->singletons = [];
        $this->instances = [];
    }

    private function resolve(object|string $concrete): object
    {
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        if (is_object($concrete)) {
            return $concrete;
        }

        if (class_exists($concrete)) {
            return new $concrete();
        }

        throw new RuntimeException("Unable to resolve service.");
    }
}
