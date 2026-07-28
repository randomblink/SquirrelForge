<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Support;

use SquirrelForge\Contracts\AuthenticationManagerInterface;

/**
 * A test double for AuthenticationManagerInterface. Authenticates any
 * request whose access token is in the configured allow-list.
 */
final class FakeAuthenticationManager implements AuthenticationManagerInterface
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $calls = [];

    /**
     * @param array<int, string> $validAccessTokens
     */
    public function __construct(private readonly array $validAccessTokens)
    {
    }

    public function authenticate(?string $accessToken, ?string $claimedIdentityRef, string $correlationId): array
    {
        $this->calls[] = [
            'access_token' => $accessToken,
            'claimed_identity_ref' => $claimedIdentityRef,
            'correlation_id' => $correlationId,
        ];

        $authenticated = $accessToken !== null && in_array($accessToken, $this->validAccessTokens, true);

        return [
            'authentication_ref' => 'authentication_fake_' . hash('sha256', $accessToken ?? ''),
            'authenticated' => $authenticated,
            'identity_ref' => $authenticated ? $claimedIdentityRef : null,
            'session_ref' => null,
            'correlation_id' => $correlationId,
            'rationale' => $authenticated ? 'Access token is on the allow-list.' : 'Access token is missing or not recognized.',
        ];
    }
}
