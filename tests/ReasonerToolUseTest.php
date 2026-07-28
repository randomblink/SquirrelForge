<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Contracts\LlmClientInterface;
use SquirrelForge\Llm\Reasoner;
use SquirrelForge\Tests\Support\FakeLlmClient;
use SquirrelForge\Tools\CallbackTool;
use SquirrelForge\Tools\ToolRegistry;

final class ReasonerToolUseTest extends TestCase
{
    public function testToolUseTurnIsDispatchedAndFinalAnswerIsParsed(): void
    {
        $tools = new ToolRegistry();
        $tools->register(new CallbackTool(
            'echo',
            'Echo',
            'Echoes the given text back.',
            fn(string $op): bool => $op === 'echo',
            fn(string $op, array $params): array => ['echoed' => $params['text'] ?? null]
        ));

        $llm = new FakeLlmClient('unused', [
            [
                'stop_reason' => 'tool_use',
                'text' => '',
                'tool_calls' => [['id' => 'call_1', 'name' => 'echo', 'input' => ['text' => 'hi']]],
                'assistant_content' => [['type' => 'tool_use', 'id' => 'call_1', 'name' => 'echo', 'input' => ['text' => 'hi']]],
            ],
            [
                'stop_reason' => 'end_turn',
                'text' => '{"answer":"done"}',
                'tool_calls' => [],
                'assistant_content' => [['type' => 'text', 'text' => '{"answer":"done"}']],
            ],
        ]);

        $reasoner = new Reasoner($llm, $tools);
        $result = $reasoner->reason('Agent Developer', 'Test', 'instructions', ['answer'], ['plan' => []]);

        $this->assertSame(['answer' => 'done'], $result);
        $this->assertCount(1, $reasoner->lastToolCalls());
        $this->assertSame('echo', $reasoner->lastToolCalls()[0]['name']);
        $this->assertSame(['echoed' => 'hi'], $reasoner->lastToolCalls()[0]['result']);
        $this->assertCount(2, $llm->toolCalls);
    }

    public function testUnknownToolNameProducesErrorResultInsteadOfCrashing(): void
    {
        $tools = new ToolRegistry();
        $tools->register(new CallbackTool(
            'echo',
            'Echo',
            'Echoes.',
            fn(string $op): bool => true,
            fn(string $op, array $params): array => ['echoed' => true]
        ));

        $llm = new FakeLlmClient('unused', [
            [
                'stop_reason' => 'tool_use',
                'text' => '',
                'tool_calls' => [['id' => 'call_1', 'name' => 'bogus', 'input' => []]],
                'assistant_content' => [],
            ],
            [
                'stop_reason' => 'end_turn',
                'text' => '{"answer":"done"}',
                'tool_calls' => [],
                'assistant_content' => [],
            ],
        ]);

        $reasoner = new Reasoner($llm, $tools);
        $result = $reasoner->reason('Agent Developer', 'Test', 'instructions', ['answer'], []);

        $this->assertSame(['answer' => 'done'], $result);
        $this->assertSame('Unknown tool "bogus".', $reasoner->lastToolCalls()[0]['result']['error']);
    }

    public function testExceedingMaxTurnsThrows(): void
    {
        $tools = new ToolRegistry();
        $tools->register(new CallbackTool(
            'echo',
            'Echo',
            'Echoes.',
            fn(string $op): bool => true,
            fn(string $op, array $params): array => []
        ));

        $llm = new FakeLlmClient('unused', static fn(): array => [
            'stop_reason' => 'tool_use',
            'text' => '',
            'tool_calls' => [],
            'assistant_content' => [],
        ]);

        $reasoner = new Reasoner($llm, $tools);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/exceeded 6 tool-use turns/');

        $reasoner->reason('Agent Developer', 'Test', 'instructions', ['answer'], []);
    }

    public function testConstructorRejectsToolsWithoutToolCallingClient(): void
    {
        $tools = new ToolRegistry();
        $tools->register(new CallbackTool(
            'echo',
            'Echo',
            'Echoes.',
            fn(string $op): bool => true,
            fn(string $op, array $params): array => []
        ));

        $plainLlm = new class implements LlmClientInterface {
            public function complete(string $systemPrompt, string $userPrompt): string
            {
                return '{}';
            }
        };

        $this->expectException(RuntimeException::class);

        new Reasoner($plainLlm, $tools);
    }
}
