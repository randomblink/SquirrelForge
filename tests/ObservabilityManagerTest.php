<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Observability\DashboardManager;
use SquirrelForge\Observability\DiagnosticsEngine;
use SquirrelForge\Observability\HealthReporter;
use SquirrelForge\Observability\LogManager;
use SquirrelForge\Observability\MetricsManager;
use SquirrelForge\Observability\ObservabilityManager;
use SquirrelForge\Observability\SqliteAlertManager;
use SquirrelForge\Observability\SqliteObservabilityGovernance;
use SquirrelForge\Observability\TelemetryCollector;
use SquirrelForge\Observability\TraceManager;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class ObservabilityManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-observability-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{0: LogManager, 1: MetricsManager, 2: TraceManager, 3: SqliteAlertManager, 4: SqliteObservabilityGovernance, 5: ObservabilityManager}
     */
    private function fullyWiredManager(?EventBus $events = null): array
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $telemetry = new TelemetryCollector();
        $logs = new LogManager($documents);
        $metrics = new MetricsManager($documents);
        $traces = new TraceManager($documents);
        $diagnostics = new DiagnosticsEngine($logs, $metrics, $traces);
        $alerts = new SqliteAlertManager($this->tempPath('alerts'), $metrics, $diagnostics);
        $health = new HealthReporter($alerts, $metrics, $diagnostics);
        $dashboards = new DashboardManager($documents, $logs, $metrics, $traces, $alerts, $health);
        $governance = new SqliteObservabilityGovernance($this->tempPath('governance'));

        $manager = new ObservabilityManager($telemetry, $logs, $metrics, $traces, $diagnostics, $alerts, $health, $dashboards, $governance, $events);

        return [$logs, $metrics, $traces, $alerts, $governance, $manager];
    }

    private function telemetry(string $signalType, array $payload): array
    {
        return [
            'event_id' => 'evt_' . bin2hex(random_bytes(4)),
            'source' => 'workflow-engine',
            'signal_type' => $signalType,
            'correlation_id' => 'trace_1',
            'payload' => $payload,
            'timestamp' => '2026-07-29T12:00:00Z',
        ];
    }

    public function testUnknownOperationIsRejected(): void
    {
        $manager = new ObservabilityManager();

        $result = $manager->coordinate('teleport', []);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('none', $result['target_component']);
    }

    public function testMissingRequiredFieldIsRejectedBeforeRouting(): void
    {
        $manager = new ObservabilityManager();

        $result = $manager->coordinate('record_log', ['event_id' => 'evt_1']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('source', (string) $result['error']);
    }

    public function testCollectTelemetryRoutesToTelemetryCollector(): void
    {
        $manager = new ObservabilityManager(telemetryCollector: new TelemetryCollector());

        $result = $manager->coordinate('collect_telemetry', $this->telemetry('log', ['message' => 'hi']));

        $this->assertSame('telemetry_collector', $result['target_component']);
        $this->assertSame('collected', $result['outcome']);
    }

    public function testCollectTelemetryWithoutTelemetryCollectorIsRejected(): void
    {
        $manager = new ObservabilityManager();

        $result = $manager->coordinate('collect_telemetry', $this->telemetry('log', ['message' => 'hi']));

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testRecordLogRoutesToLogManager(): void
    {
        [, , , , , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('record_log', $this->telemetry('log', ['message' => 'hi']));

        $this->assertSame('log_manager', $result['target_component']);
        $this->assertSame('recorded', $result['outcome']);
    }

    public function testRecordMetricRoutesToMetricsManager(): void
    {
        [, , , , , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('record_metric', $this->telemetry('metric', ['metric_name' => 'queue_depth', 'value' => 5]));

        $this->assertSame('metrics_manager', $result['target_component']);
        $this->assertSame('recorded', $result['outcome']);
    }

    public function testRecordSpanRoutesToTraceManager(): void
    {
        [, , , , , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('record_span', $this->telemetry('trace', ['span_id' => 'span_a', 'operation' => 'step_a']));

        $this->assertSame('trace_manager', $result['target_component']);
        $this->assertSame('created', $result['outcome']);
    }

    public function testCorrelateEvidenceRoutesToDiagnosticsEngine(): void
    {
        [$logs, , , , , $manager] = $this->fullyWiredManager();
        $logs->recordLog($this->telemetry('log', ['message' => 'hi']));

        $result = $manager->coordinate('correlate_evidence', ['correlation_id' => 'trace_1']);

        $this->assertSame('diagnostics_engine', $result['target_component']);
        $this->assertCount(1, $result['result']['logs']);
    }

    public function testDiagnoseFailurePathRoutesToDiagnosticsEngine(): void
    {
        [, , $traces, , , $manager] = $this->fullyWiredManager();
        $traces->recordSpan($this->telemetry('trace', ['span_id' => 'span_a', 'operation' => 'step_a', 'status' => 'failed']));

        $result = $manager->coordinate('diagnose_failure_path', ['trace_id' => 'trace_1']);

        $this->assertSame(['span_a'], $result['result']['observed_failed_spans']);
    }

    public function testDiagnoseBottlenecksRoutesToDiagnosticsEngine(): void
    {
        [, , $traces, , , $manager] = $this->fullyWiredManager();
        $traces->recordSpan($this->telemetry('trace', ['span_id' => 'span_a', 'operation' => 'step_a', 'started_at' => '2026-07-29T12:00:00Z', 'ended_at' => '2026-07-29T12:00:05Z']));

        $result = $manager->coordinate('diagnose_bottlenecks', ['trace_id' => 'trace_1']);

        $this->assertSame('span_a', $result['result']['bottleneck_candidates'][0]['span_id']);
    }

    public function testDiagnoseMetricAnomaliesRoutesToDiagnosticsEngine(): void
    {
        [, $metrics, , , , $manager] = $this->fullyWiredManager();
        foreach ([100, 102, 98, 500] as $value) {
            $metrics->recordMetric($this->telemetry('metric', ['metric_name' => 'latency_ms', 'value' => $value]));
        }

        $result = $manager->coordinate('diagnose_metric_anomalies', ['metric_name' => 'latency_ms']);

        $this->assertSame('analyzed', $result['outcome']);
    }

    public function testEvaluateAlertRuleRoutesToAlertManager(): void
    {
        [, $metrics, , , , $manager] = $this->fullyWiredManager();
        $metrics->recordMetric($this->telemetry('metric', ['metric_name' => 'queue_depth', 'value' => 500]));

        $result = $manager->coordinate('evaluate_alert_rule', ['rule' => [
            'id' => 'rule_1', 'type' => 'metric_threshold', 'source' => 'workflow-engine',
            'category' => 'performance', 'severity' => 'warning',
            'metric' => 'queue_depth', 'operator' => '>', 'threshold' => 100,
        ]]);

        $this->assertSame('alert_manager', $result['target_component']);
        $this->assertTrue($result['result']['condition_met']);
    }

    public function testAcknowledgeAlertRoutesToAlertManager(): void
    {
        [, , , $alerts, , $manager] = $this->fullyWiredManager();
        $alert = $alerts->create('workflow-engine', 'performance', 'warning', []);

        $result = $manager->coordinate('acknowledge_alert', ['alert_id' => $alert['alert_id'], 'acknowledged_by' => 'oncall_jane']);

        $this->assertSame('transitioned', $result['outcome']);
    }

    public function testResolveAlertRoutesToAlertManager(): void
    {
        [, , , $alerts, , $manager] = $this->fullyWiredManager();
        $alert = $alerts->create('workflow-engine', 'performance', 'warning', []);

        $result = $manager->coordinate('resolve_alert', ['alert_id' => $alert['alert_id'], 'resolution_evidence' => 'Fixed.']);

        $this->assertSame('transitioned', $result['outcome']);
    }

    public function testReportHealthRoutesToHealthReporter(): void
    {
        [, , , , , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('report_health', ['source' => 'workflow-engine']);

        $this->assertSame('health_reporter', $result['target_component']);
        $this->assertSame('Healthy', $result['result']['state']);
    }

    public function testRenderDashboardRoutesToDashboardManager(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $dashboards = new DashboardManager($documents);
        $defined = $dashboards->defineDashboard('ops', [['id' => 'w1', 'type' => 'log']]);
        $manager = new ObservabilityManager(dashboardManager: $dashboards);

        $result = $manager->coordinate('render_dashboard', ['dashboard_ref' => $defined['dashboard_ref']]);

        $this->assertSame('dashboard_manager', $result['target_component']);
        $this->assertSame('rendered', $result['outcome']);
    }

    public function testReviewSignalStandardRoutesToObservabilityGovernance(): void
    {
        [, , , , , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('review_signal_standard', ['proposal_id' => 'proposal_1']);

        $this->assertSame('observability_governance', $result['target_component']);
        $this->assertSame('approved', $result['outcome']);
    }

    public function testCoordinateDispatchesAnEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('observability.finished', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        [, , , , , $manager] = $this->fullyWiredManager($events);
        $manager->coordinate('report_health', ['source' => 'workflow-engine']);

        $this->assertCount(1, $captured);
    }
}
