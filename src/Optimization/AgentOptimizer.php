<?php

declare(strict_types=1);

namespace SquirrelForge\Optimization;

use SquirrelForge\Learning\PatternDetector;

/**
 * Analyzes an agent's execution history for measurable improvement
 * opportunities across planning, reasoning, memory, tool use,
 * collaboration, and execution, per
 * 32_OPTIMIZATION/AGENT-OPTIMIZER.md.
 *
 * This deliberately does not duplicate OptimizationEngine: it scopes
 * PatternDetector's real findRecurringPatterns()/findAnomalies() to a
 * single agent (`source`) and adds the two things that are genuinely
 * agent-domain-specific -- dimension classification and measurable
 * targets -- rather than proposal creation or risk consumption, which
 * stay with OptimizationEngine/OptimizationGovernance downstream. A
 * caller can feed one of this class's findings into
 * OptimizationEngine::createProposal() directly; the two compose rather
 * than overlap.
 *
 * Dimension classification is a real, deterministic keyword match
 * against the event_category string (e.g. a category containing "plan"
 * classifies as `planning`), not a fabricated semantic understanding of
 * what the agent was doing -- it's the same kind of honest heuristic
 * EventListener uses for its own dot-namespace classification, just
 * substring-based instead. "Measurable targets" are real, simple
 * formulas derived directly from the finding's own numbers (halve a
 * recurring count, bring an anomaly's z-score within threshold, reverse
 * a declining trend) -- not a fabricated performance model.
 *
 * Like OptimizationEngine, this class has no database: the spec forbids
 * owning "audit infrastructure", so findings are pure return values.
 */
final class AgentOptimizer
{
    private const DIMENSION_KEYWORDS = [
        'planning' => ['plan'],
        'memory' => ['memory', 'recall', 'retrieval'],
        'tool_use' => ['tool'],
        'collaboration' => ['collab', 'delegat', 'handoff'],
        'reasoning' => ['reason', 'decision', 'confidence'],
    ];

    public function __construct(private readonly ?PatternDetector $patternDetector = null)
    {
    }

    /**
     * @param array{event_category?: string, status?: string} $filters
     * @param array{min_occurrences?: int, z_score_threshold?: float} $options
     * @return array{findings: array<int, array<string, mixed>>, outcome: string, error: ?string}
     */
    public function analyzeAgent(string $agentId, array $filters = [], array $options = []): array
    {
        if ($this->patternDetector === null) {
            return ['findings' => [], 'outcome' => 'not_configured', 'error' => 'Pattern Detector is not configured.'];
        }

        $scopedFilters = [...$filters, 'source' => $agentId];
        $findings = [];

        $recurring = $this->patternDetector->findRecurringPatterns($scopedFilters, $options['min_occurrences'] ?? 3);

        foreach ($recurring['findings'] as $finding) {
            $findings[] = $this->buildFinding(
                'recurring_issue',
                $agentId,
                $finding['event_category'],
                $this->classify($finding['event_category']),
                ['count' => $finding['count'], 'confidence' => $finding['confidence']],
                sprintf('Reduce recurrence of "%s" below %d occurrences within the same observation window.', $finding['event_category'], (int) ceil($finding['count'] / 2)),
                sprintf('Review %s logic for agent "%s": "%s" recurred %d times.', $this->classify($finding['event_category']), $agentId, $finding['event_category'], $finding['count'])
            );
        }

        $anomalies = $this->patternDetector->findAnomalies($scopedFilters, $options['z_score_threshold'] ?? 2.0);

        foreach ($anomalies['anomalies'] as $anomaly) {
            $category = $filters['event_category'] ?? 'unclassified';
            $findings[] = $this->buildFinding(
                'anomaly',
                $agentId,
                $category,
                $this->classify($category),
                ['experience_id' => $anomaly['experience_id'], 'confidence' => $anomaly['confidence'], 'z_score' => $anomaly['z_score']],
                sprintf('Bring confidence within %.1f standard deviations of the group mean.', $options['z_score_threshold'] ?? 2.0),
                sprintf('Investigate experience "%s" for agent "%s": confidence deviated %.2f standard deviations from the norm.', $anomaly['experience_id'], $agentId, $anomaly['z_score'])
            );
        }

        if (isset($filters['event_category'])) {
            $trend = $this->patternDetector->findTrend($agentId, $filters['event_category']);

            if ($trend['outcome'] === 'trend_found' && $trend['direction'] === 'decreasing') {
                $findings[] = $this->buildFinding(
                    'declining_trend',
                    $agentId,
                    $filters['event_category'],
                    $this->classify($filters['event_category']),
                    ['direction' => $trend['direction'], 'magnitude' => $trend['magnitude'], 'sample_size' => $trend['sample_size']],
                    'Reverse the declining confidence trend for this category.',
                    sprintf('Confidence for agent "%s" on "%s" has been declining (magnitude %.3f over %d samples).', $agentId, $filters['event_category'], $trend['magnitude'], $trend['sample_size'])
                );
            }
        }

        return ['findings' => $findings, 'outcome' => 'analyzed', 'error' => null];
    }

    /**
     * @param array<string, mixed> $evidence
     * @return array{type: string, agent_id: string, event_category: string, dimension: string, evidence: array<string, mixed>, measurable_target: string, behavior_change_proposal: string}
     */
    private function buildFinding(string $type, string $agentId, string $eventCategory, string $dimension, array $evidence, string $target, string $proposal): array
    {
        return [
            'type' => $type,
            'agent_id' => $agentId,
            'event_category' => $eventCategory,
            'dimension' => $dimension,
            'evidence' => $evidence,
            'measurable_target' => $target,
            'behavior_change_proposal' => $proposal,
        ];
    }

    private function classify(string $eventCategory): string
    {
        $lowered = strtolower($eventCategory);

        foreach (self::DIMENSION_KEYWORDS as $dimension => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lowered, $keyword)) {
                    return $dimension;
                }
            }
        }

        return 'execution';
    }
}
