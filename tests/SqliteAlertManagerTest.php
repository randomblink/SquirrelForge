<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Observability\DiagnosticsEngine;
use SquirrelForge\Observability\MetricsManager;
use SquirrelForge\Observability\SqliteAlertManager;
use SquirrelForge\Observability\SqliteObservabilityGovernance;
use SquirrelForge\Observability\TraceManager;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class SqliteAlertManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-alert-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function manager(): SqliteAlertManager
    {
        return new SqliteAlertManager($this->tempPath('alerts'));
    }

    public function testCreateOpensANewAlert(): void
    {
        $manager = $this->manager();

        $result = $manager->create('workflow-engine', 'performance', 'warning', ['note' => 'queue backing up']);

        $this->assertSame('created', $result['outcome']);
        $this->assertSame('Open', $result['status']);
        $alert = $manager->get($result['alert_id']);
        $this->assertSame('performance', $alert['category']);
        $this->assertSame(['note' => 'queue backing up'], $alert['evidence']);
    }

    public function testCreateSuppressesADuplicateOfAnOpenAlert(): void
    {
        $manager = $this->manager();
        $first = $manager->create('workflow-engine', 'performance', 'warning', []);

        $second = $manager->create('workflow-engine', 'performance', 'warning', []);

        $this->assertSame('duplicate_suppressed', $second['outcome']);
        $this->assertSame('Suppressed', $second['status']);
        $this->assertSame($first['alert_id'], $second['duplicate_of']);
    }

    public function testCreateAfterTheOriginalIsResolvedIsNotADuplicate(): void
    {
        $manager = $this->manager();
        $first = $manager->create('workflow-engine', 'performance', 'warning', []);
        $manager->resolve($first['alert_id'], 'Queue drained.');

        $second = $manager->create('workflow-engine', 'performance', 'warning', []);

        $this->assertSame('created', $second['outcome']);
        $this->assertSame('Open', $second['status']);
    }

    public function testGetReturnsNullForAnUnknownAlert(): void
    {
        $manager = $this->manager();

        $this->assertNull($manager->get('alert_ghost'));
    }

    public function testAcknowledgeRequiresANonEmptyAcknowledgedBy(): void
    {
        $manager = $this->manager();
        $alert = $manager->create('workflow-engine', 'performance', 'warning', []);

        $result = $manager->acknowledge($alert['alert_id'], '');

        $this->assertSame('invalid_request', $result['outcome']);
    }

    public function testAcknowledgeTransitionsFromOpen(): void
    {
        $manager = $this->manager();
        $alert = $manager->create('workflow-engine', 'performance', 'warning', []);

        $result = $manager->acknowledge($alert['alert_id'], 'oncall_jane');

        $this->assertSame('transitioned', $result['outcome']);
        $this->assertSame('Acknowledged', $result['status']);
    }

    public function testAcknowledgeRejectsAnInvalidTransition(): void
    {
        $manager = $this->manager();
        $alert = $manager->create('workflow-engine', 'performance', 'warning', []);
        $manager->acknowledge($alert['alert_id'], 'oncall_jane');

        $result = $manager->acknowledge($alert['alert_id'], 'oncall_jane');

        $this->assertSame('invalid_transition', $result['outcome']);
    }

    public function testTransitioningAnUnknownAlertIsNotFound(): void
    {
        $manager = $this->manager();

        $result = $manager->acknowledge('alert_ghost', 'oncall_jane');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testSuppressRequiresANonEmptyReason(): void
    {
        $manager = $this->manager();
        $alert = $manager->create('workflow-engine', 'performance', 'warning', []);

        $result = $manager->suppress($alert['alert_id'], '');

        $this->assertSame('invalid_request', $result['outcome']);
    }

    public function testSuppressTransitionsFromOpenOrAcknowledged(): void
    {
        $manager = $this->manager();
        $alert = $manager->create('workflow-engine', 'performance', 'warning', []);

        $result = $manager->suppress($alert['alert_id'], 'Known maintenance window.');

        $this->assertSame('transitioned', $result['outcome']);
        $this->assertSame('Suppressed', $result['status']);
    }

    public function testRequestEscalationRequiresANonEmptyReason(): void
    {
        $manager = $this->manager();
        $alert = $manager->create('workflow-engine', 'performance', 'warning', []);

        $result = $manager->requestEscalation($alert['alert_id'], '');

        $this->assertSame('invalid_request', $result['outcome']);
    }

    public function testRequestEscalationTransitionsAndNeverExecutesADeliveryAction(): void
    {
        $manager = $this->manager();
        $alert = $manager->create('workflow-engine', 'performance', 'critical', []);

        $result = $manager->requestEscalation($alert['alert_id'], 'No response after 15 minutes.');

        $this->assertSame('transitioned', $result['outcome']);
        $this->assertSame('Escalation Requested', $result['status']);
        $this->assertArrayNotHasKey('notification_sent', $result);
    }

    public function testResolveRequiresNonEmptyResolutionEvidence(): void
    {
        $manager = $this->manager();
        $alert = $manager->create('workflow-engine', 'performance', 'warning', []);

        $result = $manager->resolve($alert['alert_id'], '');

        $this->assertSame('invalid_request', $result['outcome']);
        $this->assertSame('Open', $manager->get($alert['alert_id'])['status']);
    }

    public function testResolveClosesTheAlertAndStoresTheEvidence(): void
    {
        $manager = $this->manager();
        $alert = $manager->create('workflow-engine', 'performance', 'warning', []);

        $result = $manager->resolve($alert['alert_id'], 'Queue drained after scale-up.');

        $this->assertSame('transitioned', $result['outcome']);
        $this->assertSame('Resolved', $result['status']);
        $this->assertSame('Queue drained after scale-up.', $manager->get($alert['alert_id'])['resolution_evidence']);
    }

    public function testResolveTwiceIsAnInvalidTransition(): void
    {
        $manager = $this->manager();
        $alert = $manager->create('workflow-engine', 'performance', 'warning', []);
        $manager->resolve($alert['alert_id'], 'Fixed.');

        $result = $manager->resolve($alert['alert_id'], 'Fixed again.');

        $this->assertSame('invalid_transition', $result['outcome']);
    }

    public function testHistoryReturnsTransitionsInChronologicalOrder(): void
    {
        $manager = $this->manager();
        $alert = $manager->create('workflow-engine', 'performance', 'warning', []);
        $manager->acknowledge($alert['alert_id'], 'oncall_jane');
        $manager->resolve($alert['alert_id'], 'Fixed.');

        $history = $manager->history($alert['alert_id']);

        $this->assertCount(3, $history);
        $this->assertSame(['Open', 'Acknowledged', 'Resolved'], array_column($history, 'to_status'));
    }

    public function testEvaluateWithAnUnknownRuleTypeIsInvalid(): void
    {
        $manager = $this->manager();

        $result = $manager->evaluate(['id' => 'rule_1', 'type' => 'unknown']);

        $this->assertSame('invalid_rule', $result['outcome']);
    }

    public function testEvaluateWithoutAnIdIsInvalid(): void
    {
        $manager = $this->manager();

        $result = $manager->evaluate(['type' => 'metric_threshold']);

        $this->assertSame('invalid_rule', $result['outcome']);
    }

    public function testEvaluateMetricThresholdRuleWithoutMetricsManagerIsNotConfigured(): void
    {
        $manager = $this->manager();

        $result = $manager->evaluate(['id' => 'rule_1', 'type' => 'metric_threshold']);

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testEvaluateMetricThresholdRuleWithMissingFieldsIsInvalid(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $manager = new SqliteAlertManager($this->tempPath('alerts'), $metrics);

        $result = $manager->evaluate(['id' => 'rule_1', 'type' => 'metric_threshold']);

        $this->assertSame('invalid_rule', $result['outcome']);
    }

    public function testEvaluateMetricThresholdRuleNotMetCreatesNoAlert(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $manager = new SqliteAlertManager($this->tempPath('alerts'), $metrics);

        $result = $manager->evaluate([
            'id' => 'rule_1', 'type' => 'metric_threshold', 'source' => 'workflow-engine',
            'category' => 'performance', 'severity' => 'warning',
            'metric' => 'queue_depth', 'operator' => '>', 'threshold' => 100,
        ]);

        $this->assertSame('condition_not_met', $result['outcome']);
        $this->assertNull($result['alert']);
    }

    public function testEvaluateMetricThresholdRuleMetCreatesARealAlertFromRealEvidence(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $manager = new SqliteAlertManager($this->tempPath('alerts'), $metrics);
        $metrics->recordMetric([
            'event_id' => 'evt_1', 'source' => 'workflow-engine', 'signal_type' => 'metric',
            'correlation_id' => 'corr_1', 'payload' => ['metric_name' => 'queue_depth', 'value' => 500],
            'timestamp' => '2026-07-29T12:00:00Z',
        ]);

        $result = $manager->evaluate([
            'id' => 'rule_1', 'type' => 'metric_threshold', 'source' => 'workflow-engine',
            'category' => 'performance', 'severity' => 'warning',
            'metric' => 'queue_depth', 'operator' => '>', 'threshold' => 100,
        ]);

        $this->assertTrue($result['condition_met']);
        $this->assertSame('created', $result['alert']['outcome']);
        $stored = $manager->get($result['alert']['alert_id']);
        $this->assertEquals(500.0, $stored['evidence']['aggregate']['value']);
    }

    public function testEvaluateFailurePathRuleWithoutDiagnosticsEngineIsNotConfigured(): void
    {
        $manager = $this->manager();

        $result = $manager->evaluate(['id' => 'rule_1', 'type' => 'failure_path']);

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testEvaluateFailurePathRuleNotMetCreatesNoAlert(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $traces = new TraceManager($documents);
        $diagnostics = new DiagnosticsEngine(traces: $traces);
        $manager = new SqliteAlertManager($this->tempPath('alerts'), diagnostics: $diagnostics);
        $traces->recordSpan([
            'event_id' => 'evt_1', 'source' => 'workflow-engine', 'signal_type' => 'trace',
            'correlation_id' => 'trace_1', 'payload' => ['span_id' => 'span_a', 'operation' => 'step_a', 'status' => 'completed'],
            'timestamp' => '2026-07-29T12:00:00Z',
        ]);

        $result = $manager->evaluate([
            'id' => 'rule_1', 'type' => 'failure_path', 'source' => 'workflow-engine',
            'category' => 'reliability', 'severity' => 'error', 'trace_id' => 'trace_1',
        ]);

        $this->assertSame('condition_not_met', $result['outcome']);
    }

    public function testEvaluateFailurePathRuleMetCreatesARealAlertFromRealEvidence(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $traces = new TraceManager($documents);
        $diagnostics = new DiagnosticsEngine(traces: $traces);
        $manager = new SqliteAlertManager($this->tempPath('alerts'), diagnostics: $diagnostics);
        $traces->recordSpan([
            'event_id' => 'evt_1', 'source' => 'workflow-engine', 'signal_type' => 'trace',
            'correlation_id' => 'trace_1', 'payload' => ['span_id' => 'span_a', 'operation' => 'step_a', 'status' => 'failed'],
            'timestamp' => '2026-07-29T12:00:00Z',
        ]);

        $result = $manager->evaluate([
            'id' => 'rule_1', 'type' => 'failure_path', 'source' => 'workflow-engine',
            'category' => 'reliability', 'severity' => 'error', 'trace_id' => 'trace_1',
        ]);

        $this->assertTrue($result['condition_met']);
        $this->assertSame('created', $result['alert']['outcome']);
    }

    public function testRetentionDaysFromGovernanceIsCarriedIntoTheAlertRecord(): void
    {
        $governance = new SqliteObservabilityGovernance($this->tempPath('governance'));
        $governance->defineStandard('alert', ['retention_days' => 180]);
        $manager = new SqliteAlertManager($this->tempPath('alerts'), governance: $governance);

        $alert = $manager->create('workflow-engine', 'performance', 'warning', []);

        $this->assertSame(180, $manager->get($alert['alert_id'])['retention_days']);
    }

    public function testCreateDispatchesAnEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('alert.created', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $manager = new SqliteAlertManager($this->tempPath('alerts'), events: $events);
        $manager->create('workflow-engine', 'performance', 'warning', []);

        $this->assertCount(1, $captured);
    }
}
