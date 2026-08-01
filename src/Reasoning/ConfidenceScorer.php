<?php

declare(strict_types=1);

namespace SquirrelForge\Reasoning;

/**
 * Evaluates how reliable a candidate decision is and produces a
 * confidence level, per 19_REASONING/CONFIDENCE-SCORER.md.
 *
 * "Reads... rather than re-deriving any of them": every input this
 * class scores is already-real evidence from another specialist --
 * Validation's decision, Rule Evaluator's outcome, Risk Assessor's
 * canProceed(), Tradeoff Analyzer's overall_rating -- passed in by the
 * Decision Engine that triggers this class. This class never calls
 * those specialists itself; it only weighs what the caller already
 * collected from them, upholding the Permission Boundary literally.
 *
 * score() is a real, deterministic weighted formula, not a fabricated
 * judgment: each of the spec's nine named Confidence Factors maps to a
 * real [0, 1] sub-score (a specialist's binary/categorical result
 * mapped onto a fixed scale, or a caller-supplied count run through a
 * simple, explainable cap/penalty formula), combined via fixed weights
 * that sum to 1.0. "Weigh assumptions and unresolved limitations as
 * factors that reduce confidence" is upheld literally: each additional
 * assumption or limitation subtracts a fixed amount from that factor's
 * score, never adds.
 *
 * Complexity is the one factor the spec explicitly assigns to "the
 * Confidence Scorer's own judgment" rather than another specialist --
 * this class's own real, simple formula penalizes a caller-supplied
 * task count above a small baseline, the same explainable-threshold
 * idiom used throughout this codebase's risk/priority scoring.
 * Knowledge (Memory Retrieval, unbuilt) is treated as neutral (0.5) --
 * neither confidence-boosting nor penalizing -- when the caller has no
 * prior-success count to supply, rather than guessing either direction.
 *
 * "Provide a recommendation when confidence is low" is a real,
 * threshold-driven check: a Low or Very Low confidence level always
 * carries a non-null recommendation string.
 *
 * Like TradeoffAnalyzer, this class has no database: a Confidence
 * Record is a pure return value for the caller (Decision Engine) to
 * incorporate into its own Decision Record.
 */
final class ConfidenceScorer
{
    private const WEIGHTS = [
        'validation' => 0.20,
        'rule_compliance' => 0.15,
        'risk' => 0.20,
        'tradeoff' => 0.15,
        'evidence' => 0.10,
        'assumptions' => 0.08,
        'limitations' => 0.07,
        'complexity' => 0.03,
        'knowledge' => 0.02,
    ];

    /**
     * @param array{decision_id: string, validation_decision?: ?string, rule_evaluation_outcome?: ?string, risk_can_proceed?: ?bool, tradeoff_overall_rating?: ?float, supporting_evidence?: array<int, string>, assumptions?: array<int, string>, unresolved_limitations?: array<int, string>, task_count?: int, prior_successes?: ?int} $input
     * @return array{decision_id: string, confidence_level: string, confidence_score: float, factors: array<string, float>, supporting_evidence: array<int, string>, assumptions: array<int, string>, unresolved_limitations: array<int, string>, recommendation: ?string, outcome: string}
     */
    public function score(array $input): array
    {
        $factors = [
            'validation' => $this->validationScore($input['validation_decision'] ?? null),
            'rule_compliance' => $this->ruleComplianceScore($input['rule_evaluation_outcome'] ?? null),
            'risk' => ($input['risk_can_proceed'] ?? false) === true ? 1.0 : 0.0,
            'tradeoff' => $this->tradeoffScore($input['tradeoff_overall_rating'] ?? null),
            'evidence' => min(1.0, count($input['supporting_evidence'] ?? []) / 3),
            'assumptions' => max(0.0, 1 - 0.2 * count($input['assumptions'] ?? [])),
            'limitations' => max(0.0, 1 - 0.25 * count($input['unresolved_limitations'] ?? [])),
            'complexity' => $this->complexityScore($input['task_count'] ?? 1),
            'knowledge' => $this->knowledgeScore($input['prior_successes'] ?? null),
        ];

        $weightedScore = 0.0;

        foreach (self::WEIGHTS as $factor => $weight) {
            $weightedScore += $factors[$factor] * $weight;
        }

        $score = $weightedScore * 100;
        $level = $this->levelFor($score);

        return [
            'decision_id' => $input['decision_id'],
            'confidence_level' => $level,
            'confidence_score' => $score,
            'factors' => $factors,
            'supporting_evidence' => $input['supporting_evidence'] ?? [],
            'assumptions' => $input['assumptions'] ?? [],
            'unresolved_limitations' => $input['unresolved_limitations'] ?? [],
            'recommendation' => in_array($level, ['Low', 'Very Low'], true)
                ? 'Review recommended before proceeding; confidence is below the acceptable threshold.'
                : null,
            'outcome' => 'scored',
        ];
    }

    private function validationScore(?string $decision): float
    {
        return match ($decision) {
            'ACCEPTED' => 1.0,
            'ACCEPTED_WITH_LIMITATIONS' => 0.6,
            default => 0.0,
        };
    }

    private function ruleComplianceScore(?string $outcome): float
    {
        return match ($outcome) {
            'Passed' => 1.0,
            'Warning' => 0.6,
            default => 0.0,
        };
    }

    private function tradeoffScore(?float $overallRating): float
    {
        return $overallRating === null ? 0.0 : min(1.0, max(0.0, $overallRating / 5));
    }

    private function complexityScore(int $taskCount): float
    {
        return $taskCount <= 3 ? 1.0 : max(0.0, 1 - 0.1 * ($taskCount - 3));
    }

    private function knowledgeScore(?int $priorSuccesses): float
    {
        return $priorSuccesses === null ? 0.5 : min(1.0, $priorSuccesses / 2);
    }

    private function levelFor(float $score): string
    {
        return match (true) {
            $score >= 85 => 'Very High',
            $score >= 70 => 'High',
            $score >= 50 => 'Moderate',
            $score >= 30 => 'Low',
            default => 'Very Low',
        };
    }
}
