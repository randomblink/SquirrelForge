#!/usr/bin/env php
<?php

declare(strict_types=1);

use SquirrelForge\Security\SqliteAuthorizationManager;
use SquirrelForge\Security\SqliteIdentityManager;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    if (getenv('SQUIRRELFORGE_ALLOW_SMOKE_PROVISIONING') !== '1') {
        throw new RuntimeException('Smoke provisioning requires explicit one-time enablement.');
    }

    $databasePath = (string) getenv('SQUIRRELFORGE_ENGINE_DB');
    $identityRef = (string) getenv('SQUIRRELFORGE_SMOKE_IDENTITY_REF');
    $permissionRef = (string) getenv('SQUIRRELFORGE_SMOKE_PERMISSION_REF');

    if ($databasePath === '' || $identityRef === '' || $permissionRef === '') {
        throw new RuntimeException('Smoke database, identity, and permission references are required.');
    }

    (new SqliteIdentityManager($databasePath))->provision($identityRef, 'SERVICE');
    (new SqliteAuthorizationManager($databasePath))->issueGrant(
        $permissionRef,
        $identityRef,
        ['engine.submit', 'engine.result'],
        ['*']
    );

    fwrite(STDOUT, "Smoke identity and permission provisioned. Disable provisioning before startup.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Smoke provisioning failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
