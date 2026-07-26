<?php

declare(strict_types=1);

/**
 * Verifies a backup against its manifest without modifying it.
 */

$backupPath = requiredEnvironment('SQUIRRELFORGE_BACKUP_PATH');
$manifestPath = environment('SQUIRRELFORGE_BACKUP_MANIFEST_PATH', $backupPath . '.manifest.json');
$manifest = verifiedManifest($backupPath, $manifestPath);

fwrite(STDOUT, json_encode([
    'status' => 'VERIFIED',
    'backup' => basename($backupPath),
    'sha256' => $manifest['sha256'],
], JSON_THROW_ON_ERROR) . PHP_EOL);

function requiredEnvironment(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || trim($value) === '') {
        throw new RuntimeException($name . ' is required.');
    }

    return $value;
}

function environment(string $name, string $default): string
{
    $value = getenv($name);

    return is_string($value) && trim($value) !== '' ? $value : $default;
}

/**
 * @return array{schema_version: int, database: string, size_bytes: int, sha256: string}
 */
function verifiedManifest(string $backupPath, string $manifestPath): array
{
    if (!is_file($backupPath) || !is_file($manifestPath)) {
        throw new RuntimeException('The backup and manifest must both exist.');
    }

    $contents = file_get_contents($manifestPath);
    if (!is_string($contents)) {
        throw new RuntimeException('Unable to read the backup manifest.');
    }

    $manifest = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (
        !is_array($manifest)
        || ($manifest['schema_version'] ?? null) !== 1
        || !is_string($manifest['database'] ?? null)
        || !is_int($manifest['size_bytes'] ?? null)
        || !is_string($manifest['sha256'] ?? null)
    ) {
        throw new RuntimeException('The backup manifest is invalid or unsupported.');
    }

    if ($manifest['database'] !== basename($backupPath)) {
        throw new RuntimeException('The manifest does not identify this backup.');
    }

    $size = filesize($backupPath);
    $checksum = hash_file('sha256', $backupPath);
    if ($size !== $manifest['size_bytes'] || $checksum !== $manifest['sha256']) {
        throw new RuntimeException('The backup does not match its manifest.');
    }

    $database = new PDO('sqlite:' . $backupPath);
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($database->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
        throw new RuntimeException('SQLite integrity verification failed.');
    }

    return $manifest;
}
