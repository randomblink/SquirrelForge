<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Storage\SqliteDocumentStorage;
use SquirrelForge\Storage\SqliteObjectStorage;
use SquirrelForge\Storage\SqliteStorageReplication;
use SquirrelForge\Tools\LocalFileSystem;

final class SqliteStorageReplicationTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-storage-replication-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function realFilesystem(): LocalFileSystem
    {
        $root = sys_get_temp_dir() . '/squirrelforge-storage-replication-fs-' . bin2hex(random_bytes(8));
        mkdir($root);
        $this->filesystemRoots[] = $root;

        return new LocalFileSystem($root);
    }

    public function testRegisterTargetRejectsAnUnknownSourceType(): void
    {
        $replication = new SqliteStorageReplication($this->tempPath('main'));
        $replica = new SqliteDocumentStorage($this->tempPath('replica'));

        $result = $replication->registerTarget('replica_1', 'astrology', $replica);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testReplicateWithNoRegisteredTargetsReportsNoTargets(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('primary'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $replication = new SqliteStorageReplication($this->tempPath('main'), $documents);

        $result = $replication->replicate('document', $stored['document_ref']);

        $this->assertSame('no_targets', $result['outcome']);
    }

    public function testReplicateOfAMissingSourceIsRejected(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('primary'));
        $replication = new SqliteStorageReplication($this->tempPath('main'), $documents);

        $result = $replication->replicate('document', 'document_missing');

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testReplicateCopiesRealDocumentContentToARegisteredTargetAndItIsIndependentlyRetrievable(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('primary'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $replica = new SqliteDocumentStorage($this->tempPath('replica'));
        $replication = new SqliteStorageReplication($this->tempPath('main'), $documents);
        $replication->registerTarget('replica_1', 'document', $replica);

        $result = $replication->replicate('document', $stored['document_ref']);

        $this->assertSame('replicated', $result['outcome']);
        $this->assertSame('replicated', $result['targets']['replica_1']['outcome']);
        $this->assertSame('current', $replication->health('document', $stored['document_ref'])['replica_1']);

        $replicaDocuments = $replica->search(['type' => 'replica']);
        $this->assertCount(1, $replicaDocuments);
        $replicaContent = $replica->retrieve($replicaDocuments[0]['document_ref']);
        $this->assertSame(['days' => 30], $replicaContent['content']);
    }

    public function testReplicateCopiesRealObjectContentToARegisteredTarget(): void
    {
        $objects = new SqliteObjectStorage($this->tempPath('primary'), $this->realFilesystem());
        $stored = $objects->store('file.txt', 'text/plain', 'hello world');
        $replicaObjects = new SqliteObjectStorage($this->tempPath('replica'), $this->realFilesystem());
        $replication = new SqliteStorageReplication($this->tempPath('main'), null, $objects);
        $replication->registerTarget('replica_1', 'object', $replicaObjects);

        $result = $replication->replicate('object', $stored['object_ref']);

        $this->assertSame('replicated', $result['outcome']);
        $this->assertSame('replicated', $result['targets']['replica_1']['outcome']);
    }

    public function testReReplicatingAnUpdatedSourceUpdatesTheSameReplicaInsteadOfCreatingANewOne(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('primary'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $replica = new SqliteDocumentStorage($this->tempPath('replica'));
        $replication = new SqliteStorageReplication($this->tempPath('main'), $documents);
        $replication->registerTarget('replica_1', 'document', $replica);
        $replication->replicate('document', $stored['document_ref']);

        $documents->updateVersion($stored['document_ref'], ['days' => 60]);
        $replication->replicate('document', $stored['document_ref']);

        $replicaDocuments = $replica->search(['type' => 'replica']);
        $this->assertCount(1, $replicaDocuments, 'Re-replication must update the existing replica, not create a second one.');
        $replicaContent = $replica->retrieve($replicaDocuments[0]['document_ref']);
        $this->assertSame(['days' => 60], $replicaContent['content']);
    }

    public function testHealthReportsNeverReplicatedForATargetThatHasNoReplicationRecordYet(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('primary'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $replica = new SqliteDocumentStorage($this->tempPath('replica'));
        $replication = new SqliteStorageReplication($this->tempPath('main'), $documents);
        $replication->registerTarget('replica_1', 'document', $replica);

        $health = $replication->health('document', $stored['document_ref']);

        $this->assertSame('never_replicated', $health['replica_1']);
    }

    public function testHealthReportsLaggingAfterTheSourceIsUpdatedWithoutReReplicating(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('primary'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $replica = new SqliteDocumentStorage($this->tempPath('replica'));
        $replication = new SqliteStorageReplication($this->tempPath('main'), $documents);
        $replication->registerTarget('replica_1', 'document', $replica);
        $replication->replicate('document', $stored['document_ref']);

        $documents->updateVersion($stored['document_ref'], ['days' => 60]);

        $this->assertSame('lagging', $replication->health('document', $stored['document_ref'])['replica_1']);
    }

    public function testVerifyReportsHealthyForAnUnmodifiedReplica(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('primary'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $replica = new SqliteDocumentStorage($this->tempPath('replica'));
        $replication = new SqliteStorageReplication($this->tempPath('main'), $documents);
        $replication->registerTarget('replica_1', 'document', $replica);
        $replication->replicate('document', $stored['document_ref']);

        $result = $replication->verify('document', $stored['document_ref'], 'replica_1');

        $this->assertSame('healthy', $result['outcome']);
    }

    public function testVerifyReportsDivergedWhenTheReplicaWasIndependentlyModified(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('primary'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $replica = new SqliteDocumentStorage($this->tempPath('replica'));
        $replication = new SqliteStorageReplication($this->tempPath('main'), $documents);
        $replication->registerTarget('replica_1', 'document', $replica);
        $replication->replicate('document', $stored['document_ref']);

        $replicaDocuments = $replica->search(['type' => 'replica']);
        $replica->updateVersion($replicaDocuments[0]['document_ref'], ['days' => 999]);

        $result = $replication->verify('document', $stored['document_ref'], 'replica_1');

        $this->assertSame('diverged', $result['outcome']);
    }

    public function testVerifyReportsUnreachableWithoutAPriorReplicationRecord(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('primary'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $replication = new SqliteStorageReplication($this->tempPath('main'), $documents);

        $result = $replication->verify('document', $stored['document_ref'], 'replica_1');

        $this->assertSame('unreachable', $result['outcome']);
    }

    public function testRecentReturnsRecordedOperations(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('primary'));
        $stored = $documents->store('policy', 'Retention', ['days' => 30]);
        $replica = new SqliteDocumentStorage($this->tempPath('replica'));
        $replication = new SqliteStorageReplication($this->tempPath('main'), $documents);
        $replication->registerTarget('replica_1', 'document', $replica);

        $replication->replicate('document', $stored['document_ref']);

        $this->assertCount(1, $replication->recent());
    }
}
