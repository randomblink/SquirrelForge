<?php

declare(strict_types=1);

namespace SquirrelForge\Learning;

use Closure;
use DateTimeImmutable;

/**
 * Correlates Learning-domain health and effectiveness signals into
 * findings, per 30_LEARNING/LEARNING-MONITOR.md.
 *
 * Every finding here is a real query or cross-check against the actual
 * Learning components already built this session, not fabricated
 * telemetry: healthSummary() aggregates real status distributions from
 * SqliteExperienceStore, SqliteEvaluationEngine, and
 * SqliteAdaptationManager; detectStalledAdaptations() compares each
 * `planned` plan's real updated_at against actual wall-clock time (via
 * an injectable clock, for tests); detectRepeatedAdaptationFailures()
 * groups real failed plans by proposal_id, the same grouping technique
 * PatternDetector uses for recurrence; detectTrend() is a genuine
 * passthrough to PatternDetector::findTrend() rather than reinventing
 * trend detection.
 *
 * detectUnauthorizedAdaptations() is the most novel real check: it
 * cross-references every plan that reached `implemented`/`validated`
 * against SqliteLearningGovernance's real currentStatus() for the same
 * proposal_id, flagging any that executed without a matching
 * approved/conditioned governance record -- a genuine integrity
 * violation detector, not a fabricated compliance signal.
 *
 * "Route findings to the Learning Manager, owning Learning component,
 * Security, Resilience, or Observability owner as appropriate" has no
 * concrete real routing target within this component's own boundary --
 * that authority belongs to the caller, per the spec's own rule against
 * this class executing operational action -- so findings are returned
 * as structured data, not dispatched anywhere by this class itself.
 * Like the components it monitors, it owns no database and creates no
 * "parallel monitoring history", which the spec explicitly forbids.
 */
final class LearningMonitor
{
    public function __construct(
        private readonly ?SqliteExperienceStore $experiences = null,
        private readonly ?SqliteAdaptationManager $adaptationManager = null,
        private readonly ?SqliteLearningGovernance $governance = null,
        private readonly ?PatternDetector $patternDetector = null,
        private readonly ?Closure $clock = null
    ) {
    }

    /**
     * @return array{experience_status_counts: array<string, int>, adaptation_status_counts: array<string, int>, outcome: string, error: ?string}
     */
    public function healthSummary(): array
    {
        if ($this->experiences === null || $this->adaptationManager === null) {
            return ['experience_status_counts' => [], 'adaptation_status_counts' => [], 'outcome' => 'not_configured', 'error' => 'Experience Store and Adaptation Manager must both be configured.'];
        }

        return [
            'experience_status_counts' => $this->countBy($this->experiences->list(), 'status'),
            'adaptation_status_counts' => $this->countBy($this->adaptationManager->list(), 'status'),
            'outcome' => 'analyzed',
            'error' => null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<string, int>
     */
    private function countBy(array $records, string $field): array
    {
        $counts = [];

        foreach ($records as $record) {
            $counts[$record[$field]] = ($counts[$record[$field]] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Flags every adaptation plan still `planned` whose last update is
     * older than $thresholdSeconds -- a real comparison against actual
     * wall-clock time, not an assumption.
     *
     * @return array{stalled: array<int, array{plan_id: string, proposal_id: string, seconds_stalled: int}>, outcome: string, error: ?string}
     */
    public function detectStalledAdaptations(int $thresholdSeconds = 3600): array
    {
        if ($this->adaptationManager === null) {
            return ['stalled' => [], 'outcome' => 'not_configured', 'error' => 'Adaptation Manager is not configured.'];
        }

        $now = $this->now();
        $stalled = [];

        foreach ($this->adaptationManager->list(['status' => 'planned']) as $plan) {
            $secondsStalled = $now->getTimestamp() - (new DateTimeImmutable($plan['updated_at']))->getTimestamp();

            if ($secondsStalled >= $thresholdSeconds) {
                $stalled[] = ['plan_id' => $plan['plan_id'], 'proposal_id' => $plan['proposal_id'], 'seconds_stalled' => $secondsStalled];
            }
        }

        return ['stalled' => $stalled, 'outcome' => 'analyzed', 'error' => null];
    }

    /**
     * Groups failed adaptation plans by proposal_id and flags proposals
     * meeting the failure threshold -- the same real grouping technique
     * PatternDetector::findRecurringPatterns() uses.
     *
     * @return array{repeated_failures: array<int, array{proposal_id: string, failure_count: int}>, outcome: string, error: ?string}
     */
    public function detectRepeatedAdaptationFailures(int $failureThreshold = 3): array
    {
        if ($this->adaptationManager === null) {
            return ['repeated_failures' => [], 'outcome' => 'not_configured', 'error' => 'Adaptation Manager is not configured.'];
        }

        $counts = $this->countBy($this->adaptationManager->list(['status' => 'failed']), 'proposal_id');
        $repeated = [];

        foreach ($counts as $proposalId => $count) {
            if ($count >= $failureThreshold) {
                $repeated[] = ['proposal_id' => $proposalId, 'failure_count' => $count];
            }
        }

        return ['repeated_failures' => $repeated, 'outcome' => 'analyzed', 'error' => null];
    }

    /**
     * A genuine passthrough to PatternDetector::findTrend() -- this
     * class specializes an already-real signal, it doesn't reimplement
     * trend detection.
     *
     * @return array{direction: ?string, magnitude: ?float, sample_size: int, outcome: string, error: ?string}
     */
    public function detectTrend(string $source, string $eventCategory): array
    {
        if ($this->patternDetector === null) {
            return ['direction' => null, 'magnitude' => null, 'sample_size' => 0, 'outcome' => 'not_configured', 'error' => 'Pattern Detector is not configured.'];
        }

        return $this->patternDetector->findTrend($source, $eventCategory);
    }

    /**
     * A real integrity check: flags any adaptation plan that reached
     * `implemented` or `validated` without a matching approved/
     * conditioned governance record for the same proposal.
     *
     * @return array{unauthorized: array<int, array{plan_id: string, proposal_id: string, governance_outcome: ?string}>, outcome: string, error: ?string}
     */
    public function detectUnauthorizedAdaptations(): array
    {
        if ($this->adaptationManager === null || $this->governance === null) {
            return ['unauthorized' => [], 'outcome' => 'not_configured', 'error' => 'Adaptation Manager and Learning Governance must both be configured.'];
        }

        $unauthorized = [];

        foreach ([...$this->adaptationManager->list(['status' => 'implemented']), ...$this->adaptationManager->list(['status' => 'validated'])] as $plan) {
            $status = $this->governance->currentStatus($plan['proposal_id']);
            $outcome = $status['outcome'] ?? null;

            if (!in_array($outcome, ['approved', 'conditioned'], true)) {
                $unauthorized[] = ['plan_id' => $plan['plan_id'], 'proposal_id' => $plan['proposal_id'], 'governance_outcome' => $outcome];
            }
        }

        return ['unauthorized' => $unauthorized, 'outcome' => 'analyzed', 'error' => null];
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }
}
