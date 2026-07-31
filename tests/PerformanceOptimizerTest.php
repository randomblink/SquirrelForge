<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Observability\DiagnosticsEngine;
use SquirrelForge\Observability\MetricsManager;
use SquirrelForge\Observability\TraceManager;
use SquirrelForge\Optimization\PerformanceOptimizer;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class PerformanceOptimizerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-performance-optimizer-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function telemetry(array $payload, string $correlationId = 'trace_1'): array
    {
        return [
            'event_id' => 'evt_' . bin2hex(random_bytes(4)),
            'source' => 'workflow-engine',
            'signal_type' => 'trace',
            'correlation_id' => $correlationId,
            'payload' => $payload,
            'timestamp' => '2026-07-29T12:00:00Z',
        ];
    }

    public function testAnalyzeBottlenecksWithoutDiagnosticsEngineIsNotConfigured(): void
    {
        $optimizer = new PerformanceOptimizer();

        $result = $optimizer->analyzeBottlenecks('trace_1');

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testAnalyzeBottlenecksBuildsARealFindingFromDiagnosticsEvidence(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $traces = new TraceManager($documents);
        $diagnostics = new DiagnosticsEngine(traces: $traces);
        $optimizer = new PerformanceOptimizer($diagnostics);
        $traces->recordSpan($this->telemetry(['span_id' => 'span_a', 'operation' => 'checkout', 'started_at' => '2026-07-29T12:00:00Z', 'ended_at' => '2026-07-29T12:00:10Z']));

        $result = $optimizer->analyzeBottlenecks('trace_1');

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertSame('bottleneck', $result['findings'][0]['type']);
        $this->assertSame('span_a', $result['findings'][0]['evidence']['span_id']);
        $this->assertStringContainsString('checkout', $result['findings'][0]['measurable_target']);
        $this->assertStringContainsString('5000 ms', $result['findings'][0]['measurable_target']);
    }

    public function testAnalyzeMetricAnomaliesWithoutDiagnosticsEngineIsNotConfigured(): void
    {
        $optimizer = new PerformanceOptimizer();

        $result = $optimizer->analyzeMetricAnomalies('latency_ms');

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testAnalyzeMetricAnomaliesPassesThroughInsufficientData(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $diagnostics = new DiagnosticsEngine(metrics: $metrics);
        $optimizer = new PerformanceOptimizer($diagnostics);

        $result = $optimizer->analyzeMetricAnomalies('latency_ms');

        $this->assertSame('insufficient_data', $result['outcome']);
        $this->assertSame([], $result['findings']);
    }

    public function testAnalyzeMetricAnomaliesBuildsARealFindingFromDiagnosticsEvidence(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $diagnostics = new DiagnosticsEngine(metrics: $metrics);
        $optimizer = new PerformanceOptimizer($diagnostics);
        foreach ([100, 102, 98, 101, 99, 500] as $value) {
            $metrics->recordMetric([
                'event_id' => 'evt_' . bin2hex(random_bytes(4)), 'source' => 'workflow-engine', 'signal_type' => 'metric',
                'correlation_id' => 'corr_1', 'payload' => ['metric_name' => 'latency_ms', 'value' => $value],
                'timestamp' => '2026-07-29T12:00:00Z',
            ]);
        }

        $result = $optimizer->analyzeMetricAnomalies('latency_ms');

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertSame('anomaly', $result['findings'][0]['type']);
        $this->assertEquals(500.0, $result['findings'][0]['evidence']['value']);
    }

    public function testCompareBaselineWithoutMetricsManagerIsNotConfigured(): void
    {
        $optimizer = new PerformanceOptimizer();

        $result = $optimizer->compareBaseline('latency_ms_baseline', 'latency_ms_candidate');

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testCompareBaselineWithoutDataForEitherMetricIsNoData(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $optimizer = new PerformanceOptimizer(metrics: $metrics);

        $result = $optimizer->compareBaseline('latency_ms_baseline', 'latency_ms_candidate');

        $this->assertSame('no_data', $result['outcome']);
    }

    public function testCompareBaselineComputesARealPercentChange(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $optimizer = new PerformanceOptimizer(metrics: $metrics);
        $metrics->recordMetric([
            'event_id' => 'evt_1', 'source' => 'workflow-engine', 'signal_type' => 'metric',
            'correlation_id' => 'corr_1', 'payload' => ['metric_name' => 'latency_ms_baseline', 'value' => 100],
            'timestamp' => '2026-07-29T12:00:00Z',
        ]);
        $metrics->recordMetric([
            'event_id' => 'evt_2', 'source' => 'workflow-engine', 'signal_type' => 'metric',
            'correlation_id' => 'corr_1', 'payload' => ['metric_name' => 'latency_ms_candidate', 'value' => 80],
            'timestamp' => '2026-07-29T12:00:00Z',
        ]);

        $result = $optimizer->compareBaseline('latency_ms_baseline', 'latency_ms_candidate');

        $this->assertSame('compared', $result['outcome']);
        $this->assertSame('baseline_comparison', $result['finding']['type']);
        $this->assertEquals(-20.0, $result['finding']['evidence']['percent_change']);
        $this->assertStringContainsString('lower', $result['finding']['improvement_proposal']);
    }

    public function testCompareBaselineWithAZeroBaselineIsUndefined(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $optimizer = new PerformanceOptimizer(metrics: $metrics);
        $metrics->recordMetric([
            'event_id' => 'evt_1', 'source' => 'workflow-engine', 'signal_type' => 'metric',
            'correlation_id' => 'corr_1', 'payload' => ['metric_name' => 'latency_ms_baseline', 'value' => 0],
            'timestamp' => '2026-07-29T12:00:00Z',
        ]);
        $metrics->recordMetric([
            'event_id' => 'evt_2', 'source' => 'workflow-engine', 'signal_type' => 'metric',
            'correlation_id' => 'corr_1', 'payload' => ['metric_name' => 'latency_ms_candidate', 'value' => 10],
            'timestamp' => '2026-07-29T12:00:00Z',
        ]);

        $result = $optimizer->compareBaseline('latency_ms_baseline', 'latency_ms_candidate');

        $this->assertSame('no_baseline', $result['outcome']);
    }
}
