#!/usr/bin/env php
<?php

declare(strict_types=1);

try {
    $manifestPath = requiredEnvironment('SQUIRRELFORGE_LAUNCH_MANIFEST_PATH');
    $receiptPath = requiredEnvironment('SQUIRRELFORGE_LAUNCH_RECEIPT_PATH');
    $now = time();
    $maximumAges = [
        'admission' => positiveInteger('SQUIRRELFORGE_LAUNCH_MAXIMUM_DEPLOYMENT_EVIDENCE_AGE_SECONDS', 86400),
        'rollout' => positiveInteger('SQUIRRELFORGE_LAUNCH_MAXIMUM_DEPLOYMENT_EVIDENCE_AGE_SECONDS', 86400),
        'observation' => positiveInteger('SQUIRRELFORGE_LAUNCH_MAXIMUM_DEPLOYMENT_EVIDENCE_AGE_SECONDS', 86400),
        'health' => positiveInteger('SQUIRRELFORGE_LAUNCH_MAXIMUM_HEALTH_AGE_SECONDS', 900),
        'backup' => positiveInteger('SQUIRRELFORGE_LAUNCH_MAXIMUM_BACKUP_AGE_SECONDS', 86400),
        'restore' => positiveInteger('SQUIRRELFORGE_LAUNCH_MAXIMUM_RESTORE_DRILL_AGE_SECONDS', 7776000),
        'capacity' => positiveInteger('SQUIRRELFORGE_LAUNCH_MAXIMUM_CAPACITY_AGE_SECONDS', 2592000),
    ];

    if (!is_file($manifestPath)) {
        throw new RuntimeException('The launch evidence manifest does not exist.');
    }
    if (file_exists($receiptPath)) {
        throw new RuntimeException('The launch decision receipt already exists.');
    }

    $manifest = jsonFile($manifestPath);
    if (($manifest['schema_version'] ?? null) !== 1) {
        throw new RuntimeException('The launch evidence manifest version is unsupported.');
    }

    foreach (['release_ref', 'change_ref', 'approval_ref', 'candidate_image', 'rollback_image'] as $field) {
        if (!is_string($manifest[$field] ?? null) || trim($manifest[$field]) === '') {
            throw new RuntimeException("Launch manifest field $field is required.");
        }
    }
    foreach (['release', 'on_call', 'recovery', 'security'] as $owner) {
        if (!is_string($manifest['owners'][$owner] ?? null) || trim($manifest['owners'][$owner]) === '') {
            throw new RuntimeException("Launch owner $owner is required.");
        }
    }

    $candidateImage = $manifest['candidate_image'];
    $rollbackImage = $manifest['rollback_image'];
    if (!immutableImage($candidateImage) || !immutableImage($rollbackImage)) {
        throw new RuntimeException('Candidate and rollback images must use immutable SHA-256 digests.');
    }
    if ($candidateImage === $rollbackImage) {
        throw new RuntimeException('Candidate and rollback images must be different.');
    }

    $baseDirectory = realpath(dirname($manifestPath));
    if (!is_string($baseDirectory)) {
        throw new RuntimeException('Unable to resolve the launch evidence directory.');
    }

    $failures = [];
    $evidenceDigests = [];
    $receipts = [];
    foreach (array_keys($maximumAges) as $name) {
        $entry = $manifest['evidence'][$name] ?? null;
        if (!is_array($entry)) {
            $failures[] = strtoupper($name) . '_EVIDENCE_MISSING';
            continue;
        }
        $receipts[$name] = evidence(
            $name,
            $entry,
            $baseDirectory,
            $evidenceDigests,
            $failures
        );
    }

    verifyReceipt($receipts['admission'] ?? null, 'PASSED', 'verified_at', $maximumAges['admission'], $now, 'ADMISSION', $failures);
    if (($receipts['admission']['image'] ?? null) !== $candidateImage) {
        $failures[] = 'ADMISSION_IMAGE_MISMATCH';
    }

    verifyReceipt($receipts['rollout'] ?? null, 'PROMOTED', 'recorded_at', $maximumAges['rollout'], $now, 'ROLLOUT', $failures);
    if (($receipts['rollout']['candidate_image'] ?? null) !== $candidateImage
        || ($receipts['rollout']['active_image'] ?? null) !== $candidateImage) {
        $failures[] = 'ROLLOUT_IMAGE_MISMATCH';
    }
    if (($receipts['rollout']['rollback_image'] ?? null) !== $rollbackImage) {
        $failures[] = 'ROLLBACK_IMAGE_MISMATCH';
    }

    verifyReceipt($receipts['observation'] ?? null, 'PASSED', 'observed_at', $maximumAges['observation'], $now, 'OBSERVATION', $failures);
    if (($receipts['observation']['image'] ?? null) !== $candidateImage) {
        $failures[] = 'OBSERVATION_IMAGE_MISMATCH';
    }

    verifyReceipt($receipts['health'] ?? null, 'HEALTHY', 'checked_at', $maximumAges['health'], $now, 'HEALTH', $failures);
    if (($receipts['health']['expected_image'] ?? null) !== $candidateImage
        || ($receipts['health']['active_image'] ?? null) !== $candidateImage) {
        $failures[] = 'HEALTH_IMAGE_MISMATCH';
    }

    verifyReceipt($receipts['backup'] ?? null, null, 'created_at', $maximumAges['backup'], $now, 'BACKUP', $failures);
    verifyBackup($receipts['backup'] ?? null, $baseDirectory, $failures);

    verifyReceipt($receipts['restore'] ?? null, 'RESTORED', 'restored_at', $maximumAges['restore'], $now, 'RESTORE', $failures);
    if (isset($receipts['backup'], $receipts['restore'])
        && ($receipts['restore']['sha256'] ?? null) !== ($receipts['backup']['sha256'] ?? null)) {
        $failures[] = 'RESTORE_BACKUP_MISMATCH';
    }

    verifyReceipt($receipts['capacity'] ?? null, 'PASSED', 'evaluated_at', $maximumAges['capacity'], $now, 'CAPACITY', $failures);

    $failures = array_values(array_unique($failures));
    $decision = $failures === [] ? 'READY' : 'NOT_READY';
    $receipt = [
        'schema_version' => 1,
        'decision' => $decision,
        'release_ref' => $manifest['release_ref'],
        'change_ref' => $manifest['change_ref'],
        'approval_ref' => $manifest['approval_ref'],
        'candidate_image' => $candidateImage,
        'rollback_image' => $rollbackImage,
        'evaluated_at' => gmdate('Y-m-d\TH:i:s\Z', $now),
        'manifest_sha256' => hash_file('sha256', $manifestPath),
        'evidence_sha256' => $evidenceDigests,
        'owners' => $manifest['owners'],
        'failure_codes' => $failures,
    ];

    $json = json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($receiptPath, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write the launch decision receipt.');
    }
    chmod($receiptPath, 0600);
    fwrite(STDOUT, $decision . PHP_EOL);
    exit($decision === 'READY' ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Launch readiness evaluation failed: ' . $exception->getMessage() . PHP_EOL);
    exit(2);
}

function requiredEnvironment(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || trim($value) === '') {
        throw new RuntimeException($name . ' is required.');
    }
    return $value;
}

