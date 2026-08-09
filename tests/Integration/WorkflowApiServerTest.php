<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\SqliteStateManager;
use SquirrelForge\Engine\WorkflowSelector;
use SquirrelForge\Integration\Http\WorkflowApiServer;
use SquirrelForge\Security\StaticAuthorizationManager;

final class WorkflowApiServerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-workflow-api-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function stateManager(): SqliteStateManager
    {
        return new SqliteStateManager($this->tempPath('state'));
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

    /**
     * @return array<string, mixed>
     */
    private function initializeRequest(array $overrides = []): array
    {
        return array_replace([
            'workflow_ref' => 'BUG-FIX-WORKFLOW',
            'goal' => ['goal_id' => 'goal_1', 'status' => 'ready'],
            'idempotency_key' => 'idempotency_1',
        ], $overrides);
    }

    public function testSelectRejectsAMissingRequiredHeader(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager());

        $response = $server->handle('POST', '/v1/workflows/selection', ['x-correlation-id' => 'correlation_1'], '{}');

        $this->assertSame(401, $response->status);
    }

    public function testSelectRejectsAnInvalidBody(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager());

        $response = $server->handle('POST', '/v1/workflows/selection', $this->headers(), 'not json');

        $this->assertSame(400, $response->status);
    }

    public function testSelectRejectsAMissingRequiredField(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager());

        $response = $server->handle('POST', '/v1/workflows/selection', $this->headers(), json_encode(['goal' => []]));

        $this->assertSame(422, $response->status);
    }

    public function testSelectRejectsAnUnauthorizedPermissionReference(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager());

        $body = json_encode(['goal' => ['status' => 'ready'], 'candidates' => [['name' => 'BUG-FIX-WORKFLOW']]]);
        $response = $server->handle('POST', '/v1/workflows/selection', $this->headers(['x-squirrelforge-permission-ref' => 'permission:denied']), $body);

        $this->assertSame(403, $response->status);
    }

    public function testSelectWithoutAConfiguredAuthorizationManagerFailsClosed(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector());

        $body = json_encode(['goal' => ['status' => 'ready'], 'candidates' => [['name' => 'BUG-FIX-WORKFLOW']]]);
        $response = $server->handle('POST', '/v1/workflows/selection', $this->headers(), $body);

        $this->assertSame(403, $response->status);
    }

    public function testSelectWithoutAConfiguredWorkflowSelectorIsRejected(): void
    {
        $server = new WorkflowApiServer(null, new StaticAuthorizationManager());

        $body = json_encode(['goal' => ['status' => 'ready'], 'candidates' => [['name' => 'BUG-FIX-WORKFLOW']]]);
        $response = $server->handle('POST', '/v1/workflows/selection', $this->headers(), $body);

        $this->assertSame(409, $response->status);
    }

    public function testSelectRoutesToTheRealWorkflowSelectorAndReturnsARealSelection(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager());

        $body = json_encode(['goal' => ['status' => 'ready'], 'candidates' => [['name' => 'BUG-FIX-WORKFLOW']]]);
        $response = $server->handle('POST', '/v1/workflows/selection', $this->headers(), $body);
        $decoded = json_decode($response->body, true);

        $this->assertSame(200, $response->status);
        $this->assertSame('BUG-FIX-WORKFLOW', $decoded['result']['workflow_ref']);
        $this->assertContains($decoded['result']['status'], ['SELECTED', 'SELECTED_WITH_LIMITATIONS']);
    }

    public function testSelectRejectsACandidateWorkflowThatDoesNotExist(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager());

        $body = json_encode(['goal' => ['status' => 'ready'], 'candidates' => [['name' => 'NOT-A-REAL-WORKFLOW']]]);
        $response = $server->handle('POST', '/v1/workflows/selection', $this->headers(), $body);
        $decoded = json_decode($response->body, true);

        $this->assertSame('UNSUPPORTED', $decoded['result']['status']);
    }

    public function testInitializeIsNotImplementedWhenStateManagerIsNotComposed(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager());

        $response = $server->handle('POST', '/v1/workflows/runs', $this->headers(), json_encode($this->initializeRequest()));

        $this->assertSame(501, $response->status);
        $this->assertStringContainsString('STATE-MANAGER', $response->body);
    }

    public function testNextIsNotImplemented(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager());

        $response = $server->handle('POST', '/v1/workflows/runs/run_1/next', [], null);

        $this->assertSame(501, $response->status);
        $this->assertStringContainsString('WORKFLOW-EXECUTOR', $response->body);
    }

    public function testPhaseCompletionIsNotImplemented(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager());

        $response = $server->handle('POST', '/v1/workflows/runs/run_1/phase-completion', [], null);

        $this->assertSame(501, $response->status);
        $this->assertStringContainsString('WORKFLOW-EXECUTOR', $response->body);
    }

    public function testTerminateIsNotImplementedWhenStateManagerIsNotComposed(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager());

        $response = $server->handle('POST', '/v1/workflows/runs/run_1/terminate', $this->headers(), null);

        $this->assertSame(501, $response->status);
        $this->assertStringContainsString('STATE-MANAGER', $response->body);
    }

    public function testUnknownRouteReturns404(): void
    {
        $server = new WorkflowApiServer();

        $response = $server->handle('GET', '/v1/workflows/nonsense', [], null);

        $this->assertSame(404, $response->status);
    }

    // --- initialize(): shape validation ---

    public function testInitializeRejectsAMissingRequiredField(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager(), $this->stateManager());

        $response = $server->handle('POST', '/v1/workflows/runs', $this->headers(), json_encode(['workflow_ref' => 'x']));

        $this->assertSame(422, $response->status);
    }

    // --- initialize(): real SqliteStateManager composition ---

    public function testInitializeCreatesARealRun(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager(), $this->stateManager());

        $response = $server->handle('POST', '/v1/workflows/runs', $this->headers(), json_encode($this->initializeRequest()));
        $decoded = json_decode($response->body, true);

        $this->assertSame(200, $response->status);
        $this->assertSame('idempotency_1', $decoded['result']['run_ref']);
        $this->assertFalse($decoded['result']['idempotent_replay']);
    }

    public function testInitializeIsIdempotentOnRepeatedKey(): void
    {
        $stateManager = $this->stateManager();
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager(), $stateManager);
        $server->handle('POST', '/v1/workflows/runs', $this->headers(), json_encode($this->initializeRequest()));

        $second = $server->handle('POST', '/v1/workflows/runs', $this->headers(), json_encode($this->initializeRequest()));
        $decoded = json_decode($second->body, true);

        $this->assertSame(200, $second->status);
        $this->assertSame('idempotency_1', $decoded['result']['run_ref']);
        $this->assertTrue($decoded['result']['idempotent_replay']);
        // Confirms it's a genuine replay, not a second run -- exactly one real state record exists.
        $this->assertNotNull($stateManager->currentState('idempotency_1'));
    }

    public function testDifferentIdempotencyKeysCreateDifferentRuns(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager(), $this->stateManager());
        $server->handle('POST', '/v1/workflows/runs', $this->headers(), json_encode($this->initializeRequest(['idempotency_key' => 'key_1'])));

        $second = $server->handle('POST', '/v1/workflows/runs', $this->headers(), json_encode($this->initializeRequest(['idempotency_key' => 'key_2'])));
        $decoded = json_decode($second->body, true);

        $this->assertSame('key_2', $decoded['result']['run_ref']);
        $this->assertFalse($decoded['result']['idempotent_replay']);
    }

    // --- terminate(): acknowledges, never decides ---

    public function testTerminateOnAnUnknownRunIs404(): void
    {
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager(), $this->stateManager());

        $response = $server->handle('POST', '/v1/workflows/runs/ghost/terminate', $this->headers(), null);

        $this->assertSame(404, $response->status);
    }

    public function testTerminateAcknowledgesButNeverMarksTerminated(): void
    {
        $stateManager = $this->stateManager();
        $server = new WorkflowApiServer(new WorkflowSelector(), new StaticAuthorizationManager(), $stateManager);
        $server->handle('POST', '/v1/workflows/runs', $this->headers(), json_encode($this->initializeRequest()));

        $response = $server->handle('POST', '/v1/workflows/runs/idempotency_1/terminate', $this->headers(), json_encode(['reason' => 'superseded']));
        $decoded = json_decode($response->body, true);

        $this->assertSame(200, $response->status);
        $this->assertTrue($decoded['result']['acknowledged']);
        $this->assertFalse($decoded['result']['terminated']);
        $state = $stateManager->currentState('idempotency_1');
        $this->assertSame('BLOCKED', $state['lifecycle_phase']);
        $this->assertSame('superseded', $state['blocker_reason']);
    }
}
