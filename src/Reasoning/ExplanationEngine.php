<?php

declare(strict_types=1);

namespace SquirrelForge\Reasoning;

/**
 * Produces a clear, traceable explanation of a decision and its
 * resulting strategy, per 19_REASONING/EXPLANATION-ENGINE.md.
 *
 * "The Explanation Engine explains; it does not re-derive the
 * evaluations it references" is upheld literally: explain() never
 * calls RuleEvaluator, SqliteRiskAssessor, TradeoffAnalyzer, or
 * ConfidenceScorer itself. Every summary field is built by formatting
 * data that already exists on DecisionEngine::decide()'s own real
 * output -- `rule_evaluation_reference`, `risk_assessment_reference`,
 * `tradeoff_reference`, `approved_knowledge` -- and
 * StrategyPlanner::planStrategy()'s own real output. "Describe why
 * rejected alternatives were not selected" is rendered directly from
 * the Decision Record's own real `alternatives_considered` reasoning
 * strings, the exact text TradeoffAnalyzer already produced for each
 * losing option -- never a fabricated restatement.
 *
 * A real structural integrity check backs the whole explanation: it can
 * only be built from a Decision Record whose outcome is literally
 * `decided` and a Strategy Record whose outcome is literally `planned`,
 * and the Strategy Record's own `decision_reference` must actually
 * match the Decision Record being explained -- an explanation can never
 * be produced for a decision/strategy pair that don't actually belong
 * together.
 *
 * Like Strategy Planner, this class has no database: an Explanation
 * Record is a pure return value for the caller (Reasoning / Reporting)
 * to present.
 */
final class ExplanationEngine
{
    /**
     * @param array{outcome: string, decision?: array{decision_id: string, selected_option: string, rationale: string, alternatives_considered: array<int, array{option: string, reasoning: string}>, rule_evaluation_reference: ?array<string, mixed>, risk_assessment_reference: ?array<string, mixed>, tradeoff_reference: ?array<string, mixed>, approved_knowledge: mixed, unresolved_limitations: array<int, string>}} $decisionResult DecisionEngine::decide()'s output
     * @param array{outcome: string, strategy?: array{strategy_id: string, expected_outcome: ?string, decision_reference: string}} $strategyResult StrategyPlanner::planStrategy()'s output
     * @param array{primary_goal?: ?string} $goal GoalPlanner::planGoal()'s `goal` output
     * @return array{explanation: ?array<string, mixed>, outcome: string, error: ?string}
     */
    public function explain(array $decisionResult, array $strategyResult, array $goal = []): array
    {
        if (($decisionResult['outcome'] ?? null) !== 'decided' || !isset($decisionResult['decision'])) {
            return ['explanation' => null, 'outcome' => 'no_decision', 'error' => 'A decided Decision Record is required before an explanation can be built.'];
        }

        if (($strategyResult['outcome'] ?? null) !== 'planned' || !isset($strategyResult['strategy'])) {
            return ['explanation' => null, 'outcome' => 'no_strategy', 'error' => 'A planned Strategy Record is required before an explanation can be built.'];
        }

        $decision = $decisionResult['decision'];
        $strategy = $strategyResult['strategy'];

        if ($decision['decision_id'] !== $strategy['decision_reference']) {
            return ['explanation' => null, 'outcome' => 'mismatched_records', 'error' => 'The Strategy Record does not reference this Decision Record.'];
        }

        $explanation = [
            'explanation_id' => 'explanation_' . bin2hex(random_bytes(12)),
            'decision_reference' => $decision['decision_id'],
            'strategy_reference' => $strategy['strategy_id'],
            'goal' => $goal['primary_goal'] ?? null,
            'rules_applied' => $this->summarizeRuleEvaluation($decision['rule_evaluation_reference']),
            'risk_summary' => $this->summarizeRisk($decision['risk_assessment_reference']),
            'tradeoffs' => $this->summarizeTradeoff($decision['tradeoff_reference'], $decision['alternatives_considered']),
            'knowledge_used' => $this->summarizeKnowledge($decision['approved_knowledge']),
            'reasoning' => $decision['rationale'],
            'expected_outcome' => $strategy['expected_outcome'],
            'unresolved_limitations' => $decision['unresolved_limitations'],
        ];

        return ['explanation' => $explanation, 'outcome' => 'explained', 'error' => null];
    }

    /**
     * @param array{outcome: string, records: array<int, mixed>}|null $ruleEvaluation
     */
    private function summarizeRuleEvaluation(?array $ruleEvaluation): string
    {
        if ($ruleEvaluation === null) {
            return 'No rule evaluation was recorded for the selected option.';
        }

        return sprintf('Rule evaluation outcome: %s (%d record(s)).', $ruleEvaluation['outcome'], count($ruleEvaluation['records'] ?? []));
    }

    /**
     * @param array{can_proceed: bool, blocking_risks: array<int, string>}|null $risk
     */
    private function summarizeRisk(?array $risk): string
    {
        if ($risk === null) {
            return 'No risk assessment reference was recorded.';
        }

        return $risk['can_proceed'] === true
            ? 'No unmitigated critical risks block this option.'
            : sprintf('Blocked by unmitigated critical risk(s): %s.', implode(', ', $risk['blocking_risks']));
    }

    /**
     * @param array{reasoning?: string, overall_rating?: ?float}|null $tradeoffReference
     * @param array<int, array{option: string, reasoning: string}> $alternatives
     * @return array{selected_reasoning: ?string, overall_rating: ?float, rejected_alternatives: array<int, string>}
     */
    private function summarizeTradeoff(?array $tradeoffReference, array $alternatives): array
    {
        return [
            'selected_reasoning' => $tradeoffReference['reasoning'] ?? null,
            'overall_rating' => $tradeoffReference['overall_rating'] ?? null,
            'rejected_alternatives' => array_map(
                static fn(array $alternative): string => sprintf('"%s" was not selected: %s', $alternative['option'], $alternative['reasoning']),
                $alternatives
            ),
        ];
    }

    private function summarizeKnowledge(mixed $approvedKnowledge): string
    {
        if ($approvedKnowledge === null) {
            return 'No approved reusable knowledge was consulted for this decision.';
        }

        if (is_array($approvedKnowledge) && ($approvedKnowledge['outcome'] ?? null) !== 'searched') {
            return sprintf('Knowledge Manager was consulted but returned no usable result: %s', $approvedKnowledge['error'] ?? 'unknown reason.');
        }

        return 'Approved reusable knowledge was consulted; see the Decision Record for the full search result.';
    }
}
