<?php

declare(strict_types=1);

namespace SquirrelForge\Tools;

use SquirrelForge\Contracts\ConfigurationInterface;
use SquirrelForge\Contracts\ContainerInterface;

/**
 * Whether ReleaseAgent is allowed to perform real, externally consequential
 * actions (finalizing CHANGELOG.md, committing, tagging, and pushing) as
 * opposed to only reporting a Ready/Hold gate status.
 *
 * This is deliberately a separate opt-in from having an LLM configured.
 * Having ANTHROPIC_API_KEY set enables reasoning; it must not also silently
 * enable git pushes. Enable via SQUIRRELFORGE_ENABLE_RELEASE_ACTIONS=1 (env)
 * or release.actions_enabled (via ConfigurationInterface).
 */
final class ReleaseActionsPolicy
{
    public static function isEnabled(ContainerInterface $container): bool
    {
        if ($container->has(ConfigurationInterface::class)) {
            /** @var ConfigurationInterface $config */
            $config = $container->make(ConfigurationInterface::class);

            if (self::isTruthy($config->get('release.actions_enabled'))) {
                return true;
            }
        }

        $envValue = getenv('SQUIRRELFORGE_ENABLE_RELEASE_ACTIONS');

        return $envValue !== false && self::isTruthy($envValue);
    }

    private static function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
