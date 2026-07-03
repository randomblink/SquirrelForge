<?php

declare(strict_types=1);

namespace SquirrelForge\Memory;

use SquirrelForge\Contracts\ConfigurationInterface;
use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\MemoryStoreInterface;

/**
 * Decides which MemoryStoreInterface backs the pipeline: a persistent
 * FileMemoryStore when a storage path is configured, or the in-memory
 * default otherwise.
 *
 * A path is resolved from ConfigurationInterface (key
 * "memory.store_path"), falling back to the SQUIRRELFORGE_MEMORY_STORE_PATH
 * environment variable -- the same precedence LlmClientResolver uses for
 * the Anthropic API key. Backward compatible and opt-in: with neither set,
 * memory behaves exactly as it did before (InMemoryStore, lost on process
 * exit).
 */
final class MemoryStoreResolver
{
    public static function resolve(ContainerInterface $container): MemoryStoreInterface
    {
        $path = self::resolvePath($container);

        return $path !== null ? new FileMemoryStore($path) : new InMemoryStore();
    }

    private static function resolvePath(ContainerInterface $container): ?string
    {
        if ($container->has(ConfigurationInterface::class)) {
            /** @var ConfigurationInterface $config */
            $config = $container->make(ConfigurationInterface::class);
            $configured = $config->get('memory.store_path');

            if (is_string($configured) && trim($configured) !== '') {
                return $configured;
            }
        }

        $envPath = getenv('SQUIRRELFORGE_MEMORY_STORE_PATH');

        return $envPath !== false && trim($envPath) !== '' ? $envPath : null;
    }
}
