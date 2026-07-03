<?php

declare(strict_types=1);

namespace SquirrelForge\Llm;

use SquirrelForge\Contracts\ConfigurationInterface;
use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\LlmClientInterface;

/**
 * Builds an Anthropic-backed LLM client when an API key is configured,
 * either via `ConfigurationInterface` (key: `llm.anthropic.api_key`) or
 * the `ANTHROPIC_API_KEY` environment variable. Returns null when no key
 * is available, in which case callers should fall back to deterministic
 * behavior rather than fail.
 *
 * Extracted so any module (not just one specific service provider) can
 * resolve an LLM client the same way.
 */
final class LlmClientResolver
{
    public static function resolve(ContainerInterface $container): ?LlmClientInterface
    {
        $apiKey = null;

        if ($container->has(ConfigurationInterface::class)) {
            /** @var ConfigurationInterface $config */
            $config = $container->make(ConfigurationInterface::class);
            $apiKey = $config->get('llm.anthropic.api_key');
        }

        if ($apiKey === null || trim((string) $apiKey) === '') {
            $envKey = getenv('ANTHROPIC_API_KEY');
            $apiKey = $envKey !== false ? $envKey : null;
        }

        if ($apiKey === null || trim((string) $apiKey) === '') {
            return null;
        }

        $model = getenv('ANTHROPIC_MODEL');

        return new AnthropicClient(
            (string) $apiKey,
            $model !== false && $model !== '' ? $model : 'claude-sonnet-5'
        );
    }
}
