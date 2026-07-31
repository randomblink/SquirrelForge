<?php

declare(strict_types=1);

namespace SquirrelForge\Observability;

use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Top-level coordinator for the Observability Layer, per
 * 27_OBSERVABILITY/OBSERVABILITY-MANAGER.md, mirroring how
 * LearningManager, AutomationManager, and KnowledgeManager route to
 * their own layer's real specialists.
 *
 * Eight of the nine components the roster lists are real and each gets
 * at least one routed operation: Telemetry Collector (collect_telemetry),
 * Log/Metrics/Trace Manager (record_log/record_metric/record_span),
 * Diagnostics Engine (correlate_evidence, diagnose_failure_path,
 * diagnose_bottlenecks, diagnose_metric_anomalies), Alert Manager
 * (evaluate_alert_rule, acknowledge_alert, resolve_alert), Health
 * Reporter (report_health), Dashboard Manager (render_dashboard), and
 * Observability Governance (review_signal_standard). Audit Trail has no
 * implementation yet, so there is no operation routing to it -- this
 * class doesn't invent one, the same restraint LearningManager showed
 * by not routing to the unbuilt Learning Monitor.
 *
 * This class never re-implements any specialist's logic, only forwards
 * payloads and passes back real results unmodified, upholding Rule 1
 * ("must route work to the owning Observability component") and Rule 2
 * ("may aggregate status and evidence references only"). Like its
 * sibling coordinators, it owns no database of its own -- the spec
 * forbids owning "storage infrastructure" -- only an optional
 * EventBusInterface announcement per operation.
 */
final class ObservabilityManager
{
    private const REQUIRED_FIELDS = [
        'collect_telemetry' => [],
        'record_log' => ['event_id', 'source', 'signal_type', 'correlation_id', 'payload', 'timestamp'],
        'record_metric' => ['event_id', 'source', 'signal_type', 'correlation_id', 'payload', 'timestamp'],
        'record_span' => ['event_id', 'source', 'signal_type', 'correlation_id', 'payload', 'timestamp'],
        'correlate_evidence' => ['correlation_id'],
        'diagnose_failure_path' => ['trace_id'],
        'diagnose_bottlenecks' => ['trace_id'],
        'diagnose_metric_anomalies' => ['metric_name'],
        'evaluate_alert_rule' => ['rule'],
        'acknowledge_alert' => ['alert_id', 'acknowledged_by'],
        'resolve_alert' => ['alert_id', 'resolution_evidence'],
        'report_health' => ['source'],
        'render_dashboard' => ['dashboard_ref'],
        'review_signal_standard' => ['proposal_id'],
    ];

