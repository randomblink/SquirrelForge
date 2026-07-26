<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Contract;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\MfaVerifierInterface;

abstract class MfaVerifierContractTestCase extends TestCase
{
    abstract protected function mfaVerifier(): MfaVerifierInterface;

    abstract protected function validProof(): string;

    public function testProviderReturnsTypedDecisionsWithoutEchoingProof(): void
    {
        $provider = $this->mfaVerifier();
        $proof = $this->validProof();
        $accepted = $provider->verify('identity_contract', $proof, 'correlation_contract_1');
        $denied = $provider->verify('identity_contract', 'invalid_' . $proof, 'correlation_contract_2');

        self::assertTrue($accepted['verified']);
        self::assertIsString($accepted['verification_ref']);
        self::assertFalse($denied['verified']);
        self::assertNull($denied['verification_ref']);
        self::assertStringNotContainsString($proof, json_encode([$accepted, $denied], JSON_THROW_ON_ERROR));
    }
}
