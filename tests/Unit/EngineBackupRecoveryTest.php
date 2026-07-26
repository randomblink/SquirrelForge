<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;

final class EngineBackupRecoveryTest extends TestCase
{
    private string $directory;
    private string $source;
    private string $backup;
    private string $manifest;
    private string $restore;
    private string $receipt;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/squirrelforge-backup-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700, true);
        $this->source = $this->directory . '/engine.sqlite';
        $this->backup = $this->directory . '/engine.backup.sqlite';
        $this->manifest = $this->directory . '/engine.backup.manifest.json';
        $this->restore = $this->directory . '/restored.sqlite';
        $this->receipt = $this->directory . '/restore-receipt.json';

        $database = new PDO('sqlite:' . $this->source);
        $database->exec('PRAGMA journal_mode = WAL');
        $database->exec('CREATE TABLE executions (id TEXT PRIMARY KEY, decision TEXT NOT NULL)');
        $database->exec("INSERT INTO executions VALUES ('execution_1', 'ACCEPTED')");
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($this->directory);
    }

    public function testBackupCanBeVerifiedAndRestoredWithReceipt(): void
    {
        $backup = $this->runCommand('bin/backup-engine.php', [
            'SQUIRRELFORGE_ENGINE_DB' => $this->source,
            'SQUIRRELFORGE_BACKUP_PATH' => $this->backup,
            'SQUIRRELFORGE_BACKUP_MANIFEST_PATH' => $this->manifest,
        ]);
        self::assertSame(0, $backup['exit']);
        self::assertStringContainsString('"status":"BACKED_UP"', $backup['output']);

        $verification = $this->runCommand('bin/verify-engine-backup.php', [
            'SQUIRRELFORGE_BACKUP_PATH' => $this->backup,
            'SQUIRRELFORGE_BACKUP_MANIFEST_PATH' => $this->manifest,
        ]);
        self::assertSame(0, $verification['exit']);
        self::assertStringContainsString('"status":"VERIFIED"', $verification['output']);

        $restoration = $this->runCommand('bin/restore-engine-backup.php', [
            'SQUIRRELFORGE_BACKUP_PATH' => $this->backup,
            'SQUIRRELFORGE_BACKUP_MANIFEST_PATH' => $this->manifest,
            'SQUIRRELFORGE_RESTORE_DB' => $this->restore,
            'SQUIRRELFORGE_RESTORE_RECEIPT_PATH' => $this->receipt,
        ]);
        self::assertSame(0, $restoration['exit']);
        self::assertFileExists($this->receipt);

        $restored = new PDO('sqlite:' . $this->restore);
        self::assertSame(
            ['id' => 'execution_1', 'decision' => 'ACCEPTED'],
            $restored->query('SELECT id, decision FROM executions')->fetch(PDO::FETCH_ASSOC)
        );
    }

    public function testTamperedBackupFailsVerificationAndRestore(): void
    {
        self::assertSame(0, $this->runCommand('bin/backup-engine.php', [
            'SQUIRRELFORGE_ENGINE_DB' => $this->source,
            'SQUIRRELFORGE_BACKUP_PATH' => $this->backup,
            'SQUIRRELFORGE_BACKUP_MANIFEST_PATH' => $this->manifest,
        ])['exit']);

        file_put_contents($this->backup, 'tamper', FILE_APPEND);

        self::assertNotSame(0, $this->runCommand('bin/verify-engine-backup.php', [
            'SQUIRRELFORGE_BACKUP_PATH' => $this->backup,
            'SQUIRRELFORGE_BACKUP_MANIFEST_PATH' => $this->manifest,
        ])['exit']);
        self::assertNotSame(0, $this->runCommand('bin/restore-engine-backup.php', [
            'SQUIRRELFORGE_BACKUP_PATH' => $this->backup,
            'SQUIRRELFORGE_BACKUP_MANIFEST_PATH' => $this->manifest,
            'SQUIRRELFORGE_RESTORE_DB' => $this->restore,
        ])['exit']);
        self::assertFileDoesNotExist($this->restore);
    }

    public function testRestoreRefusesToOverwriteExistingDatabase(): void
    {
        self::assertSame(0, $this->runCommand('bin/backup-engine.php', [
            'SQUIRRELFORGE_ENGINE_DB' => $this->source,
            'SQUIRRELFORGE_BACKUP_PATH' => $this->backup,
            'SQUIRRELFORGE_BACKUP_MANIFEST_PATH' => $this->manifest,
        ])['exit']);
        file_put_contents($this->restore, 'preserve me');

        $result = $this->runCommand('bin/restore-engine-backup.php', [
            'SQUIRRELFORGE_BACKUP_PATH' => $this->backup,
            'SQUIRRELFORGE_BACKUP_MANIFEST_PATH' => $this->manifest,
            'SQUIRRELFORGE_RESTORE_DB' => $this->restore,
        ]);

        self::assertNotSame(0, $result['exit']);
        self::assertSame('preserve me', file_get_contents($this->restore));
    }

    /**
     * @param array<string, string> $environment
     * @return array{exit: int, output: string}
     */
    private function runCommand(string $script, array $environment): array
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/' . $script);
        $pipes = [];
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2),
            array_merge($_ENV, $environment)
        );
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['exit' => proc_close($process), 'output' => $output];
    }
}
