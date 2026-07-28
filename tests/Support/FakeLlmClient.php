<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Support;

use Closure;
use SquirrelForge\Contracts\LlmClientInterface;
use SquirrelForge\Contracts\ToolCallingLlmClientInterface;

/**
 * A test double for LlmClientInterface. Configure it with either a fixed
 * response string or a callable(systemPrompt, userPrompt): string, and
 * inspect `$calls` afterward to assert whether (and how) it was invoked.
 *
 * Also implements ToolCallingLlmClientInterface for tests exercising
 * Reasoner's tool-use loop: pass `$toolResponses` as an array of canned
 * completeWithTools() responses (consumed one per call, in order) or a
 * callable(systemPrompt, messages, tools): array.
 */
final class FakeLlmClient implements LlmClientInterface, ToolCallingLlmClientInterface
{
    /**
     * @var array<int, array{system: string, prompt: string}>
     */
    public array $calls = [];

    /**
     * @var array<int, array{system: string, messages: array, tools: array}>
     */
    public array $toolCalls = [];

    /**
     * @param array<int, array<string, mixed>>|Closure|null $toolResponses
     */
    public function __construct(
        private readonly string|Closure $response,
        private readonly array|Closure|null $toolResponses = null
    ) {
    }

    public function complete(string $systemPrompt, string $userPrompt): string
    {
        $this->calls[] = ['system' => $systemPrompt, 'prompt' => $userPrompt];

        if ($this->response instanceof Closure) {
            return ($this->response)($systemPrompt, $userPrompt);
        }

        return $this->response;
    }

    public function completeWithTools(string $systemPrompt, array $messages, array $tools): array
    {
        $this->toolCalls[] = ['system' => $systemPrompt, 'messages' => $messages, 'tools' => $tools];

        if ($this->toolResponses instanceof Closure) {
            return ($this->toolResponses)($systemPrompt, $messages, $tools);
        }

        $index = count($this->toolCalls) - 1;

        return $this->toolResponses[$index] ?? [
            'stop_reason' => 'end_turn',
            'text' => '{}',
            'tool_calls' => [],
            'assistant_content' => [],
        ];
    }
}
