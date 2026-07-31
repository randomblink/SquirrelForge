<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Observability\AuditTrail;
use SquirrelForge\Observability\SqliteObservabilityGovernance;
use SquirrelForge\Security\StaticAuthorizationManager;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class AuditTrailTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-audit-trail-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function event(array $overrides = []): array
    {
        return array_merge([
            'actor_ref' => 'user_42',
            'action' => 'update_config',
            'resource_ref' => 'config:feature_flags',
            'outcome' => 'success',
            'decision' => 'approved',
        ], $overrides);
    }

    public function testRecordEventWithoutDocumentStorageConfiguredIsNotConfigured(): void
    {
        $trail = new AuditTrail();

        $result = $trail->recordEvent($this->event());

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testRecordEventRejectsAMissingRequiredField(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $trail = new AuditTrail($documents);
        $event = $this->event();
        unset($event['actor_ref']);

        $result = $trail->recordEvent($event);

        $this->assertSame('invalid_event', $result['outcome']);
        $this->assertStringContainsString('actor_ref', (string) $result['error']);
    }

    public function testRecordEventRejectsProhibitedEvidenceOutright(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $trail = new AuditTrail($documents);

        $result = $trail->recordEvent(
            $this->event(['evidence' => ['note' => 'ok', 'api_key' => 'sk_live_secret']]),
            ['prohibited_fields' => ['api_key']]
        );

        $this->assertSame('prohibited_evidence', $result['outcome']);
        $this->assertNull($result['audit_ref']);
    }

    public function testRecordEventDelegatesRealPersistenceToDocumentStorage(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $trail = new AuditTrail($documents);

        $result = $trail->recordEvent($this->event());

        $this->assertSame('recorded', $result['outcome']);
        $this->assertNotNull($result['audit_ref']);
        $stored = $documents->retrieve($result['audit_ref']);
        $this->assertSame('user_42', $stored['content']['actor_ref']);
        $this->assertSame('approved', $stored['content']['decision']);
    }

    public function testRecordEventNeverValidatesTheSuppliedDecision(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $trail = new AuditTrail($documents);

        $result = $trail->recordEvent($this->event(['decision' => 'nonsense_value']));

        $this->assertSame('recorded', $result['outcome']);
        $stored = $documents->retrieve($result['audit_ref']);
        $this->assertSame('nonsense_value', $stored['content']['decision']);
    }

    public function testSensitiveEvidenceIsRedactedIndependentlyWhenTheStandardRequiresIt(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $governance = new SqliteObservabilityGovernance($this->tempPath('governance'));
        $governance->defineStandard('audit_event', ['redaction_required' => true]);
        $trail = new AuditTrail($documents, $governance);

        $result = $trail->recordEvent(
            $this->event(['evidence' => ['note' => 'ok', 'ssn' => '123-45-6789']]),
            ['sensitive_fields' => ['ssn']]
        );

        $stored = $documents->retrieve($result['audit_ref']);
        $this->assertSame('[REDACTED]', $stored['content']['evidence']['ssn']);
        $this->assertSame('ok', $stored['content']['evidence']['note']);
    }

    public function testRetentionDaysFromTheStandardIsCarriedIntoStoredMetadata(): void
    {
        $documentsPath = $this->tempPath('documents');
        $documents = new SqliteDocumentStorage($documentsPath);
        $governance = new SqliteObservabilityGovernance($this->tempPath('governance'));
        $governance->defineStandard('audit_event', ['retention_days' => 2555]);
        $trail = new AuditTrail($documents, $governance);

        $result = $trail->recordEvent($this->event());

        $pdo = new \PDO('sqlite:' . $documentsPath);
        $statement = $pdo->prepare('SELECT metadata_json FROM documents WHERE document_ref = :document_ref');
        $statement->execute(['document_ref' => $result['audit_ref']]);
        $metadata = json_decode((string) $statement->fetchColumn(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(2555, $metadata['retention_days']);
    }

    public function testRetrieveWithoutDocumentStorageConfiguredIsNotFound(): void
    {
        $trail = new AuditTrail();

        $result = $trail->retrieve('document_ghost');

        $this->assertFalse($result['found']);
    }

    public function testRetrieveReturnsTheStoredAuditRecord(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $trail = new AuditTrail($documents);
        $recorded = $trail->recordEvent($this->event());

        $result = $trail->retrieve($recorded['audit_ref']);

        $this->assertTrue($result['found']);
        $this->assertSame('update_config', $result['audit']['action']);
    }

    public function testRetrieveDeniesAnUnauthorizedRequester(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $trail = new AuditTrail($documents, authorization: new StaticAuthorizationManager('permission:allowed'));
        $recorded = $trail->recordEvent($this->event());

        $result = $trail->retrieve($recorded['audit_ref']);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('denied', (string) $result['error']);
    }

    public function testRetrieveAllowsAnAuthorizedRequester(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $trail = new AuditTrail($documents, authorization: $authorization);
        $recorded = $trail->recordEvent($this->event());

        $result = $trail->retrieve($recorded['audit_ref'], ['identity_ref' => 'compliance_officer', 'permission_ref' => 'permission:allowed']);

        $this->assertTrue($result['found']);
    }

    public function testSearchFiltersByActorAndAction(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $trail = new AuditTrail($documents);
        $trail->recordEvent($this->event(['actor_ref' => 'user_42', 'action' => 'update_config']));
        $trail->recordEvent($this->event(['actor_ref' => 'user_99', 'action' => 'delete_resource']));

        $result = $trail->search(['actor_ref' => 'user_42']);

        $this->assertCount(1, $result);
        $this->assertSame('update_config', $result[0]['action']);
    }

    public function testSearchDeniesAnUnauthorizedRequester(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $trail = new AuditTrail($documents, authorization: new StaticAuthorizationManager('permission:allowed'));
        $trail->recordEvent($this->event());

        $result = $trail->search();

        $this->assertSame([], $result);
    }
}
