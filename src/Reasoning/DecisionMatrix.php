<?php

declare(strict_types=1);

namespace SquirrelForge\Reasoning;

/**
 * Scores feasible options across 1-5 criteria and disqualifies any
 * option with a mandatory-rule failure regardless of total, per
 * 19_REASONING/DECISION-MATRIX.md.
 *
 * Does not take Decision Engine or Tradeoff Analyzer as dependencies
 * despite the spec's own header listing them: both are documented
 * *consumers* of Decision Matrix's scores elsewhere in this category
 * (Tradeoff Analyzer's own spec: "reference Decision Matrix's weighted
 * scores as supporting evidence"), and Decision Engine has no code yet
 * regardless. The two dependencies this class actually needs to compute
 * anything real are Rule Evaluator (just built) and the already-real
 * Risk Assessor -- the same kind of cycle-breaking substitution
 * CapacityPlanner already made for Cost Optimizer.
 *
 * Two of the eight scoring criteria are real, deterministic
 * compositions, not fabricated judgment: `rule_compliance` comes from
 * RuleEvaluator::evaluate() (Passed=5, Warning=3, Failed/Escalate=1),
 * and `delivery_risk` comes from SqliteRiskAssessor::canProceed()
 * (can_proceed=5, blocked=1). "Mandatory-rule failure disqualifies an
 * option regardless of total" is upheld literally: scoreOption()
 * cross-references the caller's own rule definitions against
 * RuleEvaluator's per-rule records, and any rule with `mandatory` not
 * explicitly false that came back Failed disqualifies the option
 * outright, independent of its weighted total.
 *
 * The remaining six criteria (requirement_fit, maintainability,
 * security, performance, reversibility, evidence) are caller-supplied
 * 1-5 scores -- no component in this codebase can honestly judge
 * "does this fit the requirement" or "is this maintainable" without
 * fabricating a model this class doesn't have, the same boundary
 * OptimizationEngine draws around expected benefit and effort.
 *
 * Like RuleEvaluator, this class has no database: matrices are pure
 * return values for the caller (eventually Decision Engine) to persist
 * and forward.
 */
final class DecisionMatrix
{
    private const CALLER_SUPPLIED_CRITERIA = ['requirement_fit', 'maintainability', 'security', 'performance', 'reversibility', 'evidence'];
    private const ALL_CRITERIA = ['rule_compliance', 'delivery_risk', 'requirement_fit', 'maintainability', 'security', 'performance', 'reversibility', 'evidence'];

    public function __construct(
        private readonly ?RuleEvaluator $ruleEvaluator = null,
        private readonly ?SqliteRiskAssessor $riskAssessor = null
    ) {
    }

