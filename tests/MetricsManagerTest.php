<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Observability\MetricsManager;
use SquirrelForge\Observability\SqliteObservabilityGovernance;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class MetricsManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-metrics-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function telemetry(array $payloadOverrides = [], array $overrides = []): array
    {
        return array_merge([
            'event_id' => 'evt_' . bin2hex(random_bytes(4)),
            'source' => 'workflow-engine',
            'signal_type' => 'metric',
            'classification' => null,
            'correlation_id' => 'corr_1',
            'payload' => array_merge(['metric_name' => 'queue_depth', 'value' => 5], $payloadOverrides),
            'timestamp' => '2026-07-29T12:00:00Z',
        ], $overrides);
    }

    public function testWithoutDocumentStorageConfiguredIsNotConfigured(): void
    {
        $manager = new MetricsManager();

        $result = $manager->recordMetric($this->telemetry());

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testMissingMetricNameIsInvalid(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new MetricsManager($documents);
        $telemetry = $this->telemetry();
        unset($telemetry['payload']['metric_name']);

        $result = $manager->recordMetric($telemetry);

        $this->assertSame('invalid_metric', $result['outcome']);
    }

    public function testNonNumericValueIsInvalid(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new MetricsManager($documents);

        $result = $manager->recordMetric($this->telemetry(['value' => 'not-a-number']));

        $this->assertSame('invalid_metric', $result['outcome']);
    }

    public function testRecordMetricDelegatesRealPersistenceToDocumentStorage(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new MetricsManager($documents);

        $result = $manager->recordMetric($this->telemetry());

        $this->assertSame('recorded', $result['outcome']);
        $this->assertNotNull($result['metric_ref']);
        $stored = $documents->retrieve($result['metric_ref']);
        $this->assertTrue($stored['found']);
        $this->assertEquals(5.0, $stored['content']['value']);
    }

    public function testAggregateWithoutDocumentStorageConfiguredIsNotConfigured(): void
    {
        $manager = new MetricsManager();

        $result = $manager->aggregate('queue_depth');

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testAggregateWithAnUnknownTypeIsInvalid(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new MetricsManager($documents);

        $result = $manager->aggregate('queue_depth', 'median');

        $this->assertSame('invalid_type', $result['outcome']);
    }

    public function testAggregateWithNoRecordedValuesIsNoData(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new MetricsManager($documents);

        $result = $manager->aggregate('nonexistent_metric', 'mean');

        $this->assertSame('no_data', $result['outcome']);
        $this->assertNull($result['value']);
    }

    public function testAggregateWithNoRecordedValuesCountIsZero(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new MetricsManager($documents);

        $result = $manager->aggregate('nonexistent_metric', 'count');

        $this->assertSame('computed', $result['outcome']);
        $this->assertSame(0.0, $result['value']);
    }

    public function testAggregatesComputeRealDeterministicValuesAcrossRecordedMetrics(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new MetricsManager($documents);
        foreach ([5, 10, 15] as $value) {
            $manager->recordMetric($this->telemetry(['value' => $value]));
        }

        $this->assertSame(30.0, $manager->aggregate('queue_depth', 'sum')['value']);
        $this->assertSame(3.0, $manager->aggregate('queue_depth', 'count')['value']);
        $this->assertSame(10.0, $manager->aggregate('queue_depth', 'mean')['value']);
        $this->assertSame(5.0, $manager->aggregate('queue_depth', 'min')['value']);
        $this->assertSame(15.0, $manager->aggregate('queue_depth', 'max')['value']);
    }

    public function testDimensionsAreRedactedIndependentlyWhenTheStandardRequiresIt(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $governance = new SqliteObservabilityGovernance($this->tempPath('governance'));
        $governance->defineStandard('metric', ['redaction_required' => true]);
        $manager = new MetricsManager($documents, $governance);

        $result = $manager->recordMetric(
            $this->telemetry(['dimensions' => ['user_id' => 'user_42', 'region' => 'us-east']]),
            ['sensitive_fields' => ['user_id']]
        );

        $stored = $documents->retrieve($result['metric_ref']);
        $this->assertSame('[REDACTED]', $stored['content']['dimensions']['user_id']);
        $this->assertSame('us-east', $stored['content']['dimensions']['region']);
    }

    public function testThresholdEvidenceReusesRuleEngineWithoutDecidingAnAlert(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new MetricsManager($documents);
        foreach ([90, 95, 99] as $value) {
            $manager->recordMetric($this->telemetry(['value' => $value]));
        }

        $result = $manager->thresholdEvidence('queue_depth', '>', 90.0, 'mean');

        $this->assertTrue($result['satisfied']);
        $this->assertArrayNotHasKey('alert', $result);
    }

    public function testThresholdEvidenceWithNoDataReportsNoSatisfaction(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new MetricsManager($documents);

        $result = $manager->thresholdEvidence('nonexistent_metric', '>', 50.0);

        $this->assertNull($result['satisfied']);
    }

    public function testRetentionDaysFromTheStandardIsCarriedIntoStoredMetadata(): void
    {
        $documentsPath = $this->tempPath('documents');
        $documents = new SqliteDocumentStorage($documentsPath);
        $governance = new SqliteObservabilityGovernance($this->tempPath('governance'));
        $governance->defineStandard('metric', ['retention_days' => 30]);
        $manager = new MetricsManager($documents, $governance);

        $result = $manager->recordMetric($this->telemetry());

        $pdo = new \PDO('sqlite:' . $documentsPath);
        $statement = $pdo->prepare('SELECT metadata_json FROM documents WHERE document_ref = :document_ref');
        $statement->execute(['document_ref' => $result['metric_ref']]);
        $metadata = json_decode((string) $statement->fetchColumn(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(30, $metadata['retention_days']);
    }

    public function testValuesWithoutDocumentStorageConfiguredReturnsEmpty(): void
    {
        $manager = new MetricsManager();

        $this->assertSame([], $manager->values('queue_depth'));
    }

    public function testValuesReturnsEveryRecordedValueForAMetricName(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new MetricsManager($documents);
        foreach ([5, 10, 15] as $value) {
            $manager->recordMetric($this->telemetry(['value' => $value]));
        }

        $this->assertSame([15.0, 10.0, 5.0], $manager->values('queue_depth'));
    }
}
