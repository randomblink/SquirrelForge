<?php

declare(strict_types=1);

namespace SquirrelForge\Learning;

/**
 * Identifies recurring patterns, trends, and anomalies across experience
 * records, per 30_LEARNING/PATTERN-DETECTOR.md.
 *
 * Every finding here is real, deterministic statistics computed directly
 * from SqliteExperienceStore data -- grouping and counting for
 * recurrence, a mean-confidence comparison between the earlier and later
 * halves of a time-ordered set for trend, and a real z-score against the
 * group's own mean/standard deviation for anomalies. None of this is
 * fabricated machine learning; it's arithmetic over real stored numbers,
 * scoped to match what's honestly computable without a real model.
 *
 * "Qualified... evaluation... references" from Evaluation Engine are not
 * consumed -- that component has no code yet to define "qualified" --
 * so detection operates over whatever experience records match the
 * caller's own filters, unfiltered by evaluation status. Correlation
 * detection (co-occurring event categories per workflow) is out of
 * scope for this pass: it would need querying experience records by
 * workflow_reference, which SqliteExperienceStore::list() doesn't
 * project today, and adding that wasn't necessary to make the other
 * three detectors real.
 *
 * Like RuleEngine, this class has no database of its own -- the spec
 * forbids owning "general historical storage or audit infrastructure"
 * -- findings are pure output, never persisted here. Pattern strength
 * and confidence are deliberately simple, explainable heuristics
 * (bigger sample size raises confidence, asymptotically), not a
 * fabricated statistical rigor this class doesn't actually implement.
 */
final class PatternDetector
{
    public function __construct(private readonly ?SqliteExperienceStore $experiences = null)
    {
    }

    /**
     * Groups matching experience records by (source, event_category) and
     * reports every group meeting the minimum occurrence count.
     *
     * @param array{source?: string, event_category?: string, status?: string} $filters
     * @return array{findings: array<int, array{source: string, event_category: string, count: int, confidence: float}>, outcome: string, error: ?string}
     */
    public function findRecurringPatterns(array $filters = [], int $minOccurrences = 3): array
    {
        if ($this->experiences === null) {
            return ['findings' => [], 'outcome' => 'not_configured', 'error' => 'Experience Store is not configured.'];
        }

        $groups = [];

        foreach ($this->experiences->list($filters) as $record) {
            $key = $record['source'] . '|' . $record['event_category'];
            $groups[$key] ??= ['source' => $record['source'], 'event_category' => $record['event_category'], 'count' => 0];
            $groups[$key]['count']++;
        }

        $findings = [];

        foreach ($groups as $group) {
            if ($group['count'] >= $minOccurrences) {
                $findings[] = [
                    'source' => $group['source'],
                    'event_category' => $group['event_category'],
                    'count' => $group['count'],
                    'confidence' => $this->recurrenceConfidence($group['count'], $minOccurrences),
                ];
            }
        }

        return ['findings' => $findings, 'outcome' => 'analyzed', 'error' => null];
    }

    /**
     * A simple, explainable heuristic: confidence approaches 1.0 as the
     * occurrence count grows past minOccurrences, never claiming more
     * certainty than a doubled threshold's worth of evidence supports.
     */
    private function recurrenceConfidence(int $count, int $minOccurrences): float
    {
        return min(1.0, $count / ($minOccurrences * 2));
    }

    /**
     * Compares mean confidence between the earlier and later halves of a
     * time-ordered set of matching experience records.
     *
     * @return array{direction: ?string, magnitude: ?float, sample_size: int, outcome: string, error: ?string}
     */
    public function findTrend(string $source, string $eventCategory): array
    {
        if ($this->experiences === null) {
            return ['direction' => null, 'magnitude' => null, 'sample_size' => 0, 'outcome' => 'not_configured', 'error' => 'Experience Store is not configured.'];
        }

        $records = $this->experiences->list(['source' => $source, 'event_category' => $eventCategory]);
        $confidences = $this->orderedConfidences($records);

        if (count($confidences) < 4) {
            return ['direction' => null, 'magnitude' => null, 'sample_size' => count($confidences), 'outcome' => 'insufficient_data', 'error' => 'At least 4 confidence-bearing records are required to detect a trend.'];
        }

        $midpoint = intdiv(count($confidences), 2);
        $earlierMean = array_sum(array_slice($confidences, 0, $midpoint)) / $midpoint;
        $laterMean = array_sum(array_slice($confidences, $midpoint)) / (count($confidences) - $midpoint);
        $magnitude = $laterMean - $earlierMean;

        $direction = match (true) {
            abs($magnitude) < 0.01 => 'stable',
            $magnitude > 0 => 'increasing',
            default => 'decreasing',
        };

        return ['direction' => $direction, 'magnitude' => $magnitude, 'sample_size' => count($confidences), 'outcome' => 'trend_found', 'error' => null];
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, float>
     */
    private function orderedConfidences(array $records): array
    {
        usort($records, static fn(array $a, array $b): int => $a['created_at'] <=> $b['created_at']);

        return array_values(array_map(
            static fn(array $record): float => (float) $record['confidence'],
            array_filter($records, static fn(array $record): bool => $record['confidence'] !== null)
        ));
    }

    /**
     * Flags matching experience records whose confidence deviates from
     * the group's own mean by more than $zScoreThreshold standard
     * deviations -- a real z-score, not a fabricated anomaly signal.
     *
     * @param array{source?: string, event_category?: string, status?: string} $filters
     * @return array{anomalies: array<int, array{experience_id: string, confidence: float, z_score: float}>, outcome: string, error: ?string}
     */
    public function findAnomalies(array $filters = [], float $zScoreThreshold = 2.0): array
    {
        if ($this->experiences === null) {
            return ['anomalies' => [], 'outcome' => 'not_configured', 'error' => 'Experience Store is not configured.'];
        }

        $records = array_values(array_filter($this->experiences->list($filters), static fn(array $r): bool => $r['confidence'] !== null));

        if (count($records) < 2) {
            return ['anomalies' => [], 'outcome' => 'insufficient_data', 'error' => 'At least 2 confidence-bearing records are required to detect anomalies.'];
        }

        $confidences = array_map(static fn(array $r): float => (float) $r['confidence'], $records);
        $mean = array_sum($confidences) / count($confidences);
        $variance = array_sum(array_map(static fn(float $c): float => ($c - $mean) ** 2, $confidences)) / count($confidences);
        $standardDeviation = sqrt($variance);

        if ($standardDeviation === 0.0) {
            return ['anomalies' => [], 'outcome' => 'analyzed', 'error' => null];
        }

        $anomalies = [];

        foreach ($records as $index => $record) {
            $zScore = ($confidences[$index] - $mean) / $standardDeviation;

            if (abs($zScore) >= $zScoreThreshold) {
                $anomalies[] = ['experience_id' => $record['experience_id'], 'confidence' => $confidences[$index], 'z_score' => $zScore];
            }
        }

        return ['anomalies' => $anomalies, 'outcome' => 'analyzed', 'error' => null];
    }
}
