<?php

declare(strict_types=1);

namespace SquirrelForge\Observability;

/**
 * Interprets Log/Metrics/Trace Manager records to produce diagnostic
 * findings, correlation reports, and probable-cause references, per
 * 27_OBSERVABILITY/DIAGNOSTICS-ENGINE.md.
 *
 * Like RuleEngine/PatternDetector, this class has no database of its
 * own: findings are pure output, never persisted here. It does not take
 * a direct SqliteObservabilityGovernance dependency even though the
 * spec lists it, because Diagnostics Engine writes nothing new --
 * redaction and retention were already enforced when Log/Metrics/Trace
 * Manager wrote the records this class only reads back through their
 * own real methods (LogManager::recent(), MetricsManager::values()/
 * aggregate(), TraceManager::executionPath()/latencyEvidence()) --
 * there is no separate governed action here to gate.
 *
 * Rule 3 ("must distinguish observed evidence from inferred probable
 * cause") is upheld structurally throughout: every finding method
 * separates its `observed_*` fields (facts read directly from records)
 * from at most one `inferred_*` field (a narrow, explainable guess),
 * and no method ever claims a root cause, assigns fault, or recommends
 * remediation -- per Rule 2, that belongs to Alert Manager, Health
 * Reporter, and the owning domain components.
 *
 * findMetricAnomalies() implements a real z-score over
 * MetricsManager::values(), the same well-understood statistic
 * PatternDetector already uses for experience records -- reimplemented
 * here rather than shared, since the two operate over unrelated data
 * sources and the arithmetic is a dozen lines, not worth a premature
 * shared abstraction. "alert, health" references from the spec's
 * Responsibilities are consumed only as caller-supplied opaque evidence
 * (Alert Manager and Health Reporter have no code yet to define a real
 * shape for either).
 */
final class DiagnosticsEngine
{
    public function __construct(
        private readonly ?LogManager $logs = null,
        private readonly ?MetricsManager $metrics = null,
        private readonly ?TraceManager $traces = null
    ) {
    }

    /**
     * Pulls every real record this engine can reach for a single
     * correlation identifier: matching logs, the trace's execution path,
     * and any caller-named metric aggregates. A genuine composition of
     * already-real query methods, not a fabricated join.
     *
     * @param array<int, string> $metricNames
     * @return array{correlation_id: string, logs: array<int, array<string, mixed>>, trace: ?array<string, mixed>, metrics: array<string, array<string, mixed>>}
     */
    public function correlate(string $correlationId, array $metricNames = []): array
    {
        $logs = $this->logs?->recent(['correlation_id' => $correlationId]) ?? [];
        $trace = $this->traces?->executionPath($correlationId);

        $metrics = [];

        foreach ($metricNames as $metricName) {
            $metrics[$metricName] = $this->metrics?->aggregate($metricName) ?? ['outcome' => 'not_configured'];
        }

        return ['correlation_id' => $correlationId, 'logs' => $logs, 'trace' => $trace, 'metrics' => $metrics];
    }

    /**
     * Observed failed spans in execution order, plus a narrow inferred
     * probable cause: the earliest observed failure. Per Rule 3, this is
     * an inference clearly separated from the observed evidence, never
     * asserted as the definitive cause.
     *
     * @return array{trace_id: string, observed_failed_spans: array<int, string>, observed_unresolved_parents: array<int, string>, inferred_probable_cause: ?string, outcome: string}
     */
    public function findFailurePath(string $traceId): array
    {
        if ($this->traces === null) {
            return ['trace_id' => $traceId, 'observed_failed_spans' => [], 'observed_unresolved_parents' => [], 'inferred_probable_cause' => null, 'outcome' => 'not_configured'];
        }

        $path = $this->traces->executionPath($traceId);
        $failedSpans = array_values(array_map(
            static fn(array $span): string => $span['span_id'],
            array_filter($path['spans'], static fn(array $span): bool => ($span['status'] ?? null) === 'failed')
        ));

        return [
            'trace_id' => $traceId,
            'observed_failed_spans' => $failedSpans,
            'observed_unresolved_parents' => $path['unresolved_parents'],
            'inferred_probable_cause' => $failedSpans[0] ?? null,
            'outcome' => 'analyzed',
        ];
    }

    /**
     * The slowest observed spans in a trace, as evidence only -- never
     * labeled a bottleneck verdict, per Rule 1.
     *
     * @return array{trace_id: string, bottleneck_candidates: array<int, array{span_id: string, operation: ?string, duration_ms: int}>, outcome: string}
     */
    public function findBottlenecks(string $traceId, int $limit = 3): array
    {
        if ($this->traces === null) {
            return ['trace_id' => $traceId, 'bottleneck_candidates' => [], 'outcome' => 'not_configured'];
        }

        $evidence = $this->traces->latencyEvidence($traceId);

        return ['trace_id' => $traceId, 'bottleneck_candidates' => array_slice($evidence['spans'], 0, $limit), 'outcome' => 'analyzed'];
    }

    /**
     * A real z-score against a metric's own recorded mean/standard
     * deviation, flagging values that deviate by at least
     * $zScoreThreshold standard deviations. Not a fabricated ML signal.
     *
     * @return array{metric_name: string, anomalies: array<int, array{value: float, z_score: float}>, sample_count: int, outcome: string, error: ?string}
     */
    public function findMetricAnomalies(string $metricName, float $zScoreThreshold = 2.0): array
    {
        if ($this->metrics === null) {
            return ['metric_name' => $metricName, 'anomalies' => [], 'sample_count' => 0, 'outcome' => 'not_configured', 'error' => 'Metrics Manager is not configured.'];
        }

        $values = $this->metrics->values($metricName);

        if (count($values) < 2) {
            return ['metric_name' => $metricName, 'anomalies' => [], 'sample_count' => count($values), 'outcome' => 'insufficient_data', 'error' => 'At least 2 recorded values are required to detect anomalies.'];
        }

        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(static fn(float $value): float => ($value - $mean) ** 2, $values)) / count($values);
        $standardDeviation = sqrt($variance);

        if ($standardDeviation === 0.0) {
            return ['metric_name' => $metricName, 'anomalies' => [], 'sample_count' => count($values), 'outcome' => 'analyzed', 'error' => null];
        }

        $anomalies = [];

        foreach ($values as $value) {
            $zScore = ($value - $mean) / $standardDeviation;

            if (abs($zScore) >= $zScoreThreshold) {
                $anomalies[] = ['value' => $value, 'z_score' => $zScore];
            }
        }

        return ['metric_name' => $metricName, 'anomalies' => $anomalies, 'sample_count' => count($values), 'outcome' => 'analyzed', 'error' => null];
    }

    /**
     * Reports which named dependencies are currently breaching their
     * caller-defined threshold -- evidence only, never a health verdict
     * (that belongs to the not-yet-built Health Reporter).
     *
     * @param array<string, array{metric: string, operator: string, threshold: float, aggregate_type?: string}> $dependencies
     * @return array{degraded: array<int, string>, evidence: array<string, array<string, mixed>>}
     */
    public function degradedDependencyEvidence(array $dependencies): array
    {
        $evidence = [];
        $degraded = [];

        foreach ($dependencies as $name => $definition) {
            $result = $this->metrics?->thresholdEvidence(
                $definition['metric'],
                $definition['operator'],
                $definition['threshold'],
                $definition['aggregate_type'] ?? 'mean'
            ) ?? ['satisfied' => null];

            $evidence[$name] = $result;

            if ($result['satisfied'] === true) {
                $degraded[] = $name;
            }
        }

        return ['degraded' => $degraded, 'evidence' => $evidence];
    }
}
