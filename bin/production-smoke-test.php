#!/usr/bin/env php
<?php

declare(strict_types=1);

use SquirrelForge\Integration\Http\NativeHttpTransport;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $baseUrl = rtrim((string) getenv('SQUIRRELFORGE_SMOKE_BASE_URL'), '/');
    $identityRef = (string) getenv('SQUIRRELFORGE_SMOKE_IDENTITY_REF');
    $permissionRef = (string) getenv('SQUIRRELFORGE_SMOKE_PERMISSION_REF');
    $apiKey = (string) getenv('SQUIRRELFORGE_SMOKE_API_KEY');

    if ($baseUrl === '' || $identityRef === '' || $permissionRef === '' || $apiKey === '') {
        throw new RuntimeException('Smoke-test URL, identity, permission, and API key are required.');
    }

    $transport = new NativeHttpTransport();
    $readiness = $transport->request('GET', $baseUrl . '/v1/health/providers', [], null, 10.0);
    $readinessBody = json_decode($readiness->body, true, 512, JSON_THROW_ON_ERROR);

    if ($readiness->status !== 200 || ($readinessBody['ready'] ?? false) !== true) {
        throw new RuntimeException('Provider readiness failed.');
    }

    $correlationId = 'smoke_' . bin2hex(random_bytes(12));
    $session = $transport->request(
        'POST',
        $baseUrl . '/v1/authentication/sessions',
        [
            'Content-Type' => 'application/json',
            'X-Correlation-ID' => $correlationId,
            'X-Source-Ref' => 'source:production-smoke-test',
        ],
        json_encode(['identity_ref' => $identityRef, 'api_key' => $apiKey], JSON_THROW_ON_ERROR),
        10.0
    );
    $sessionBody = json_decode($session->body, true, 512, JSON_THROW_ON_ERROR);

    if ($session->status !== 201 || !is_string($sessionBody['access_token'] ?? null)) {
        throw new RuntimeException('Authentication session creation failed.');
    }

    $engineHeaders = [
        'Authorization' => 'Bearer ' . $sessionBody['access_token'],
        'Content-Type' => 'application/json',
        'X-SquirrelForge-Identity-Ref' => $identityRef,
        'X-SquirrelForge-Permission-Ref' => $permissionRef,
        'X-Correlation-ID' => $correlationId,
    ];
    $idempotencyKey = 'smoke_' . bin2hex(random_bytes(12));
    $submit = $transport->request(
        'POST',
        $baseUrl . '/v1/engine/requests',
        $engineHeaders + ['Idempotency-Key' => $idempotencyKey],
        json_encode([
            'request' => ['goal' => 'Run the production deployment smoke test.'],
            'project_ref' => 'project:production-smoke-test',
        ], JSON_THROW_ON_ERROR),
        15.0
    );
    $submitBody = json_decode($submit->body, true, 512, JSON_THROW_ON_ERROR);
    $executionRef = $submitBody['execution_ref'] ?? null;

    if ($submit->status !== 202 || !is_string($executionRef)) {
        throw new RuntimeException('Engine submission failed.');
    }

    $result = $transport->request(
        'GET',
        $baseUrl . '/v1/engine/executions/' . rawurlencode($executionRef) . '/result',
        $engineHeaders,
        null,
        15.0
    );
    $resultBody = json_decode($result->body, true, 512, JSON_THROW_ON_ERROR);

    if ($result->status !== 200
        || ($resultBody['execution_ref'] ?? null) !== $executionRef
        || !in_array($resultBody['validation_decision'] ?? null, [
            'ACCEPTED',
            'ACCEPTED_WITH_LIMITATIONS',
        ], true)) {
        throw new RuntimeException('Engine result retrieval or validation failed.');
    }

    fwrite(STDOUT, json_encode([
        'smoke_test' => 'PASSED',
        'provider_ready' => true,
        'authentication' => 'PASSED',
        'authorization' => 'PASSED',
        'execution_ref' => $executionRef,
        'validation_decision' => $resultBody['validation_decision'],
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'SquirrelForge production smoke test failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
