<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Integration\Flock\FlockPluginAdapter;
use SquirrelForge\Tests\Support\FakeEngineClient;

final class FlockPluginAdapterTest extends TestCase
{
    public function testAuthorizedRequestCompletesEndToEndWithValidatedReferences(): void
    {
        $engine = new FakeEngineClient();
        $adapter = new FlockPluginAdapter($engine);

        $submitted = $adapter->handle($this->request('submit'));
        $result = $adapter->handle($this->request('result', [
            'execution_ref' => $submitted['execution_ref'],
        ]));

        $this->assertSame('ACCEPTED', $submitted['status']);
        $this->assertSame('COMPLETE', $result['status']);
        $this->assertSame('ACCEPTED', $result['validation_decision']);
        $this->assertSame('validation_exec_1', $result['validation_record_ref']);
        $this->assertSame(['result_set_exec_1'], $result['result_refs']);
        $this->assertSame(['report_exec_1'], $result['report_refs']);
        $this->assertSame('correlation_1', $result['correlation_id']);
    }

    public function testLimitedAcceptancePreservesLimitations(): void
    {
        $adapter = new FlockPluginAdapter(new FakeEngineClient('ACCEPTED_WITH_LIMITATIONS'));
        $submitted = $adapter->handle($this->request('submit'));
        $result = $adapter->handle($this->request('result', [
            'execution_ref' => $submitted['execution_ref'],
        ]));

        $this->assertSame('COMPLETE_WITH_LIMITATIONS', $result['status']);
        $this->assertSame('ACCEPTED_WITH_LIMITATIONS', $result['validation_decision']);
        $this->assertNotEmpty($result['limitations']);
    }

    public function testRejectedValidationIsNotReportedAsComplete(): void
    {
        $adapter = new FlockPluginAdapter(new FakeEngineClient('REJECTED'));
        $submitted = $adapter->handle($this->request('submit'));
        $result = $adapter->handle($this->request('result', [
            'execution_ref' => $submitted['execution_ref'],
        ]));

        $this->assertSame('REJECTED', $result['status']);
        $this->assertSame('REJECTED', $result['validation_decision']);
    }

    public function testDeniedPermissionFailsClosed(): void
    {
        $adapter = new FlockPluginAdapter(new FakeEngineClient());
        $response = $adapter->handle($this->request('submit', [
            'permission_ref' => 'permission:denied',
        ]));

        $this->assertSame('REJECTED', $response['status']);
        $this->assertSame('UNAUTHORIZED', $response['error']['type']);
        $this->assertFalse($response['error']['retryable']);
    }

    public function testDuplicateSubmissionReturnsOriginalExecutionReference(): void
    {
        $engine = new FakeEngineClient();
        $adapter = new FlockPluginAdapter($engine);
        $request = $this->request('submit');

        $first = $adapter->handle($request);
        $second = $adapter->handle($request);

        $this->assertSame($first['execution_ref'], $second['execution_ref']);
        $this->assertSame(2, $engine->submitCalls);
    }

    public function testCancellationDistinguishesConfirmedCancellation(): void
    {
        $adapter = new FlockPluginAdapter(new FakeEngineClient());
        $submitted = $adapter->handle($this->request('submit'));
        $cancelled = $adapter->handle($this->request('cancel', [
            'execution_ref' => $submitted['execution_ref'],
            'payload' => ['reason' => 'User requested cancellation.'],
        ]));
        $result = $adapter->handle($this->request('result', [
            'execution_ref' => $submitted['execution_ref'],
        ]));

        $this->assertSame('CANCELLED', $cancelled['status']);
        $this->assertSame('CANCELLED', $result['status']);
    }

    public function testTransportFailureIsNormalizedWithoutLeakingException(): void
    {
        $engine = new FakeEngineClient();
        $engine->transportUnavailable = true;
        $adapter = new FlockPluginAdapter($engine);

        $response = $adapter->handle($this->request('submit'));

        $this->assertSame('FAILED', $response['status']);
        $this->assertSame('TRANSPORT_UNAVAILABLE', $response['error']['type']);
        $this->assertTrue($response['error']['retryable']);
        $this->assertStringNotContainsString('Transport unavailable', $response['message']);
    }

    public function testInvalidEnvelopeIsRejectedBeforeEngineCall(): void
    {
        $engine = new FakeEngineClient();
        $adapter = new FlockPluginAdapter($engine);
        $request = $this->request('submit');
        unset($request['permission_ref']);

        $response = $adapter->handle($request);

        $this->assertSame('INVALID_FLOCK_REQUEST', $response['error']['type']);
        $this->assertSame(0, $engine->submitCalls);
    }

    private function request(string $operation, array $overrides = []): array
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
                'goal' => 'Run the minimal Flock workflow.',
            ],
        ], $overrides);
    }
}
