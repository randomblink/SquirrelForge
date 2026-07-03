<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Core\Kernel;
use SquirrelForge\Workflow\CallbackWorkflow;
use SquirrelForge\Workflow\WorkflowEngine;

final class WorkflowTest extends TestCase
{
    private WorkflowEngine $engine;

    protected function setUp(): void
    {
        $kernel = new Kernel();
        $app = $kernel->boot();
        $this->engine = $app->container()->make(WorkflowEngine::class);
    }

    public function testWorkflowExecutesAndReturnsResult(): void
    {
        $this->engine->register(new CallbackWorkflow(
            'hello',
            'Simple hello workflow.',
            fn(array $context): bool => ($context['intent'] ?? null) === 'hello',
            fn(array $context): array => ['status' => 'success', 'context' => $context]
        ));

        $result = $this->engine->execute(['intent' => 'hello', 'name' => 'randomblink']);

        $this->assertSame('success', $result['status']);
        $this->assertSame('randomblink', $result['context']['name']);
    }

    public function testWorkflowThrowsWhenNoneSupportsContext(): void
    {
        $this->expectException(RuntimeException::class);

        $this->engine->execute(['intent' => 'no-match-' . uniqid()]);
    }

    public function testFirstMatchingWorkflowWins(): void
    {
        $this->engine->register(new CallbackWorkflow(
            'first',
            'First workflow.',
            fn(array $context): bool => true,
            fn(array $context): array => ['status' => 'success', 'which' => 'first']
        ));

        $this->engine->register(new CallbackWorkflow(
            'second',
            'Second workflow.',
            fn(array $context): bool => true,
            fn(array $context): array => ['status' => 'success', 'which' => 'second']
        ));

        $result = $this->engine->execute([]);

        $this->assertSame('first', $result['which']);
    }
}
