<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Observability\SqliteObservabilityGovernance;
use SquirrelForge\Observability\TraceManager;
use SquirrelForge\Security\StaticAuthorizationManager;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class TraceManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-trace-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function telemetry(array $payloadOverrides = [], array $overrides = []): array
    {
        return array_merge([
            'event_id' => 'evt_' . bin2hex(random_bytes(4)),
            'source' => 'workflow-engine',
            'signal_type' => 'trace',
            'classification' => null,
            'correlation_id' => 'trace_1',
            'payload' => array_merge(['span_id' => 'span_1', 'operation' => 'process_order'], $payloadOverrides),
            'timestamp' => '2026-07-29T12:00:00Z',
        ], $overrides);
    }

    public function testWithoutDocumentStorageConfiguredIsNotConfigured(): void
    {
        $manager = new TraceManager();

        $result = $manager->recordSpan($this->telemetry());

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testMissingSpanIdIsInvalid(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new TraceManager($documents);
        $telemetry = $this->telemetry();
        unset($telemetry['payload']['span_id']);

        $result = $manager->recordSpan($telemetry);

        $this->assertSame('invalid_span', $result['outcome']);
    }

    public function testEmptyCorrelationIdIsInvalid(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new TraceManager($documents);

        $result = $manager->recordSpan($this->telemetry([], ['correlation_id' => '']));

        $this->assertSame('invalid_span', $result['outcome']);
    }

    public function testCreatingANewSpanWithoutOperationIsInvalid(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new TraceManager($documents);
        $telemetry = $this->telemetry();
        unset($telemetry['payload']['operation']);

        $result = $manager->recordSpan($telemetry);

        $this->assertSame('invalid_span', $result['outcome']);
    }

    public function testCreatingANewSpanDelegatesRealPersistenceToDocumentStorage(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new TraceManager($documents);

        $result = $manager->recordSpan($this->telemetry());

        $this->assertSame('created', $result['outcome']);
        $this->assertNotNull($result['span_ref']);
        $stored = $documents->retrieve($result['span_ref']);
        $this->assertSame('span_1', $stored['content']['span_id']);
        $this->assertSame('trace_1', $stored['content']['trace_id']);
        $this->assertSame('started', $stored['content']['status']);
        $this->assertSame('2026-07-29T12:00:00Z', $stored['content']['started_at']);
    }

    public function testUpdatingAnExistingSpanMergesFieldsAsARealNewImmutableVersion(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new TraceManager($documents);
        $created = $manager->recordSpan($this->telemetry());

        $result = $manager->recordSpan($this->telemetry(['status' => 'completed', 'ended_at' => '2026-07-29T12:00:05Z']));

        $this->assertSame('updated', $result['outcome']);
        $this->assertSame($created['span_ref'], $result['span_ref']);
        $latest = $documents->retrieve($result['span_ref']);
        $this->assertSame('completed', $latest['content']['status']);
        $this->assertSame('2026-07-29T12:00:05Z', $latest['content']['ended_at']);
        $this->assertSame('process_order', $latest['content']['operation']);
        $original = $documents->retrieve($result['span_ref'], 1);
        $this->assertSame('started', $original['content']['status']);
    }

    public function testAttributesAreRedactedIndependentlyWhenTheStandardRequiresIt(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $governance = new SqliteObservabilityGovernance($this->tempPath('governance'));
        $governance->defineStandard('trace', ['redaction_required' => true]);
        $manager = new TraceManager($documents, $governance);

        $result = $manager->recordSpan(
            $this->telemetry(['attributes' => ['customer_email' => 'user@example.com', 'region' => 'us-east']]),
            ['sensitive_fields' => ['customer_email']]
        );

        $stored = $documents->retrieve($result['span_ref']);
        $this->assertSame('[REDACTED]', $stored['content']['attributes']['customer_email']);
        $this->assertSame('us-east', $stored['content']['attributes']['region']);
    }

    public function testDocumentStorageRejectionPropagatesAsRejectedOutcome(): void
    {
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $documents = new SqliteDocumentStorage($this->tempPath('documents'), authorization: $authorization);
        $manager = new TraceManager($documents);

        $result = $manager->recordSpan($this->telemetry());

        $this->assertSame('rejected', $result['outcome']);
        $this->assertNull($result['span_ref']);
    }

    public function testExecutionPathOrdersSpansByStartTimeAndDetectsUnresolvedParents(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new TraceManager($documents);
        $manager->recordSpan($this->telemetry(['span_id' => 'span_b', 'started_at' => '2026-07-29T12:00:05Z', 'parent_span_id' => 'span_a']));
        $manager->recordSpan($this->telemetry(['span_id' => 'span_a', 'started_at' => '2026-07-29T12:00:00Z']));
        $manager->recordSpan($this->telemetry(['span_id' => 'span_c', 'started_at' => '2026-07-29T12:00:10Z', 'parent_span_id' => 'span_ghost']));

        $path = $manager->executionPath('trace_1');

        $this->assertSame(['span_a', 'span_b', 'span_c'], array_column($path['spans'], 'span_id'));
        $this->assertSame(['span_c'], $path['unresolved_parents']);
    }

    public function testLatencyEvidenceComputesRealDurationsWithoutLabelingAnomalies(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new TraceManager($documents);
        $manager->recordSpan($this->telemetry(['span_id' => 'span_a', 'started_at' => '2026-07-29T12:00:00Z', 'ended_at' => '2026-07-29T12:00:03Z']));
        $manager->recordSpan($this->telemetry(['span_id' => 'span_b', 'started_at' => '2026-07-29T12:00:01Z', 'ended_at' => '2026-07-29T12:00:11Z']));

        $evidence = $manager->latencyEvidence('trace_1');

        $this->assertSame(['span_b', 'span_a'], array_column($evidence['spans'], 'span_id'));
        $this->assertSame(10000, $evidence['spans'][0]['duration_ms']);
        $this->assertSame(3000, $evidence['spans'][1]['duration_ms']);
        $this->assertSame(11000, $evidence['total_duration_ms']);
        $this->assertArrayNotHasKey('anomalous', $evidence);
    }

    public function testLatencyEvidenceSkipsSpansMissingTimestamps(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new TraceManager($documents);
        $manager->recordSpan($this->telemetry(['span_id' => 'span_a']));

        $evidence = $manager->latencyEvidence('trace_1');

        $this->assertSame([], $evidence['spans']);
        $this->assertNull($evidence['total_duration_ms']);
    }
}
