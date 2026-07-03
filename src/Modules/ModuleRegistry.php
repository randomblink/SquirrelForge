<?php

declare(strict_types=1);

namespace SquirrelForge\Modules;

final class ModuleRegistry
{
    /**
     * @var array<string, ModuleInterface>
     */
    private array $modules = [];

    public function register(ModuleInterface $module): void
    {
        $this->modules[$module->getId()] = $module;
    }

    public function get(string $id): ?ModuleInterface
    {
        return $this->modules[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->modules[$id]);
    }

    /**
     * @return array<int, ModuleInterface>
     */
    public function all(): array
    {
        return array_values($this->modules);
    }

    public function count(): int
    {
        return count($this->modules);
    }

    public function unregister(string $id): bool
    {
        if (!isset($this->modules[$id])) {
            return false;
        }

        unset($this->modules[$id]);

        return true;
    }
}