<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Security\StaticAuthorizationManager;
use SquirrelForge\Storage\SqliteArchiveStorage;
use SquirrelForge\Storage\SqliteDocumentStorage;
use SquirrelForge\Storage\SqliteObjectStorage;
use SquirrelForge\Tools\LocalFileSystem;

final class SqliteArchiveStorageTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-archive-storage-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function realFilesystem(): LocalFileSystem
    {
        $root = sys_get_temp_dir() . '/squirrelforge-archive-storage-fs-' . bin2hex(random_bytes(8));
        mkdir($root);
        $this->filesystemRoots[] = $root;

        return new LocalFileSystem($root);
    }

    public function testArchiveRejectsAnUnknownSourceType(): void
    {
        $archive = new SqliteArchiveStorage($this->tempPath('main'));

        $result = $archive->archive('astrology', 'ref_1', 30);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testArchiveRejectsARetentionPeriodBelowOneDay(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $archive = new SqliteArchiveStorage($this->tempPath('main'), $documents);

        $result = $archive->archive('document', 'document_1', 0);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testArchiveOfAMissingDocumentIsRejected(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $archive = new SqliteArchiveStorage($this->tempPath('main'), $documents);

        $result = $archive->archive('document', 'document_missing', 30);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('not found', $result['error']);
    }

    public function testArchiveCapturesRealDocumentContentAndItIsRetrievable(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $archive = new SqliteArchiveStorage($this->tempPath('main'), $documents);

        $result = $archive->archive('document', $stored['document_ref'], 30);
        $retrieved = $archive->retrieve($result['archive_ref']);

        $this->assertSame('archived', $result['outcome']);
        $this->assertTrue($retrieved['found']);
        $this->assertSame(['days' => 30], $retrieved['content']);
        $this->assertSame('document', $retrieved['source_type']);
    }

    public function testArchiveCapturesRealObjectContentAndItIsRetrievable(): void
    {
        $objects = new SqliteObjectStorage($this->tempPath('objects'), $this->realFilesystem());
        $stored = $objects->store('file.txt', 'text/plain', 'hello world');
        $archive = new SqliteArchiveStorage($this->tempPath('main'), null, $objects);

        $result = $archive->archive('object', $stored['object_ref'], 30);
        $retrieved = $archive->retrieve($result['archive_ref']);

        $this->assertSame('archived', $result['outcome']);
        $this->assertTrue($retrieved['found']);
        $this->assertSame('hello world', $retrieved['content']);
    }

    public function testRetrieveOfAnUnknownArchiveIsNotFound(): void
    {
        $archive = new SqliteArchiveStorage($this->tempPath('main'));

        $result = $archive->retrieve('archive_unknown');

        $this->assertFalse($result['found']);
    }

    public function testDisposeIsBlockedWithoutAGovernanceAuthorizationReference(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $archive = new SqliteArchiveStorage($this->tempPath('main'), $documents);
        $archived = $archive->archive('document', $stored['document_ref'], 30);

        $result = $archive->dispose($archived['archive_ref'], '');

        $this->assertSame('authorization_required', $result['outcome']);
    }

    public function testDisposeIsBlockedBeforeTheRetentionPeriodElapses(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $archive = new SqliteArchiveStorage($this->tempPath('main'), $documents);
        $archived = $archive->archive('document', $stored['document_ref'], 30);

        $result = $archive->dispose($archived['archive_ref'], 'governance_ref_1');

        $this->assertSame('retention_not_elapsed', $result['outcome']);
    }

    public function testDisposeSucceedsOnceTheRetentionPeriodHasElapsedAndPurgesContent(): void
    {
        $path = $this->tempPath('main');
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);

        $early = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $late = new DateTimeImmutable('2026-02-01T00:01:00Z');

        $archiveAtCreation = new SqliteArchiveStorage($path, $documents, clock: fn(): DateTimeImmutable => $early);
        $archived = $archiveAtCreation->archive('document', $stored['document_ref'], 30);

        $archiveAtDisposal = new SqliteArchiveStorage($path, $documents, clock: fn(): DateTimeImmutable => $late);
        $result = $archiveAtDisposal->dispose($archived['archive_ref'], 'governance_ref_1');
        $retrieved = $archiveAtDisposal->retrieve($archived['archive_ref']);

        $this->assertSame('disposed', $result['outcome']);
        $this->assertFalse($retrieved['found'], 'Retrieval must fail once the archive has been disposed.');
    }

    public function testDisposeIsIdempotentAfterTheFirstSuccessfulDisposal(): void
    {
        $path = $this->tempPath('main');
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);

        $early = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $late = new DateTimeImmutable('2026-02-01T00:01:00Z');

        $archiveAtCreation = new SqliteArchiveStorage($path, $documents, clock: fn(): DateTimeImmutable => $early);
        $archived = $archiveAtCreation->archive('document', $stored['document_ref'], 30);

        $archiveAtDisposal = new SqliteArchiveStorage($path, $documents, clock: fn(): DateTimeImmutable => $late);
        $firstDispose = $archiveAtDisposal->dispose($archived['archive_ref'], 'governance_ref_1');
        $secondDispose = $archiveAtDisposal->dispose($archived['archive_ref'], 'governance_ref_1');
        $retrievedAfterDisposal = $archiveAtDisposal->retrieve($archived['archive_ref']);

        $this->assertSame('disposed', $firstDispose['outcome']);
        $this->assertSame('already_disposed', $secondDispose['outcome']);
        $this->assertFalse($retrievedAfterDisposal['found']);

        $metadata = $archiveAtDisposal->get($archived['archive_ref']);
        $this->assertTrue($metadata['disposed']);
    }

    public function testDeniedAuthorizationBlocksArchiveAndRetrieve(): void
    {
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $archive = new SqliteArchiveStorage($this->tempPath('main'), $documents, null, $authorization);

        $result = $archive->archive('document', $stored['document_ref'], 30, ['identity_ref' => 'user_1', 'permission_ref' => 'permission:denied']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testGetReturnsMetadataWithoutContent(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $archive = new SqliteArchiveStorage($this->tempPath('main'), $documents);
        $archived = $archive->archive('document', $stored['document_ref'], 30);

        $metadata = $archive->get($archived['archive_ref']);

        $this->assertArrayNotHasKey('content_blob', $metadata);
        $this->assertArrayNotHasKey('checksum', $metadata);
        $this->assertFalse($metadata['disposed']);
    }

    public function testRecentReturnsRecordedOperations(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $archive = new SqliteArchiveStorage($this->tempPath('main'), $documents);
        $archived = $archive->archive('document', $stored['document_ref'], 30);
        $archive->retrieve($archived['archive_ref']);

        $this->assertCount(2, $archive->recent());
    }
}
