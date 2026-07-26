<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface AuthorizationManagerInterface
{
    /**
     * @return array{
     *   authorization_ref: string,
     *   decision: string,
     *   authorized: bool,
     *   identity_ref: string,
     *   permission_ref: string,
     *   operation: string,
     *   resource_ref: string,
     *   correlation_id: string,
     *   rationale: string,
     *   restrictions: array
     * }
     */
    public function authorize(
        string $identityRef,
        string $permissionRef,
        string $operation,
        string $resourceRef,
        string $correlationId
    ): array;
}
