<?php

declare(strict_types=1);

namespace SquirrelForge\Llm;

use RuntimeException;
use SquirrelForge\Contracts\LlmClientInterface;

/**
 * Asks an `LlmClientInterface` to fill in specific fields, expressed as a
 * strict JSON object with exactly those keys, and validates the response.
 *
 * Extracted out of `AbstractRoleAgent`, which previously implemented this
 * prompt-building/parsing/validation logic inline. A role agent only
 * constructs one of these when an LLM client is actually injected -- there
 * is deliberately no "no LLM configured" case handled inside this class;
 * that decision belongs to the caller (see `AbstractRoleAgent::reason()`,
 * which returns null without ever constructing a `Reasoner` when no LLM is
 * configured).
 */
final class Reasoner
{
    public function __construct(
        private readonly LlmClientInterface $llm
    ) {
    }

    /**
     * @param string $agentName Display name used in the system prompt (e.g.
     *                          "Agent Architect"), matching what the model
     *                          used to see when this lived inline.
     * @param string $callerClass The concrete role agent's class name (e.g.
     *                            `static::class` from the caller), used to
     *                            identify the caller in error messages --
     *                            kept separate from $agentName so error text
     *                            is byte-for-byte identical to before this
     *                            was extracted out of AbstractRoleAgent.
     * @param array<int, string> $fields Keys the model must return.
     * @param array<string, mixed> $payload Data the model should reason over.
     * @return array<string, mixed>
     *
     * @throws RuntimeException if the LLM returns a response that cannot be
     *                           parsed as JSON, or that is missing a
     *                           requested key.
     */
    public function reason(
        string $agentName,
        string $callerClass,
        string $instructions,
        array $fields,
        array $payload
    ): array {
        $system = sprintf(
            "You are the %s in the SquirrelForge agent pipeline.\n%s\n\n" .
            "Respond with ONLY a single JSON object containing exactly these keys: %s. " .
            "Do not include any prose, explanation, or Markdown code fences around the JSON.",
            $agentName,
            $instructions,
            implode(', ', $fields)
        );

        $userPrompt = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $raw = trim($this->llm->complete($system, $userPrompt));
        $raw = $this->stripCodeFence($raw);

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf(
                '%s: expected a JSON object from the LLM but got: %s',
                $callerClass,
                substr($raw, 0, 200)
            ));
        }

        foreach ($fields as $field) {
            if (!array_key_exists($field, $decoded)) {
                throw new RuntimeException(sprintf(
                    '%s: LLM response is missing required field "%s".',
                    $callerClass,
                    $field
                ));
            }
        }

        return $decoded;
    }

    /**
     * Models sometimes wrap JSON in ```json ... ``` fences despite being
     * told not to. Strip that defensively rather than failing the parse.
     */
    private function stripCodeFence(string $raw): string
    {
        if (str_starts_with($raw, '```')) {
            $raw = preg_replace('/^```[a-zA-Z]*\n|\n```$/', '', $raw) ?? $raw;
        }

        return trim($raw);
    }
}
