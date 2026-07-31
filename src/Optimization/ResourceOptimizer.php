<?php

declare(strict_types=1);

namespace SquirrelForge\Optimization;

use SquirrelForge\Observability\DiagnosticsEngine;
use SquirrelForge\Observability\MetricsManager;

/**
 * Owns analysis and proposals for efficient use of compute, memory,
 * storage capacity, network, database, cache, and queue resources, per
 * 32_OPTIMIZATION/RESOURCE-OPTIMIZER.md.
 *
 * analyzeUtilizationBand() is this class's genuinely new logic, and the
 * real substance of "right-sizing": a caller-supplied target utilization
 * band ([targetMin, targetMax]) compared against a metric's current
 * MetricsManager aggregate. Below the band is a real, literal
 * over-provisioning signal (capacity is going unused); above the band is
 * a real constraint signal (capacity is insufficient). The band itself
 * is never invented -- there is no general model here for "correct"
 * utilization, only the caller's own stated target, the same honesty
 * PerformanceOptimizer applies by comparing two caller-named metrics
 * rather than guessing a baseline.
 *
 * analyzeAnomalies() and analyzeConstraints() are genuine compositions
 * of Diagnostics Engine's real methods (findMetricAnomalies(),
 * degradedDependencyEvidence()), never a duplicated re-implementation
 * -- the same restraint PerformanceOptimizer already shows toward the
 * same engine, just scoped to caller-named resource metrics instead of
 * latency ones.
 *
 * The 37_STORAGE dependency the spec lists is not taken directly: no
 * storage component in this codebase exposes a real utilization or
 * capacity figure (SqliteObjectStorage/SqliteDocumentStorage track
 * individual records, not aggregate bytes-used), so there is nothing
 * genuine to consume from it yet. Storage-domain utilization is
 * reachable the same way any other resource is -- as a caller-recorded
 * MetricsManager metric -- rather than fabricating a storage-specific
 * code path this codebase can't back with real numbers.
 *
 * Findings carry `type` and `evidence` keys matching
 * OptimizationEngine::createProposal()'s `$opportunity` shape, the same
 * composition contract AgentOptimizer and PerformanceOptimizer already
 * honor. Like its siblings, this class has no database: the Boundary
 * forbids owning "audit infrastructure".
 */
final class ResourceOptimizer
{
    public function __construct(
        private readonly ?MetricsManager $metrics = null,
        private readonly ?DiagnosticsEngine $diagnostics = null
    ) {
    }

    /**
     * @return array{finding: ?array<string, mixed>, outcome: string, error: ?string}
     */
    public function analyzeUtilizationBand(string $metricName, float $targetMin, float $targetMax, string $aggregateType = 'mean'): array
    {
        if ($this->metrics === null) {
            return ['finding' => null, 'outcome' => 'not_configured', 'error' => 'Metrics Manager is not configured.'];
        }

        $aggregate = $this->metrics->aggregate($metricName, $aggregateType);

        if ($aggregate['value'] === null) {
            return ['finding' => null, 'outcome' => 'no_data', 'error' => sprintf('No recorded data for metric "%s".', $metricName)];
        }

        $value = $aggregate['value'];

        if ($value >= $targetMin && $value <= $targetMax) {
            return ['finding' => null, 'outcome' => 'within_target', 'error' => null];
        }

        $type = $value < $targetMin ? 'over_provisioned' : 'constrained';
        $finding = [
            'type' => $type,
            'metric_name' => $metricName,
            'evidence' => ['aggregate' => $aggregate, 'target_min' => $targetMin, 'target_max' => $targetMax],
            'measurable_target' => $type === 'over_provisioned'
                ? sprintf('Right-size "%s" down toward its target band of %.2f-%.2f (currently %.2f).', $metricName, $targetMin, $targetMax, $value)
                : sprintf('Increase capacity for "%s" to bring it within its target band of %.2f-%.2f (currently %.2f).', $metricName, $targetMin, $targetMax, $value),
            'improvement_proposal' => $type === 'over_provisioned'
                ? sprintf('"%s" is running at %.2f, below its target band minimum of %.2f -- allocated capacity appears underused.', $metricName, $value, $targetMin)
                : sprintf('"%s" is running at %.2f, above its target band maximum of %.2f -- allocated capacity appears insufficient.', $metricName, $value, $targetMax),
        ];

        return ['finding' => $finding, 'outcome' => $type, 'error' => null];
    }

    /**
     * @return array{findings: array<int, array<string, mixed>>, outcome: string, error: ?string}
     */
    public function analyzeAnomalies(string $metricName, float $zScoreThreshold = 2.0): array
    {
        if ($this->diagnostics === null) {
            return ['findings' => [], 'outcome' => 'not_configured', 'error' => 'Diagnostics Engine is not configured.'];
        }

        $result = $this->diagnostics->findMetricAnomalies($metricName, $zScoreThreshold);

        if ($result['outcome'] !== 'analyzed') {
            return ['findings' => [], 'outcome' => $result['outcome'], 'error' => $result['error']];
        }

        $findings = [];

        foreach ($result['anomalies'] as $anomaly) {
            $findings[] = [
                'type' => 'resource_anomaly',
                'metric_name' => $metricName,
                'evidence' => $anomaly,
                'measurable_target' => sprintf('Bring "%s" within %.1f standard deviations of its own mean.', $metricName, $zScoreThreshold),
                'improvement_proposal' => sprintf('Investigate "%s": a recorded value of %.2f deviated %.2f standard deviations from the metric\'s own mean.', $metricName, $anomaly['value'], $anomaly['z_score']),
            ];
        }

        return ['findings' => $findings, 'outcome' => 'analyzed', 'error' => null];
    }

    /**
     * @param array<string, array{metric: string, operator: string, threshold: float, aggregate_type?: string}> $resources
     * @return array{findings: array<int, array<string, mixed>>, outcome: string, error: ?string}
     */
    public function analyzeConstraints(array $resources): array
    {
        if ($this->diagnostics === null) {
            return ['findings' => [], 'outcome' => 'not_configured', 'error' => 'Diagnostics Engine is not configured.'];
        }

        $result = $this->diagnostics->degradedDependencyEvidence($resources);
        $findings = [];

        foreach ($result['degraded'] as $name) {
            $findings[] = [
                'type' => 'constraint',
                'resource' => $name,
                'evidence' => $result['evidence'][$name],
                'measurable_target' => sprintf('Bring resource "%s" back under its defined threshold.', $name),
                'improvement_proposal' => sprintf('Resource "%s" is currently breaching its defined threshold.', $name),
            ];
        }

        return ['findings' => $findings, 'outcome' => 'analyzed', 'error' => null];
    }
}
