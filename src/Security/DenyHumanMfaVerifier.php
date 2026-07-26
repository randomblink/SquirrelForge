<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use SquirrelForge\Contracts\MfaVerifierInterface;
use SquirrelForge\Contracts\ProviderReadinessInterface;

final class DenyHumanMfaVerifier implements MfaVerifierInterface, ProviderReadinessInterface
{
    public function providerRef(): string
    {
        return 'mfa:deny-unconfigured';
    }

    public function isProductionReady(): bool
    {
        return false;
    }

    public function verify(string $identityRef, ?string $proof, string $correlationId): array
    {
        return [
            'verified' => false,
            'verification_ref' => null,
            'rationale' => 'No production MFA verifier is configured.',
        ];
    }
}
