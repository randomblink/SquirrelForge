<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface IdentityManagerInterface
{
    public function identity(string $identityRef): ?array;

    public function isActive(string $identityRef): bool;
}
