<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

final class RuntimeEnvironmentPolicy
{
    public function __construct(private readonly string $environment = 'production')
    {
    }

    public static function fromEnvironment(): self
    {
        $environment = getenv('SQUIRRELFORGE_ENVIRONMENT');

        return new self(is_string($environment) && trim($environment) !== '' ? $environment : 'production');
    }

    public function allowsBootstrapProvisioning(): bool
    {
        return $this->allowsLocalProviders();
    }

    public function allowsLocalProviders(): bool
    {
        return in_array(strtolower(trim($this->environment)), ['local', 'test'], true);
    }
}
