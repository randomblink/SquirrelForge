<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use SquirrelForge\Contracts\MfaVerifierInterface;
use SquirrelForge\Security\StaticMfaVerifier;
use SquirrelForge\Tests\Contract\MfaVerifierContractTestCase;

final class LocalMfaProviderConformanceTest extends MfaVerifierContractTestCase
{
    protected function mfaVerifier(): MfaVerifierInterface
    {
        return new StaticMfaVerifier($this->validProof());
    }

    protected function validProof(): string
    {
        return 'contract-proof-123456';
    }
}
