<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface ConfigurationInterface
{
    /**
     * Determine whether a configuration key exists.
     */
    public function has(string $key): bool;

    /**
     * Retrieve a configuration value.
     *
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store or overwrite a configuration value.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Remove a configuration value.
     */
    public function remove(string $key): void;

    /**
     * Return all configuration values.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Merge configuration values into the existing configuration.
     *
     * @param array<string, mixed> $values
     */
    public function merge(array $values): void;

    /**
     * Validate the current configuration.
     *
     * Returns an array of validation errors.
     *
     * @return array<string>
     */
    public function validate(): array;
}