function positiveInteger(string $name, int $default): int
{
    $raw = getenv($name);
    if ($raw === false || $raw === '') {
        return $default;
    }
    if (!ctype_digit($raw) || (int) $raw < 1) {
        throw new RuntimeException($name . ' must be a positive integer.');
    }
    return (int) $raw;
}

/** @return array<string, mixed> */
function jsonFile(string $path): array
{
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        throw new RuntimeException('Unable to read JSON evidence.');
    }
    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('JSON evidence must be an object.');
    }
    return $decoded;
}

function immutableImage(string $image): bool
{
    return preg_match('/^[^@\s]+@sha256:[0-9a-f]{64}$/', $image) === 1;
}

/**
 * @param array<string, mixed> $entry
 * @param array<string, string> $digests
 * @param list<string> $failures
 * @return array<string, mixed>|null
 */
function evidence(
    string $name,
    array $entry,
    string $baseDirectory,
    array &$digests,
    array &$failures
): ?array {
    $path = $entry['path'] ?? null;
    $expectedDigest = $entry['sha256'] ?? null;
    if (!is_string($path) || $path === '' || str_starts_with($path, '/') || str_contains($path, '..')
        || !is_string($expectedDigest) || preg_match('/^[0-9a-f]{64}$/', $expectedDigest) !== 1) {
        $failures[] = strtoupper($name) . '_EVIDENCE_INVALID';
        return null;
    }

    $resolved = realpath($baseDirectory . '/' . $path);
    if (!is_string($resolved) || !str_starts_with($resolved, $baseDirectory . '/')) {
        $failures[] = strtoupper($name) . '_EVIDENCE_MISSING';
        return null;
    }
    $actualDigest = hash_file('sha256', $resolved);
    if (!is_string($actualDigest) || !hash_equals($expectedDigest, $actualDigest)) {
        $failures[] = strtoupper($name) . '_CHECKSUM_MISMATCH';
        return null;
    }

    $digests[$name] = $actualDigest;
    try {
        return jsonFile($resolved);
    } catch (Throwable) {
        $failures[] = strtoupper($name) . '_EVIDENCE_INVALID';
        return null;
    }
}

/**
 * @param array<string, mixed>|null $receipt
 * @param list<string> $failures
 */
function verifyReceipt(
    ?array $receipt,
    ?string $expectedStatus,
    string $timestampField,
    int $maximumAge,
    int $now,
    string $prefix,
    array &$failures
): void {
    if ($receipt === null) {
        return;
    }
    if ($expectedStatus !== null && ($receipt['status'] ?? null) !== $expectedStatus) {
        $failures[] = $prefix . '_STATUS_REJECTED';
    }
    $timestamp = $receipt[$timestampField] ?? null;
    $parsed = is_string($timestamp) ? strtotime($timestamp) : false;
    if (!is_int($parsed) || $parsed > $now + 300 || $now - $parsed > $maximumAge) {
        $failures[] = $prefix . '_EVIDENCE_STALE';
    }
}

/**
 * @param array<string, mixed>|null $manifest
 * @param list<string> $failures
 */
function verifyBackup(?array $manifest, string $baseDirectory, array &$failures): void
{
    if ($manifest === null) {
        return;
    }
    $database = $manifest['database'] ?? null;
    $expectedDigest = $manifest['sha256'] ?? null;
    $expectedSize = $manifest['size_bytes'] ?? null;
    if (!is_string($database) || basename($database) !== $database
        || !is_string($expectedDigest) || preg_match('/^[0-9a-f]{64}$/', $expectedDigest) !== 1
        || !is_int($expectedSize)) {
        $failures[] = 'BACKUP_MANIFEST_INVALID';
        return;
    }
    $path = $baseDirectory . '/' . $database;
    $actualDigest = is_file($path) ? hash_file('sha256', $path) : false;
    $actualSize = is_file($path) ? filesize($path) : false;
    if (!is_string($actualDigest) || !hash_equals($expectedDigest, $actualDigest)
        || $actualSize !== $expectedSize) {
        $failures[] = 'BACKUP_ARTIFACT_MISMATCH';
    }
}
