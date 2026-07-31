<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Observability\DiagnosticsEngine;
use SquirrelForge\Observability\LogManager;
use SquirrelForge\Observability\MetricsManager;
use SquirrelForge\Observability\TraceManager;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class DiagnosticsEngineTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-diagnostics-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function telemetry(string $signalType, array $payload, array $overrides = []): array
    {
        return array_merge([
            'event_id' => 'evt_' . bin2hex(random_bytes(4)),
            'source' => 'workflow-engine',
            'signal_type' => $signalType,
            'classification' => null,
            'correlation_id' => 'trace_1',
            'payload' => $payload,
            'timestamp' => '2026-07-29T12:00:00Z',
        ], $overrides);
    }

    public function testCorrelateWithNothingConfiguredReturnsEmptyEvidence(): void
    {
        $engine = new DiagnosticsEngine();

        $result = $engine->correlate('trace_1', ['queue_depth']);

        $this->assertSame([], $result['logs']);
        $this->assertNull($result['trace']);
        $this->assertSame('not_configured', $result['metrics']['queue_depth']['outcome']);
    }

    public function testCorrelatePullsRealLogsTraceAndMetricsForASharedCorrelationId(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $logs = new LogManager($documents);
        $metrics = new MetricsManager($documents);
        $traces = new TraceManager($documents);
        $engine = new DiagnosticsEngine($logs, $metrics, $traces);

        $logs->recordLog($this->telemetry('log', ['message' => 'order accepted']));
        $metrics->recordMetric($this->telemetry('metric', ['metric_name' => 'queue_depth', 'value' => 12]));
        $traces->recordSpan($this->telemetry('trace', ['span_id' => 'span_1', 'operation' => 'process_order']));

        $result = $engine->correlate('trace_1', ['queue_depth']);

        $this->assertCount(1, $result['logs']);
        $this->assertSame('order accepted', $result['logs'][0]['payload']['message']);
        $this->assertSame(['span_1'], array_column($result['trace']['spans'], 'span_id'));
        $this->assertSame(12.0, $result['metrics']['queue_depth']['value']);
    }

    public function testFindFailurePathWithoutTraceManagerConfiguredIsNotConfigured(): void
    {
        $engine = new DiagnosticsEngine();

        $result = $engine->findFailurePath('trace_1');

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testFindFailurePathSeparatesObservedFailuresFromTheInferredProbableCause(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $traces = new TraceManager($documents);
        $engine = new DiagnosticsEngine(traces: $traces);
        $traces->recordSpan($this->telemetry('trace', ['span_id' => 'span_a', 'operation' => 'step_a', 'status' => 'completed', 'started_at' => '2026-07-29T12:00:00Z']));
        $traces->recordSpan($this->telemetry('trace', ['span_id' => 'span_b', 'operation' => 'step_b', 'status' => 'failed', 'started_at' => '2026-07-29T12:00:01Z']));
        $traces->recordSpan($this->telemetry('trace', ['span_id' => 'span_c', 'operation' => 'step_c', 'status' => 'failed', 'started_at' => '2026-07-29T12:00:02Z']));

        $result = $engine->findFailurePath('trace_1');

        $this->assertSame(['span_b', 'span_c'], $result['observed_failed_spans']);
        $this->assertSame('span_b', $result['inferred_probable_cause']);
    }

    public function testFindFailurePathWithNoFailuresHasNoInferredCause(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $traces = new TraceManager($documents);
        $engine = new DiagnosticsEngine(traces: $traces);
        $traces->recordSpan($this->telemetry('trace', ['span_id' => 'span_a', 'operation' => 'step_a', 'status' => 'completed']));

        $result = $engine->findFailurePath('trace_1');

        $this->assertSame([], $result['observed_failed_spans']);
        $this->assertNull($result['inferred_probable_cause']);
    }

    public function testFindBottlenecksReturnsSlowestSpansAsEvidenceOnly(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $traces = new TraceManager($documents);
        $engine = new DiagnosticsEngine(traces: $traces);
        $traces->recordSpan($this->telemetry('trace', ['span_id' => 'span_a', 'operation' => 'fast', 'started_at' => '2026-07-29T12:00:00Z', 'ended_at' => '2026-07-29T12:00:01Z']));
        $traces->recordSpan($this->telemetry('trace', ['span_id' => 'span_b', 'operation' => 'slow', 'started_at' => '2026-07-29T12:00:00Z', 'ended_at' => '2026-07-29T12:00:10Z']));

        $result = $engine->findBottlenecks('trace_1', 1);

        $this->assertCount(1, $result['bottleneck_candidates']);
        $this->assertSame('span_b', $result['bottleneck_candidates'][0]['span_id']);
    }

    public function testFindMetricAnomaliesWithInsufficientDataReportsThatOutcome(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $engine = new DiagnosticsEngine(metrics: $metrics);
        $metrics->recordMetric($this->telemetry('metric', ['metric_name' => 'latency_ms', 'value' => 100]));

        $result = $engine->findMetricAnomalies('latency_ms');

        $this->assertSame('insufficient_data', $result['outcome']);
    }

    public function testFindMetricAnomaliesFlagsARealZScoreDeviation(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $engine = new DiagnosticsEngine(metrics: $metrics);
        foreach ([100, 102, 98, 101, 99, 500] as $value) {
            $metrics->recordMetric($this->telemetry('metric', ['metric_name' => 'latency_ms', 'value' => $value]));
        }

        $result = $engine->findMetricAnomalies('latency_ms', 2.0);

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertCount(1, $result['anomalies']);
        $this->assertSame(500.0, $result['anomalies'][0]['value']);
    }

    public function testFindMetricAnomaliesWithNoVarianceReportsNoAnomalies(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $engine = new DiagnosticsEngine(metrics: $metrics);
        foreach ([100, 100, 100] as $value) {
            $metrics->recordMetric($this->telemetry('metric', ['metric_name' => 'latency_ms', 'value' => $value]));
        }

        $result = $engine->findMetricAnomalies('latency_ms');

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertSame([], $result['anomalies']);
    }

    public function testDegradedDependencyEvidenceReportsBreachesWithoutAHealthVerdict(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $engine = new DiagnosticsEngine(metrics: $metrics);
        $metrics->recordMetric($this->telemetry('metric', ['metric_name' => 'payment_api_latency_ms', 'value' => 900]));

        $result = $engine->degradedDependencyEvidence([
            'payment-api' => ['metric' => 'payment_api_latency_ms', 'operator' => '>', 'threshold' => 500.0],
            'inventory-api' => ['metric' => 'inventory_api_latency_ms', 'operator' => '>', 'threshold' => 500.0],
        ]);

        $this->assertSame(['payment-api'], $result['degraded']);
        $this->assertArrayNotHasKey('health', $result);
    }
}
