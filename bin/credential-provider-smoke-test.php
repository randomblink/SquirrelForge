#!/usr/bin/env php
<?php

declare(strict_types=1);

use SquirrelForge\CredentialProvider\TotpVerifier;
use SquirrelForge\Integration\Http\NativeHttpTransport;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $baseUrl = rtrim((string) getenv('SQUIRRELFORGE_SMOKE_BASE_URL'), '/');
    $providerToken = (string) getenv('SQUIRRELFORGE_SMOKE_PROVIDER_TOKEN');
    $identityRef = (string) getenv('SQUIRRELFORGE_SMOKE_IDENTITY_REF');
    $apiKey = (string) getenv('SQUIRRELFORGE_SMOKE_API_KEY');
    $mfaSecretPath = (string) getenv('SQUIRRELFORGE_SMOKE_MFA_SECRET_PATH');

    if ($baseUrl === '' || $providerToken === '' || $identityRef === '' || $apiKey === '' || $mfaSecretPath === '') {
        throw new RuntimeException('Smoke-test URL, provider token, identity, API key, and MFA secret path are required.');
    }

    $mfaSecret = @file_get_contents($mfaSecretPath);

    if (!is_string($mfaSecret) || $mfaSecret === '') {
        throw new RuntimeException('The enrolled MFA secret could not be read from the shared runtime volume.');
    }

    $transport = new NativeHttpTransport();
    $headers = [
        'Authorization' => 'Bearer ' . $providerToken,
        'Content-Type' => 'application/json',
    ];

    $health = $transport->request('GET', $baseUrl . '/v1/provider/health', $headers, null, 10.0);
    $healthBody = json_decode($health->body, true, 512, JSON_THROW_ON_ERROR);

    if ($health->status !== 200 || ($healthBody['healthy'] ?? false) !== true) {
        throw new RuntimeException('Provider health check failed.');
    }

    $register = $transport->request(
        'POST',
        $baseUrl . '/v1/provider/secrets/register',
        $headers,
        json_encode(['identity_ref' => $identityRef, 'api_key' => $apiKey], JSON_THROW_ON_ERROR),
        10.0
    );
    $registerBody = json_decode($register->body, true, 512, JSON_THROW_ON_ERROR);
    $secretRef = $registerBody['secret_ref'] ?? null;

    if ($register->status !== 200 || !is_string($secretRef)) {
        throw new RuntimeException('Secret registration failed.');
    }

    $verify = $transport->request(
        'POST',
        $baseUrl . '/v1/provider/secrets/verify',
        $headers,
        json_encode(['identity_ref' => $identityRef, 'api_key' => $apiKey], JSON_THROW_ON_ERROR),
        10.0
    );
    $verifyBody = json_decode($verify->body, true, 512, JSON_THROW_ON_ERROR);

    if ($verify->status !== 200 || ($verifyBody['verified'] ?? false) !== true) {
        throw new RuntimeException('Secret verification failed.');
    }

    $rotatedApiKey = 'ci_rotated_' . bin2hex(random_bytes(16));
    $rotate = $transport->request(
        'POST',
        $baseUrl . '/v1/provider/secrets/rotate',
        $headers,
        json_encode(['secret_ref' => $secretRef, 'new_api_key' => $rotatedApiKey], JSON_THROW_ON_ERROR),
        10.0
    );
    $rotateBody = json_decode($rotate->body, true, 512, JSON_THROW_ON_ERROR);
    $rotatedSecretRef = $rotateBody['secret_ref'] ?? null;

    if ($rotate->status !== 200 || !is_string($rotatedSecretRef)) {
        throw new RuntimeException('Secret rotation failed.');
    }

    $code = (new TotpVerifier())->code($mfaSecret);
    $mfaVerify = $transport->request(
        'POST',
        $baseUrl . '/v1/provider/mfa/verify',
        $headers,
        json_encode(['identity_ref' => $identityRef, 'proof' => $code], JSON_THROW_ON_ERROR),
        10.0
    );
    $mfaVerifyBody = json_decode($mfaVerify->body, true, 512, JSON_THROW_ON_ERROR);

    if ($mfaVerify->status !== 200 || ($mfaVerifyBody['verified'] ?? false) !== true) {
        throw new RuntimeException('MFA verification failed.');
    }

    $correlationId = 'smoke_' . bin2hex(random_bytes(12));
    $event = $transport->request(
        'POST',
        $baseUrl . '/v1/provider/security-events',
        $headers,
        json_encode([
            'event_type' => 'credential_provider_smoke_test',
            'outcome' => 'PASSED',
            'correlation_id' => $correlationId,
            'identity_ref' => $identityRef,
        ], JSON_THROW_ON_ERROR),
        10.0
    );
    $eventBody = json_decode($event->body, true, 512, JSON_THROW_ON_ERROR);
    $securityEventRef = $eventBody['security_event_ref'] ?? null;

    if ($event->status !== 200 || !is_string($securityEventRef)) {
        throw new RuntimeException('Security-event recording failed.');
    }

    $revoke = $transport->request(
        'POST',
        $baseUrl . '/v1/provider/secrets/revoke',
        $headers,
        json_encode(['secret_ref' => $rotatedSecretRef], JSON_THROW_ON_ERROR),
        10.0
    );
    $revokeBody = json_decode($revoke->body, true, 512, JSON_THROW_ON_ERROR);

    if ($revoke->status !== 200 || ($revokeBody['revoked'] ?? false) !== true) {
        throw new RuntimeException('Secret revocation failed.');
    }

    fwrite(STDOUT, json_encode([
        'smoke_test' => 'PASSED',
        'provider_healthy' => true,
        'secret_registration' => 'PASSED',
        'secret_verification' => 'PASSED',
        'secret_rotation' => 'PASSED',
        'mfa_verification' => 'PASSED',
        'security_event' => 'PASSED',
        'secret_revocation' => 'PASSED',
        'secret_ref' => $rotatedSecretRef,
        'security_event_ref' => $securityEventRef,
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Credential provider smoke test failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
