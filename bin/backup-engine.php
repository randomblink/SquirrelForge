<?php

declare(strict_types=1);

/**
 * Creates a consistent, independently verifiable SQLite Engine backup.
 */

$sourcePath = requiredEnvironment('SQUIRRELFORGE_ENGINE_DB');
$backupPath = requiredEnvironment('SQUIRRELFORGE_BACKUP_PATH');
$manifestPath = environment('SQUIRRELFORGE_BACKUP_MANIFEST_PATH', $backupPath . '.manifest.json');

if (!is_file($sourcePath)) {
    throw new RuntimeException('The Engine database does not exist.');
}

foreach ([$backupPath, $manifestPath] as $outputPath) {
    if (file_exists($outputPath)) {
        throw new RuntimeException('Backup output already exists: ' . basename($outputPath));
    }

    $directory = dirname($outputPath);
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('The backup output directory must exist and be writable.');
    }
}

$source = sqlite($sourcePath);
$source->exec('PRAGMA busy_timeout = 30000');
$source->exec('VACUUM INTO ' . $source->quote($backupPath));

try {
    assertIntegrity($backupPath);
    $checksum = hash_file('sha256', $backupPath);
    $size = filesize($backupPath);

    if (!is_string($checksum) || !is_int($size)) {
        throw new RuntimeException('Unable to measure the completed backup.');
    }

    $manifest = [
        'schema_version' => 1,
        'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'database' => basename($backupPath),
        'size_bytes' => $size,
        'sha256' => $checksum,
        'sqlite_user_version' => (int) sqlite($backupPath)->query('PRAGMA user_version')->fetchColumn(),
    ];

    $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($manifestPath, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write the backup manifest.');
    }

    chmod($backupPath, 0600);
    chmod($manifestPath, 0600);

    fwrite(STDOUT, json_encode([
        'status' => 'BACKED_UP',
        'backup' => basename($backupPath),
        'manifest' => basename($manifestPath),
        'sha256' => $checksum,
    ], JSON_THROW_ON_ERROR) . PHP_EOL);
} catch (Throwable $error) {
    @unlink($backupPath);
    @unlink($manifestPath);
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

function sqlite(string $path): PDO
{
    $database = new PDO('sqlite:' . $path);
    $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $database;
}

function assertIntegrity(string $path): void
{
    $result = sqlite($path)->query('PRAGMA integrity_check')->fetchColumn();
    if ($result !== 'ok') {
        throw new RuntimeException('SQLite integrity verification failed.');
    }
}
