<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Knowledge\SqliteCitationManager;
use SquirrelForge\Knowledge\SqliteDocumentRepository;
use SquirrelForge\Knowledge\SqliteKnowledgeVersioning;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class SqliteDocumentRepositoryTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }
    }

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-knowledge-repo-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    public function testRegisterReferenceWithoutStorageReferenceStartsAsDraft(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));
        $result = $repository->registerReference('Deploy Runbook', 'workflow');

        $this->assertTrue($result['found']);
        $metadata = $repository->readMetadata($result['document_id']);
        $this->assertSame('draft', $metadata['status']);
    }

    public function testRegisterReferenceRejectsUnknownType(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));
        $result = $repository->registerReference('Deploy Runbook', 'napkin_sketch');

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('Unknown document type', (string) $result['error']);
    }

    public function testRegisterReferenceVerifiesAStorageReferenceThatDoesResolve(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $stored = $documents->store('workflow_definition', 'Deploy Pipeline', ['steps' => []]);
        $repository = new SqliteDocumentRepository($this->tempPath('main'), $documents);

        $result = $repository->registerReference('Deploy Pipeline', 'workflow', ['storage_reference' => $stored['document_ref']]);

        $this->assertTrue($result['found']);
        $metadata = $repository->readMetadata($result['document_id']);
        $this->assertSame('active', $metadata['status']);
        $this->assertSame($stored['document_ref'], $metadata['storage_reference']);
    }

    public function testRegisterReferenceRejectsAStorageReferenceThatDoesNotResolve(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $repository = new SqliteDocumentRepository($this->tempPath('main'), $documents);

        $result = $repository->registerReference('Ghost Doc', 'workflow', ['storage_reference' => 'document_does_not_exist']);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('does not resolve', (string) $result['error']);
    }

    public function testExistsIsTrueForARegisteredReferenceRegardlessOfProtectionAndFalseOtherwise(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));
        $protected = $repository->registerReference('Secret Policy', 'policy', ['protected' => true]);

        $this->assertTrue($repository->exists($protected['document_id']));
        $this->assertFalse($repository->exists('knowledge_document_does_not_exist'));
    }

    public function testRegisterReferenceSkipsVerificationWhenNoDocumentStorageIsConfigured(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));

        $result = $repository->registerReference('Unverified Doc', 'workflow', ['storage_reference' => 'anything']);

        $this->assertTrue($result['found']);
    }

    public function testReadMetadataReturnsNotFoundForUnknownReference(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));

        $result = $repository->readMetadata('knowledge_document_does_not_exist');

        $this->assertFalse($result['found']);
    }

    public function testProtectedReferenceRequiresAnAuthorizedResult(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));
        $registered = $repository->registerReference('Secret Policy', 'policy', ['protected' => true]);

        $withoutAuth = $repository->readMetadata($registered['document_id']);
        $withDeniedAuth = $repository->readMetadata($registered['document_id'], ['authorized' => false]);
        $withGrantedAuth = $repository->readMetadata($registered['document_id'], ['authorized' => true]);

        $this->assertFalse($withoutAuth['found']);
        $this->assertFalse($withDeniedAuth['found']);
        $this->assertTrue($withGrantedAuth['found']);
    }

    public function testUpdateMetadataChangesTitleAndPreservesOtherFields(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));
        $registered = $repository->registerReference('Old Title', 'policy', ['owner' => 'team_a']);

        $repository->updateMetadata($registered['document_id'], ['title' => 'New Title']);

        $metadata = $repository->readMetadata($registered['document_id']);
        $this->assertSame('New Title', $metadata['title']);
        $this->assertSame('team_a', $metadata['owner']);
    }

    public function testUpdateMetadataRejectsUnknownType(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));
        $registered = $repository->registerReference('Title', 'policy');

        $result = $repository->updateMetadata($registered['document_id'], ['type' => 'napkin_sketch']);

        $this->assertFalse($result['found']);
    }

    public function testArchiveThenRestoreReference(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));
        $registered = $repository->registerReference('Old Policy', 'policy');

        $archived = $repository->archiveReference($registered['document_id']);
        $this->assertTrue($archived['found']);
        $this->assertSame('archived', $repository->readMetadata($registered['document_id'])['status']);

        $restored = $repository->restoreReference($registered['document_id']);
        $this->assertTrue($restored['found']);
        $this->assertSame('active', $repository->readMetadata($registered['document_id'])['status']);
    }

    public function testCannotArchiveAnAlreadyArchivedReference(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));
        $registered = $repository->registerReference('Old Policy', 'policy');
        $repository->archiveReference($registered['document_id']);

        $result = $repository->archiveReference($registered['document_id']);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('Cannot transition', (string) $result['error']);
    }

    public function testCannotRestoreAReferenceThatIsNotArchived(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));
        $registered = $repository->registerReference('Active Policy', 'policy');

        $result = $repository->restoreReference($registered['document_id']);

        $this->assertFalse($result['found']);
    }

    public function testSyncStatusFromStorageAutoArchivesWhenTheStoredDocumentWasDeleted(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $stored = $documents->store('workflow_definition', 'Deploy Pipeline', ['steps' => []]);
        $repository = new SqliteDocumentRepository($this->tempPath('main'), $documents);
        $registered = $repository->registerReference('Deploy Pipeline', 'workflow', ['storage_reference' => $stored['document_ref']]);

        $documents->delete($stored['document_ref']);
        $sync = $repository->syncStatusFromStorage($registered['document_id']);

        $this->assertTrue($sync['synced']);
        $this->assertTrue($sync['archived']);
        $this->assertSame('archived', $repository->readMetadata($registered['document_id'])['status']);
    }

    public function testSyncStatusFromStorageDoesNothingWhileTheStoredDocumentIsStillActive(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $stored = $documents->store('workflow_definition', 'Deploy Pipeline', ['steps' => []]);
        $repository = new SqliteDocumentRepository($this->tempPath('main'), $documents);
        $registered = $repository->registerReference('Deploy Pipeline', 'workflow', ['storage_reference' => $stored['document_ref']]);

        $sync = $repository->syncStatusFromStorage($registered['document_id']);

        $this->assertFalse($sync['synced']);
        $this->assertSame('active', $repository->readMetadata($registered['document_id'])['status']);
    }

    public function testSyncStatusFromStorageWithoutADocumentStorageConfiguredIsANoop(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));
        $registered = $repository->registerReference('Draft', 'policy');

        $sync = $repository->syncStatusFromStorage($registered['document_id']);

        $this->assertFalse($sync['synced']);
        $this->assertStringContainsString('not configured', (string) $sync['reason']);
    }

    public function testAttachVersionReference(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));
        $registered = $repository->registerReference('Policy', 'policy');

        $repository->attachVersionReference($registered['document_id'], 'version_ref_7');

        $this->assertSame('version_ref_7', $repository->readMetadata($registered['document_id'])['version_reference']);
    }

    public function testAttachVersionReferenceRejectsAnUnresolvableReferenceWhenVersioningIsConfigured(): void
    {
        $versioning = new SqliteKnowledgeVersioning($this->tempPath('versioning'));
        $repository = new SqliteDocumentRepository($this->tempPath('main'), versioning: $versioning);
        $registered = $repository->registerReference('Policy', 'policy');

        $result = $repository->attachVersionReference($registered['document_id'], 'kversion_unknown');

        $this->assertFalse($result['found']);
        $this->assertNull($repository->readMetadata($registered['document_id'])['version_reference']);
    }

    public function testAttachVersionReferenceAcceptsARealVersionWhenVersioningIsConfigured(): void
    {
        $versioning = new SqliteKnowledgeVersioning($this->tempPath('versioning'));
        $created = $versioning->createVersion('knowledge_1', ['body' => 'v1']);
        $repository = new SqliteDocumentRepository($this->tempPath('main'), versioning: $versioning);
        $registered = $repository->registerReference('Policy', 'policy');

        $result = $repository->attachVersionReference($registered['document_id'], $created['version_id']);

        $this->assertTrue($result['found']);
        $this->assertSame($created['version_id'], $repository->readMetadata($registered['document_id'])['version_reference']);
    }

    public function testRegisterReferenceRejectsAnUnresolvableVersionReferenceWhenVersioningIsConfigured(): void
    {
        $versioning = new SqliteKnowledgeVersioning($this->tempPath('versioning'));
        $repository = new SqliteDocumentRepository($this->tempPath('main'), versioning: $versioning);

        $result = $repository->registerReference('Policy', 'policy', ['version_reference' => 'kversion_unknown']);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('Knowledge Versioning', $result['error']);
    }

    public function testAttachCitationAppendsAndDeduplicates(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));
        $registered = $repository->registerReference('Policy', 'policy');

        $repository->attachCitation($registered['document_id'], 'citation_1');
        $repository->attachCitation($registered['document_id'], 'citation_2');
        $repository->attachCitation($registered['document_id'], 'citation_1');

        $this->assertSame(['citation_1', 'citation_2'], $repository->readMetadata($registered['document_id'])['citation_references']);
    }

    public function testAttachCitationRejectsAnUnresolvableReferenceWhenCitationManagerIsConfigured(): void
    {
        $citations = new SqliteCitationManager($this->tempPath('citations'));
        $repository = new SqliteDocumentRepository($this->tempPath('main'), citations: $citations);
        $registered = $repository->registerReference('Policy', 'policy');

        $result = $repository->attachCitation($registered['document_id'], 'citation_unknown');

        $this->assertFalse($result['found']);
        $this->assertSame([], $repository->readMetadata($registered['document_id'])['citation_references']);
    }

    public function testAttachCitationAcceptsARealCitationWhenCitationManagerIsConfigured(): void
    {
        $citations = new SqliteCitationManager($this->tempPath('citations'));
        $registeredCitation = $citations->registerCitation('knowledge_1', 'https://example.com', 'primary_source', ['url' => 'https://example.com']);
        $repository = new SqliteDocumentRepository($this->tempPath('main'), citations: $citations);
        $registered = $repository->registerReference('Policy', 'policy');

        $result = $repository->attachCitation($registered['document_id'], $registeredCitation['citation_id']);

        $this->assertTrue($result['found']);
        $this->assertSame([$registeredCitation['citation_id']], $repository->readMetadata($registered['document_id'])['citation_references']);
    }

    public function testSearchFiltersByTypeStatusAndOwner(): void
    {
        $repository = new SqliteDocumentRepository($this->tempPath('main'));
        $repository->registerReference('A', 'policy', ['owner' => 'team_a']);
        $repository->registerReference('B', 'workflow', ['owner' => 'team_b']);
        $archived = $repository->registerReference('C', 'policy', ['owner' => 'team_a']);
        $repository->archiveReference($archived['document_id']);

        $this->assertCount(2, $repository->search(['type' => 'policy']));
        $this->assertCount(2, $repository->search(['owner' => 'team_a']));
        $this->assertCount(1, $repository->search(['status' => 'archived']));
    }

    public function testRegisteredReferenceDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('knowledge_document.registered', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $repository = new SqliteDocumentRepository($this->tempPath('main'), events: $events);
        $repository->registerReference('Policy', 'policy');

        $this->assertCount(1, $captured);
    }
}
