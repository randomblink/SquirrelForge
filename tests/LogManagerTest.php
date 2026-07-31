<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Observability\LogManager;
use SquirrelForge\Observability\SqliteObservabilityGovernance;
use SquirrelForge\Security\StaticAuthorizationManager;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class LogManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-log-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function telemetry(array $overrides = []): array
    {
        return array_merge([
            'event_id' => 'evt_1',
            'source' => 'workflow-engine',
            'signal_type' => 'log',
            'classification' => null,
            'correlation_id' => 'corr_1',
            'payload' => ['message' => 'hello'],
            'timestamp' => '2026-07-29T12:00:00Z',
        ], $overrides);
    }

    public function testWithoutDocumentStorageConfiguredIsNotConfigured(): void
    {
        $manager = new LogManager();

        $result = $manager->recordLog($this->telemetry());

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testRecordLogDelegatesRealPersistenceToDocumentStorage(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new LogManager($documents);

        $result = $manager->recordLog($this->telemetry());

        $this->assertSame('recorded', $result['outcome']);
        $this->assertNotNull($result['log_ref']);
        $stored = $documents->retrieve($result['log_ref']);
        $this->assertTrue($stored['found']);
        $this->assertSame('hello', $stored['content']['payload']['message']);
    }

    public function testSeverityDefaultsFromSignalType(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new LogManager($documents);

        $result = $manager->recordLog($this->telemetry(['signal_type' => 'alert']));

        $this->assertSame('error', $result['severity']);
    }

    public function testExplicitSeverityOverridesTheDefault(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new LogManager($documents);

        $result = $manager->recordLog($this->telemetry(['signal_type' => 'alert']), ['severity' => 'critical']);

        $this->assertSame('critical', $result['severity']);
    }

    public function testRedactionIsAppliedIndependentlyWhenTheStandardRequiresIt(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $governance = new SqliteObservabilityGovernance($this->tempPath('governance'));
        $governance->defineStandard('log', ['redaction_required' => true]);
        $manager = new LogManager($documents, $governance);

        $result = $manager->recordLog(
            $this->telemetry(['payload' => ['message' => 'hi', 'email' => 'user@example.com']]),
            ['sensitive_fields' => ['email']]
        );

        $stored = $documents->retrieve($result['log_ref']);
        $this->assertSame('[REDACTED]', $stored['content']['payload']['email']);
    }

    public function testNoRedactionWhenTheStandardDoesNotRequireIt(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $governance = new SqliteObservabilityGovernance($this->tempPath('governance'));
        $governance->defineStandard('log', ['redaction_required' => false]);
        $manager = new LogManager($documents, $governance);

        $result = $manager->recordLog(
            $this->telemetry(['payload' => ['email' => 'user@example.com']]),
            ['sensitive_fields' => ['email']]
        );

        $stored = $documents->retrieve($result['log_ref']);
        $this->assertSame('user@example.com', $stored['content']['payload']['email']);
    }

    public function testRetentionDaysFromTheStandardIsCarriedIntoStoredMetadata(): void
    {
        $documentsPath = $this->tempPath('documents');
        $documents = new SqliteDocumentStorage($documentsPath);
        $governance = new SqliteObservabilityGovernance($this->tempPath('governance'));
        $governance->defineStandard('log', ['retention_days' => 90]);
        $manager = new LogManager($documents, $governance);

        $result = $manager->recordLog($this->telemetry());

        $pdo = new \PDO('sqlite:' . $documentsPath);
        $statement = $pdo->prepare('SELECT metadata_json FROM documents WHERE document_ref = :document_ref');
        $statement->execute(['document_ref' => $result['log_ref']]);
        $metadata = json_decode((string) $statement->fetchColumn(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(90, $metadata['retention_days']);
    }

    public function testDocumentStorageRejectionPropagatesAsRejectedOutcome(): void
    {
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $documents = new SqliteDocumentStorage($this->tempPath('documents'), authorization: $authorization);
        $manager = new LogManager($documents);

        $result = $manager->recordLog($this->telemetry());

        $this->assertSame('rejected', $result['outcome']);
        $this->assertNull($result['log_ref']);
    }

    public function testRecentWithoutDocumentStorageConfiguredReturnsEmpty(): void
    {
        $manager = new LogManager();

        $this->assertSame([], $manager->recent());
    }

    public function testRecentFiltersOnSourceSignalTypeSeverityAndCorrelationId(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new LogManager($documents);
        $manager->recordLog($this->telemetry(['source' => 'workflow-engine', 'correlation_id' => 'corr_a']));
        $manager->recordLog($this->telemetry(['source' => 'other-service', 'correlation_id' => 'corr_b', 'signal_type' => 'alert']));

        $bySource = $manager->recent(['source' => 'workflow-engine']);
        $this->assertCount(1, $bySource);
        $this->assertSame('corr_a', $bySource[0]['correlation_id']);

        $bySeverity = $manager->recent(['severity' => 'error']);
        $this->assertCount(1, $bySeverity);
        $this->assertSame('alert', $bySeverity[0]['signal_type']);

        $byCorrelation = $manager->recent(['correlation_id' => 'corr_b']);
        $this->assertCount(1, $byCorrelation);
        $this->assertSame('other-service', $byCorrelation[0]['source']);
    }
}
