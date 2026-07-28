<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Storage\CacheManager;
use SquirrelForge\Storage\SqliteBackupManager;
use SquirrelForge\Storage\SqliteDataManager;
use SquirrelForge\Storage\SqliteDataValidator;
use SquirrelForge\Storage\SqliteDocumentStorage;
use SquirrelForge\Storage\SqliteObjectStorage;
use SquirrelForge\Tools\LocalFileSystem;

final class SqliteDataManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-data-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function realFilesystem(): LocalFileSystem
    {
        $root = sys_get_temp_dir() . '/squirrelforge-data-manager-fs-' . bin2hex(random_bytes(8));
        mkdir($root);
        $this->filesystemRoots[] = $root;

        return new LocalFileSystem($root);
    }

    private function makeManager(): SqliteDataManager
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $objects = new SqliteObjectStorage($this->tempPath('objects'), $this->realFilesystem());
        $cache = new CacheManager();
        $backups = new SqliteBackupManager($this->tempPath('backups'), $documents, $objects);
        $validator = new SqliteDataValidator($this->tempPath('validator'));

        return new SqliteDataManager($this->tempPath('manager'), $documents, $objects, $cache, $backups, $validator);
    }

    public function testValidateRoutesToDataValidator(): void
    {
        $manager = $this->makeManager();
        $result = $manager->coordinate('validate', ['record_type' => 'workflow', 'record_id' => 'wf_1', 'data' => ['name' => 'Deploy']]);

        $this->assertSame('valid', $result['outcome']);
        $this->assertSame('validated', $result['validation_status']);
        $this->assertSame('data_validator', $result['target_component']);
    }

    public function testStoreDocumentThenRetrieveDocumentRoundTrips(): void
    {
        $manager = $this->makeManager();
        $stored = $manager->coordinate('store_document', ['type' => 'policy', 'title' => 'Retention', 'content' => ['days' => 30]]);

        $this->assertSame('stored', $stored['outcome']);
        $documentRef = $stored['result']['document_ref'];

        $retrieved = $manager->coordinate('retrieve_document', ['document_ref' => $documentRef]);

        $this->assertSame('retrieved', $retrieved['outcome']);
        $this->assertSame(['days' => 30], $retrieved['result']['content']);
    }

    public function testUpdateDocumentAppendsANewVersion(): void
    {
        $manager = $this->makeManager();
        $stored = $manager->coordinate('store_document', ['type' => 'policy', 'title' => 'Retention', 'content' => ['days' => 30]]);
        $documentRef = $stored['result']['document_ref'];

        $updated = $manager->coordinate('update_document', ['document_ref' => $documentRef, 'content' => ['days' => 90]]);

        $this->assertSame('updated', $updated['outcome']);
        $this->assertSame(2, $updated['result']['version']);
    }

    public function testStoreObjectThenRetrieveObjectRoundTrips(): void
    {
        $manager = $this->makeManager();
        $stored = $manager->coordinate('store_object', ['name' => 'report.pdf', 'type' => 'export_package', 'contents' => 'binary-bytes']);

        $this->assertSame('stored', $stored['outcome']);
        $objectRef = $stored['result']['object_ref'];

        $retrieved = $manager->coordinate('retrieve_object', ['object_ref' => $objectRef]);

        $this->assertSame('retrieved', $retrieved['outcome']);
        $this->assertSame('binary-bytes', $retrieved['result']['contents']);
    }

    public function testUpdateObjectAppendsANewVersion(): void
    {
        $manager = $this->makeManager();
        $stored = $manager->coordinate('store_object', ['name' => 'model.bin', 'type' => 'model_artifact', 'contents' => 'v1']);
        $objectRef = $stored['result']['object_ref'];

        $updated = $manager->coordinate('update_object', ['object_ref' => $objectRef, 'contents' => 'v2']);

        $this->assertSame('updated', $updated['outcome']);
        $this->assertSame(2, $updated['result']['version']);
    }

    public function testBackupThenRestoreRoundTripsThroughBackupManager(): void
    {
        $manager = $this->makeManager();
        $stored = $manager->coordinate('store_document', ['type' => 'policy', 'title' => 'Retention', 'content' => ['days' => 30]]);
        $documentRef = $stored['result']['document_ref'];

        $backup = $manager->coordinate('backup', ['source_type' => 'document', 'source_ref' => $documentRef]);
        $this->assertSame('created', $backup['outcome']);

        $manager->coordinate('update_document', ['document_ref' => $documentRef, 'content' => ['days' => 90]]);

        $restore = $manager->coordinate('restore', ['backup_ref' => $backup['result']['backup_ref']]);

        $this->assertSame('restored', $restore['outcome']);

        $current = $manager->coordinate('retrieve_document', ['document_ref' => $documentRef]);
        $this->assertSame(['days' => 30], $current['result']['content']);
    }

    public function testCachePutGetAndForgetRoutesToCacheManager(): void
    {
        $manager = $this->makeManager();

        $put = $manager->coordinate('cache_put', ['key' => 'greeting', 'value' => 'hello', 'ttl_seconds' => 60]);
        $this->assertSame('cached', $put['outcome']);

        $hit = $manager->coordinate('cache_get', ['key' => 'greeting']);
        $this->assertSame('hit', $hit['outcome']);
        $this->assertSame('hello', $hit['result']['value']);

        $forget = $manager->coordinate('cache_forget', ['key' => 'greeting']);
        $this->assertSame('forgotten', $forget['outcome']);

        $miss = $manager->coordinate('cache_get', ['key' => 'greeting']);
        $this->assertSame('miss', $miss['outcome']);
    }

    public function testCacheGetOnUnknownKeyIsAMiss(): void
    {
        $manager = $this->makeManager();

        $result = $manager->coordinate('cache_get', ['key' => 'never-set']);

        $this->assertSame('miss', $result['outcome']);
        $this->assertNull($result['result']['value']);
    }

    public function testMissingDependencyIsRejectedNotFatal(): void
    {
        $manager = new SqliteDataManager($this->tempPath('manager-bare'));

        $result = $manager->coordinate('store_document', ['type' => 'policy', 'title' => 'x', 'content' => []]);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('not configured', (string) $result['error']);
    }

    public function testUnsupportedOperationEscalatesWithoutTouchingAnyComponent(): void
    {
        $manager = $this->makeManager();

        $result = $manager->coordinate('search_index', ['query' => 'anything']);

        $this->assertSame('escalated', $result['outcome']);
        $this->assertSame('none', $result['target_component']);
    }

    public function testUnknownOperationIsRejected(): void
    {
        $manager = $this->makeManager();

        $result = $manager->coordinate('teleport_data', []);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('invalid', $result['validation_status']);
    }

    public function testMissingRequiredFieldIsRejectedBeforeRoutingIsAttempted(): void
    {
        $manager = $this->makeManager();

        $result = $manager->coordinate('store_document', ['type' => 'policy', 'title' => 'x']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('content', (string) $result['error']);
    }

    public function testOperationRetrievesAPersistedAuditRecordByRef(): void
    {
        $manager = $this->makeManager();
        $manager->coordinate('cache_put', ['key' => 'k', 'value' => 'v', 'ttl_seconds' => 30]);

        $recent = $manager->recent(1);
        $fetched = $manager->operation($recent[0]['operation_ref']);

        $this->assertNotNull($fetched);
        $this->assertSame('cached', $fetched['outcome']);
    }
}
