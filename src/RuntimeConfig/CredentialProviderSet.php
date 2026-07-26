<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use SquirrelForge\Contracts\MfaVerifierInterface;
use SquirrelForge\Contracts\SecretsManagerInterface;
use SquirrelForge\Contracts\SecurityEventSinkInterface;

final class CredentialProviderSet
{
    public function __construct(
        public readonly SecretsManagerInterface $secrets,
        public readonly MfaVerifierInterface $mfa,
        public readonly SecurityEventSinkInterface $securityEvents
    ) {
    }
}
