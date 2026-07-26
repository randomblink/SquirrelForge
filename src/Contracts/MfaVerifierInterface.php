<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface MfaVerifierInterface
{
    public function verify(string $identityRef, ?string $proof, string $correlationId): array;
}
