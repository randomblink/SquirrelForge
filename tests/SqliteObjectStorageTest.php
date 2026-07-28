<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Security\StaticAuthorizationManager;
use SquirrelForge\Storage\SqliteObjectStorage;
use SquirrelForge\Tests\Support\FakeFileSystem;
use SquirrelForge\Tools\LocalFileSystem;

final class SqliteObjectStorageTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-object-storage-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function realFilesystem(): LocalFileSystem
    {
        $root = sys_get_temp_dir() . '/squirrelforge-object-fs-' . bin2hex(random_bytes(8));
        mkdir($root);
        $this->filesystemRoots[] = $root;

        return new LocalFileSystem($root);
    }

    public function testStoreWritesRealBytesToDiskAndTheyAreRetrievable(): void
    {
        $storage = new SqliteObjectStorage($this->tempPath('main'), $this->realFilesystem());
        $stored = $storage->store('report.pdf', 'export_package', 'binary-bytes-here');

        $this->assertSame('stored', $stored['outcome']);
        $this->assertSame(1, $stored['version']);
        $this->assertSame(hash('sha256', 'binary-bytes-here'), $stored['checksum']);

        $retrieved = $storage->retrieve($stored['object_ref']);
        $this->assertTrue($retrieved['found']);
        $this->assertSame('binary-bytes-here', $retrieved['contents']);
    }

    public function testUpdateAppendsANewVersionWithoutMutatingTheOriginalBytes(): void
    {
        $storage = new SqliteObjectStorage($this->tempPath('main'), $this->realFilesystem());
        $stored = $storage->store('model.bin', 'model_artifact', 'v1-bytes');
        $objectRef = $stored['object_ref'];

        $updated = $storage->updateVersion($objectRef, 'v2-bytes');

        $this->assertSame('updated', $updated['outcome']);
        $this->assertSame(2, $updated['version']);

        $original = $storage->retrieve($objectRef, 1);
        $latest = $storage->retrieve($objectRef);

        $this->assertSame('v1-bytes', $original['contents']);
        $this->assertSame('v2-bytes', $latest['contents']);
        $this->assertCount(2, $storage->versions($objectRef));
    }

    public function testStoreRejectsPostWriteIntegrityMismatchAndDoesNotPersistMetadata(): void
    {
        $filesystem = new FakeFileSystem();
        $filesystem->forcedReadOverride = 'corrupted-bytes';
        $storage = new SqliteObjectStorage($this->tempPath('main'), $filesystem);

        $result = $storage->store('report.pdf', 'export_package', 'original-bytes');

        $this->assertSame('failed', $result['outcome']);
        $this->assertNull($result['object_ref']);
        $this->assertStringContainsString('integrity verification failed', (string) $result['error']);
    }

    public function testRetrieveFailsClosedWhenStoredBytesNoLongerMatchTheRecordedChecksum(): void
    {
        $filesystem = new FakeFileSystem();
        $storage = new SqliteObjectStorage($this->tempPath('main'), $filesystem);
        $stored = $storage->store('report.pdf', 'export_package', 'original-bytes');

        $filesystem->forcedReadOverride = 'silently-corrupted-on-disk';
        $result = $storage->retrieve($stored['object_ref']);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('Retrieval verification failed', (string) $result['error']);
    }

    public function testRetrievingANonexistentVersionFails(): void
    {
        $storage = new SqliteObjectStorage($this->tempPath('main'), $this->realFilesystem());
        $stored = $storage->store('report.pdf', 'export_package', 'bytes');

        $result = $storage->retrieve($stored['object_ref'], 9);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('Version 9', (string) $result['error']);
    }

    public function testDeletedObjectIsNoLongerRetrievableButVersionsSurvive(): void
    {
        $storage = new SqliteObjectStorage($this->tempPath('main'), $this->realFilesystem());
        $stored = $storage->store('log.bundle', 'log_bundle', 'log-bytes');
        $objectRef = $stored['object_ref'];

        $deleted = $storage->delete($objectRef);
        $retrieved = $storage->retrieve($objectRef);

        $this->assertSame('deleted', $deleted['outcome']);
        $this->assertFalse($retrieved['found']);
        $this->assertCount(1, $storage->versions($objectRef));
    }

    public function testObjectUnderLegalHoldCannotBeDeleted(): void
    {
        $storage = new SqliteObjectStorage($this->tempPath('main'), $this->realFilesystem());
        $stored = $storage->store('evidence.zip', 'export_package', 'bytes');
        $objectRef = $stored['object_ref'];

        $storage->setLegalHold($objectRef, true);
        $result = $storage->delete($objectRef);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('legal hold', (string) $result['error']);
    }

    public function testSetLegalHoldReturnsFalseForUnknownObject(): void
    {
        $storage = new SqliteObjectStorage($this->tempPath('main'), $this->realFilesystem());

        $this->assertFalse($storage->setLegalHold('object_does_not_exist', true));
    }

    public function testDeniedAuthorizationBlocksStorageAndRecordsPermissionStatus(): void
    {
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $storage = new SqliteObjectStorage($this->tempPath('main'), $this->realFilesystem(), $authorization);

        $result = $storage->store('report.pdf', 'export_package', 'bytes', ['identity_ref' => 'user_1', 'permission_ref' => 'permission:denied']);

        $this->assertSame('rejected', $result['outcome']);
        $recent = $storage->recent(1);
        $this->assertSame('denied', $recent[0]['permission_status']);
    }

    public function testGrantedAuthorizationRecordsPermissionStatus(): void
    {
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $storage = new SqliteObjectStorage($this->tempPath('main'), $this->realFilesystem(), $authorization);

        $storage->store('report.pdf', 'export_package', 'bytes', ['identity_ref' => 'user_1', 'permission_ref' => 'permission:allowed']);

        $recent = $storage->recent(1);
        $this->assertSame('granted', $recent[0]['permission_status']);
    }

    public function testOperationDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('object.stored', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $storage = new SqliteObjectStorage($this->tempPath('main'), $this->realFilesystem(), events: $events);
        $storage->store('report.pdf', 'export_package', 'bytes');

        $this->assertCount(1, $captured);
    }

    public function testOperationRetrievesAPersistedAuditRecordByRef(): void
    {
        $storage = new SqliteObjectStorage($this->tempPath('main'), $this->realFilesystem());
        $storage->store('report.pdf', 'export_package', 'bytes', ['requesting_component' => 'export-service']);

        $recent = $storage->recent(1);

        $this->assertSame('export-service', $recent[0]['requesting_component']);
        $fetched = $storage->operation($recent[0]['operation_ref']);
        $this->assertNotNull($fetched);
        $this->assertSame('stored', $fetched['outcome']);
    }
}
