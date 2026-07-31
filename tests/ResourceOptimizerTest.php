<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Observability\DiagnosticsEngine;
use SquirrelForge\Observability\MetricsManager;
use SquirrelForge\Optimization\ResourceOptimizer;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class ResourceOptimizerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-resource-optimizer-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function recordMetric(MetricsManager $metrics, string $metricName, float $value): void
    {
        $metrics->recordMetric([
            'event_id' => 'evt_' . bin2hex(random_bytes(4)), 'source' => 'workflow-engine', 'signal_type' => 'metric',
            'correlation_id' => 'corr_1', 'payload' => ['metric_name' => $metricName, 'value' => $value],
            'timestamp' => '2026-07-29T12:00:00Z',
        ]);
    }

    public function testAnalyzeUtilizationBandWithoutMetricsManagerIsNotConfigured(): void
    {
        $optimizer = new ResourceOptimizer();

        $result = $optimizer->analyzeUtilizationBand('cpu_utilization_pct', 40.0, 80.0);

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testAnalyzeUtilizationBandWithNoRecordedDataIsNoData(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $optimizer = new ResourceOptimizer($metrics);

        $result = $optimizer->analyzeUtilizationBand('cpu_utilization_pct', 40.0, 80.0);

        $this->assertSame('no_data', $result['outcome']);
    }

    public function testAnalyzeUtilizationBandWithinTargetProducesNoFinding(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $this->recordMetric($metrics, 'cpu_utilization_pct', 60.0);
        $optimizer = new ResourceOptimizer($metrics);

        $result = $optimizer->analyzeUtilizationBand('cpu_utilization_pct', 40.0, 80.0);

        $this->assertSame('within_target', $result['outcome']);
        $this->assertNull($result['finding']);
    }

    public function testAnalyzeUtilizationBandBelowTargetIsOverProvisioned(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $this->recordMetric($metrics, 'cpu_utilization_pct', 10.0);
        $optimizer = new ResourceOptimizer($metrics);

        $result = $optimizer->analyzeUtilizationBand('cpu_utilization_pct', 40.0, 80.0);

        $this->assertSame('over_provisioned', $result['outcome']);
        $this->assertSame('over_provisioned', $result['finding']['type']);
        $this->assertStringContainsString('underused', $result['finding']['improvement_proposal']);
    }

    public function testAnalyzeUtilizationBandAboveTargetIsConstrained(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $this->recordMetric($metrics, 'cpu_utilization_pct', 95.0);
        $optimizer = new ResourceOptimizer($metrics);

        $result = $optimizer->analyzeUtilizationBand('cpu_utilization_pct', 40.0, 80.0);

        $this->assertSame('constrained', $result['outcome']);
        $this->assertSame('constrained', $result['finding']['type']);
        $this->assertStringContainsString('insufficient', $result['finding']['improvement_proposal']);
    }

    public function testAnalyzeAnomaliesWithoutDiagnosticsEngineIsNotConfigured(): void
    {
        $optimizer = new ResourceOptimizer();

        $result = $optimizer->analyzeAnomalies('queue_depth');

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testAnalyzeAnomaliesPassesThroughInsufficientData(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $diagnostics = new DiagnosticsEngine(metrics: $metrics);
        $optimizer = new ResourceOptimizer(diagnostics: $diagnostics);

        $result = $optimizer->analyzeAnomalies('queue_depth');

        $this->assertSame('insufficient_data', $result['outcome']);
        $this->assertSame([], $result['findings']);
    }

    public function testAnalyzeAnomaliesBuildsARealFindingFromDiagnosticsEvidence(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $diagnostics = new DiagnosticsEngine(metrics: $metrics);
        $optimizer = new ResourceOptimizer(diagnostics: $diagnostics);
        foreach ([50, 52, 48, 51, 49, 500] as $value) {
            $this->recordMetric($metrics, 'queue_depth', $value);
        }

        $result = $optimizer->analyzeAnomalies('queue_depth');

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertSame('resource_anomaly', $result['findings'][0]['type']);
        $this->assertEquals(500.0, $result['findings'][0]['evidence']['value']);
    }

    public function testAnalyzeConstraintsWithoutDiagnosticsEngineIsNotConfigured(): void
    {
        $optimizer = new ResourceOptimizer();

        $result = $optimizer->analyzeConstraints([]);

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testAnalyzeConstraintsReportsOnlyBreachingResources(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $diagnostics = new DiagnosticsEngine(metrics: $metrics);
        $optimizer = new ResourceOptimizer(diagnostics: $diagnostics);
        $this->recordMetric($metrics, 'db_connections', 95.0);
        $this->recordMetric($metrics, 'cache_hit_rate', 0.9);

        $result = $optimizer->analyzeConstraints([
            'database' => ['metric' => 'db_connections', 'operator' => '>', 'threshold' => 80.0],
            'cache' => ['metric' => 'cache_hit_rate', 'operator' => '<', 'threshold' => 0.5],
        ]);

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertCount(1, $result['findings']);
        $this->assertSame('database', $result['findings'][0]['resource']);
        $this->assertSame('constraint', $result['findings'][0]['type']);
    }
}
