<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use RuntimeException;
use SquirrelForge\Contracts\MfaVerifierInterface;
use SquirrelForge\Contracts\ProviderReadinessInterface;
use SquirrelForge\Contracts\SecretsManagerInterface;
use SquirrelForge\Contracts\SecurityEventSinkInterface;

final class ProviderStartupValidator
{
    public function __construct(private readonly RuntimeEnvironmentPolicy $environment)
    {
    }

    public function validate(
        SecretsManagerInterface $secrets,
        MfaVerifierInterface $mfa,
        SecurityEventSinkInterface $securityEvents
    ): void {
        if ($this->environment->allowsLocalProviders()) {
            return;
        }

        $providers = [
            'Secrets Manager' => $secrets,
            'MFA verifier' => $mfa,
            'security-event sink' => $securityEvents,
        ];
        $unready = [];

        foreach ($providers as $role => $provider) {
            if (!$provider instanceof ProviderReadinessInterface || !$provider->isProductionReady()) {
                $reference = $provider instanceof ProviderReadinessInterface
                    ? $provider->providerRef()
                    : get_debug_type($provider);
                $unready[] = sprintf('%s (%s)', $role, $reference);
            }
        }

        if ($unready !== []) {
            throw new RuntimeException(
                'Production startup refused non-production credential providers: ' . implode(', ', $unready) . '.'
            );
        }
    }
}
