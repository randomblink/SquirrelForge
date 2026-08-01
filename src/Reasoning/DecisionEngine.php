<?php

declare(strict_types=1);

namespace SquirrelForge\Reasoning;

use SquirrelForge\Knowledge\KnowledgeManager;

/**
 * Aggregates specialist evaluations and selects a preferred option,
 * producing a traceable Decision Record, per
 * 19_REASONING/DECISION-ENGINE.md.
 *
 * "The Decision Engine aggregates; it does not perform the specialist
 * evaluations itself" is upheld by genuine composition, never
 * re-derivation: rule compliance comes from the injected
 * RuleEvaluator::evaluate(), and the tradeoff comparison -- including
 * its own real risk-eligibility check and matrix-score-based ranking --
 * comes from the injected TradeoffAnalyzer::compareOptions(). This
 * class's own selection logic does exactly one more real thing
 * TradeoffAnalyzer's own recommendation doesn't: it walks the ranked,
 * tradeoff-eligible candidates and picks the highest-rated one that is
 * *also* rule-compliant, since rule compliance and tradeoff fitness are
 * two independent gates neither specialist enforces on the other's
 * behalf.
 *
 * Does not take Confidence Scorer as a constructor dependency: it and
 * Decision Engine depend on each other in the spec's own headers (a
 * genuine cycle, the same shape as Capacity Planner/Cost Optimizer),
 * and this class builds first. A per-option `confidence` value is
 * accepted as optional caller-supplied input instead; when it's
 * missing, that gap is recorded as a real "Unresolved Limitation" --
 * the spec's own Decision Record field for exactly this situation --
 * rather than silently proceeding as if scoring happened.
 *
 * Memory Retrieval has no src/ implementation, so historical context is
 * accepted as caller-supplied input the same way, with its absence
 * recorded as a limitation too. Knowledge Manager, by contrast, is
 * real: "retrieve... approved reusable knowledge" is a genuine
 * composition of KnowledgeManager::coordinate('search', ...) when the
 * caller supplies a knowledge_query.
 *
 * Like RuleEvaluator, this class has no database: a Decision Record is
 * a pure return value for the caller (eventually Strategy Planner) to
 * act on.
 */
final class DecisionEngine
{
    public function __construct(
        private readonly ?RuleEvaluator $ruleEvaluator = null,
        private readonly ?TradeoffAnalyzer $tradeoffAnalyzer = null,
        private readonly ?KnowledgeManager $knowledgeManager = null
    ) {
    }

