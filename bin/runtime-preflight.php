#!/usr/bin/env php
<?php

declare(strict_types=1);

use SquirrelForge\Contracts\ProviderHealthInterface;
use SquirrelForge\Engine\SqliteEngineRuntime;
use SquirrelForge\Integration\Http\NativeHttpTransport;
use SquirrelForge\RuntimeConfig\CredentialProviderLoader;
use SquirrelForge\RuntimeConfig\ProviderStartupValidator;
use SquirrelForge\RuntimeConfig\ResilientHttpTransport;
use SquirrelForge\RuntimeConfig\RuntimeEnvironmentPolicy;
use SquirrelForge\RuntimeConfig\SqliteProviderTelemetry;
use SquirrelForge\Security\SqliteAuthenticationManager;
use SquirrelForge\Security\SqliteAuthorizationManager;
use SquirrelForge\Security\SqliteIdentityManager;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $databasePath = getenv('SQUIRRELFORGE_ENGINE_DB');

    if (!is_string($databasePath) || trim($databasePath) === '') {
        throw new RuntimeException('SQUIRRELFORGE_ENGINE_DB is required.');
    }

    $directory = dirname($databasePath);

    if ((!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory))
        || !is_writable($directory)) {
        throw new RuntimeException('The Engine database directory must be writable.');
    }

    $environment = RuntimeEnvironmentPolicy::fromEnvironment();
    $telemetry = new SqliteProviderTelemetry($databasePath);
    $transport = new ResilientHttpTransport(
        new NativeHttpTransport(),
        max(1, (int) (getenv('SQUIRRELFORGE_PROVIDER_MAX_ATTEMPTS') ?: 3)),
        max(1, (int) (getenv('SQUIRRELFORGE_PROVIDER_CIRCUIT_FAILURES') ?: 3)),
        max(1, (int) (getenv('SQUIRRELFORGE_PROVIDER_CIRCUIT_COOLDOWN_SECONDS') ?: 30)),
        $telemetry
    );
    $providers = (new CredentialProviderLoader($environment, $transport))->load($databasePath);
    (new ProviderStartupValidator($environment))->validate(
        $providers->secrets,
        $providers->mfa,
        $providers->securityEvents
    );

    if ($providers->secrets instanceof ProviderHealthInterface
        && ($providers->secrets->health()['healthy'] ?? false) !== true) {
        throw new RuntimeException('The configured credential provider is not healthy.');
    }

    $identities = new SqliteIdentityManager($databasePath);
    new SqliteAuthenticationManager($databasePath, $identities, $providers->securityEvents);
    new SqliteAuthorizationManager($databasePath);
    new SqliteEngineRuntime($databasePath);

    fwrite(STDOUT, "SquirrelForge runtime preflight passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'SquirrelForge runtime preflight failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
