<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use SquirrelForge\Contracts\AuthenticationManagerInterface;

final class StaticHeaderAuthenticationManager implements AuthenticationManagerInterface
{
    public function authenticate(
        ?string $accessToken,
        ?string $claimedIdentityRef,
        string $correlationId
    ): array {
        $authenticated = is_string($claimedIdentityRef) && trim($claimedIdentityRef) !== '';

        return [
            'authentication_ref' => 'authentication_static_' . hash(
                'sha256',
                implode('|', [(string) $claimedIdentityRef, $correlationId])
            ),
            'authenticated' => $authenticated,
            'identity_ref' => $authenticated ? $claimedIdentityRef : null,
            'session_ref' => null,
            'correlation_id' => $correlationId,
            'rationale' => $authenticated
                ? 'The identity header is accepted only by the deterministic test authenticator.'
                : 'An identity reference is required.',
        ];
    }
}