    /**
     * @param array<int, array{option: string, advantages?: array<string, array<int, string>>, disadvantages?: array<string, array<int, string>>, matrix_score?: array{weighted_total: ?float, disqualified: bool, disqualification_reason: ?string}, rules?: array<int, array<string, mixed>>, rule_context?: array<string, mixed>, confidence?: ?float}> $options
     * @param array{historical_context?: mixed, knowledge_query?: string} $context
     * @return array{decision: ?array<string, mixed>, outcome: string, error: ?string}
     */
    public function decide(array $options, array $context = []): array
    {
        if ($options === []) {
            return ['decision' => null, 'outcome' => 'no_options', 'error' => 'At least one option is required.'];
        }

        if ($this->tradeoffAnalyzer === null) {
            return ['decision' => null, 'outcome' => 'not_configured', 'error' => 'Tradeoff Analyzer is not configured.'];
        }

        $limitations = [];
        $approvedKnowledge = $this->retrieveApprovedKnowledge($context, $limitations);

        if (!array_key_exists('historical_context', $context)) {
            $limitations[] = 'No historical context was supplied through Memory Retrieval.';
        }

        [$ruleResults, $compliantOptions] = $this->evaluateRuleCompliance($options, $limitations);

        if (array_filter($options, static fn(array $option): bool => !array_key_exists('confidence', $option)) !== []) {
            $limitations[] = 'One or more options carry no confidence score from Confidence Scorer.';
        }

        $comparison = $this->tradeoffAnalyzer->compareOptions(array_map(
            static fn(array $option): array => [
                'option' => $option['option'],
                'advantages' => $option['advantages'] ?? [],
                'disadvantages' => $option['disadvantages'] ?? [],
                'matrix_score' => $option['matrix_score'] ?? null,
            ],
            $options
        ));

        $ranked = $comparison['comparison'];
        usort($ranked, static fn(array $a, array $b): int => ($b['overall_rating'] ?? -INF) <=> ($a['overall_rating'] ?? -INF));

        $selected = null;

        foreach ($ranked as $candidate) {
            if ($candidate['eligible'] && in_array($candidate['option'], $compliantOptions, true)) {
                $selected = $candidate;

                break;
            }
        }

        if ($selected === null) {
            return ['decision' => null, 'outcome' => 'no_eligible_option', 'error' => 'No option is both tradeoff-eligible and rule-compliant.'];
        }

        $optionsByName = $this->indexByOption($options);
        $alternatives = array_values(array_map(
            static fn(array $candidate): array => ['option' => $candidate['option'], 'reasoning' => $candidate['reasoning']],
            array_filter($ranked, static fn(array $c): bool => $c['option'] !== $selected['option'])
        ));

        $decision = [
            'decision_id' => 'decision_' . bin2hex(random_bytes(12)),
            'selected_option' => $selected['option'],
            'rationale' => $selected['reasoning'],
            'alternatives_considered' => $alternatives,
            'rule_evaluation_reference' => $ruleResults[$selected['option']] ?? null,
            'risk_assessment_reference' => $selected['risk'] ?? null,
            'tradeoff_reference' => $selected,
            'confidence_reference' => $optionsByName[$selected['option']]['confidence'] ?? null,
            'approved_knowledge' => $approvedKnowledge,
            'unresolved_limitations' => $limitations,
        ];

        return ['decision' => $decision, 'outcome' => 'decided', 'error' => null];
    }

    /**
     * @param array{knowledge_query?: string} $context
     * @param array<int, string> $limitations
     */
    private function retrieveApprovedKnowledge(array $context, array &$limitations): mixed
    {
        if (!isset($context['knowledge_query'])) {
            return null;
        }

        if ($this->knowledgeManager === null) {
            $limitations[] = 'Knowledge Manager is not configured; approved knowledge was not consulted.';

            return null;
        }

        return $this->knowledgeManager->coordinate('search', ['query' => $context['knowledge_query']]);
    }

    /**
     * @param array<int, array{option: string, rules?: array<int, array<string, mixed>>, rule_context?: array<string, mixed>}> $options
     * @param array<int, string> $limitations
     * @return array{0: array<string, array<string, mixed>>, 1: array<int, string>}
     */
    private function evaluateRuleCompliance(array $options, array &$limitations): array
    {
        $results = [];
        $compliant = [];
        $verifiedAny = false;

        foreach ($options as $option) {
            if ($this->ruleEvaluator !== null && isset($option['rules'])) {
                $verifiedAny = true;
                $result = $this->ruleEvaluator->evaluate($option['rules'], $option['rule_context'] ?? []);
                $results[$option['option']] = $result;

                if (in_array($result['outcome'], ['Passed', 'Warning'], true)) {
                    $compliant[] = $option['option'];
                }

                continue;
            }

            $compliant[] = $option['option'];
        }

        if ($this->ruleEvaluator === null || !$verifiedAny) {
            $limitations[] = 'Rule Evaluator did not independently verify compliance for one or more options.';
        }

        return [$results, $compliant];
    }

    /**
     * @param array<int, array{option: string}> $options
     * @return array<string, array<string, mixed>>
     */
    private function indexByOption(array $options): array
    {
        $indexed = [];

        foreach ($options as $option) {
            $indexed[$option['option']] = $option;
        }

        return $indexed;
    }
}
