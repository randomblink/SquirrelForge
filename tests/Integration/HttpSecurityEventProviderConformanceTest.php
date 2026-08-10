<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use SquirrelForge\Contracts\SecurityEventSinkInterface;
use SquirrelForge\RuntimeConfig\HttpCredentialProvider;
use SquirrelForge\Tests\Contract\SecurityEventSinkContractTestCase;
use SquirrelForge\Tests\Support\FakeCredentialProviderServer;
use SquirrelForge\Tests\Support\FakeHttpTransport;

/**
 * Runs the same contract test the local `SqliteSecurityEventSink` is
 * verified against (`LocalSecurityEventProviderConformanceTest`)
 * against `HttpCredentialProvider`, proving the HTTP client correctly
 * returns an evidence reference and preserves the correlation ID
 * against a real compliant server.
 */
final class HttpSecurityEventProviderConformanceTest extends SecurityEventSinkContractTestCase
{
    private FakeCredentialProviderServer $server;

    protected function setUp(): void
    {
        $this->server = new FakeCredentialProviderServer('unused-for-this-contract');
    }

    protected function securityEventSink(): SecurityEventSinkInterface
    {
        return new HttpCredentialProvider(
            'https://credentials.example.test',
            str_repeat('t', 32),
            new FakeHttpTransport(fn(array $request) => $this->server->handle($request))
        );
    }

    protected function recordedEvents(string $identityRef): array
    {
        return array_values(array_filter(
            $this->server->securityEvents,
            static fn(array $event): bool => $event['identity_ref'] === $identityRef
        ));
    }
}
