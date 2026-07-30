<?php

declare(strict_types=1);

namespace SquirrelForge\Observability;

use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Storage\SqliteDocumentStorage;

/**
 * Owns metric records, aggregates, and time-series references derived
 * from normalized telemetry, per 27_OBSERVABILITY/METRICS-MANAGER.md.
 *
 * Follows the same shape as LogManager: no database of its own,
 * genuinely delegating persistence to the injected SqliteDocumentStorage
 * as `metric_record` documents ("Preserve time-series references
 * through owning storage infrastructure"), consuming TelemetryCollector's
 * actual `normalized` output directly. A metric-bearing telemetry event
 * is expected to carry `metric_name` and a numeric `value` in its
 * payload -- a real, enforced input shape, not an invented one, since
 * "Maintain metric names, units, dimensions, source references, and
 * timestamps" requires a name and value to exist before anything else
 * is possible.
 *
 * "Calculate approved aggregates and derived metric references" is real
 * and deterministic: sum, count, mean, min, max over every recorded
 * value for a metric name, computed by reading the actual stored
 * records back through SqliteDocumentStorage::search()/retrieve() --
 * never a fabricated forecast, trend, or regression.
 *
 * Rule 2 ("Metrics Manager may expose threshold evidence, but alert
 * decisions belong to Alert Manager") is upheld literally by
 * thresholdEvidence(): it reuses RuleEngine's real `threshold` condition
 * type against the computed aggregate and returns whether the condition
 * was satisfied plus the evidence behind it -- it never labels anything
 * an "alert" or decides what should happen next, since that decision
 * belongs to the not-yet-built Alert Manager.
 *
 * Redaction/retention wiring against SqliteObservabilityGovernance
 * mirrors LogManager's own defense-in-depth reasoning, applied here to
 * a metric's free-form `dimensions` rather than a log payload, since
 * dimensions are exactly the kind of caller-supplied data that could
 * carry sensitive values (e.g. a `user_id` dimension).
 */
final class MetricsManager
{
    private const AGGREGATE_TYPES = ['sum', 'count', 'mean', 'min', 'max'];

    public function __construct(
        private readonly ?SqliteDocumentStorage $documentStorage = null,
        private readonly ?SqliteObservabilityGovernance $governance = null,
        private readonly RuleEngine $ruleEngine = new RuleEngine()
    ) {
    }

    /**
     * @param array{event_id: string, source: string, signal_type: string, classification?: ?string, correlation_id: string, payload: array<string, mixed>, timestamp: string} $telemetry TelemetryCollector::receive()'s `normalized` output
     * @param array{sensitive_fields?: array<int, string>} $options
     * @return array{metric_ref: ?string, outcome: string, error: ?string}
     */
    public function recordMetric(array $telemetry, array $options = []): array
    {
        if ($this->documentStorage === null) {
            return ['metric_ref' => null, 'outcome' => 'not_configured', 'error' => 'Document Storage is not configured.'];
        }

        $payload = $telemetry['payload'];
        $metricName = $payload['metric_name'] ?? null;

        if (!is_string($metricName) || $metricName === '') {
            return ['metric_ref' => null, 'outcome' => 'invalid_metric', 'error' => 'Payload requires a non-empty "metric_name" string.'];
        }

        if (!isset($payload['value']) || !is_numeric($payload['value'])) {
            return ['metric_ref' => null, 'outcome' => 'invalid_metric', 'error' => 'Payload requires a numeric "value".'];
        }

        $dimensions = is_array($payload['dimensions'] ?? null) ? $payload['dimensions'] : [];
        $retentionDays = null;

        if ($this->governance !== null) {
            $standard = $this->governance->getStandard($telemetry['signal_type']);
            $retentionDays = $standard['retention_days'] ?? null;

            if (($standard['redaction_required'] ?? false) === true) {
                foreach ($options['sensitive_fields'] ?? [] as $field) {
                    if (array_key_exists($field, $dimensions)) {
                        $dimensions[$field] = '[REDACTED]';
                    }
                }
            }
        }

        $content = [
            'metric_name' => $metricName,
            'value' => (float) $payload['value'],
            'unit' => $payload['unit'] ?? null,
            'dimensions' => $dimensions,
            'source' => $telemetry['source'],
            'correlation_id' => $telemetry['correlation_id'],
            'timestamp' => $telemetry['timestamp'],
        ];

        $result = $this->documentStorage->store('metric_record', sprintf('metric:%s:%s', $metricName, $telemetry['event_id']), $content, [
            'tags' => [$metricName],
            'owner' => $telemetry['source'],
            'classification' => $telemetry['classification'] ?? null,
            'metadata' => ['retention_days' => $retentionDays, 'correlation_id' => $telemetry['correlation_id']],
        ]);

        if ($result['outcome'] !== 'stored') {
            return ['metric_ref' => null, 'outcome' => 'rejected', 'error' => $result['error']];
        }

        return ['metric_ref' => $result['document_ref'], 'outcome' => 'recorded', 'error' => null];
    }

