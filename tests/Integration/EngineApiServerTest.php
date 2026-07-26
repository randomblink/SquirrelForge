<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\InMemoryEngineRuntime;
use SquirrelForge\Integration\Flock\FlockPluginAdapter;
use SquirrelForge\Integration\Flock\HttpEngineClient;
use SquirrelForge\Integration\Http\EngineApiServer;
use SquirrelForge\Integration\Http\InProcessHttpTransport;

final class EngineApiServerTest extends TestCase
{
    public function testFullFlockRoundTripReturnsValidatedResult(): void
    {
        $adapter = $this->adapter(new InMemoryEngineRuntime());

        $submitted = $adapter->handle($this->flockRequest('submit'));
        $inspected = $adapter->handle($this->flockRequest('inspect', [
            'execution_ref' => $submitted['execution_ref'],
        ]));
        $result = $adapter->handle($this->flockRequest('result', [
            'execution_ref' => $submitted['execution_ref'],
        ]));

        $this->assertSame('ACCEPTED', $submitted['status']);
        $this->assertSame('COMPLETE', $inspected['status']);
        $this->assertSame('COMPLETE', $result['status']);
        $this->assertSame('ACCEPTED', $result['validation_decision']);
        $this->assertSame('validation_exec_1', $result['validation_record_ref']);
        $this->assertSame(['result_set_exec_1'], $result['result_refs']);
        $this->assertSame(['report_exec_1'], $result['report_refs']);
    }

    public function testIdempotentSubmissionReturnsOneExecution(): void
    {
        $adapter = $this->adapter(new InMemoryEngineRuntime());
        $request = $this->flockRequest('submit');

        $first = $adapter->handle($request);
        $second = $adapter->handle($request);

        $this->assertSame('exec_1', $first['execution_ref']);
        $this->assertSame($first['execution_ref'], $second['execution_ref']);
    }

    public function testRejectedValidationPropagatesToFlock(): void
    {
        $runtime = new InMemoryEngineRuntime(static fn(array $request): string => 'REJECTED');
        $adapter = $this->adapter($runtime);
        $submitted = $adapter->handle($this->flockRequest('submit'));
        $result = $adapter->handle($this->flockRequest('result', [
            'execution_ref' => $submitted['execution_ref'],
        ]));

        $this->assertSame('REJECTED', $result['status']);
        $this->assertSame('REJECTED', $result['validation_decision']);
        $this->assertSame('Repair the rejected result before resubmission.', $result['next_action']);
    }

    public function testAuthorizationDenialFailsClosed(): void
    {
        $adapter = $this->adapter(new InMemoryEngineRuntime());
        $response = $adapter->handle($this->flockRequest('submit', [
            'permission_ref' => 'permission:denied',
        ]));

        $this->assertSame('REJECTED', $response['status']);
        $this->assertSame('UNAUTHORIZED', $response['error']['type']);
    }

    public function testExternalInputReturnsCorrelatedReceipt(): void
    {
        $adapter = $this->adapter(new InMemoryEngineRuntime());
        $submitted = $adapter->handle($this->flockRequest('submit'));
        $input = $adapter->handle($this->flockRequest('provide_input', [
            'execution_ref' => $submitted['execution_ref'],
            'payload' => ['answer' => 'Continue.'],
        ]));

        $this->assertSame('ACCEPTED', $input['status']);
        $this->assertSame(['input_receipt_exec_1_1'], $input['result_refs']);
    }

    public function testCancellationChangesTerminalResult(): void
    {
        $adapter = $this->adapter(new InMemoryEngineRuntime());
        $submitted = $adapter->handle($this->flockRequest('submit'));
        $cancelled = $adapter->handle($this->flockRequest('cancel', [
            'execution_ref' => $submitted['execution_ref'],
            'payload' => ['reason' => 'User cancelled.'],
        ]));
        $result = $adapter->handle($this->flockRequest('result', [
            'execution_ref' => $submitted['execution_ref'],
        ]));

        $this->assertSame('CANCELLED', $cancelled['status']);
        $this->assertSame('CANCELLED', $result['status']);
        $this->assertNull($result['validation_decision']);
    }

    public function testUnknownExecutionReturnsTypedError(): void
    {
        $adapter = $this->adapter(new InMemoryEngineRuntime());
        $response = $adapter->handle($this->flockRequest('result', [
            'execution_ref' => 'exec_unknown',
        ]));

        $this->assertSame('FAILED', $response['status']);
        $this->assertSame('UNKNOWN_EXECUTION', $response['error']['type']);
    }

    public function testMalformedServerRequestIsRejected(): void
    {
        $server = new EngineApiServer(new InMemoryEngineRuntime());
        $response = $server->handle(
            'POST',
            '/v1/engine/requests',
            [
                'X-SquirrelForge-Identity-Ref' => 'identity_1',
                'X-SquirrelForge-Permission-Ref' => 'permission_1',
                'X-Correlation-ID' => 'correlation_1',
                'Idempotency-Key' => 'idempotency_1',
            ],
            '{not-json'
        );
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(400, $response->status);
        $this->assertSame('INVALID_ENGINE_REQUEST', $decoded['error']['type']);
    }

    private function adapter(InMemoryEngineRuntime $runtime): FlockPluginAdapter
    {
        $baseUrl = 'https://engine.internal';
        $server = new EngineApiServer($runtime);
        $transport = new InProcessHttpTransport($baseUrl, $server);

        return new FlockPluginAdapter(new HttpEngineClient($baseUrl, $transport));
    }

    private function flockRequest(string $operation, array $overrides = []): array
    {
        return array_replace_recursive([
            'adapter_version' => '1.0.0',
            'operation' => $operation,
            'request_id' => 'flock_request_1',
            'conversation_id' => 'flock_conversation_1',
            'actor' => [
                'identity_ref' => 'identity_1',
                'tenant_ref' => 'tenant_1',
            ],
            'permission_ref' => 'permission:allowed',
            'correlation_id' => 'correlation_1',
            'trace_id' => 'trace_1',
            'idempotency_key' => 'idempotency_1',
            'project_ref' => 'project_1',
            'payload' => [
                'goal' => 'Run the deterministic Engine workflow.',
            ],
        ], $overrides);
    }
}
