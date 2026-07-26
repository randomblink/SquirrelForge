<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Integration\Http\HttpTransportResponse;
use SquirrelForge\Integration\Http\ProviderReadinessApiServer;
use SquirrelForge\RuntimeConfig\ResilientHttpCredentialProvider;
use SquirrelForge\RuntimeConfig\ResilientHttpTransport;
use SquirrelForge\RuntimeConfig\SqliteProviderTelemetry;
use SquirrelForge\Tests\Support\FakeHttpTransport;

final class ProviderOperationalReadinessTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-provider-operations-'
            . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testMockGatewayRoundTripPublishesRedactedReadinessAndRetryCounters(): void
    {
        $verifyAttempt = 0;
        $gateway = new FakeHttpTransport(
            static function (array $request) use (&$verifyAttempt): HttpTransportResponse {
                $path = parse_url($request['url'], PHP_URL_PATH);

                if ($path === '/v1/provider/health') {
                    return new HttpTransportResponse(200, [], '{"healthy":true}');
                }

                if ($path === '/v1/provider/secrets/verify') {
                    $verifyAttempt++;

                    return $verifyAttempt === 1
                        ? new HttpTransportResponse(503, [], '{}')
                        : new HttpTransportResponse(200, [], json_encode([
                            'verified' => true,
                            'secret_ref' => 'secret_1',
                            'verification_ref' => 'verification_1',
                            'rationale' => 'verified',
                        ], JSON_THROW_ON_ERROR));
                }

                return new HttpTransportResponse(404, [], '{}');
            }
        );
        $telemetry = new SqliteProviderTelemetry($this->databasePath);
        $transport = new ResilientHttpTransport($gateway, 3, 3, 30, $telemetry);
        $providerToken = str_repeat('provider-token-', 4);
        $provider = new ResilientHttpCredentialProvider(
            'https://credentials.example.test',
            $providerToken,
            $transport
        );
        $apiKey = 'api_key_' . bin2hex(random_bytes(24));

        $verification = $provider->verifyApiKey('identity_1', $apiKey);
        $response = (new ProviderReadinessApiServer($provider, $telemetry))->handle(
            'GET',
            '/v1/health/providers'
        );
        $body = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($verification['verified']);
        $this->assertSame(200, $response->status);
        $this->assertTrue($body['ready']);
        $this->assertSame('CLOSED', $body['circuit_state']);
        $this->assertSame(1, $body['counters']['provider.retry']);
        $this->assertGreaterThanOrEqual(3, $body['counters']['provider.attempt']);
        $this->assertStringNotContainsString($apiKey, $response->body);
        $this->assertStringNotContainsString($providerToken, $response->body);
        $this->assertSame('no-store', $response->headers['cache-control']);
    }

    public function testUnhealthyProviderReturnsNotReadyWithoutRemoteBody(): void
    {
        $gateway = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse =>
                new HttpTransportResponse(503, [], '{"internal":"sensitive-provider-detail"}')
        );
        $telemetry = new SqliteProviderTelemetry($this->databasePath);
        $provider = new ResilientHttpCredentialProvider(
            'https://credentials.example.test',
            str_repeat('t', 40),
            new ResilientHttpTransport($gateway, 1, 1, 30, $telemetry)
        );
        $response = (new ProviderReadinessApiServer($provider, $telemetry))->handle(
            'GET',
            '/v1/health/providers'
        );

        $this->assertSame(503, $response->status);
        $this->assertStringNotContainsString('sensitive-provider-detail', $response->body);
        $this->assertFalse(json_decode($response->body, true, 512, JSON_THROW_ON_ERROR)['ready']);
    }
}
