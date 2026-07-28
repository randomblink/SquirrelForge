<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Security\StaticAuthorizationManager;
use SquirrelForge\Storage\SqliteDataValidator;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class SqliteDocumentStorageTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-doc-storage-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    public function testStoreCreatesVersionOneAndIsRetrievable(): void
    {
        $storage = new SqliteDocumentStorage($this->tempPath('main'));
        $stored = $storage->store('workflow_definition', 'Deploy Pipeline', ['steps' => ['build', 'test']]);

        $this->assertSame('stored', $stored['outcome']);
        $this->assertSame(1, $stored['version']);

        $retrieved = $storage->retrieve($stored['document_ref']);
        $this->assertTrue($retrieved['found']);
        $this->assertSame(1, $retrieved['version']);
        $this->assertSame(['steps' => ['build', 'test']], $retrieved['content']);
    }

    public function testUpdateAppendsANewVersionWithoutMutatingTheOriginal(): void
    {
        $storage = new SqliteDocumentStorage($this->tempPath('main'));
        $stored = $storage->store('policy', 'Retention Policy', ['days' => 30]);
        $documentRef = $stored['document_ref'];

        $updated = $storage->updateVersion($documentRef, ['days' => 90]);

        $this->assertSame('updated', $updated['outcome']);
        $this->assertSame(2, $updated['version']);

        $original = $storage->retrieve($documentRef, 1);
        $latest = $storage->retrieve($documentRef);

        $this->assertSame(['days' => 30], $original['content']);
        $this->assertSame(['days' => 90], $latest['content']);
        $this->assertSame(2, $latest['version']);
        $this->assertCount(2, $storage->versions($documentRef));
    }

    public function testRetrievingANonexistentVersionFails(): void
    {
        $storage = new SqliteDocumentStorage($this->tempPath('main'));
        $stored = $storage->store('report', 'Q1 Report', ['summary' => 'ok']);

        $result = $storage->retrieve($stored['document_ref'], 7);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('Version 7', (string) $result['error']);
    }

    public function testDeletedDocumentIsNoLongerRetrievableButRowIsNeverRemoved(): void
    {
        $storage = new SqliteDocumentStorage($this->tempPath('main'));
        $stored = $storage->store('template', 'Onboarding Email', ['subject' => 'Welcome']);
        $documentRef = $stored['document_ref'];

        $deleted = $storage->delete($documentRef);
        $retrieved = $storage->retrieve($documentRef);

        $this->assertSame('deleted', $deleted['outcome']);
        $this->assertFalse($retrieved['found']);
        $this->assertStringContainsString('deleted', (string) $retrieved['error']);
        $this->assertCount(1, $storage->versions($documentRef));
    }

    public function testDocumentUnderLegalHoldCannotBeDeleted(): void
    {
        $storage = new SqliteDocumentStorage($this->tempPath('main'));
        $stored = $storage->store('governance_document', 'Audit Findings', ['finding' => 'none']);
        $documentRef = $stored['document_ref'];

        $storage->setLegalHold($documentRef, true);
        $result = $storage->delete($documentRef);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('legal hold', (string) $result['error']);
    }

    public function testLiftingLegalHoldAllowsDeletionAgain(): void
    {
        $storage = new SqliteDocumentStorage($this->tempPath('main'));
        $stored = $storage->store('governance_document', 'Audit Findings', ['finding' => 'none']);
        $documentRef = $stored['document_ref'];
        $storage->setLegalHold($documentRef, true);
        $storage->setLegalHold($documentRef, false);

        $result = $storage->delete($documentRef);

        $this->assertSame('deleted', $result['outcome']);
    }

    public function testSetLegalHoldReturnsFalseForUnknownDocument(): void
    {
        $storage = new SqliteDocumentStorage($this->tempPath('main'));

        $this->assertFalse($storage->setLegalHold('document_does_not_exist', true));
    }

    public function testValidatorRejectionPreventsStorage(): void
    {
        $validator = new SqliteDataValidator($this->tempPath('validator'));
        $storage = new SqliteDocumentStorage($this->tempPath('main'), $validator);

        $result = $storage->store('workflow_definition', 'Broken', [], ['required_fields' => ['steps']]);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertNull($result['document_ref']);
        $this->assertStringContainsString('steps', (string) $result['error']);
    }

    public function testValidatorApprovalAllowsStorage(): void
    {
        $validator = new SqliteDataValidator($this->tempPath('validator'));
        $storage = new SqliteDocumentStorage($this->tempPath('main'), $validator);

        $result = $storage->store('workflow_definition', 'Working', ['steps' => ['a']], ['required_fields' => ['steps']]);

        $this->assertSame('stored', $result['outcome']);
    }

    public function testDeniedAuthorizationBlocksStorage(): void
    {
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $storage = new SqliteDocumentStorage($this->tempPath('main'), authorization: $authorization);

        $result = $storage->store('workflow_definition', 'Deploy', [], ['identity_ref' => 'user_1', 'permission_ref' => 'permission:denied']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertNull($result['document_ref']);
    }

    public function testDeniedAuthorizationBlocksRetrieval(): void
    {
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $storage = new SqliteDocumentStorage($this->tempPath('main'), authorization: $authorization);
        $stored = $storage->store('workflow_definition', 'Deploy', [], ['identity_ref' => 'user_1', 'permission_ref' => 'permission:allowed']);

        $result = $storage->retrieve($stored['document_ref'], context: ['identity_ref' => 'user_1', 'permission_ref' => 'permission:denied']);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('denied', (string) $result['error']);
    }

    public function testSearchFiltersByTypeOwnerAndTag(): void
    {
        $storage = new SqliteDocumentStorage($this->tempPath('main'));
        $storage->store('workflow_definition', 'A', [], ['owner' => 'team_a', 'tags' => ['release']]);
        $storage->store('workflow_definition', 'B', [], ['owner' => 'team_b', 'tags' => ['draft']]);
        $storage->store('policy', 'C', [], ['owner' => 'team_a', 'tags' => ['release']]);

        $byType = $storage->search(['type' => 'workflow_definition']);
        $byOwner = $storage->search(['owner' => 'team_a']);
        $byTag = $storage->search(['tag' => 'release']);

        $this->assertCount(2, $byType);
        $this->assertCount(2, $byOwner);
        $this->assertCount(2, $byTag);
    }

    public function testSearchExcludesDeletedDocuments(): void
    {
        $storage = new SqliteDocumentStorage($this->tempPath('main'));
        $stored = $storage->store('report', 'Old Report', []);
        $storage->delete($stored['document_ref']);

        $this->assertSame([], $storage->search(['type' => 'report']));
    }

    public function testOperationDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('document.stored', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $storage = new SqliteDocumentStorage($this->tempPath('main'), events: $events);
        $storage->store('report', 'New Report', []);

        $this->assertCount(1, $captured);
    }

    public function testOperationRetrievesAPersistedAuditRecordByRef(): void
    {
        $storage = new SqliteDocumentStorage($this->tempPath('main'));
        $storage->store('report', 'New Report', [], ['requesting_component' => 'reporting-service']);

        $recent = $storage->recent(1);

        $this->assertSame('reporting-service', $recent[0]['requesting_component']);
        $fetched = $storage->operation($recent[0]['operation_ref']);
        $this->assertNotNull($fetched);
        $this->assertSame('stored', $fetched['outcome']);
    }
}
