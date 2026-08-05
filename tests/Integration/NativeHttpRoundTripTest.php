<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Integration\Flock\FlockPluginAdapter;
use SquirrelForge\Integration\Flock\HttpAuthenticationClient;
use SquirrelForge\Integration\Flock\HttpEngineClient;
use SquirrelForge\Integration\Http\NativeHttpTransport;

final class NativeHttpRoundTripTest extends TestCase
{
    private mixed $process = null;

    /**
     * @var array<int, resource>
     */
    private array $pipes = [];

    private ?string $databasePath = null;

    protected function tearDown(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }

        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if ($this->databasePath !== null) {
            foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function testNativeHttpTransportCompletesPersistentRoundTrip(): void
    {
        if (!function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is required for the loopback HTTP integration test.');
        }

        $port = $this->availablePort();
        $baseUrl = 'http://127.0.0.1:' . $port;
        $apiKey = 'test_api_key_' . bin2hex(random_bytes(24));
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-http-' . bin2hex(random_bytes(8)) . '.sqlite';
        $this->startServer($port, $apiKey);
        $this->waitUntilReady($port);
        $session = (new HttpAuthenticationClient($baseUrl, new NativeHttpTransport()))->createSession(
            'identity_1',
            $apiKey,
            'authentication_correlation_1',
            'source:loopback_test'
        );

        $adapter = new FlockPluginAdapter(
            new HttpEngineClient($baseUrl, new NativeHttpTransport(), 5.0, $session['access_token'])
        );
        $request = $this->flockRequest('submit');
        $submitted = $adapter->handle($request);
        $duplicate = $adapter->handle($request);
        $result = $adapter->handle($this->flockRequest('result', [
            'execution_ref' => $submitted['execution_ref'],
        ]));

        $this->assertSame('ACCEPTED', $submitted['status']);
        $this->assertSame($submitted['execution_ref'], $duplicate['execution_ref']);
        $this->assertSame('COMPLETE', $result['status']);
        $this->assertSame('ACCEPTED', $result['validation_decision']);
        $this->assertFileExists($this->databasePath);

        // Confirms /v1/memory/* really dispatches to the real Memory API
        // server (401 "missing identity header" from MemoryApiServer's own
        // requiredHeaders() check) rather than falling through to
        // EngineApiServer's default 404 "unknown route".
        $memoryResponse = (new NativeHttpTransport())->request('POST', $baseUrl . '/v1/memory/records', ['Content-Type' => 'application/json'], '{}', 5.0);
        $this->assertSame(401, $memoryResponse->status);
        $this->assertStringContainsString('UNAUTHORIZED', $memoryResponse->body);

        // Same confirmation for /v1/agents/* (Agent API Server) and
        // /v1/workflows/* (Workflow API Server): a real 401 from their own
        // requiredHeaders() check, not a 404 falling through to
        // EngineApiServer's default route.
        $agentResponse = (new NativeHttpTransport())->request('POST', $baseUrl . '/v1/agents/assignments', ['Content-Type' => 'application/json'], '{}', 5.0);
        $this->assertSame(401, $agentResponse->status);

        $workflowResponse = (new NativeHttpTransport())->request('POST', $baseUrl . '/v1/workflows/selection', ['Content-Type' => 'application/json'], '{}', 5.0);
        $this->assertSame(401, $workflowResponse->status);
    }

    private function availablePort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($socket === false) {
            $this->markTestSkipped('Loopback port allocation is unavailable: ' . $errorMessage);
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr((string) $name, strrpos((string) $name, ':') + 1);
    }

    private function startServer(int $port, string $apiKey): void
    {
        $root = dirname(__DIR__, 2);
        $command = [
            PHP_BINARY,
            '-S',
            '127.0.0.1:' . $port,
            $root . '/public/engine-api.php',
        ];
        $environment = array_merge($_ENV, [
            'SQUIRRELFORGE_ENVIRONMENT' => 'test',
            'SQUIRRELFORGE_ENGINE_DB' => $this->databasePath,
            'SQUIRRELFORGE_BOOTSTRAP_IDENTITY_REF' => 'identity_1',
            'SQUIRRELFORGE_BOOTSTRAP_PERMISSION_REF' => 'permission:allowed',
            'SQUIRRELFORGE_BOOTSTRAP_API_KEY' => $apiKey,
        ]);
        $this->process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $this->pipes,
            $root,
            $environment
        );

        if (!is_resource($this->process)) {
            self::fail('Could not start the loopback Engine API server.');
        }
    }

    private function waitUntilReady(int $port): void
    {
        $lastError = '';

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $socket = @stream_socket_client(
                'tcp://127.0.0.1:' . $port,
                $errorCode,
                $errorMessage,
                0.1
            );

            if (is_resource($socket)) {
                fclose($socket);

                return;
            }

            $lastError = sprintf('Connection error %d: %s. ', $errorCode, $errorMessage);

            if (isset($this->pipes[2]) && is_resource($this->pipes[2])) {
                stream_set_blocking($this->pipes[2], false);
                $lastError .= (string) stream_get_contents($this->pipes[2]);
            }

            usleep(20_000);
        }

        self::fail('Loopback Engine API server did not become ready. ' . trim($lastError));
    }

    private function flockRequest(string $operation, array $overrides = []): array
    {
        return array_replace_recursive([
            'adapter_version' => '1.0.0',
            'operation' => $operation,
            'request_id' => 'flock_network_request_1',
            'actor' => ['identity_ref' => 'identity_1', 'tenant_ref' => 'tenant_1'],
            'permission_ref' => 'permission:allowed',
            'correlation_id' => 'correlation_1',
            'trace_id' => 'trace_1',
            'idempotency_key' => 'network_idempotency_1',
            'project_ref' => 'project_1',
            'payload' => ['goal' => 'Run the persistent network workflow.'],
        ], $overrides);
    }
}
