<?php

declare(strict_types=1);

namespace SquirrelForge\Optimization;

use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Learning\PatternDetector;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

/**
 * Synthesizes qualified learning patterns and risk references into
 * cross-domain optimization proposals, per
 * 32_OPTIMIZATION/OPTIMIZATION-ENGINE.md.
 *
 * "Identify cross-domain optimization opportunities from qualified
 * inputs" is a real synthesis of PatternDetector's own real findings,
 * not fabricated opportunity discovery: identifyOpportunities() calls
 * the injected PatternDetector's real findRecurringPatterns() and
 * findAnomalies() and turns each finding into an opportunity candidate,
 * carrying its real confidence/count or z-score forward rather than
 * inventing a new number.
 *
 * "Consume general risk-assessment references and attach them to
 * proposals" is real: createProposal() calls the injected
 * SqliteRiskAssessor::canProceed() for the proposal's own id, the same
 * check Automation Governance and Learning Governance both perform, and
 * a proposal currently blocked by an unmitigated critical risk is
 * surfaced with a lower priority rather than being silently hidden --
 * "an Optimization Engine proposal is analytical output, not permission
 * to change production behavior", so a risky proposal is still valid
 * output, just deprioritized.
 *
 * Priority scoring is a deliberately simple, explainable formula (the
 * real confidence/count or z-score, scaled and capped, halved when risk
 * blocks progress), the same honesty PatternDetector and Evaluation
 * Engine commit to for their own heuristics rather than claiming
 * analytical rigor this class doesn't implement. Expected benefit,
 * tradeoffs, effort, and success criteria are consumed as caller-
 * supplied values, not computed -- this class has no model for
 * estimating implementation effort or business tradeoffs.
 *
 * Like AutomationValidator, this class has no database: the spec
 * forbids owning "historical storage or audit-trail infrastructure",
 * so proposals are pure return values for the caller (eventually an
 * Optimization Manager) to persist and forward.
 */
final class OptimizationEngine
{
    public function __construct(
        private readonly ?PatternDetector $patternDetector = null,
        private readonly ?SqliteRiskAssessor $riskAssessor = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array{source?: string, event_category?: string, status?: string} $filters
     * @param array{min_occurrences?: int, z_score_threshold?: float} $options
     * @return array{opportunities: array<int, array<string, mixed>>, outcome: string, error: ?string}
     */
    public function identifyOpportunities(array $filters = [], array $options = []): array
    {
        if ($this->patternDetector === null) {
            return ['opportunities' => [], 'outcome' => 'not_configured', 'error' => 'Pattern Detector is not configured.'];
        }

        $opportunities = [];

        $recurring = $this->patternDetector->findRecurringPatterns($filters, $options['min_occurrences'] ?? 3);

        foreach ($recurring['findings'] as $finding) {
            $opportunities[] = [
                'type' => 'recurring_issue',
                'source' => $finding['source'],
                'event_category' => $finding['event_category'],
                'evidence' => ['count' => $finding['count'], 'confidence' => $finding['confidence']],
            ];
        }

        $anomalies = $this->patternDetector->findAnomalies($filters, $options['z_score_threshold'] ?? 2.0);

        foreach ($anomalies['anomalies'] as $anomaly) {
            $opportunities[] = [
                'type' => 'anomaly',
                'experience_id' => $anomaly['experience_id'],
                'evidence' => ['confidence' => $anomaly['confidence'], 'z_score' => $anomaly['z_score']],
            ];
        }

        return ['opportunities' => $opportunities, 'outcome' => 'analyzed', 'error' => null];
    }

    /**
     * @param array<string, mixed> $opportunity typically one of identifyOpportunities()'s results
     * @param array{expected_benefit?: ?string, tradeoffs?: array<int, string>, effort?: ?string, success_criteria?: array<int, string>} $options
     * @return array{proposal_id: string, opportunity: array<string, mixed>, expected_benefit: ?string, tradeoffs: array<int, string>, effort: ?string, success_criteria: array<int, string>, risk_can_proceed: ?bool, priority_score: float, outcome: string}
     */
    public function createProposal(string $proposalId, array $opportunity, array $options = []): array
    {
        $riskCanProceed = null;

        if ($this->riskAssessor !== null) {
            $riskCanProceed = $this->riskAssessor->canProceed($proposalId)['can_proceed'];
        }

        $priorityScore = $this->scorePriority($opportunity, $riskCanProceed);

        $proposal = [
            'proposal_id' => $proposalId,
            'opportunity' => $opportunity,
            'expected_benefit' => $options['expected_benefit'] ?? null,
            'tradeoffs' => $options['tradeoffs'] ?? [],
            'effort' => $options['effort'] ?? null,
            'success_criteria' => $options['success_criteria'] ?? [],
            'risk_can_proceed' => $riskCanProceed,
            'priority_score' => $priorityScore,
            'outcome' => 'proposed',
        ];

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'optimization.proposed',
            new DateTimeImmutable(),
            self::class,
            ['proposal_id' => $proposalId, 'priority_score' => $priorityScore]
        ));

        return $proposal;
    }

    /**
     * A deliberately simple, explainable formula: the opportunity's own
     * real evidence, scaled and capped to [0, 1], halved when an
     * unmitigated critical risk currently blocks the proposal -- not
     * eliminated, since a risky proposal is still valid analytical
     * output, just deprioritized until the risk is resolved.
     *
     * @param array<string, mixed> $opportunity
     */
    private function scorePriority(array $opportunity, ?bool $riskCanProceed): float
    {
        $evidence = $opportunity['evidence'] ?? [];

        $base = match ($opportunity['type'] ?? null) {
            'recurring_issue' => (float) ($evidence['confidence'] ?? 0.0) * min(($evidence['count'] ?? 0) / 10, 1.0),
            'anomaly' => min(abs((float) ($evidence['z_score'] ?? 0.0)) / 5, 1.0),
            default => 0.0,
        };

        return $riskCanProceed === false ? $base * 0.5 : $base;
    }
}
