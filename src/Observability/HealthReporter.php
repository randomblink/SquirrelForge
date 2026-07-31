<?php

declare(strict_types=1);

namespace SquirrelForge\Observability;

use Closure;
use DateTimeImmutable;

/**
 * Owns operational health reports derived from Alert Manager,
 * Diagnostics Engine, and Metrics Manager evidence, per
 * 27_OBSERVABILITY/HEALTH-REPORTER.md.
 *
 * Like Diagnostics Engine, this class has no database of its own --
 * health reports are pure output. It does not take a direct dependency
 * on Log Manager or Trace Manager even though the spec lists both:
 * arbitrary raw log/trace evidence has no natural "is this healthy"
 * shape on its own, and Diagnostics Engine already turns trace evidence
 * into the one health-relevant finding that does (a failure path), so
 * this class consumes that through findFailurePath() rather than
 * bypassing it into Trace Manager directly. Log Manager has no
 * comparable health-relevant read method today.
 *
 * componentHealth() combines up to three real, independent evidence
 * checks -- open alerts (via the new SqliteAlertManager::openFor()
 * retrofit), a caller-named metric threshold breach, a caller-named
 * trace's failure path, and a caller-named metric's anomaly findings --
 * and reduces them to the single strongest state (Unhealthy over
 * Degraded over Healthy). "Distinguish observed health evidence from
 * inferred degradation" is upheld structurally: `observed_evidence`
 * carries the raw facts each check produced, `state` is the one
 * inferred field derived from them, exactly like Diagnostics Engine's
 * own observed/inferred separation.
 *
 * When none of the injected components are configured, or none of the
 * caller-supplied evidence options apply, there is no evidence at all
 * to reason from -- the state is honestly `Unknown`, not a guessed
 * default of `Healthy`, per the spec's own definition of `Unknown`.
 * Rule 3 ("must identify evidence freshness and source references") is
 * satisfied by tracking the most recent alert `updated_at` seen and
 * comparing it against an injectable clock; evidence with no natural
 * timestamp (a metric threshold check, an anomaly scan) is not assigned
 * a fabricated age.
 */
final class HealthReporter
{
    private const SEVERITY_RANK = ['degraded' => 1, 'unhealthy' => 2];

    public function __construct(
        private readonly ?SqliteAlertManager $alerts = null,
        private readonly ?MetricsManager $metrics = null,
        private readonly ?DiagnosticsEngine $diagnostics = null,
        private readonly ?Closure $clock = null
    ) {
    }

    /**
     * @param array{metric_threshold?: array{metric: string, operator: string, threshold: float, aggregate_type?: string}, anomaly_metric?: string, trace_id?: string, staleness_seconds?: int} $options
     * @return array{source: string, state: string, observed_evidence: array<string, mixed>, evidence_sources: array<int, string>, evidence_age_seconds: ?int, outcome: string}
     */
    public function componentHealth(string $source, array $options = []): array
    {
        $observed = [];
        $evidenceSources = [];
        $severities = [];
        $mostRecentTimestamp = null;

        if ($this->alerts !== null) {
            $openAlerts = $this->alerts->openFor($source);
            $evidenceSources[] = 'alert_manager';
            $observed['open_alerts'] = array_map(
                static fn(array $alert): array => ['alert_id' => $alert['alert_id'], 'severity' => $alert['severity'], 'status' => $alert['status']],
                $openAlerts
            );

            foreach ($openAlerts as $alert) {
                $mostRecentTimestamp = $this->laterOf($mostRecentTimestamp, new DateTimeImmutable($alert['updated_at']));
                $severities[] = in_array($alert['severity'], ['critical', 'error'], true) ? 'unhealthy' : 'degraded';
            }
        }

        if ($this->diagnostics !== null && isset($options['trace_id'])) {
            $findings = $this->diagnostics->findFailurePath($options['trace_id']);
            $evidenceSources[] = 'diagnostics_engine';
            $observed['failure_path'] = $findings;

            if ($findings['observed_failed_spans'] !== []) {
                $severities[] = 'unhealthy';
            }
        }

        if ($this->metrics !== null && isset($options['metric_threshold'])) {
            $definition = $options['metric_threshold'];
            $evidence = $this->metrics->thresholdEvidence(
                $definition['metric'],
                $definition['operator'],
                (float) $definition['threshold'],
                $definition['aggregate_type'] ?? 'mean'
            );
            $evidenceSources[] = 'metrics_manager';
            $observed['metric_threshold'] = $evidence;

            if ($evidence['satisfied'] === true) {
                $severities[] = 'degraded';
            }
        }

        if ($this->diagnostics !== null && isset($options['anomaly_metric'])) {
            $anomalies = $this->diagnostics->findMetricAnomalies($options['anomaly_metric']);

            if ($anomalies['outcome'] === 'analyzed') {
                $evidenceSources[] = 'diagnostics_engine';
                $observed['metric_anomalies'] = $anomalies;

                if ($anomalies['anomalies'] !== []) {
                    $severities[] = 'degraded';
                }
            }
        }

        $evidenceSources = array_values(array_unique($evidenceSources));

        if ($evidenceSources === []) {
            return ['source' => $source, 'state' => 'Unknown', 'observed_evidence' => [], 'evidence_sources' => [], 'evidence_age_seconds' => null, 'outcome' => 'insufficient_evidence'];
        }

        $evidenceAgeSeconds = $mostRecentTimestamp !== null ? max(0, $this->now()->getTimestamp() - $mostRecentTimestamp->getTimestamp()) : null;

        if (isset($options['staleness_seconds']) && $evidenceAgeSeconds !== null && $evidenceAgeSeconds > $options['staleness_seconds']) {
            return ['source' => $source, 'state' => 'Unknown', 'observed_evidence' => $observed, 'evidence_sources' => $evidenceSources, 'evidence_age_seconds' => $evidenceAgeSeconds, 'outcome' => 'stale_evidence'];
        }

        $state = $this->strongestState($severities);

        return ['source' => $source, 'state' => $state, 'observed_evidence' => $observed, 'evidence_sources' => $evidenceSources, 'evidence_age_seconds' => $evidenceAgeSeconds, 'outcome' => 'reported'];
    }

    /**
     * @param array<int, string> $severities
     */
    private function strongestState(array $severities): string
    {
        $rank = 0;

        foreach ($severities as $severity) {
            $rank = max($rank, self::SEVERITY_RANK[$severity] ?? 0);
        }

        return match ($rank) {
            2 => 'Unhealthy',
            1 => 'Degraded',
            default => 'Healthy',
        };
    }

    private function laterOf(?DateTimeImmutable $current, DateTimeImmutable $candidate): DateTimeImmutable
    {
        return $current === null || $candidate > $current ? $candidate : $current;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }
}
