<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface ToolCallingLlmClientInterface
{
    /**
     * A single tool-use turn: sends the running conversation plus the
     * available tool definitions, and returns whether the model wants to
     * call a tool or has produced its final answer.
     *
     * @param array<int, array{role: string, content: mixed}> $messages
     * @param array<int, array{name: string, description: string, parameters: array<string, mixed>}> $tools
     * @return array{
     *     stop_reason: string,
     *     text: string,
     *     tool_calls: array<int, array{id: string, name: string, input: array<string, mixed>}>,
     *     assistant_content: mixed
     * }
     *
     * @throws \RuntimeException on transport or API failure.
     */
    public function completeWithTools(string $systemPrompt, array $messages, array $tools): array;
}
