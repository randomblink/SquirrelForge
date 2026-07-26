<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use SquirrelForge\Contracts\MfaVerifierInterface;
use SquirrelForge\Contracts\ProviderReadinessInterface;

final class StaticMfaVerifier implements MfaVerifierInterface, ProviderReadinessInterface
{
    public function __construct(private readonly string $expectedProof)
    {
    }

    public function providerRef(): string
    {
        return 'mfa:static-test';
    }

    public function isProductionReady(): bool
    {
        return false;
    }

    public function verify(string $identityRef, ?string $proof, string $correlationId): array
    {
        $verified = is_string($proof) && hash_equals($this->expectedProof, $proof);

        return [
            'verified' => $verified,
            'verification_ref' => $verified ? 'mfa_verification_' . bin2hex(random_bytes(12)) : null,
            'rationale' => $verified ? 'The configured test MFA proof matched.' : 'MFA verification failed.',
        ];
    }
}
