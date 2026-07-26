<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Integration\Flock\EngineTransportException;
use SquirrelForge\Integration\Flock\HttpEngineClient;
use SquirrelForge\Integration\Http\HttpTransportResponse;
use SquirrelForge\Tests\Support\FakeHttpTransport;

final class HttpEngineClientTest extends TestCase
{
    public function testSubmitMapsEnvelopeHeadersAndBody(): void
    {
        $transport = new FakeHttpTransport(
            fn(array $request): HttpTransportResponse => $this->jsonResponse(
                202,
                ['execution_ref' => 'exec_1']
            )
        );
        $client = new HttpEngineClient(
            'https://engine.example.test/',
            $transport,
            12.5,
            'access_token_12345678901234567890'
        );

        $result = $client->submit(
            ['goal' => 'Run workflow.'],
            'project_1',
            'identity_1',
            'permission_1',
            'idempotency_1',
            'correlation_1'
        );

        $wire = $transport->requests[0];
        $this->assertSame('exec_1', $result['execution_ref']);
        $this->assertSame('POST', $wire['method']);
        $this->assertSame('https://engine.example.test/v1/engine/requests', $wire['url']);
        $this->assertSame('identity_1', $wire['headers']['X-SquirrelForge-Identity-Ref']);
        $this->assertSame('permission_1', $wire['headers']['X-SquirrelForge-Permission-Ref']);
        $this->assertSame('correlation_1', $wire['headers']['X-Correlation-ID']);
        $this->assertSame('idempotency_1', $wire['headers']['Idempotency-Key']);
        $this->assertSame(
            'Bearer access_token_12345678901234567890',
            $wire['headers']['Authorization']
        );
        $this->assertSame(12.5, $wire['timeout']);
        $this->assertSame([
            'request' => ['goal' => 'Run workflow.'],
            'project_ref' => 'project_1',
        ], json_decode((string) $wire['body'], true, 512, JSON_THROW_ON_ERROR));
    }

    public function testReadOperationsEncodeExecutionReference(): void
    {
        $transport = new FakeHttpTransport(
            fn(array $request): HttpTransportResponse => $this->jsonResponse(200, ['status' => 'RUNNING'])
        );
        $client = new HttpEngineClient('https://engine.example.test', $transport);

        $client->inspect('exec/one', 'identity_1', 'permission_1', 'correlation_1');
        $client->result('exec/one', 'identity_1', 'permission_1', 'correlation_1');

        $this->assertSame(
            'https://engine.example.test/v1/engine/executions/exec%2Fone',
            $transport->requests[0]['url']
        );
        $this->assertSame(
            'https://engine.example.test/v1/engine/executions/exec%2Fone/result',
            $transport->requests[1]['url']
        );
    }

    public function testInputAndCancellationUseExpectedOperations(): void
    {
        $transport = new FakeHttpTransport(
            fn(array $request): HttpTransportResponse => $this->jsonResponse(202, ['receipt_ref' => 'receipt_1'])
        );
        $client = new HttpEngineClient('https://engine.example.test', $transport);

        $client->provideInput('exec_1', ['answer' => 'yes'], 'identity_1', 'permission_1', 'correlation_1');
        $client->cancel('exec_1', 'User request.', 'identity_1', 'permission_1', 'correlation_1');

        $this->assertSame(
            'https://engine.example.test/v1/engine/executions/exec_1/input',
            $transport->requests[0]['url']
        );
        $this->assertSame(
            ['input' => ['answer' => 'yes']],
            json_decode((string) $transport->requests[0]['body'], true, 512, JSON_THROW_ON_ERROR)
        );
        $this->assertSame(
            'https://engine.example.test/v1/engine/executions/exec_1/cancellation',
            $transport->requests[1]['url']
        );
    }

    public function testTypedEngineErrorIsPreserved(): void
    {
        $transport = new FakeHttpTransport(
            fn(array $request): HttpTransportResponse => $this->jsonResponse(403, [
                'error' => [
                    'type' => 'UNAUTHORIZED',
                    'message' => 'Permission denied.',
                ],
            ])
        );
        $client = new HttpEngineClient('https://engine.example.test', $transport);

        $result = $client->inspect('exec_1', 'identity_1', 'permission_1', 'correlation_1');

        $this->assertSame('UNAUTHORIZED', $result['error']['type']);
        $this->assertSame(403, $result['error']['http_status']);
        $this->assertFalse($result['error']['retryable']);
    }

    public function testRateLimitAddsRetryMetadata(): void
    {
        $transport = new FakeHttpTransport(
            fn(array $request): HttpTransportResponse => $this->jsonResponse(
                429,
                ['message' => 'Slow down.'],
                ['retry-after' => '10']
            )
        );
        $client = new HttpEngineClient('https://engine.example.test', $transport);

        $result = $client->inspect('exec_1', 'identity_1', 'permission_1', 'correlation_1');

        $this->assertSame('RATE_LIMITED', $result['error']['type']);
        $this->assertTrue($result['error']['retryable']);
        $this->assertSame('10', $result['error']['retry_after']);
    }

    public function testInvalidJsonFailsClosed(): void
    {
        $transport = new FakeHttpTransport(
            fn(array $request): HttpTransportResponse => new HttpTransportResponse(200, [], 'not-json')
        );
        $client = new HttpEngineClient('https://engine.example.test', $transport);

        $this->expectException(EngineTransportException::class);
        $this->expectExceptionMessage('not valid JSON');

        $client->result('exec_1', 'identity_1', 'permission_1', 'correlation_1');
    }

    public function testInvalidConfigurationIsRejected(): void
    {
        $transport = new FakeHttpTransport(
            fn(array $request): HttpTransportResponse => $this->jsonResponse(200, [])
        );

        $this->expectException(EngineTransportException::class);

        new HttpEngineClient('not-a-url', $transport);
    }

    /**
     * @param array<string, string> $headers
     */
    private function jsonResponse(int $status, array $body, array $headers = []): HttpTransportResponse
    {
        return new HttpTransportResponse(
            $status,
            $headers,
            json_encode($body, JSON_THROW_ON_ERROR)
        );
    }
}
