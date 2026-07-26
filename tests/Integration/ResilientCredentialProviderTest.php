<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Integration\Http\HttpTransportResponse;
use SquirrelForge\RuntimeConfig\ResilientHttpCredentialProvider;
use SquirrelForge\RuntimeConfig\ResilientHttpTransport;
use SquirrelForge\Tests\Support\FakeHttpTransport;

final class ResilientCredentialProviderTest extends TestCase
{
    public function testHealthProbeReportsAuthoritativeProviderState(): void
    {
        $inner = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse => new HttpTransportResponse(
                200,
                [],
                '{"healthy":true}'
            )
        );
        $provider = new ResilientHttpCredentialProvider(
            'https://credentials.example.test',
            str_repeat('t', 40),
            new ResilientHttpTransport($inner)
        );

        $health = $provider->health();

        $this->assertTrue($health['healthy']);
        $this->assertSame('credential-provider:http-json:resilient', $health['provider_ref']);
        $this->assertSame('GET', $inner->requests[0]['method']);
    }

    public function testProviderOutageFailsCredentialVerificationClosed(): void
    {
        $inner = new FakeHttpTransport(
            static fn(array $request): HttpTransportResponse => new HttpTransportResponse(503, [], '{}')
        );
        $provider = new ResilientHttpCredentialProvider(
            'https://credentials.example.test',
            str_repeat('t', 40),
            new ResilientHttpTransport($inner, 2, 1, 30)
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rejected the operation');
        $provider->verifyApiKey('identity_1', str_repeat('k', 32));
    }
}
