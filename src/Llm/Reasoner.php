<?php

declare(strict_types=1);

namespace SquirrelForge\Llm;

use RuntimeException;
use SquirrelForge\Contracts\LlmClientInterface;
use SquirrelForge\Contracts\ToolCallingLlmClientInterface;
use SquirrelForge\Contracts\ToolInterface;
use SquirrelForge\Tools\ToolRegistry;

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
 *
 * When a non-empty `ToolRegistry` is also supplied, `reason()` drives a real
 * multi-turn Claude tool-use conversation instead of a single blocking call:
 * the registered tools are advertised to the model, and any `tool_use` turn
 * is dispatched through the registry and fed back as a `tool_result` before
 * asking again, until the model returns its final JSON answer (still
 * expressed the same way -- ONLY a JSON object with the requested keys).
 * This requires the injected LLM client to also implement
 * `ToolCallingLlmClientInterface`, checked once at construction time.
 */
final class Reasoner
{
    private const MAX_TOOL_TURNS = 6;

    /**
     * @var array<int, array{name: string, input: array<string, mixed>, result: array<string, mixed>}>
     */
    private array $lastToolCalls = [];

    public function __construct(
        private readonly LlmClientInterface $llm,
        private readonly ?ToolRegistry $tools = null
    ) {
        if ($this->tools !== null
            && $this->tools->all() !== []
            && !($this->llm instanceof ToolCallingLlmClientInterface)) {
            throw new RuntimeException(sprintf(
                'Reasoner was given tools but its LLM client (%s) does not implement %s.',
                $this->llm::class,
                ToolCallingLlmClientInterface::class
            ));
        }
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
     *                           parsed as JSON, that is missing a requested
     *                           key, or (when tools are active) exceeds the
     *                           maximum number of tool-use turns.
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

        $this->lastToolCalls = [];

        if ($this->tools !== null && $this->tools->all() !== []) {
            return $this->reasonWithTools($system, $userPrompt, $fields, $callerClass);
        }

        $raw = trim($this->llm->complete($system, $userPrompt));

        return $this->parseFinalAnswer($raw, $fields, $callerClass);
    }

    /**
     * Every tool call made and its result during the most recent tool-driven
     * `reason()` call (empty when tools were not active for that call).
     *
     * @return array<int, array{name: string, input: array<string, mixed>, result: array<string, mixed>}>
     */
    public function lastToolCalls(): array
    {
        return $this->lastToolCalls;
    }

    /**
     * @param array<int, string> $fields
     * @return array<string, mixed>
     */
    private function reasonWithTools(string $system, string $userPrompt, array $fields, string $callerClass): array
    {
        /** @var ToolCallingLlmClientInterface $llm */
        $llm = $this->llm;
        $toolDefs = array_map(
            static fn(ToolInterface $tool): array => [
                'name' => $tool->getId(),
                'description' => $tool->getDescription(),
                'parameters' => $tool->parameters(),
            ],
            $this->tools->all()
        );

        $messages = [['role' => 'user', 'content' => $userPrompt]];

        for ($turn = 0; $turn < self::MAX_TOOL_TURNS; $turn++) {
            $response = $llm->completeWithTools($system, $messages, $toolDefs);

            if ($response['stop_reason'] !== 'tool_use') {
                return $this->parseFinalAnswer(trim($response['text']), $fields, $callerClass);
            }

            $messages[] = ['role' => 'assistant', 'content' => $response['assistant_content']];
            $messages[] = ['role' => 'user', 'content' => $this->dispatchToolCalls($response['tool_calls'])];
        }

        throw new RuntimeException(sprintf(
            '%s: exceeded %d tool-use turns without a final answer.',
            $callerClass,
            self::MAX_TOOL_TURNS
        ));
    }

    /**
     * @param array<int, array{id: string, name: string, input: array<string, mixed>}> $toolCalls
     * @return array<int, array{type: string, tool_use_id: string, content: string}>
     */
    private function dispatchToolCalls(array $toolCalls): array
    {
        $results = [];

        foreach ($toolCalls as $call) {
            $tool = $this->tools->get($call['name']);
            $result = $tool !== null
                ? $tool->execute($call['name'], $call['input'])
                : ['error' => sprintf('Unknown tool "%s".', $call['name'])];

            $this->lastToolCalls[] = ['name' => $call['name'], 'input' => $call['input'], 'result' => $result];

            $results[] = [
                'type' => 'tool_result',
                'tool_use_id' => $call['id'],
                'content' => json_encode($result, JSON_THROW_ON_ERROR),
            ];
        }

        return $results;
    }

    /**
     * @param array<int, string> $fields
     * @return array<string, mixed>
     */
    private function parseFinalAnswer(string $raw, array $fields, string $callerClass): array
    {
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
