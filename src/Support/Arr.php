<?php

declare(strict_types=1);

namespace SquirrelForge\Support;

final class Arr
{
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $array;
        }

        $segments = explode('.', $key);

        foreach ($segments as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }

    public static function set(array &$array, string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target = &$array;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $value;
    }

    public static function has(array $array, string $key): bool
    {
        return self::get($array, $key, null) !== null;
    }

    public static function remove(array &$array, string $key): void
    {
        $segments = explode('.', $key);
        $target = &$array;

        while (count($segments) > 1) {
            $segment = array_shift($segments);

            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                return;
            }

            $target = &$target[$segment];
        }

        unset($target[array_shift($segments)]);
    }
}