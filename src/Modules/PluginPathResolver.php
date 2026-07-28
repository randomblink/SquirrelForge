<?php

declare(strict_types=1);

namespace SquirrelForge\Modules;

use SquirrelForge\Contracts\ConfigurationInterface;
use SquirrelForge\Contracts\ContainerInterface;

/**
 * Resolves which external directories `Kernel::loadModules()` should scan
 * for plugin modules, in addition to `src/`.
 *
 * Returns an empty array unless a plugin path is explicitly configured --
 * this is opt-in, the same way `SquirrelForge\Tools\ReleaseActionsPolicy`
 * requires an explicit opt-in for its own consequential capability. A
 * configured directory gets exactly the same full container trust
 * `ModuleDiscovery` already grants anything under `src/`; pointing this at
 * untrusted code is a real risk the operator takes on by configuring it,
 * not something this class makes safer.
 */
final class PluginPathResolver
{
    /**
     * @return array<int, array{namespace: string, path: string}>
     */
    public static function resolve(ContainerInterface $container): array
    {
        if ($container->has(ConfigurationInterface::class)) {
            /** @var ConfigurationInterface $config */
            $config = $container->make(ConfigurationInterface::class);
            $configured = $config->get('modules.plugins');

            if (is_array($configured)) {
                return self::normalize($configured);
            }
        }

        $path = getenv('SQUIRRELFORGE_PLUGINS_PATH');
        $namespace = getenv('SQUIRRELFORGE_PLUGINS_NAMESPACE');

        if ($path !== false && trim($path) !== '' && $namespace !== false && trim($namespace) !== '') {
            return [['namespace' => trim($namespace), 'path' => trim($path)]];
        }

        return [];
    }

    /**
     * @param array<mixed> $configured
     * @return array<int, array{namespace: string, path: string}>
     */
    private static function normalize(array $configured): array
    {
        $resolved = [];

        foreach ($configured as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $namespace = $entry['namespace'] ?? null;
            $path = $entry['path'] ?? null;

            if (!is_string($namespace) || trim($namespace) === '' || !is_string($path) || trim($path) === '') {
                continue;
            }

            $resolved[] = ['namespace' => trim($namespace), 'path' => trim($path)];
        }

        return $resolved;
    }
}
