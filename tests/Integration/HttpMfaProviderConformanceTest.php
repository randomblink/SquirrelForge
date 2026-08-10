<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use SquirrelForge\Contracts\MfaVerifierInterface;
use SquirrelForge\RuntimeConfig\HttpCredentialProvider;
use SquirrelForge\Tests\Contract\MfaVerifierContractTestCase;
use SquirrelForge\Tests\Support\FakeCredentialProviderServer;
use SquirrelForge\Tests\Support\FakeHttpTransport;

/**
 * Runs the same contract test the local `StaticMfaVerifier` is
 * verified against (`LocalMfaProviderConformanceTest`) against
 * `HttpCredentialProvider`, proving the HTTP client never echoes the
 * proof back to the caller and correctly surfaces typed accept/deny
 * decisions from a real compliant server.
 */
final class HttpMfaProviderConformanceTest extends MfaVerifierContractTestCase
{
    protected function validProof(): string
    {
        return 'contract-proof-123456';
    }

    protected function mfaVerifier(): MfaVerifierInterface
    {
        $server = new FakeCredentialProviderServer($this->validProof());

        return new HttpCredentialProvider(
            'https://credentials.example.test',
            str_repeat('t', 32),
            new FakeHttpTransport(fn(array $request) => $server->handle($request))
        );
    }
}
