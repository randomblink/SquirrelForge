<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Engine\ContextManager;
use SquirrelForge\Engine\PromptCompiler;
use SquirrelForge\Reasoning\AIDriver;
use SquirrelForge\Tests\Support\FakeLlmClient;

final class AIDriverTest extends TestCase
{
    public function testReasonWithoutPromptCompilerIsNotConfigured(): void
    {
        $driver = new AIDriver();

        $result = $driver->reason();

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testReasonWithAMisconfiguredPromptCompilerReportsCompilationFailed(): void
    {
        $driver = new AIDriver(new PromptCompiler());

        $result = $driver->reason();

        $this->assertSame('compilation_failed', $result['outcome']);
    }

    public function testReasonWithoutAnLlmClientReportsNoProviderButStillReturnsThePrompt(): void
    {
        $context = new ContextManager();
        $context->load('goal', 'user_request', 'Ship the login feature.');
        $driver = new AIDriver(new PromptCompiler($context));

        $result = $driver->reason();

        $this->assertSame('no_provider', $result['outcome']);
        $this->assertNotNull($result['prompt']);
        $this->assertNotNull($result['prompt_hash']);
        $this->assertNull($result['result']);
    }

    public function testReasonSendsTheCompiledPromptVerbatimToTheLlmClient(): void
    {
        $context = new ContextManager();
        $context->load('goal', 'user_request', 'Ship the login feature.');
        $promptCompiler = new PromptCompiler($context);
        $llm = new FakeLlmClient('The login feature is ready.');
        $driver = new AIDriver($promptCompiler, $llm);

        $result = $driver->reason();

        $this->assertSame('completed', $result['outcome']);
        $this->assertSame('The login feature is ready.', $result['result']);
        $this->assertCount(1, $llm->calls);
        $this->assertSame($result['prompt'], $llm->calls[0]['prompt']);
        $this->assertSame($result['prompt_hash'], hash('sha256', $result['prompt']));
    }

    public function testReasonUsesTheDefaultSystemPromptWhenNoneIsSupplied(): void
    {
        $llm = new FakeLlmClient('ok');
        $driver = new AIDriver(new PromptCompiler(new ContextManager()), $llm);

        $driver->reason();

        $this->assertStringContainsString('SquirrelForge reasoning engine', $llm->calls[0]['system']);
    }

    public function testReasonUsesACustomSystemPromptWhenSupplied(): void
    {
        $llm = new FakeLlmClient('ok');
        $driver = new AIDriver(new PromptCompiler(new ContextManager()), $llm);

        $driver->reason(['system_prompt' => 'You are a specialized code reviewer.']);

        $this->assertSame('You are a specialized code reviewer.', $llm->calls[0]['system']);
    }

    public function testReasonReportsAProviderErrorWithoutLosingTheCompiledPrompt(): void
    {
        $llm = new FakeLlmClient(function (): never {
            throw new RuntimeException('The provider timed out.');
        });
        $driver = new AIDriver(new PromptCompiler(new ContextManager()), $llm);

        $result = $driver->reason();

        $this->assertSame('provider_error', $result['outcome']);
        $this->assertSame('The provider timed out.', $result['error']);
        $this->assertNotNull($result['prompt']);
    }

    public function testReasonParsesAValidStructuredResponse(): void
    {
        $llm = new FakeLlmClient('{"summary": "Done", "confidence": "high"}');
        $driver = new AIDriver(new PromptCompiler(new ContextManager()), $llm);

        $result = $driver->reason(['expected_fields' => ['summary', 'confidence']]);

        $this->assertSame('completed', $result['outcome']);
        $this->assertSame(['summary' => 'Done', 'confidence' => 'high'], $result['result']);
    }

    public function testReasonRejectsAStructuredResponseMissingAnExpectedKey(): void
    {
        $llm = new FakeLlmClient('{"summary": "Done"}');
        $driver = new AIDriver(new PromptCompiler(new ContextManager()), $llm);

        $result = $driver->reason(['expected_fields' => ['summary', 'confidence']]);

        $this->assertSame('invalid_response', $result['outcome']);
        $this->assertSame('{"summary": "Done"}', $result['raw_response']);
    }

    public function testReasonRejectsANonJsonResponseWhenFieldsAreExpected(): void
    {
        $llm = new FakeLlmClient('not json at all');
        $driver = new AIDriver(new PromptCompiler(new ContextManager()), $llm);

        $result = $driver->reason(['expected_fields' => ['summary']]);

        $this->assertSame('invalid_response', $result['outcome']);
    }
}
