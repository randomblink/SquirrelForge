<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Integration\Http\HttpTransportResponse;
use SquirrelForge\RuntimeConfig\ResilientHttpTransport;
use SquirrelForge\Tests\Support\FakeHttpTransport;

final class ResilientHttpTransportTest extends TestCase
{
    public function testTransientFailureRetriesWithStableIdempotencyKey(): void
    {
        $attempt = 0;
        $inner = new FakeHttpTransport(
            static function (array $request) use (&$attempt): HttpTransportResponse {
                $attempt++;

                return new HttpTransportResponse(
                    $attempt < 3 ? 503 : 201,
                    [],
                    $attempt < 3 ? '{}' : '{"secret_ref":"secret_1"}'
                );
            }
        );
        $transport = new ResilientHttpTransport($inner, 3, 3, 30);

        $response = $transport->request(
            'POST',
            'https://credentials.example.test/v1/provider/secrets/rotate',
            [],
            '{"secret_ref":"secret_1"}',
            1.0
        );

        $this->assertSame(201, $response->status);
        $this->assertCount(3, $inner->requests);
        $keys = array_column(array_column($inner->requests, 'headers'), 'Idempotency-Key');
        $this->assertCount(1, array_unique($keys));
        $this->assertStringStartsWith('provider_operation_', $keys[0]);
    }

    public function testExhaustedFailuresOpenCircuitAndSuppressFurtherTransportCalls(): void
    {
        $inner = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse => new HttpTransportResponse(503, [], '{}')
        );
        $transport = new ResilientHttpTransport($inner, 1, 2, 30);
        $transport->request('POST', 'https://provider.test/v1/provider/secrets/verify', [], '{}', 1.0);
        $transport->request('POST', 'https://provider.test/v1/provider/secrets/verify', [], '{}', 1.0);

        try {
            $transport->request('POST', 'https://provider.test/v1/provider/secrets/verify', [], '{}', 1.0);
            self::fail('The open circuit should reject the request.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('circuit is open', $exception->getMessage());
        }

        $this->assertCount(2, $inner->requests);
    }

    public function testClientErrorsAreNotRetriedOrCountedAsProviderOutages(): void
    {
        $inner = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse => new HttpTransportResponse(401, [], '{}')
        );
        $transport = new ResilientHttpTransport($inner, 3, 1, 30);

        $first = $transport->request('POST', 'https://provider.test/v1/provider/mfa/verify', [], '{}', 1.0);
        $second = $transport->request('POST', 'https://provider.test/v1/provider/mfa/verify', [], '{}', 1.0);

        $this->assertSame(401, $first->status);
        $this->assertSame(401, $second->status);
        $this->assertCount(2, $inner->requests);
    }
}
