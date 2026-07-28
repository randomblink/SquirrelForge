<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Security\StaticAuthorizationManager;
use SquirrelForge\Storage\SqliteBackupManager;
use SquirrelForge\Storage\SqliteDocumentStorage;
use SquirrelForge\Storage\SqliteObjectStorage;
use SquirrelForge\Tools\LocalFileSystem;

final class SqliteBackupManagerTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    /** @var array<int, string> */
    private array $filesystemRoots = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }

        foreach ($this->filesystemRoots as $root) {
            $files = glob($root . '/objects/*/*') ?: [];
            foreach ($files as $file) {
                unlink($file);
            }
            $dirs = glob($root . '/objects/*') ?: [];
            foreach ($dirs as $dir) {
                @rmdir($dir);
            }
            @rmdir($root . '/objects');
            @rmdir($root);
        }
    }

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-backup-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function realFilesystem(): LocalFileSystem
    {
        $root = sys_get_temp_dir() . '/squirrelforge-backup-fs-' . bin2hex(random_bytes(8));
        mkdir($root);
        $this->filesystemRoots[] = $root;

        return new LocalFileSystem($root);
    }

    /**
     * @return array{0: SqliteDocumentStorage, 1: SqliteObjectStorage, 2: SqliteBackupManager, 3: string}
     */
    private function makeManagers(): array
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $objects = new SqliteObjectStorage($this->tempPath('objects'), $this->realFilesystem());
        $backupDbPath = $this->tempPath('backups');
        $backups = new SqliteBackupManager($backupDbPath, $documents, $objects);

        return [$documents, $objects, $backups, $backupDbPath];
    }

    public function testBackupCapturesTheCurrentDocumentVersion(): void
    {
        [$documents, , $backups] = $this->makeManagers();
        $stored = $documents->store('policy', 'Retention Policy', ['days' => 30]);

        $result = $backups->backup('document', $stored['document_ref']);

        $this->assertSame('created', $result['outcome']);
        $this->assertSame(1, $result['source_version']);
    }

    public function testBackupOfNonexistentSourceIsRejected(): void
    {
        [, , $backups] = $this->makeManagers();

        $result = $backups->backup('document', 'document_does_not_exist');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('was not found', (string) $result['error']);
    }

    public function testBackupWithoutAConfiguredStoreIsRejected(): void
    {
        $backups = new SqliteBackupManager($this->tempPath('backups'));

        $result = $backups->backup('document', 'document_1');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('not configured', (string) $result['error']);
    }

    public function testUnknownSourceTypeIsRejected(): void
    {
        [, , $backups] = $this->makeManagers();

        $result = $backups->backup('spreadsheet', 'thing_1');

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testUnknownBackupTypeIsRejected(): void
    {
        [$documents, , $backups] = $this->makeManagers();
        $stored = $documents->store('policy', 'Retention Policy', ['days' => 30]);

        $result = $backups->backup('document', $stored['document_ref'], ['type' => 'telepathic']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testDeniedAuthorizationBlocksBackup(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $backups = new SqliteBackupManager($this->tempPath('backups'), $documents, authorization: $authorization);
        $stored = $documents->store('policy', 'Retention Policy', ['days' => 30]);

        $result = $backups->backup('document', $stored['document_ref'], ['identity_ref' => 'user_1', 'permission_ref' => 'permission:denied']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testRestoreAppendsANewVersionMatchingTheBackedUpContentAndTakesASafetySnapshot(): void
    {
        [$documents, , $backups] = $this->makeManagers();
        $stored = $documents->store('policy', 'Retention Policy', ['days' => 30]);
        $documentRef = $stored['document_ref'];

        $backupOfV1 = $backups->backup('document', $documentRef);
        $documents->updateVersion($documentRef, ['days' => 90]);

        $result = $backups->restore($backupOfV1['backup_ref']);

        $this->assertSame('restored', $result['outcome']);
        $this->assertSame(3, $result['restored_version']);
        $this->assertNotNull($result['safety_backup_ref']);

        $current = $documents->retrieve($documentRef);
        $this->assertSame(['days' => 30], $current['content']);

        $safetySnapshot = $backups->listBackups('document', $documentRef);
        $this->assertSame('pre_restoration', $safetySnapshot[0]['backup_type']);
        $this->assertSame(2, $safetySnapshot[0]['source_version']);
    }

    public function testRestoreOfAnObjectAppendsANewVersionWithTheBackedUpBytes(): void
    {
        [, $objects, $backups] = $this->makeManagers();
        $stored = $objects->store('model.bin', 'model_artifact', 'v1-bytes');
        $objectRef = $stored['object_ref'];
        $backupOfV1 = $backups->backup('object', $objectRef);
        $objects->updateVersion($objectRef, 'v2-bytes');

        $result = $backups->restore($backupOfV1['backup_ref']);

        $this->assertSame('restored', $result['outcome']);
        $current = $objects->retrieve($objectRef);
        $this->assertSame('v1-bytes', $current['contents']);
    }

    public function testRestoreOfUnknownBackupIsNotFound(): void
    {
        [, , $backups] = $this->makeManagers();

        $result = $backups->restore('backup_does_not_exist');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testRestoreFailsClosedWhenTheStoredSnapshotHasBeenTamperedWith(): void
    {
        [$documents, , $backups, $backupDbPath] = $this->makeManagers();
        $stored = $documents->store('policy', 'Retention Policy', ['days' => 30]);
        $backup = $backups->backup('document', $stored['document_ref']);

        $rawConnection = new PDO('sqlite:' . $backupDbPath);
        $rawConnection->exec("UPDATE backup_snapshots SET content_blob = '{\"days\":999}' WHERE backup_ref = '{$backup['backup_ref']}'");

        $result = $backups->restore($backup['backup_ref']);

        $this->assertSame('corrupted', $result['outcome']);
        $this->assertStringContainsString('integrity verification', (string) $result['error']);
    }

    public function testProtectedBackupSurvivesRetentionPruning(): void
    {
        [$documents, , $backups] = $this->makeManagers();
        $stored = $documents->store('policy', 'Retention Policy', ['days' => 1]);
        $documentRef = $stored['document_ref'];

        $first = $backups->backup('document', $documentRef);
        $backups->protectBackup($first['backup_ref'], true);
        $documents->updateVersion($documentRef, ['days' => 2]);
        $backups->backup('document', $documentRef);
        $documents->updateVersion($documentRef, ['days' => 3]);
        $backups->backup('document', $documentRef, ['retention_count' => 1]);

        $remaining = $backups->listBackups('document', $documentRef);
        $remainingRefs = array_column($remaining, 'backup_ref');

        $this->assertContains($first['backup_ref'], $remainingRefs, 'protected backup must survive pruning');
        $this->assertCount(2, $remaining, 'one protected + one most recent, the unprotected middle one pruned');
    }

    public function testPruneRetentionDirectlyRemovesOldestUnprotectedBackups(): void
    {
        [$documents, , $backups] = $this->makeManagers();
        $stored = $documents->store('policy', 'Retention Policy', ['days' => 1]);
        $documentRef = $stored['document_ref'];
        $backups->backup('document', $documentRef);
        $documents->updateVersion($documentRef, ['days' => 2]);
        $backups->backup('document', $documentRef);
        $documents->updateVersion($documentRef, ['days' => 3]);
        $backups->backup('document', $documentRef);

        $pruned = $backups->pruneRetention('document', $documentRef, 1);

        $this->assertCount(2, $pruned);
        $this->assertCount(1, $backups->listBackups('document', $documentRef));
    }

    public function testProtectBackupReturnsFalseForUnknownBackup(): void
    {
        [, , $backups] = $this->makeManagers();

        $this->assertFalse($backups->protectBackup('backup_does_not_exist', true));
    }

    public function testOperationDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('backup.created', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $backups = new SqliteBackupManager($this->tempPath('backups'), $documents, events: $events);
        $stored = $documents->store('policy', 'Retention Policy', ['days' => 1]);
        $backups->backup('document', $stored['document_ref']);

        $this->assertCount(1, $captured);
    }

    public function testOperationRetrievesAPersistedAuditRecordByRef(): void
    {
        [$documents, , $backups] = $this->makeManagers();
        $stored = $documents->store('policy', 'Retention Policy', ['days' => 1]);
        $backups->backup('document', $stored['document_ref']);

        $recent = $backups->recent(1);
        $fetched = $backups->operation($recent[0]['operation_ref']);

        $this->assertNotNull($fetched);
        $this->assertSame('created', $fetched['outcome']);
    }
}
