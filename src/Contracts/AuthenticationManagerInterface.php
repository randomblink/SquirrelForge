<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface AuthenticationManagerInterface
{
    /**
     * @return array{
     *   authentication_ref: string,
     *   authenticated: bool,
     *   identity_ref: ?string,
     *   session_ref: ?string,
     *   correlation_id: string,
     *   rationale: string
     * }
     */
    public function authenticate(
        ?string $accessToken,
        ?string $claimedIdentityRef,
        string $correlationId
    ): array;
}
