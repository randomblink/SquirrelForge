<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

/**
 * A minimal, provider-agnostic chat-completion client.
 *
 * Role agents use this to ask an LLM to fill in the judgment calls their
 * spec describes (e.g. "identify technical risks") when the caller hasn't
 * already supplied that information explicitly. Implementations must
 * return the raw text of the model's reply; callers are responsible for
 * parsing it (typically as JSON, per the instructions they gave the
 * model).
 */
interface LlmClientInterface
{
    /**
     * Send a single-turn completion request and return the model's raw
     * text reply.
     *
     * @throws \RuntimeException if the request fails or the provider
     *                            returns an error.
     */
    public function complete(string $systemPrompt, string $userPrompt): string;
}
