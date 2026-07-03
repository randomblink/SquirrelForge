<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\AgentRegistry;
use SquirrelForge\Agent\CallbackAgent;
use SquirrelForge\Core\Kernel;

final class AgentTest extends TestCase
{
    private AgentRegistry $registry;

    protected function setUp(): void
    {
        $kernel = new Kernel();
        $app = $kernel->boot();
        $this->registry = $app->container()->make(AgentRegistry::class);
    }

    public function testAgentCanBeRegisteredAndFound(): void
    {
        $agent = new CallbackAgent(
            'hello-agent',
            'Hello Agent',
            'A simple test agent.',
            fn(array $context): bool => ($context['intent'] ?? null) === 'hello',
            fn(array $context): array => ['status' => 'success', 'context' => $context]
        );

        $agent->boot();
        $this->registry->register($agent);

        $found = $this->registry->findFor(['intent' => 'hello']);

        $this->assertNotNull($found);
        $this->assertSame('hello-agent', $found->getId());
    }

    public function testAgentExecutesAndReturnsResult(): void
    {
        $agent = new CallbackAgent(
            'echo-agent',
            'Echo Agent',
            'Echoes the context.',
            fn(array $context): bool => true,
            fn(array $context): array => ['status' => 'success', 'echo' => $context]
        );

        $agent->boot();
        $this->registry->register($agent);

        $result = $agent->execute(['intent' => 'echo', 'value' => 42]);

        $this->assertSame('success', $result['status']);
        $this->assertSame(42, $result['echo']['value']);
    }

    public function testFindForReturnsNullWhenNoAgentMatches(): void
    {
        $found = $this->registry->findFor(['intent' => 'no-match-' . uniqid()]);

        $this->assertNull($found);
    }

    public function testAgentHealthCheck(): void
    {
        $agent = new CallbackAgent(
            'health-agent',
            'Health Agent',
            'Tests health reporting.',
            fn(array $context): bool => false,
            fn(array $context): array => []
        );

        $this->assertFalse($agent->isHealthy());

        $agent->boot();

        $this->assertTrue($agent->isHealthy());
        $this->assertSame('healthy', $agent->health()['status']);
    }
}
