<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Storage\SqliteArchiveStorage;
use SquirrelForge\Storage\SqliteDocumentStorage;
use SquirrelForge\Storage\SqliteIndexManager;
use SquirrelForge\Storage\SqliteObjectStorage;
use SquirrelForge\Storage\SqliteRetrievalManager;
use SquirrelForge\Storage\SqliteVectorStorage;
use SquirrelForge\Tools\LocalFileSystem;

final class SqliteRetrievalManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-retrieval-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function realFilesystem(): LocalFileSystem
    {
        $root = sys_get_temp_dir() . '/squirrelforge-retrieval-manager-fs-' . bin2hex(random_bytes(8));
        mkdir($root);
        $this->filesystemRoots[] = $root;

        return new LocalFileSystem($root);
    }

    /**
     * @return array{0: SqliteRetrievalManager, 1: SqliteObjectStorage, 2: SqliteDocumentStorage, 3: SqliteVectorStorage, 4: SqliteIndexManager}
     */
    private function fullyWiredManager(): array
    {
        $objects = new SqliteObjectStorage($this->tempPath('objects'), $this->realFilesystem());
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $vectors = new SqliteVectorStorage($this->tempPath('vectors'));
        $index = new SqliteIndexManager($this->tempPath('index'));
        $manager = new SqliteRetrievalManager($this->tempPath('retrieval'), $objects, $documents, $vectors, $index);

        return [$manager, $objects, $documents, $vectors, $index];
    }

    public function testRetrieveByReferenceRejectsAnUnsupportedStorageType(): void
    {
        [$manager] = $this->fullyWiredManager();

        $result = $manager->retrieveByReference('astrology', 'ref_1');

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testRetrieveByReferenceOfArchiveIsRejectedWithoutAConfiguredArchiveStorage(): void
    {
        [$manager] = $this->fullyWiredManager();

        $result = $manager->retrieveByReference('archive', 'archive_1');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testRetrieveByReferenceRoutesArchiveToTheRealArchiveStorage(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $archive = new SqliteArchiveStorage($this->tempPath('archive'), $documents);
        $archived = $archive->archive('document', $stored['document_ref'], 30);
        $manager = new SqliteRetrievalManager($this->tempPath('retrieval'), archive: $archive);

        $result = $manager->retrieveByReference('archive', $archived['archive_ref']);

        $this->assertSame('retrieved', $result['outcome']);
        $this->assertSame(['days' => 30], $result['record']['content']);
    }

    public function testRetrieveByReferenceReturnsNotFoundForAnUnknownReference(): void
    {
        [$manager] = $this->fullyWiredManager();

        $result = $manager->retrieveByReference('object', 'object_unknown');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testRetrieveByReferenceRetrievesARealObject(): void
    {
        [$manager, $objects] = $this->fullyWiredManager();
        $stored = $objects->store('file.txt', 'text/plain', 'hello world');

        $result = $manager->retrieveByReference('object', $stored['object_ref']);

        $this->assertSame('retrieved', $result['outcome']);
        $this->assertSame('hello world', $result['record']['contents']);
        $this->assertNotNull($manager->operation($result['retrieval_id']));
    }

    public function testRetrieveByReferenceRetrievesARealDocument(): void
    {
        [$manager, , $documents] = $this->fullyWiredManager();
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);

        $result = $manager->retrieveByReference('document', $stored['document_ref']);

        $this->assertSame('retrieved', $result['outcome']);
        $this->assertSame(['days' => 30], $result['record']['content']);
    }

    public function testRetrieveByReferenceRetrievesARealVector(): void
    {
        [$manager, , , $vectors] = $this->fullyWiredManager();
        $stored = $vectors->store('embeddings', [0.1, 0.2]);

        $result = $manager->retrieveByReference('vector', $stored['vector_ref']);

        $this->assertSame('retrieved', $result['outcome']);
        $this->assertSame([0.1, 0.2], $result['record']['vector']);
    }

    public function testRetrieveBatchSeparatesFoundFromNotFound(): void
    {
        [$manager, , $documents] = $this->fullyWiredManager();
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);

        $result = $manager->retrieveBatch('document', [$stored['document_ref'], 'unknown_ref']);

        $this->assertCount(1, $result['retrieved']);
        $this->assertSame(['unknown_ref'], $result['not_found']);
    }

    public function testRetrieveByIndexResolvesADocumentSourceType(): void
    {
        [$manager, , $documents, , $index] = $this->fullyWiredManager();
        $stored = $documents->store('policy', 'Checkout retention policy', ['days' => 30]);
        $index->indexRecord('documents', $stored['document_ref'], ['title' => 'Checkout retention policy']);

        $result = $manager->retrieveByIndex(['checkout']);

        $this->assertSame('searched', $result['outcome']);
        $this->assertCount(1, $result['results']);
        $this->assertSame(['days' => 30], $result['results'][0]['record']['content']);
    }

    public function testRetrieveByIndexReturnsNullRecordForAnUnmappedSourceType(): void
    {
        [$manager, , , , $index] = $this->fullyWiredManager();
        $index->indexRecord('workflow_records', 'wf_1', ['title' => 'Checkout workflow']);

        $result = $manager->retrieveByIndex(['checkout']);

        $this->assertCount(1, $result['results']);
        $this->assertNull($result['results'][0]['record']);
    }

    public function testRetrieveByIndexWithoutAnIndexManagerIsNotConfigured(): void
    {
        $manager = new SqliteRetrievalManager($this->tempPath('retrieval'));

        $result = $manager->retrieveByIndex(['checkout']);

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testRetrieveHistoryReturnsRealObjectVersions(): void
    {
        [$manager, $objects] = $this->fullyWiredManager();
        $stored = $objects->store('file.txt', 'text/plain', 'v1');
        $objects->updateVersion($stored['object_ref'], 'v2');

        $result = $manager->retrieveHistory('object', $stored['object_ref']);

        $this->assertSame('retrieved', $result['outcome']);
        $this->assertCount(2, $result['versions']);
    }

    public function testRetrieveHistoryIsNotApplicableForVectors(): void
    {
        [$manager, , , $vectors] = $this->fullyWiredManager();
        $stored = $vectors->store('embeddings', [0.1]);

        $result = $manager->retrieveHistory('vector', $stored['vector_ref']);

        $this->assertSame('not_applicable', $result['outcome']);
    }

    public function testRecentReturnsRecordedOperations(): void
    {
        [$manager, $objects] = $this->fullyWiredManager();
        $stored = $objects->store('file.txt', 'text/plain', 'hello');
        $manager->retrieveByReference('object', $stored['object_ref']);
        $manager->retrieveByReference('object', 'unknown_ref');

        $this->assertCount(2, $manager->recent());
    }
}
