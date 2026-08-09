<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\AgentRegistry;
use SquirrelForge\Agent\CallbackAgent;
use SquirrelForge\Coordination\SqliteHandoffProtocol;
use SquirrelForge\Engine\SqliteStateManager;
use SquirrelForge\Engine\TaskRouter;
use SquirrelForge\Integration\Http\AgentApiServer;
use SquirrelForge\Security\StaticAuthorizationManager;

final class AgentApiServerTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }
    }

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-agent-api-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

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

    private function stateManager(): SqliteStateManager
    {
        return new SqliteStateManager($this->tempPath('state'));
    }

    /**
     * Assigns a task through a real server and returns the resulting
     * assignment_request_id, with state genuinely initialized.
     */
    private function assignedRequestId(AgentApiServer $server): string
    {
        $body = json_encode(['task' => ['task_id' => 't1'], 'requirements' => ['required_capability' => 'stage']]);
        $response = $server->handle('POST', '/v1/agents/assignments', $this->headers(), $body);

        return json_decode($response->body, true)['result']['assignment_request_id'];
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

    public function testStatusIsNotImplementedWhenStateManagerIsNotComposed(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager());

        $response = $server->handle('GET', '/v1/agents/assignments/assignment_request_1', $this->headers(), null);

        $this->assertSame(501, $response->status);
        $this->assertStringContainsString('STATE-MANAGER', $response->body);
    }

    public function testHandoffIsNotImplementedWhenHandoffProtocolIsNotComposed(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager());

        $response = $server->handle('POST', '/v1/agents/assignments/assignment_request_1/handoff', $this->headers(), json_encode(['target' => 'agent_2']));

        $this->assertSame(501, $response->status);
        $this->assertStringContainsString('HANDOFF-PROTOCOL', $response->body);
    }

    public function testCancelIsNotImplementedWhenStateManagerIsNotComposed(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager());

        $response = $server->handle('POST', '/v1/agents/assignments/assignment_request_1/cancel', $this->headers(), null);

        $this->assertSame(501, $response->status);
        $this->assertStringContainsString('STATE-MANAGER', $response->body);
    }

    public function testStatusCancelAndHandoffStillRequireHeadersEvenWhenNotImplemented(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager());

        $response = $server->handle('GET', '/v1/agents/assignments/assignment_request_1', [], null);

        $this->assertSame(401, $response->status);
    }

    public function testUnknownRouteReturns404(): void
    {
        $server = new AgentApiServer();

        $response = $server->handle('GET', '/v1/agents/nonsense', [], null);

        $this->assertSame(404, $response->status);
    }

    // --- status(): real SqliteStateManager composition ---

    public function testStatusOnAnUnknownAssignmentIs404(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager(), $this->stateManager());

        $response = $server->handle('GET', '/v1/agents/assignments/ghost', $this->headers(), null);

        $this->assertSame(404, $response->status);
        $this->assertStringContainsString('UNKNOWN_ASSIGNMENT', $response->body);
    }

    public function testAssignInitializesRealStateAndStatusReadsItBack(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager(), $this->stateManager());
        $requestId = $this->assignedRequestId($server);

        $response = $server->handle('GET', '/v1/agents/assignments/' . $requestId, $this->headers(), null);
        $decoded = json_decode($response->body, true);

        $this->assertSame(200, $response->status);
        $this->assertSame('REQUESTED', $decoded['result']['lifecycle_phase']);
        $this->assertSame('ROUTED', $decoded['result']['tasks'][0]['state']);
        $this->assertSame('agent_1', $decoded['result']['tasks'][0]['owner']);
    }

    public function testStatusNeverMutatesState(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager(), $this->stateManager());
        $requestId = $this->assignedRequestId($server);

        $server->handle('GET', '/v1/agents/assignments/' . $requestId, $this->headers(), null);
        $second = $server->handle('GET', '/v1/agents/assignments/' . $requestId, $this->headers(), null);
        $decoded = json_decode($second->body, true);

        $this->assertSame('ROUTED', $decoded['result']['tasks'][0]['state']);
    }

    // --- cancel(): acknowledges, never decides ---

    public function testCancelOnAnUnknownAssignmentIs404(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager(), $this->stateManager());

        $response = $server->handle('POST', '/v1/agents/assignments/ghost/cancel', $this->headers(), null);

        $this->assertSame(404, $response->status);
    }

    public function testCancelAcknowledgesButNeverMarksCancelled(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager(), $this->stateManager());
        $requestId = $this->assignedRequestId($server);

        $response = $server->handle('POST', '/v1/agents/assignments/' . $requestId . '/cancel', $this->headers(), json_encode(['reason' => 'no longer needed']));
        $decoded = json_decode($response->body, true);

        $this->assertSame(200, $response->status);
        $this->assertTrue($decoded['result']['acknowledged']);
        $this->assertFalse($decoded['result']['cancelled']);
    }

    public function testCancelRecordsARealBlockerNotAFabricatedOne(): void
    {
        $stateManager = $this->stateManager();
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager(), $stateManager);
        $requestId = $this->assignedRequestId($server);

        $server->handle('POST', '/v1/agents/assignments/' . $requestId . '/cancel', $this->headers(), json_encode(['reason' => 'no longer needed']));

        $state = $stateManager->currentState($requestId);
        $this->assertSame('BLOCKED', $state['lifecycle_phase']);
        $this->assertSame('no longer needed', $state['blocker_reason']);
    }

    // --- handoff(): real SqliteHandoffProtocol composition ---

    public function testHandoffRequiresATarget(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager(), $this->stateManager(), new SqliteHandoffProtocol($this->tempPath('handoff')));
        $requestId = $this->assignedRequestId($server);

        $response = $server->handle('POST', '/v1/agents/assignments/' . $requestId . '/handoff', $this->headers(), json_encode([]));

        $this->assertSame(422, $response->status);
    }

    public function testHandoffOnAnUnknownAssignmentIs404(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager(), $this->stateManager(), new SqliteHandoffProtocol($this->tempPath('handoff')));

        $response = $server->handle('POST', '/v1/agents/assignments/ghost/handoff', $this->headers(), json_encode(['target' => 'agent_2']));

        $this->assertSame(404, $response->status);
    }

    public function testHandoffRoutesToTheRealHandoffProtocol(): void
    {
        $server = new AgentApiServer(new TaskRouter($this->agentRegistry()), new StaticAuthorizationManager(), $this->stateManager(), new SqliteHandoffProtocol($this->tempPath('handoff')));
        $requestId = $this->assignedRequestId($server);

        $response = $server->handle('POST', '/v1/agents/assignments/' . $requestId . '/handoff', $this->headers(), json_encode(['target' => 'agent_2']));
        $decoded = json_decode($response->body, true);

        $this->assertSame(200, $response->status);
        $this->assertNotNull($decoded['result']['handoff_id']);
        // No SqliteHandoffProtocol message bus composed -- a real, honest "Sent" status, not a fabricated "Accepted."
        $this->assertSame('Sent', $decoded['result']['status']);
    }
}
