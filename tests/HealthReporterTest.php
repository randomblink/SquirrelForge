<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Observability\DiagnosticsEngine;
use SquirrelForge\Observability\HealthReporter;
use SquirrelForge\Observability\MetricsManager;
use SquirrelForge\Observability\SqliteAlertManager;
use SquirrelForge\Observability\TraceManager;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class HealthReporterTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-health-reporter-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    public function testWithNoComponentsConfiguredHealthIsUnknown(): void
    {
        $reporter = new HealthReporter();

        $result = $reporter->componentHealth('workflow-engine');

        $this->assertSame('Unknown', $result['state']);
        $this->assertSame('insufficient_evidence', $result['outcome']);
    }

    public function testWithNoOpenAlertsHealthIsHealthy(): void
    {
        $alerts = new SqliteAlertManager($this->tempPath('alerts'));
        $reporter = new HealthReporter($alerts);

        $result = $reporter->componentHealth('workflow-engine');

        $this->assertSame('Healthy', $result['state']);
        $this->assertSame(['alert_manager'], $result['evidence_sources']);
    }

    public function testAWarningAlertDegradesHealth(): void
    {
        $alerts = new SqliteAlertManager($this->tempPath('alerts'));
        $alerts->create('workflow-engine', 'performance', 'warning', []);
        $reporter = new HealthReporter($alerts);

        $result = $reporter->componentHealth('workflow-engine');

        $this->assertSame('Degraded', $result['state']);
    }

    public function testACriticalAlertMakesHealthUnhealthy(): void
    {
        $alerts = new SqliteAlertManager($this->tempPath('alerts'));
        $alerts->create('workflow-engine', 'performance', 'critical', []);
        $reporter = new HealthReporter($alerts);

        $result = $reporter->componentHealth('workflow-engine');

        $this->assertSame('Unhealthy', $result['state']);
    }

    public function testAFailurePathMakesHealthUnhealthy(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $traces = new TraceManager($documents);
        $diagnostics = new DiagnosticsEngine(traces: $traces);
        $reporter = new HealthReporter(diagnostics: $diagnostics);
        $traces->recordSpan([
            'event_id' => 'evt_1', 'source' => 'workflow-engine', 'signal_type' => 'trace',
            'correlation_id' => 'trace_1', 'payload' => ['span_id' => 'span_a', 'operation' => 'step_a', 'status' => 'failed'],
            'timestamp' => '2026-07-29T12:00:00Z',
        ]);

        $result = $reporter->componentHealth('workflow-engine', ['trace_id' => 'trace_1']);

        $this->assertSame('Unhealthy', $result['state']);
        $this->assertSame(['diagnostics_engine'], $result['evidence_sources']);
    }

    public function testABreachingMetricThresholdDegradesHealth(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $reporter = new HealthReporter(metrics: $metrics);
        $metrics->recordMetric([
            'event_id' => 'evt_1', 'source' => 'workflow-engine', 'signal_type' => 'metric',
            'correlation_id' => 'corr_1', 'payload' => ['metric_name' => 'queue_depth', 'value' => 500],
            'timestamp' => '2026-07-29T12:00:00Z',
        ]);

        $result = $reporter->componentHealth('workflow-engine', [
            'metric_threshold' => ['metric' => 'queue_depth', 'operator' => '>', 'threshold' => 100.0],
        ]);

        $this->assertSame('Degraded', $result['state']);
    }

    public function testMetricAnomaliesDegradeHealth(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $diagnostics = new DiagnosticsEngine(metrics: $metrics);
        $reporter = new HealthReporter(diagnostics: $diagnostics);
        foreach ([100, 102, 98, 101, 99, 500] as $value) {
            $metrics->recordMetric([
                'event_id' => 'evt_1', 'source' => 'workflow-engine', 'signal_type' => 'metric',
                'correlation_id' => 'corr_1', 'payload' => ['metric_name' => 'latency_ms', 'value' => $value],
                'timestamp' => '2026-07-29T12:00:00Z',
            ]);
        }

        $result = $reporter->componentHealth('workflow-engine', ['anomaly_metric' => 'latency_ms']);

        $this->assertSame('Degraded', $result['state']);
    }

    public function testUnhealthyOutranksDegradedWhenBothAreObserved(): void
    {
        $alerts = new SqliteAlertManager($this->tempPath('alerts'));
        $alerts->create('workflow-engine', 'performance', 'warning', []);
        $alerts->create('workflow-engine', 'reliability', 'critical', []);
        $reporter = new HealthReporter($alerts);

        $result = $reporter->componentHealth('workflow-engine');

        $this->assertSame('Unhealthy', $result['state']);
    }

    public function testEvidenceAgeIsComputedFromTheMostRecentAlertUpdate(): void
    {
        $clock = fn(): DateTimeImmutable => new DateTimeImmutable('2026-07-29T12:05:00Z');
        $alerts = new SqliteAlertManager($this->tempPath('alerts'));
        $alerts->create('workflow-engine', 'performance', 'warning', []);
        $reporter = new HealthReporter($alerts, clock: $clock);

        $result = $reporter->componentHealth('workflow-engine');

        $this->assertIsInt($result['evidence_age_seconds']);
        $this->assertGreaterThanOrEqual(0, $result['evidence_age_seconds']);
    }

    public function testStaleEvidenceBeyondTheThresholdReportsUnknown(): void
    {
        $farFuture = fn(): DateTimeImmutable => new DateTimeImmutable('2026-08-29T12:00:00Z');
        $alerts = new SqliteAlertManager($this->tempPath('alerts'));
        $alerts->create('workflow-engine', 'performance', 'warning', []);
        $reporter = new HealthReporter($alerts, clock: $farFuture);

        $result = $reporter->componentHealth('workflow-engine', ['staleness_seconds' => 60]);

        $this->assertSame('Unknown', $result['state']);
        $this->assertSame('stale_evidence', $result['outcome']);
    }

    public function testObservedEvidenceIsSeparateFromTheInferredState(): void
    {
        $alerts = new SqliteAlertManager($this->tempPath('alerts'));
        $alerts->create('workflow-engine', 'performance', 'warning', []);
        $reporter = new HealthReporter($alerts);

        $result = $reporter->componentHealth('workflow-engine');

        $this->assertArrayHasKey('open_alerts', $result['observed_evidence']);
        $this->assertSame('warning', $result['observed_evidence']['open_alerts'][0]['severity']);
        $this->assertSame('Degraded', $result['state']);
    }
}
