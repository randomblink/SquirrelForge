<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use SquirrelForge\Contracts\AuthorizationManagerInterface;

final class StaticAuthorizationManager implements AuthorizationManagerInterface
{
    public function __construct(private readonly string $allowedPermissionRef = 'permission:allowed')
    {
    }

    public function authorize(
        string $identityRef,
        string $permissionRef,
        string $operation,
        string $resourceRef,
        string $correlationId
    ): array {
        $authorized = $identityRef !== '' && hash_equals($this->allowedPermissionRef, $permissionRef);

        return [
            'authorization_ref' => 'authorization_static_' . hash(
                'sha256',
                implode('|', [$identityRef, $permissionRef, $operation, $resourceRef, $correlationId])
            ),
            'decision' => $authorized ? 'AUTHORIZED' : 'DENIED',
            'authorized' => $authorized,
            'identity_ref' => $identityRef,
            'permission_ref' => $permissionRef,
            'operation' => $operation,
            'resource_ref' => $resourceRef,
            'correlation_id' => $correlationId,
            'rationale' => $authorized
                ? 'The reference permission is authorized for contract testing.'
                : 'The reference permission is not authorized.',
            'restrictions' => [],
        ];
    }
}
