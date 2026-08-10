<?php

declare(strict_types=1);

use SquirrelForge\CredentialProvider\CredentialProviderRouter;
use SquirrelForge\CredentialProvider\MfaSecretStore;
use SquirrelForge\RuntimeConfig\SqliteSecretsManager;
use SquirrelForge\Security\SqliteEncryptionManager;
use SquirrelForge\Security\SqliteSecurityEventSink;

/**
 * The HTTP transport entry point for `CredentialProviderRouter` --
 * the standalone, production-ready implementation of
 * `deploy/CREDENTIAL-PROVIDER-CONTRACT.md`, meant to be deployed as
 * its own service and pointed to by another SquirrelForge instance's
 * `SQUIRRELFORGE_CREDENTIAL_PROVIDER_URL`/`_TOKEN` (`http-json` mode).
 *
 * This is the only place in the credential-provider service that
 * touches superglobals or emits output directly; `CredentialProviderRouter`
 * itself stays fully transport-agnostic and testable without one, the
 * same separation `public/engine-api.php` keeps for the main API.
 */
require dirname(__DIR__) . '/vendor/autoload.php';

$databasePath = getenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER_DB');

if ($databasePath === false || trim($databasePath) === '') {
    $databasePath = dirname(__DIR__) . '/var/credential-provider.sqlite';
}

$token = getenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER_TOKEN');
$masterKeyEncoded = getenv('SQUIRRELFORGE_CREDENTIAL_PROVIDER_MFA_MASTER_KEY');
$masterKey = is_string($masterKeyEncoded) ? base64_decode($masterKeyEncoded, true) : false;

if (!is_string($token) || trim($token) === '' || !is_string($masterKey) || strlen($masterKey) !== 32) {
    // Fail closed: an unconfigured provider must refuse to serve
    // requests, not fall back to some default/guessable authority.
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'server_misconfigured']);

    return;
}

$encryption = new SqliteEncryptionManager($databasePath);
$router = new CredentialProviderRouter(
    new SqliteSecretsManager($databasePath),
    new MfaSecretStore($databasePath, $encryption, $masterKey),
    new SqliteSecurityEventSink($databasePath),
    $token
);

$headers = [];

foreach (getallheaders() as $name => $value) {
    if (is_string($name) && is_string($value)) {
        $headers[$name] = $value;
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$body = file_get_contents('php://input') ?: null;

[$status, $responseBody] = $router->handle($method, $path, $headers, $body);

http_response_code($status);
header('Content-Type: application/json');
echo json_encode($responseBody);
