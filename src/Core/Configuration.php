<?php

declare(strict_types=1);

namespace SquirrelForge\Core;

use SquirrelForge\Contracts\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        private array $values = []
    ) {
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->values)
            ? $this->values[$key]
            : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function all(): array
    {
        return $this->values;
    }

    public function merge(array $values): void
    {
        $this->values = array_replace_recursive($this->values, $values);
    }

    public function validate(): array
    {
        $errors = [];
        $this->validateValues($this->values, '', $errors);

        return $errors;
    }

    /**
     * @param array<mixed> $values
     * @param array<int, string> $errors
     */
    private function validateValues(array $values, string $path, array &$errors): void
    {
        $isList = array_is_list($values);

        foreach ($values as $key => $value) {
            $currentPath = $path === '' ? (string) $key : "{$path}.{$key}";

            if (!$isList && (!is_string($key) || trim($key) === '')) {
                $errors[] = "Configuration key '{$currentPath}' must be a non-empty string.";
            }

            if (is_resource($value)) {
                $errors[] = "Configuration value '{$currentPath}' cannot be a resource.";
                continue;
            }

            if (is_array($value)) {
                $this->validateValues($value, $currentPath, $errors);
            }
        }
    }
}
