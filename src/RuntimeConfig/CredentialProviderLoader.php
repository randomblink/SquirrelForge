<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use RuntimeException;
use SquirrelForge\Contracts\HttpTransportInterface;
use SquirrelForge\Security\DenyHumanMfaVerifier;
use SquirrelForge\Security\SqliteSecurityEventSink;

final class CredentialProviderLoader
{
    public function __construct(
        private readonly RuntimeEnvironmentPolicy $environment,
        private readonly HttpTransportInterface $transport
    ) {
    }

    public function load(string $databasePath): CredentialProviderSet
    {
        $configured = getenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER');
        $provider = is_string($configured) && trim($configured) !== ''
            ? strtolower(trim($configured))
            : ($this->environment->allowsLocalProviders() ? 'local' : '');

        if ($provider === 'local') {
            if (!$this->environment->allowsLocalProviders()) {
                throw new RuntimeException('The local credential provider is forbidden in production.');
            }

            return new CredentialProviderSet(
                new SqliteSecretsManager($databasePath),
                new DenyHumanMfaVerifier(),
                new SqliteSecurityEventSink($databasePath)
            );
        }

        if ($provider === 'http-json') {
            $url = getenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER_URL');
            $token = getenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN');

            if (!is_string($url) || !is_string($token)) {
                throw new RuntimeException('HTTP credential provider URL and token are required.');
            }

            $adapter = new ResilientHttpCredentialProvider($url, $token, $this->transport);

            return new CredentialProviderSet($adapter, $adapter, $adapter);
        }

        throw new RuntimeException('A recognized credential provider must be configured.');
    }
}