    /**
     * @param array<string, int> $scores caller-supplied 1-5 scores for self::CALLER_SUPPLIED_CRITERIA
     * @param array{rules?: array<int, array{id: string, source: string, mandatory?: bool, condition: array<string, mixed>}>, rule_context?: array<string, mixed>, weights?: array<string, float>, evidence?: array<string, string>, uncertainty?: ?string} $options
     * @return array{option: string, scores: array<string, ?int>, weighted_total: ?float, disqualified: bool, disqualification_reason: ?string, weights: array<string, float>, evidence: array<string, string>, uncertainty: ?string}
     */
    public function scoreOption(string $optionReference, array $scores, array $options = []): array
    {
        foreach (self::CALLER_SUPPLIED_CRITERIA as $criterion) {
            if (!isset($scores[$criterion]) || $scores[$criterion] < 1 || $scores[$criterion] > 5) {
                return $this->invalid($optionReference, sprintf('Criterion "%s" requires a 1-5 score.', $criterion), $options);
            }
        }

        $ruleCompliance = null;
        $disqualificationReason = null;

        if ($this->ruleEvaluator !== null && isset($options['rules'])) {
            $ruleResult = $this->ruleEvaluator->evaluate($options['rules'], $options['rule_context'] ?? []);
            $ruleCompliance = match ($ruleResult['outcome']) {
                'Passed' => 5,
                'Warning' => 3,
                default => 1,
            };

            $recordsById = [];

            foreach ($ruleResult['records'] as $record) {
                $recordsById[$record['rule_id']] = $record;
            }

            foreach ($options['rules'] as $rule) {
                $isMandatory = $rule['mandatory'] ?? true;
                $record = $recordsById[$rule['id']] ?? null;

                if ($isMandatory && $record !== null && $record['result'] === 'Failed') {
                    $disqualificationReason = sprintf('Mandatory rule "%s" failed: %s', $rule['id'], $record['reason']);
                }
            }
        }

        $deliveryRisk = null;

        if ($this->riskAssessor !== null) {
            $risk = $this->riskAssessor->canProceed($optionReference);
            $deliveryRisk = $risk['can_proceed'] ? 5 : 1;
        }

        $allScores = [...$scores, 'rule_compliance' => $ruleCompliance, 'delivery_risk' => $deliveryRisk];
        $weights = $options['weights'] ?? array_fill_keys(self::ALL_CRITERIA, 1.0);

        $weightedTotal = $disqualificationReason === null
            ? $this->weightedAverage($allScores, $weights)
            : null;

        return [
            'option' => $optionReference,
            'scores' => $allScores,
            'weighted_total' => $weightedTotal,
            'disqualified' => $disqualificationReason !== null,
            'disqualification_reason' => $disqualificationReason,
            'weights' => $weights,
            'evidence' => $options['evidence'] ?? [],
            'uncertainty' => $options['uncertainty'] ?? null,
        ];
    }

    /**
     * Ranks scoreOption() results by weighted_total, excluding
     * disqualified options from the ranking entirely -- a genuine,
     * literal reading of "regardless of total".
     *
     * @param array<int, array<string, mixed>> $optionScores
     * @return array{ranked: array<int, array<string, mixed>>, rejected: array<int, array<string, mixed>>, rationale: ?string}
     */
    public function compareOptions(array $optionScores): array
    {
        $ranked = array_values(array_filter($optionScores, static fn(array $s): bool => !$s['disqualified']));
        $rejected = array_values(array_filter($optionScores, static fn(array $s): bool => $s['disqualified']));

        usort($ranked, static fn(array $a, array $b): int => $b['weighted_total'] <=> $a['weighted_total']);

        $rationale = $ranked !== []
            ? sprintf('"%s" scored highest at %.2f among %d qualifying option(s).', $ranked[0]['option'], $ranked[0]['weighted_total'], count($ranked))
            : ($rejected !== [] ? 'Every scored option was disqualified by a mandatory-rule failure.' : null);

        return ['ranked' => $ranked, 'rejected' => $rejected, 'rationale' => $rationale];
    }

    /**
     * @param array<string, ?int> $scores
     * @param array<string, float> $weights
     */
    private function weightedAverage(array $scores, array $weights): float
    {
        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($scores as $criterion => $score) {
            if ($score === null) {
                continue;
            }

            $weight = $weights[$criterion] ?? 1.0;
            $weightedSum += $score * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $weightedSum / $totalWeight : 0.0;
    }

    /**
     * @param array{weights?: array<string, float>, evidence?: array<string, string>, uncertainty?: ?string} $options
     * @return array{option: string, scores: array<string, ?int>, weighted_total: ?float, disqualified: bool, disqualification_reason: ?string, weights: array<string, float>, evidence: array<string, string>, uncertainty: ?string}
     */
    private function invalid(string $optionReference, string $reason, array $options): array
    {
        return [
            'option' => $optionReference,
            'scores' => [],
            'weighted_total' => null,
            'disqualified' => true,
            'disqualification_reason' => $reason,
            'weights' => $options['weights'] ?? [],
            'evidence' => $options['evidence'] ?? [],
            'uncertainty' => $options['uncertainty'] ?? null,
        ];
    }
}
