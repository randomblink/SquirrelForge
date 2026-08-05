<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\AgentRegistry;
use SquirrelForge\Agent\CallbackAgent;
use SquirrelForge\Engine\TaskRouter;
use SquirrelForge\Integration\Http\AgentApiServer;
use SquirrelForge\Security\StaticAuthorizationManager;

final class AgentApiServerTest extends TestCase
{
    /**
     * @param array<string, string> $extraHeaders
     * @return array<string, string>
     */
    private function headers(array $extraHeaders = []): array
    {
        return array_merge([
            'x-squirrelforge-identity-ref' => 'identity_1',
            'x-squirrelforge-permission-ref' => 'permission:allowed',
            'x-correlation-id' => 'correlation_1',
        ], $extraHeaders);
    }

    private function agentRegistry(): AgentRegistry
    {
        $registry = new AgentRegistry();
        $registry->register(new CallbackAgent(
            'agent_1',
            'Agent One',
            'A test agent.',
            static fn(array $context): bool => ($context['stage'] ?? null) === 'stage',
            static fn(array $context): array => $context
        ));

        return $registry;
    }

    public function testAssignRejectsAMissingRequiredHeader(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager());

        $response = $server->handle('POST', '/v1/agents/assignments', ['x-correlation-id' => 'correlation_1'], '{}');

        $this->assertSame(401, $response->status);
        $this->assertStringContainsString('UNAUTHORIZED', $response->body);
    }

    public function testAssignRejectsAnInvalidBody(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager());

        $response = $server->handle('POST', '/v1/agents/assignments', $this->headers(), 'not json');

        $this->assertSame(400, $response->status);
    }

    public function testAssignRejectsAMissingRequiredField(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager());

        $response = $server->handle('POST', '/v1/agents/assignments', $this->headers(), json_encode(['task' => ['task_id' => 't1']]));

        $this->assertSame(422, $response->status);
    }

    public function testAssignRejectsAnUnauthorizedPermissionReference(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager());

        $body = json_encode(['task' => ['task_id' => 't1'], 'requirements' => ['required_capability' => 'stage']]);
        $response = $server->handle('POST', '/v1/agents/assignments', $this->headers(['x-squirrelforge-permission-ref' => 'permission:denied']), $body);

        $this->assertSame(403, $response->status);
    }

    public function testAssignWithoutAConfiguredAuthorizationManagerFailsClosed(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()));

        $body = json_encode(['task' => ['task_id' => 't1'], 'requirements' => ['required_capability' => 'stage']]);
        $response = $server->handle('POST', '/v1/agents/assignments', $this->headers(), $body);

        $this->assertSame(403, $response->status);
    }

    public function testAssignWithoutAConfiguredTaskRouterIsRejected(): void
    {
        $server = new AgentApiServer(null, new StaticAuthorizationManager());

        $body = json_encode(['task' => ['task_id' => 't1'], 'requirements' => ['required_capability' => 'stage']]);
        $response = $server->handle('POST', '/v1/agents/assignments', $this->headers(), $body);

        $this->assertSame(409, $response->status);
    }

    public function testAssignRoutesToTheRealTaskRouterAndReturnsARealRoutingDecision(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager());

        $body = json_encode(['task' => ['task_id' => 't1'], 'requirements' => ['required_capability' => 'stage']]);
        $response = $server->handle('POST', '/v1/agents/assignments', $this->headers(), $body);
        $decoded = json_decode($response->body, true);

        $this->assertSame(200, $response->status);
        $this->assertSame('ROUTED', $decoded['result']['status']);
        $this->assertSame('agent_1', $decoded['result']['owner']);
        $this->assertNotNull($decoded['result']['assignment_request_id']);
    }

    public function testStatusIsNotImplemented(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager());

        $response = $server->handle('GET', '/v1/agents/assignments/assignment_request_1', [], null);

        $this->assertSame(501, $response->status);
        $this->assertStringContainsString('STATE-MANAGER', $response->body);
    }

    public function testHandoffIsNotImplemented(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager());

        $response = $server->handle('POST', '/v1/agents/assignments/assignment_request_1/handoff', [], null);

        $this->assertSame(501, $response->status);
        $this->assertStringContainsString('HANDOFF-PROTOCOL', $response->body);
    }

    public function testCancelIsNotImplemented(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager());

        $response = $server->handle('POST', '/v1/agents/assignments/assignment_request_1/cancel', [], null);

        $this->assertSame(501, $response->status);
        $this->assertStringContainsString('STATE-MANAGER', $response->body);
    }

    public function testUnknownRouteReturns404(): void
    {
        $server = new AgentApiServer();

        $response = $server->handle('GET', '/v1/agents/nonsense', [], null);

        $this->assertSame(404, $response->status);
    }
}