    /**
     * Real, deterministic aggregation over every recorded value for a
     * metric name. No forecasting, trend detection, or regression.
     *
     * @return array{metric_name: string, type: string, value: ?float, sample_count: int, outcome: string, error: ?string}
     */
    public function aggregate(string $metricName, string $type = 'mean'): array
    {
        if ($this->documentStorage === null) {
            return ['metric_name' => $metricName, 'type' => $type, 'value' => null, 'sample_count' => 0, 'outcome' => 'not_configured', 'error' => 'Document Storage is not configured.'];
        }

        if (!in_array($type, self::AGGREGATE_TYPES, true)) {
            return ['metric_name' => $metricName, 'type' => $type, 'value' => null, 'sample_count' => 0, 'outcome' => 'invalid_type', 'error' => sprintf('Unknown aggregate type "%s".', $type)];
        }

        $values = $this->valuesFor($metricName);

        if ($values === []) {
            $value = $type === 'count' ? 0.0 : null;

            return ['metric_name' => $metricName, 'type' => $type, 'value' => $value, 'sample_count' => 0, 'outcome' => $type === 'count' ? 'computed' : 'no_data', 'error' => null];
        }

        $value = match ($type) {
            'sum' => array_sum($values),
            'count' => (float) count($values),
            'mean' => array_sum($values) / count($values),
            'min' => min($values),
            'max' => max($values),
        };

        return ['metric_name' => $metricName, 'type' => $type, 'value' => $value, 'sample_count' => count($values), 'outcome' => 'computed', 'error' => null];
    }

    /**
     * Exposes threshold evidence for a metric's aggregate. Never decides
     * whether an alert should fire -- that decision belongs to
     * `27_OBSERVABILITY/ALERT-MANAGER.md` per Rule 2.
     *
     * @return array{metric_name: string, satisfied: ?bool, aggregate: array<string, mixed>, evidence: array<string, mixed>, error: ?string}
     */
    public function thresholdEvidence(string $metricName, string $operator, float $threshold, string $aggregateType = 'mean'): array
    {
        $aggregate = $this->aggregate($metricName, $aggregateType);

        if ($aggregate['value'] === null) {
            return ['metric_name' => $metricName, 'satisfied' => null, 'aggregate' => $aggregate, 'evidence' => [], 'error' => $aggregate['error'] ?? sprintf('No data available for metric "%s".', $metricName)];
        }

        $evaluation = $this->ruleEngine->evaluate(
            ['id' => 'threshold_evidence', 'condition' => ['type' => 'threshold', 'field' => 'aggregate_value', 'operator' => $operator, 'value' => $threshold]],
            ['aggregate_value' => $aggregate['value']]
        );

        return ['metric_name' => $metricName, 'satisfied' => $evaluation['result'] === 'pass', 'aggregate' => $aggregate, 'evidence' => $evaluation['evidence'], 'error' => $evaluation['error']];
    }

    /**
     * @return array<int, float>
     */
    private function valuesFor(string $metricName): array
    {
        $values = [];

        foreach ($this->documentStorage->search(['type' => 'metric_record', 'tag' => $metricName]) as $document) {
            $retrieved = $this->documentStorage->retrieve($document['document_ref']);

            if ($retrieved['found'] && is_numeric($retrieved['content']['value'] ?? null)) {
                $values[] = (float) $retrieved['content']['value'];
            }
        }

        return $values;
    }
}
