<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use SquirrelForge\Contracts\SecretsManagerInterface;
use SquirrelForge\RuntimeConfig\HttpCredentialProvider;
use SquirrelForge\Tests\Contract\SecretsManagerContractTestCase;
use SquirrelForge\Tests\Support\FakeCredentialProviderServer;
use SquirrelForge\Tests\Support\FakeHttpTransport;

/**
 * Runs the same contract test the local `SqliteSecretsManager` is
 * verified against (`LocalProviderConformanceTest`) against
 * `HttpCredentialProvider`, proving the HTTP client genuinely
 * preserves rotate/revoke security properties against a real
 * compliant server -- not just that it sends well-formed requests.
 */
final class HttpSecretsProviderConformanceTest extends SecretsManagerContractTestCase
{
    protected function secretsManager(): SecretsManagerInterface
    {
        $server = new FakeCredentialProviderServer('unused-for-this-contract');

        return new HttpCredentialProvider(
            'https://credentials.example.test',
            str_repeat('t', 32),
            new FakeHttpTransport(fn(array $request) => $server->handle($request))
        );
    }
}