    public function __construct(
        private readonly ?TelemetryCollector $telemetryCollector = null,
        private readonly ?LogManager $logManager = null,
        private readonly ?MetricsManager $metricsManager = null,
        private readonly ?TraceManager $traceManager = null,
        private readonly ?DiagnosticsEngine $diagnosticsEngine = null,
        private readonly ?SqliteAlertManager $alertManager = null,
        private readonly ?HealthReporter $healthReporter = null,
        private readonly ?DashboardManager $dashboardManager = null,
        private readonly ?SqliteObservabilityGovernance $governance = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options forwarded to whichever specialist handles this operation
     * @return array{operation: string, target_component: string, outcome: string, error: ?string, result: mixed}
     */
    public function coordinate(string $operation, array $payload, array $options = []): array
    {
        if (!isset(self::REQUIRED_FIELDS[$operation])) {
            return $this->finish($operation, 'none', 'rejected', sprintf('Unknown observability operation "%s".', $operation), null);
        }

        $missingField = $this->firstMissingField($operation, $payload);

        if ($missingField !== null) {
            return $this->finish($operation, 'none', 'rejected', sprintf('Missing required field "%s" for operation "%s".', $missingField, $operation), null);
        }

        return match ($operation) {
            'collect_telemetry' => $this->coordinateCollectTelemetry($payload, $options),
            'record_log' => $this->coordinateRecordLog($payload, $options),
            'record_metric' => $this->coordinateRecordMetric($payload, $options),
            'record_span' => $this->coordinateRecordSpan($payload, $options),
            'correlate_evidence' => $this->coordinateCorrelateEvidence($payload),
            'diagnose_failure_path' => $this->coordinateDiagnoseFailurePath($payload),
            'diagnose_bottlenecks' => $this->coordinateDiagnoseBottlenecks($payload),
            'diagnose_metric_anomalies' => $this->coordinateDiagnoseMetricAnomalies($payload),
            'evaluate_alert_rule' => $this->coordinateEvaluateAlertRule($payload),
            'acknowledge_alert' => $this->coordinateAcknowledgeAlert($payload),
            'resolve_alert' => $this->coordinateResolveAlert($payload),
            'report_health' => $this->coordinateReportHealth($payload, $options),
            'render_dashboard' => $this->coordinateRenderDashboard($payload, $options),
            'review_signal_standard' => $this->coordinateReviewSignalStandard($payload, $options),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function firstMissingField(string $operation, array $payload): ?string
    {
        foreach (self::REQUIRED_FIELDS[$operation] as $field) {
            if (!array_key_exists($field, $payload)) {
                return $field;
            }
        }

        return null;
    }

    private function coordinateCollectTelemetry(array $payload, array $options): array
    {
        if ($this->telemetryCollector === null) {
            return $this->finish('collect_telemetry', 'telemetry_collector', 'rejected', 'Telemetry Collector is not configured.', null);
        }

        $result = $this->telemetryCollector->receive($payload, $options);

        return $this->finish('collect_telemetry', 'telemetry_collector', $result['outcome'], $result['error'], $result);
    }

    private function coordinateRecordLog(array $payload, array $options): array
    {
        if ($this->logManager === null) {
            return $this->finish('record_log', 'log_manager', 'rejected', 'Log Manager is not configured.', null);
        }

        $result = $this->logManager->recordLog($payload, $options);

        return $this->finish('record_log', 'log_manager', $result['outcome'], $result['error'], $result);
    }

    private function coordinateRecordMetric(array $payload, array $options): array
    {
        if ($this->metricsManager === null) {
            return $this->finish('record_metric', 'metrics_manager', 'rejected', 'Metrics Manager is not configured.', null);
        }

        $result = $this->metricsManager->recordMetric($payload, $options);

        return $this->finish('record_metric', 'metrics_manager', $result['outcome'], $result['error'], $result);
    }

    private function coordinateRecordSpan(array $payload, array $options): array
    {
        if ($this->traceManager === null) {
            return $this->finish('record_span', 'trace_manager', 'rejected', 'Trace Manager is not configured.', null);
        }

        $result = $this->traceManager->recordSpan($payload, $options);

        return $this->finish('record_span', 'trace_manager', $result['outcome'], $result['error'], $result);
    }

    private function coordinateCorrelateEvidence(array $payload): array
    {
        if ($this->diagnosticsEngine === null) {
            return $this->finish('correlate_evidence', 'diagnostics_engine', 'rejected', 'Diagnostics Engine is not configured.', null);
        }

        $result = $this->diagnosticsEngine->correlate($payload['correlation_id'], $payload['metric_names'] ?? []);

        return $this->finish('correlate_evidence', 'diagnostics_engine', 'correlated', null, $result);
    }

    private function coordinateDiagnoseFailurePath(array $payload): array
    {
        if ($this->diagnosticsEngine === null) {
            return $this->finish('diagnose_failure_path', 'diagnostics_engine', 'rejected', 'Diagnostics Engine is not configured.', null);
        }

        $result = $this->diagnosticsEngine->findFailurePath($payload['trace_id']);

        return $this->finish('diagnose_failure_path', 'diagnostics_engine', $result['outcome'], null, $result);
    }

    private function coordinateDiagnoseBottlenecks(array $payload): array
    {
        if ($this->diagnosticsEngine === null) {
            return $this->finish('diagnose_bottlenecks', 'diagnostics_engine', 'rejected', 'Diagnostics Engine is not configured.', null);
        }

        $result = $this->diagnosticsEngine->findBottlenecks($payload['trace_id'], $payload['limit'] ?? 3);

        return $this->finish('diagnose_bottlenecks', 'diagnostics_engine', $result['outcome'], null, $result);
    }

    private function coordinateDiagnoseMetricAnomalies(array $payload): array
    {
        if ($this->diagnosticsEngine === null) {
            return $this->finish('diagnose_metric_anomalies', 'diagnostics_engine', 'rejected', 'Diagnostics Engine is not configured.', null);
        }

        $result = $this->diagnosticsEngine->findMetricAnomalies($payload['metric_name'], $payload['z_score_threshold'] ?? 2.0);

        return $this->finish('diagnose_metric_anomalies', 'diagnostics_engine', $result['outcome'], $result['error'], $result);
    }

    private function coordinateEvaluateAlertRule(array $payload): array
    {
        if ($this->alertManager === null) {
            return $this->finish('evaluate_alert_rule', 'alert_manager', 'rejected', 'Alert Manager is not configured.', null);
        }

        $result = $this->alertManager->evaluate($payload['rule']);

        return $this->finish('evaluate_alert_rule', 'alert_manager', $result['outcome'], $result['error'], $result);
    }

    private function coordinateAcknowledgeAlert(array $payload): array
    {
        if ($this->alertManager === null) {
            return $this->finish('acknowledge_alert', 'alert_manager', 'rejected', 'Alert Manager is not configured.', null);
        }

        $result = $this->alertManager->acknowledge($payload['alert_id'], $payload['acknowledged_by']);

        return $this->finish('acknowledge_alert', 'alert_manager', $result['outcome'], $result['error'], $result);
    }

    private function coordinateResolveAlert(array $payload): array
    {
        if ($this->alertManager === null) {
            return $this->finish('resolve_alert', 'alert_manager', 'rejected', 'Alert Manager is not configured.', null);
        }

        $result = $this->alertManager->resolve($payload['alert_id'], $payload['resolution_evidence']);

        return $this->finish('resolve_alert', 'alert_manager', $result['outcome'], $result['error'], $result);
    }

    private function coordinateReportHealth(array $payload, array $options): array
    {
        if ($this->healthReporter === null) {
            return $this->finish('report_health', 'health_reporter', 'rejected', 'Health Reporter is not configured.', null);
        }

        $result = $this->healthReporter->componentHealth($payload['source'], $options);

        return $this->finish('report_health', 'health_reporter', $result['outcome'], null, $result);
    }

    private function coordinateRenderDashboard(array $payload, array $options): array
    {
        if ($this->dashboardManager === null) {
            return $this->finish('render_dashboard', 'dashboard_manager', 'rejected', 'Dashboard Manager is not configured.', null);
        }

        $result = $this->dashboardManager->renderDashboard($payload['dashboard_ref'], $options);

        return $this->finish('render_dashboard', 'dashboard_manager', $result['outcome'], $result['error'], $result);
    }

    private function coordinateReviewSignalStandard(array $payload, array $options): array
    {
        if ($this->governance === null) {
            return $this->finish('review_signal_standard', 'observability_governance', 'rejected', 'Observability Governance is not configured.', null);
        }

        $result = $this->governance->review($payload['proposal_id'], $options);

        return $this->finish('review_signal_standard', 'observability_governance', $result['outcome'], null, $result);
    }

    private function finish(string $operation, string $targetComponent, string $outcome, ?string $error, mixed $result): array
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'observability.finished',
            new DateTimeImmutable(),
            self::class,
            ['operation' => $operation, 'target_component' => $targetComponent, 'outcome' => $outcome]
        ));

        return ['operation' => $operation, 'target_component' => $targetComponent, 'outcome' => $outcome, 'error' => $error, 'result' => $result];
    }
}
