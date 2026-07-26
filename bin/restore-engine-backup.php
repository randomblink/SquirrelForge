<?php

declare(strict_types=1);

/**
 * Restores a verified Engine backup into an empty target path.
 */

$backupPath = requiredEnvironment('SQUIRRELFORGE_BACKUP_PATH');
$manifestPath = environment('SQUIRRELFORGE_BACKUP_MANIFEST_PATH', $backupPath . '.manifest.json');
$restorePath = requiredEnvironment('SQUIRRELFORGE_RESTORE_DB');
$receiptPath = environment('SQUIRRELFORGE_RESTORE_RECEIPT_PATH', '');

foreach ([$restorePath, $restorePath . '-wal', $restorePath . '-shm'] as $target) {
    if (file_exists($target)) {
        throw new RuntimeException('Restore target must be empty.');
    }
}

$directory = dirname($restorePath);
if (!is_dir($directory) || !is_writable($directory)) {
    throw new RuntimeException('The restore directory must exist and be writable.');
}
if ($receiptPath !== '' && file_exists($receiptPath)) {
    throw new RuntimeException('Restore receipt output already exists.');
}

$manifest = verifiedManifest($backupPath, $manifestPath);
$temporaryPath = $restorePath . '.restore-' . bin2hex(random_bytes(8));

try {
    if (!copy($backupPath, $temporaryPath)) {
        throw new RuntimeException('Unable to copy the verified backup.');
    }
    chmod($temporaryPath, 0600);
    assertIntegrity($temporaryPath);

    if (!rename($temporaryPath, $restorePath)) {
        throw new RuntimeException('Unable to atomically install the restored database.');
    }

    assertIntegrity($restorePath);
    $receipt = [
        'status' => 'RESTORED',
        'backup' => basename($backupPath),
        'restore_database' => basename($restorePath),
        'sha256' => $manifest['sha256'],
        'restored_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    if ($receiptPath !== '') {
        $json = json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($receiptPath, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the restore receipt.');
        }
        chmod($receiptPath, 0600);
    }

    fwrite(STDOUT, json_encode($receipt, JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $error) {
    @unlink($temporaryPath);
    throw $error;
}

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

    assertIntegrity($backupPath);

    return $manifest;
}

function assertIntegrity(string $path): void
{
    $database = new PDO('sqlite:' . $path);
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($database->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') {
        throw new RuntimeException('SQLite integrity verification failed.');
    }
}
