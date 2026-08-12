#!/usr/bin/env php
<?php

declare(strict_types=1);

use SquirrelForge\CredentialProvider\MfaSecretStore;
use SquirrelForge\RuntimeConfig\SqliteSecretsManager;
use SquirrelForge\Security\SqliteEncryptionManager;
use SquirrelForge\Security\SqliteSecurityEventSink;

/**
 * The credential-provider container's own startup preflight -- the
 * counterpart to `bin/runtime-preflight.php`, which validates the
 * main Engine API's *client* configuration for calling out to a
 * provider. This script validates the provider *server*'s own
 * configuration before it accepts traffic: a real Bearer token, a
 * real 32-byte MFA master key, and a writable database path, then
 * boots each real component once so a broken database file or
 * directory permission problem fails the container at startup
 * rather than on a caller's first request.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $token = getenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN');

    if (!is_string($token) || trim($token) === '') {
        throw new RuntimeException('SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN is required.');
    }

    if (strlen($token) < 32) {
        throw new RuntimeException('SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN must be at least 32 characters.');
    }

    $masterKeyEncoded = getenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER_MFA_MASTER_KEY');
    $masterKey = is_string($masterKeyEncoded) ? base64_decode($masterKeyEncoded, true) : false;

    if (!is_string($masterKey) || strlen($masterKey) !== 32) {
        throw new RuntimeException(
            'SQUIRRELFORGE_CREDENTIAL_PROVIDER_MFA_MASTER_KEY must be a base64-encoded 32-byte key.'
        );
    }

    $databasePath = getenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER_DB');

    if (!is_string($databasePath) || trim($databasePath) === '') {
        throw new RuntimeException('SQUIRRELFORGE_CREDENTIAL_PROVIDER_DB is required.');
    }

    $directory = dirname($databasePath);

    if ((!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory))
        || !is_writable($directory)) {
        throw new RuntimeException('The credential provider database directory must be writable.');
    }

    // Boot every real component once: creates/migrates each one's own
    // schema and surfaces a broken database file immediately, the
    // same fail-fast intent as bin/runtime-preflight.php.
    new SqliteSecretsManager($databasePath);
    new SqliteSecurityEventSink($databasePath);
    $encryption = new SqliteEncryptionManager($databasePath);
    new MfaSecretStore($databasePath, $encryption, $masterKey);

    fwrite(STDOUT, "Credential provider preflight passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Credential provider preflight failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
