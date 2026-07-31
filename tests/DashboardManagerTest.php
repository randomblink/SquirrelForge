<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Observability\DashboardManager;
use SquirrelForge\Observability\HealthReporter;
use SquirrelForge\Observability\LogManager;
use SquirrelForge\Observability\MetricsManager;
use SquirrelForge\Observability\SqliteAlertManager;
use SquirrelForge\Observability\TraceManager;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class DashboardManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-dashboard-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function telemetry(string $signalType, array $payload): array
    {
        return [
            'event_id' => 'evt_' . bin2hex(random_bytes(4)),
            'source' => 'workflow-engine',
            'signal_type' => $signalType,
            'classification' => null,
            'correlation_id' => 'trace_1',
            'payload' => $payload,
            'timestamp' => '2026-07-29T12:00:00Z',
        ];
    }

    public function testDefineDashboardWithoutDocumentStorageConfiguredIsNotConfigured(): void
    {
        $manager = new DashboardManager();

        $result = $manager->defineDashboard('ops', [['id' => 'w1', 'type' => 'log']]);

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testDefineDashboardRequiresAtLeastOneWidget(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new DashboardManager($documents);

        $result = $manager->defineDashboard('ops', []);

        $this->assertSame('invalid_definition', $result['outcome']);
    }

    public function testDefineDashboardRejectsAWidgetMissingAnId(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new DashboardManager($documents);

        $result = $manager->defineDashboard('ops', [['type' => 'log']]);

        $this->assertSame('invalid_definition', $result['outcome']);
    }

    public function testDefineDashboardRejectsAnUnknownWidgetType(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new DashboardManager($documents);

        $result = $manager->defineDashboard('ops', [['id' => 'w1', 'type' => 'weather']]);

        $this->assertSame('invalid_definition', $result['outcome']);
    }

    public function testDefineDashboardStoresARetrievableDefinition(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new DashboardManager($documents);

        $result = $manager->defineDashboard('ops', [['id' => 'w1', 'type' => 'log']]);

        $this->assertSame('defined', $result['outcome']);
        $definition = $manager->getDashboard($result['dashboard_ref']);
        $this->assertSame('ops', $definition['name']);
        $this->assertCount(1, $definition['widgets']);
    }

    public function testGetDashboardReturnsNullForAnUnknownReference(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new DashboardManager($documents);

        $this->assertNull($manager->getDashboard('document_ghost'));
    }

    public function testRenderDashboardWithAnUnknownReferenceIsNotFound(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new DashboardManager($documents);

        $result = $manager->renderDashboard('document_ghost');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testRenderDashboardWithAnUnconfiguredManagerReportsNotConfigured(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $manager = new DashboardManager($documents);
        $defined = $manager->defineDashboard('ops', [['id' => 'w1', 'type' => 'log']]);

        $result = $manager->renderDashboard($defined['dashboard_ref']);

        $this->assertSame('not_configured', $result['widgets'][0]['outcome']);
        $this->assertSame('log_manager', $result['widgets'][0]['source']);
    }

    public function testRenderDashboardEveryWidgetCarriesSourceAndFreshness(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $logs = new LogManager($documents);
        $manager = new DashboardManager($documents, $logs);
        $defined = $manager->defineDashboard('ops', [['id' => 'w1', 'type' => 'log']]);

        $result = $manager->renderDashboard($defined['dashboard_ref']);

        $this->assertSame('log_manager', $result['widgets'][0]['source']);
        $this->assertSame($result['rendered_at'], $result['widgets'][0]['freshness']);
    }

    public function testRenderDashboardLogWidgetReturnsRealRecords(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $logs = new LogManager($documents);
        $logs->recordLog($this->telemetry('log', ['message' => 'order accepted']));
        $manager = new DashboardManager($documents, $logs);
        $defined = $manager->defineDashboard('ops', [['id' => 'w1', 'type' => 'log']]);

        $result = $manager->renderDashboard($defined['dashboard_ref']);

        $this->assertSame('order accepted', $result['widgets'][0]['data'][0]['payload']['message']);
    }

    public function testRenderDashboardMetricWidgetWithoutMetricNameFilterIsNotConfigured(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $manager = new DashboardManager($documents, metrics: $metrics);
        $defined = $manager->defineDashboard('ops', [['id' => 'w1', 'type' => 'metric']]);

        $result = $manager->renderDashboard($defined['dashboard_ref']);

        $this->assertSame('not_configured', $result['widgets'][0]['outcome']);
    }

    public function testRenderDashboardMetricWidgetReturnsARealAggregate(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $metrics->recordMetric($this->telemetry('metric', ['metric_name' => 'queue_depth', 'value' => 12]));
        $manager = new DashboardManager($documents, metrics: $metrics);
        $defined = $manager->defineDashboard('ops', [['id' => 'w1', 'type' => 'metric', 'filters' => ['metric_name' => 'queue_depth']]]);

        $result = $manager->renderDashboard($defined['dashboard_ref']);

        $this->assertEquals(12.0, $result['widgets'][0]['data']['value']);
    }

    public function testRenderDashboardTraceWidgetReturnsARealExecutionPath(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $traces = new TraceManager($documents);
        $traces->recordSpan($this->telemetry('trace', ['span_id' => 'span_a', 'operation' => 'step_a']));
        $manager = new DashboardManager($documents, traces: $traces);
        $defined = $manager->defineDashboard('ops', [['id' => 'w1', 'type' => 'trace', 'filters' => ['trace_id' => 'trace_1']]]);

        $result = $manager->renderDashboard($defined['dashboard_ref']);

        $this->assertSame(['span_a'], array_column($result['widgets'][0]['data']['spans'], 'span_id'));
    }

    public function testRenderDashboardAlertWidgetReturnsRealOpenAlerts(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $alerts = new SqliteAlertManager($this->tempPath('alerts'));
        $alerts->create('workflow-engine', 'performance', 'warning', []);
        $manager = new DashboardManager($documents, alerts: $alerts);
        $defined = $manager->defineDashboard('ops', [['id' => 'w1', 'type' => 'alert', 'filters' => ['source' => 'workflow-engine']]]);

        $result = $manager->renderDashboard($defined['dashboard_ref']);

        $this->assertCount(1, $result['widgets'][0]['data']);
        $this->assertSame('warning', $result['widgets'][0]['data'][0]['severity']);
    }

    public function testRenderDashboardHealthWidgetReturnsARealHealthReport(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $alerts = new SqliteAlertManager($this->tempPath('alerts'));
        $health = new HealthReporter($alerts);
        $manager = new DashboardManager($documents, health: $health);
        $defined = $manager->defineDashboard('ops', [['id' => 'w1', 'type' => 'health', 'filters' => ['source' => 'workflow-engine']]]);

        $result = $manager->renderDashboard($defined['dashboard_ref']);

        $this->assertSame('Healthy', $result['widgets'][0]['data']['state']);
    }

    public function testRenderDashboardStripsProhibitedFieldsRecursively(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $logs = new LogManager($documents);
        $logs->recordLog($this->telemetry('log', ['message' => 'hi', 'password' => 'secret123']));
        $manager = new DashboardManager($documents, $logs);
        $defined = $manager->defineDashboard('ops', [['id' => 'w1', 'type' => 'log']]);

        $result = $manager->renderDashboard($defined['dashboard_ref'], ['prohibited_fields' => ['password']]);

        $this->assertArrayNotHasKey('password', $result['widgets'][0]['data'][0]['payload']);
        $this->assertSame('hi', $result['widgets'][0]['data'][0]['payload']['message']);
    }

    public function testRenderDashboardWithMultipleWidgetsRendersEachIndependently(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $logs = new LogManager($documents);
        $metrics = new MetricsManager($documents);
        $logs->recordLog($this->telemetry('log', ['message' => 'hi']));
        $metrics->recordMetric($this->telemetry('metric', ['metric_name' => 'queue_depth', 'value' => 5]));
        $manager = new DashboardManager($documents, $logs, $metrics);
        $defined = $manager->defineDashboard('ops', [
            ['id' => 'logs', 'type' => 'log'],
            ['id' => 'metrics', 'type' => 'metric', 'filters' => ['metric_name' => 'queue_depth']],
        ]);

        $result = $manager->renderDashboard($defined['dashboard_ref']);

        $this->assertCount(2, $result['widgets']);
        $this->assertSame('rendered', $result['widgets'][0]['outcome']);
        $this->assertSame('rendered', $result['widgets'][1]['outcome']);
    }
}
