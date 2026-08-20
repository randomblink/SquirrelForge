#!/usr/bin/env php
<?php

declare(strict_types=1);

use SquirrelForge\CredentialProvider\MfaSecretStore;
use SquirrelForge\Security\SqliteEncryptionManager;

/**
 * CI-only fixture provisioner for the credential-provider image's
 * smoke test -- the counterpart to `bin/provision-smoke-identity.php`
 * for the main Engine API image, gated by the SAME
 * `SQUIRRELFORGE_ALLOW_SMOKE_PROVISIONING=1` switch that script uses.
 *
 * This script enrolls exactly one thing: a TOTP secret for a single
 * fixed smoke-test identity, via the real `MfaSecretStore::enroll()`
 * (never reimplementing its encryption/storage). It provisions
 * nothing else -- no API-key secret, no security event, no identity
 * record in any other store -- so reusing the shared gate variable
 * does not imply any broader provisioning capability than that.
 *
 * `MfaSecretStore::enroll()` exposes the raw TOTP secret exactly once,
 * at enrollment time, and the later smoke-test-runner is a separate
 * one-off container -- so the secret is written to a file inside the
 * same mounted CI runtime volume both containers share, never to
 * stdout/stderr or the workflow log.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

try {
    if (getenv('SQUIRRELFORGE_ALLOW_SMOKE_PROVISIONING') !== '1') {
        throw new RuntimeException('Smoke provisioning requires explicit one-time enablement.');
    }

    $databasePath = (string) getenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER_DB');
    $masterKeyEncoded = (string) getenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER_MFA_MASTER_KEY');
    $identityRef = (string) getenv('SQUIRRELFORGE_SMOKE_IDENTITY_REF');
    $secretPath = (string) getenv('SQUIRRELFORGE_SMOKE_MFA_SECRET_PATH');

    if ($databasePath === '' || $masterKeyEncoded === '' || $identityRef === '' || $secretPath === '') {
        throw new RuntimeException('Smoke database, master key, identity, and secret path are required.');
    }

    $masterKey = base64_decode($masterKeyEncoded, true);

    if (!is_string($masterKey) || strlen($masterKey) !== 32) {
        throw new RuntimeException('The MFA master key must be a base64-encoded 32-byte key.');
    }

    $encryption = new SqliteEncryptionManager($databasePath);
    $mfa = new MfaSecretStore($databasePath, $encryption, $masterKey);
    $enrollment = $mfa->enroll($identityRef);

    if ($enrollment['outcome'] !== 'enrolled' || !is_string($enrollment['secret'])) {
        throw new RuntimeException('MFA enrollment failed: ' . ($enrollment['error'] ?? 'unknown error'));
    }

    if (file_put_contents($secretPath, $enrollment['secret']) === false) {
        throw new RuntimeException('Unable to write the enrolled secret to the shared runtime volume.');
    }

    chmod($secretPath, 0600);

    fwrite(STDOUT, "Smoke MFA secret provisioned. Disable provisioning before startup.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Smoke MFA provisioning failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
