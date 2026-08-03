<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Governance\SqliteVersionManager;
use SquirrelForge\Storage\CacheManager;
use SquirrelForge\Storage\SqliteBackupManager;
use SquirrelForge\Storage\SqliteDocumentStorage;
use SquirrelForge\Storage\SqliteObjectStorage;
use SquirrelForge\Storage\SqliteStorageManager;
use SquirrelForge\Storage\SqliteVectorStorage;
use SquirrelForge\Tools\LocalFileSystem;

final class SqliteStorageManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-storage-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function realFilesystem(): LocalFileSystem
    {
        $root = sys_get_temp_dir() . '/squirrelforge-storage-manager-fs-' . bin2hex(random_bytes(8));
        mkdir($root);
        $this->filesystemRoots[] = $root;

        return new LocalFileSystem($root);
    }

    private function makeManager(): SqliteStorageManager
    {
        $objects = new SqliteObjectStorage($this->tempPath('objects'), $this->realFilesystem());
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $vectors = new SqliteVectorStorage($this->tempPath('vectors'));
        $cache = new CacheManager();
        $backups = new SqliteBackupManager($this->tempPath('backups'), $documents, $objects);
        $versions = new SqliteVersionManager($this->tempPath('versions'));

        return new SqliteStorageManager($this->tempPath('manager'), $objects, $documents, $vectors, $cache, $backups, $versions);
    }

    public function testUnknownOperationIsRejected(): void
    {
        $manager = new SqliteStorageManager($this->tempPath('manager'));

        $result = $manager->coordinate('teleport', []);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testUnsupportedOperationIsEscalated(): void
    {
        $manager = new SqliteStorageManager($this->tempPath('manager'));

        $result = $manager->coordinate('archive', []);

        $this->assertSame('escalated', $result['outcome']);
    }

    public function testMissingRequiredFieldIsRejected(): void
    {
        $manager = new SqliteStorageManager($this->tempPath('manager'));

        $result = $manager->coordinate('store_object', ['name' => 'file.txt']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('type', $result['error']);
    }

    public function testStoreObjectThenRetrieveObjectRoundTrips(): void
    {
        $manager = $this->makeManager();

        $stored = $manager->coordinate('store_object', ['name' => 'file.txt', 'type' => 'text/plain', 'contents' => 'hello']);
        $retrieved = $manager->coordinate('retrieve_object', ['object_ref' => $stored['storage_destination']]);

        $this->assertSame('stored', $stored['outcome']);
        $this->assertSame('object', $stored['storage_type']);
        $this->assertSame('retrieved', $retrieved['outcome']);
        $this->assertSame('hello', $retrieved['result']['contents']);
    }

    public function testStoreDocumentThenRetrieveDocumentRoundTrips(): void
    {
        $manager = $this->makeManager();

        $stored = $manager->coordinate('store_document', ['type' => 'policy', 'title' => 'Retention', 'content' => ['days' => 30]]);
        $retrieved = $manager->coordinate('retrieve_document', ['document_ref' => $stored['storage_destination']]);

        $this->assertSame('stored', $stored['outcome']);
        $this->assertSame('retrieved', $retrieved['outcome']);
        $this->assertSame(['days' => 30], $retrieved['result']['content']);
    }

    public function testStoreVectorThenSearchVectorFindsIt(): void
    {
        $manager = $this->makeManager();

        $stored = $manager->coordinate('store_vector', ['collection_id' => 'embeddings', 'vector' => [1.0, 0.0, 0.0]]);
        $searched = $manager->coordinate('search_vector', ['collection_id' => 'embeddings', 'query_vector' => [1.0, 0.0, 0.0]]);

        $this->assertSame('stored', $stored['outcome']);
        $this->assertSame('vector', $stored['storage_type']);
        $this->assertSame('searched', $searched['outcome']);
        $this->assertCount(1, $searched['result']['results']);
        $this->assertSame($stored['storage_destination'], $searched['result']['results'][0]['vector_ref']);
    }

    public function testRetrieveVectorRoundTrips(): void
    {
        $manager = $this->makeManager();

        $stored = $manager->coordinate('store_vector', ['collection_id' => 'embeddings', 'vector' => [0.5, 0.5]]);
        $retrieved = $manager->coordinate('retrieve_vector', ['vector_ref' => $stored['storage_destination']]);

        $this->assertSame('retrieved', $retrieved['outcome']);
        $this->assertSame([0.5, 0.5], $retrieved['result']['vector']);
    }

    public function testCachePutGetForgetRoundTrips(): void
    {
        $manager = $this->makeManager();

        $put = $manager->coordinate('cache_put', ['key' => 'k1', 'value' => 'v1', 'ttl_seconds' => 60]);
        $hit = $manager->coordinate('cache_get', ['key' => 'k1']);
        $forgotten = $manager->coordinate('cache_forget', ['key' => 'k1']);
        $miss = $manager->coordinate('cache_get', ['key' => 'k1']);

        $this->assertSame('cached', $put['outcome']);
        $this->assertSame('hit', $hit['outcome']);
        $this->assertSame('v1', $hit['result']['value']);
        $this->assertSame('forgotten', $forgotten['outcome']);
        $this->assertSame('miss', $miss['outcome']);
    }

    public function testBackupThenRestoreRoundTrips(): void
    {
        $manager = $this->makeManager();
        $stored = $manager->coordinate('store_document', ['type' => 'policy', 'title' => 'Retention', 'content' => ['days' => 30]]);

        $backup = $manager->coordinate('backup', ['source_type' => 'document', 'source_ref' => $stored['storage_destination']]);
        $restore = $manager->coordinate('restore', ['backup_ref' => $backup['storage_destination']]);

        $this->assertSame('created', $backup['outcome']);
        $this->assertSame('backup', $backup['storage_type']);
        $this->assertSame('restored', $restore['outcome']);
    }

    public function testRegisterVersionRoutesToTheRealVersionManager(): void
    {
        $manager = $this->makeManager();

        $result = $manager->coordinate('register_version', ['record_id' => 'record_1', 'record_type' => 'policy', 'content' => ['status' => 'draft']]);

        $this->assertSame('created', $result['outcome']);
        $this->assertSame('version', $result['storage_type']);
        $this->assertNotNull($result['storage_destination']);
    }

    public function testUnconfiguredComponentIsRejected(): void
    {
        $manager = new SqliteStorageManager($this->tempPath('manager'));

        $result = $manager->coordinate('store_vector', ['collection_id' => 'embeddings', 'vector' => [1.0]]);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('Vector Storage', $result['error']);
    }

    public function testEveryOperationIsRecordedAndRetrievable(): void
    {
        $manager = $this->makeManager();

        $result = $manager->coordinate('cache_put', ['key' => 'k1', 'value' => 'v1', 'ttl_seconds' => 60]);
        $record = $manager->operation($result['storage_operation_id']);

        $this->assertSame('cached', $record['final_outcome']);
        $this->assertSame('cache', $record['storage_type']);
        $this->assertSame('delegated', $record['permission_status']);
        $this->assertSame('ungoverned', $record['governance_status']);
    }

    public function testRecentReturnsRecordedOperations(): void
    {
        $manager = $this->makeManager();
        $manager->coordinate('cache_put', ['key' => 'k1', 'value' => 'v1', 'ttl_seconds' => 60]);
        $manager->coordinate('cache_put', ['key' => 'k2', 'value' => 'v2', 'ttl_seconds' => 60]);

        $this->assertCount(2, $manager->recent());
    }
}
