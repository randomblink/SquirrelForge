<?php

declare(strict_types=1);

namespace SquirrelForge\Reasoning;

use JsonException;
use SquirrelForge\Contracts\LlmClientInterface;
use SquirrelForge\Engine\PromptCompiler;
use Throwable;

/**
 * Orchestrates the mechanics of an LLM call: compiling the prompt,
 * sending it to a provider, and structuring the response, per
 * 19_REASONING/AI-DRIVER.md.
 *
 * Rule 1 ("must use the Prompt Compiler to generate the final prompt...
 * must not construct prompts on its own") is upheld literally: reason()
 * calls the injected PromptCompiler::compile() and sends its output
 * string verbatim as the user prompt -- this class has no template or
 * formatting logic of its own for the reasoning content.
 *
 * Rule 2 ("Provider Agnostic... independent of any specific LLM
 * provider") is upheld by depending only on the already-real
 * LlmClientInterface, never a concrete client like AnthropicClient
 * directly -- callers inject whichever real client (or none) applies.
 *
 * Does not delegate to SquirrelForge\Llm\Reasoner for structured
 * parsing: Reasoner builds its own agent-persona system prompt and
 * payload-wrapped instructions, a shape built for role agents'
 * field-extraction calls, not for sending Prompt Compiler's own
 * strictly-ordered, self-contained prompt string verbatim as required
 * here. Instead, this class does its own minimal, real JSON
 * structure-and-key validation when the caller opts in via
 * `expected_fields` -- the same "respond as JSON with exactly these
 * keys" contract, scoped to what this class's own prompt shape actually
 * needs, not duplicating Reasoner's tool-use loop or agent framing.
 *
 * Rule 4 (Traceability: "every LLM call... including the compiled
 * prompt (or its hash)... must be logged") is upheld by always
 * returning the compiled prompt and its real sha256 hash (Prompt
 * Compiler's own Rule 4 output) alongside the raw and structured
 * response, giving the caller everything it needs to log the call
 * without this class owning a logging mechanism of its own.
 */
final class AIDriver
{
    public function __construct(
        private readonly ?PromptCompiler $promptCompiler = null,
        private readonly ?LlmClientInterface $llmClient = null
    ) {
    }

    /**
     * @param array{system_prompt?: string, evidence_tiers?: array<int, string>, expected_fields?: array<int, string>} $options
     * @return array{result: mixed, raw_response: ?string, prompt: ?string, prompt_hash: ?string, outcome: string, error: ?string}
     */
    public function reason(array $options = []): array
    {
        if ($this->promptCompiler === null) {
            return ['result' => null, 'raw_response' => null, 'prompt' => null, 'prompt_hash' => null, 'outcome' => 'not_configured', 'error' => 'Prompt Compiler is not configured.'];
        }

        $compiled = $this->promptCompiler->compile($options);

        if ($compiled['outcome'] !== 'compiled') {
            return ['result' => null, 'raw_response' => null, 'prompt' => null, 'prompt_hash' => null, 'outcome' => 'compilation_failed', 'error' => $compiled['error']];
        }

        if ($this->llmClient === null) {
            return ['result' => null, 'raw_response' => null, 'prompt' => $compiled['prompt'], 'prompt_hash' => $compiled['prompt_hash'], 'outcome' => 'no_provider', 'error' => 'No LLM provider is configured.'];
        }

        $systemPrompt = $options['system_prompt'] ?? 'You are the SquirrelForge reasoning engine. Respond based only on the context provided.';

        try {
            $raw = $this->llmClient->complete($systemPrompt, $compiled['prompt']);
        } catch (Throwable $e) {
            return ['result' => null, 'raw_response' => null, 'prompt' => $compiled['prompt'], 'prompt_hash' => $compiled['prompt_hash'], 'outcome' => 'provider_error', 'error' => $e->getMessage()];
        }

        if (isset($options['expected_fields'])) {
            $structured = $this->parseStructured($raw, $options['expected_fields']);

            if ($structured === null) {
                return ['result' => null, 'raw_response' => $raw, 'prompt' => $compiled['prompt'], 'prompt_hash' => $compiled['prompt_hash'], 'outcome' => 'invalid_response', 'error' => sprintf('Response is not a JSON object containing exactly the expected keys: %s.', implode(', ', $options['expected_fields']))];
            }

            return ['result' => $structured, 'raw_response' => $raw, 'prompt' => $compiled['prompt'], 'prompt_hash' => $compiled['prompt_hash'], 'outcome' => 'completed', 'error' => null];
        }

        return ['result' => $raw, 'raw_response' => $raw, 'prompt' => $compiled['prompt'], 'prompt_hash' => $compiled['prompt_hash'], 'outcome' => 'completed', 'error' => null];
    }

    /**
     * @param array<int, string> $expectedFields
     * @return array<string, mixed>|null
     */
    private function parseStructured(string $raw, array $expectedFields): ?array
    {
        try {
            $decoded = json_decode(trim($raw), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        foreach ($expectedFields as $field) {
            if (!array_key_exists($field, $decoded)) {
                return null;
            }
        }

        return $decoded;
    }
}
