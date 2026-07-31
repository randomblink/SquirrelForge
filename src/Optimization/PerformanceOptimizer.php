<?php

declare(strict_types=1);

namespace SquirrelForge\Optimization;

use SquirrelForge\Observability\DiagnosticsEngine;
use SquirrelForge\Observability\MetricsManager;

/**
 * Owns performance-domain analysis and proposals for measurable
 * latency, throughput, responsiveness, and execution-efficiency
 * improvement, per 32_OPTIMIZATION/PERFORMANCE-OPTIMIZER.md.
 *
 * "Identify performance bottlenecks and affected-component references"
 * and the anomaly half of "compare baselines and candidate improvement
 * hypotheses" are genuine compositions of Diagnostics Engine's own real
 * methods (findBottlenecks(), findMetricAnomalies()), never a
 * duplicated re-implementation -- the same restraint AgentOptimizer
 * shows toward PatternDetector rather than reinventing its statistics.
 *
 * compareBaseline() is this class's one genuinely new piece of logic: a
 * real percent-change comparison between two named metrics' current
 * MetricsManager aggregates (e.g. a `latency_ms_baseline` metric
 * against a `latency_ms_candidate` metric an experiment already
 * recorded) -- a plain, explainable formula, not a fabricated
 * statistical test, since no A/B testing authority exists here to
 * consume (per the Boundary, "does not... perform tests as Testing
 * authority").
 *
 * Every finding carries `type` and `evidence` keys matching the shape
 * OptimizationEngine::createProposal()'s `$opportunity` parameter
 * already expects, so a caller can feed one of these findings straight
 * into it, exactly like AgentOptimizer's findings do. `measurable_target`
 * and `improvement_proposal` are real, simple formulas derived directly
 * from each finding's own numbers (halve a bottleneck's duration, bring
 * an anomaly within its z-score threshold, close a baseline/candidate
 * gap) -- not a fabricated performance model.
 *
 * Like OptimizationEngine/AgentOptimizer, this class has no database:
 * the Boundary forbids owning "audit infrastructure", so findings are
 * pure return values.
 */
final class PerformanceOptimizer
{
    public function __construct(
        private readonly ?DiagnosticsEngine $diagnostics = null,
        private readonly ?MetricsManager $metrics = null
    ) {
    }

    /**
     * @return array{findings: array<int, array<string, mixed>>, outcome: string, error: ?string}
     */
    public function analyzeBottlenecks(string $traceId, int $limit = 3): array
    {
        if ($this->diagnostics === null) {
            return ['findings' => [], 'outcome' => 'not_configured', 'error' => 'Diagnostics Engine is not configured.'];
        }

        $evidence = $this->diagnostics->findBottlenecks($traceId, $limit);
        $findings = [];

        foreach ($evidence['bottleneck_candidates'] as $candidate) {
            $findings[] = [
                'type' => 'bottleneck',
                'trace_id' => $traceId,
                'evidence' => $candidate,
                'measurable_target' => sprintf('Reduce "%s" latency below %d ms.', $candidate['operation'] ?? $candidate['span_id'], (int) ceil($candidate['duration_ms'] / 2)),
                'improvement_proposal' => sprintf('Investigate span "%s" (operation "%s"): observed latency of %d ms in trace "%s".', $candidate['span_id'], $candidate['operation'] ?? 'unknown', $candidate['duration_ms'], $traceId),
            ];
        }

        return ['findings' => $findings, 'outcome' => 'analyzed', 'error' => null];
    }

    /**
     * @return array{findings: array<int, array<string, mixed>>, outcome: string, error: ?string}
     */
    public function analyzeMetricAnomalies(string $metricName, float $zScoreThreshold = 2.0): array
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
                'type' => 'anomaly',
                'metric_name' => $metricName,
                'evidence' => $anomaly,
                'measurable_target' => sprintf('Bring "%s" within %.1f standard deviations of its own mean.', $metricName, $zScoreThreshold),
                'improvement_proposal' => sprintf('Investigate "%s": a recorded value of %.2f deviated %.2f standard deviations from the metric\'s own mean.', $metricName, $anomaly['value'], $anomaly['z_score']),
            ];
        }

        return ['findings' => $findings, 'outcome' => 'analyzed', 'error' => null];
    }

    /**
     * A real, plain percent-change comparison between two named
     * metrics' current aggregates -- not a fabricated statistical test.
     *
     * @return array{finding: ?array<string, mixed>, outcome: string, error: ?string}
     */
    public function compareBaseline(string $baselineMetric, string $candidateMetric, string $aggregateType = 'mean'): array
    {
        if ($this->metrics === null) {
            return ['finding' => null, 'outcome' => 'not_configured', 'error' => 'Metrics Manager is not configured.'];
        }

        $baseline = $this->metrics->aggregate($baselineMetric, $aggregateType);
        $candidate = $this->metrics->aggregate($candidateMetric, $aggregateType);

        if ($baseline['value'] === null || $candidate['value'] === null) {
            return ['finding' => null, 'outcome' => 'no_data', 'error' => 'Both the baseline and candidate metric require recorded data.'];
        }

        if ($baseline['value'] == 0.0) {
            return ['finding' => null, 'outcome' => 'no_baseline', 'error' => 'Baseline metric aggregate is zero; percent change is undefined.'];
        }

        $percentChange = (($candidate['value'] - $baseline['value']) / abs($baseline['value'])) * 100;

        $finding = [
            'type' => 'baseline_comparison',
            'evidence' => ['baseline' => $baseline, 'candidate' => $candidate, 'percent_change' => $percentChange],
            'measurable_target' => sprintf('Close the %.1f%% gap between "%s" and "%s".', abs($percentChange), $candidateMetric, $baselineMetric),
            'improvement_proposal' => sprintf('"%s" is %.1f%% %s than "%s" (%.2f vs %.2f).', $candidateMetric, abs($percentChange), $percentChange >= 0 ? 'higher' : 'lower', $baselineMetric, $candidate['value'], $baseline['value']),
        ];

        return ['finding' => $finding, 'outcome' => 'compared', 'error' => null];
    }
}
