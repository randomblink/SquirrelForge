<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Core\Kernel;
use SquirrelForge\Tools\CallbackTool;
use SquirrelForge\Tools\ToolRegistry;

final class ToolTest extends TestCase
{
    private ToolRegistry $registry;

    protected function setUp(): void
    {
        $kernel = new Kernel();
        $app = $kernel->boot();
        $this->registry = $app->container()->make(ToolRegistry::class);
    }

    public function testToolCanBeRegisteredAndFound(): void
    {
        $tool = new CallbackTool(
            'echo-tool',
            'Echo Tool',
            'Echoes input.',
            fn(string $op): bool => $op === 'echo',
            fn(string $op, array $params): array => ['status' => 'success', 'output' => $params['input'] ?? null]
        );

        $tool->boot();
        $this->registry->register($tool);

        $found = $this->registry->findFor('echo');

        $this->assertNotNull($found);
        $this->assertSame('echo-tool', $found->getId());
    }

    public function testToolExecutesAndReturnsResult(): void
    {
        $tool = new CallbackTool(
            'add-tool',
            'Add Tool',
            'Adds two numbers.',
            fn(string $op): bool => $op === 'add',
            fn(string $op, array $params): array => [
                'status' => 'success',
                'result' => ($params['a'] ?? 0) + ($params['b'] ?? 0),
            ]
        );

        $tool->boot();
        $result = $tool->execute('add', ['a' => 3, 'b' => 4]);

        $this->assertSame('success', $result['status']);
        $this->assertSame(7, $result['result']);
    }

    public function testFindForReturnsNullWhenNoToolMatches(): void
    {
        $found = $this->registry->findFor('no-match-' . uniqid());

        $this->assertNull($found);
    }

    public function testToolHealthCheck(): void
    {
        $tool = new CallbackTool(
            'health-tool',
            'Health Tool',
            'Tests health.',
            fn(string $op): bool => false,
            fn(string $op, array $params): array => []
        );

        $this->assertFalse($tool->isHealthy());

        $tool->boot();

        $this->assertTrue($tool->isHealthy());
        $this->assertSame('healthy', $tool->health()['status']);
    }
}
