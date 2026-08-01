<?php

declare(strict_types=1);

namespace SquirrelForge\Reasoning;

/**
 * Converts the Decision Engine's selected option into a high-level
 * executable strategy, per 19_REASONING/STRATEGY-PLANNER.md.
 *
 * "Define the strategic objective and expected outcome... that aligns
 * with project goals" is a genuine composition of two already-real
 * sources rather than fresh judgment: the objective is derived directly
 * from DecisionEngine::decide()'s own `selected_option`, and the
 * expected outcome is GoalPlanner::planGoal()'s own real
 * `expected_output` -- the actual goal, not an invented restatement of
 * it.
 *
 * "Propose high-level implementation phases" and "propose milestones"
 * are inherently semantic judgments -- deciding what the actual phases
 * of a strategy are requires understanding the decision's meaning, the
 * same boundary GoalPlanner and TaskDecomposer already draw around
 * their own semantic fields. Phases and milestones are therefore
 * caller-supplied. What this class enforces for real: milestones must
 * reference a phase that actually exists in the proposed phase list
 * (the same structural cross-check TaskDecomposer already applies to
 * dependencies), and duplicate phase identifiers are rejected outright.
 *
 * "Note where validation is strategically required" is tied to real
 * upstream evidence, not fabricated: when DecisionEngine's own
 * `unresolved_limitations` is non-empty, planStrategy() requires at
 * least one phase to carry a `validation_note` -- a real, enforced gate
 * connecting the Decision Record's own real gaps to the Strategy
 * Record's own required field, rather than a decorative checklist item.
 *
 * A strategy can only be planned from a Decision Record whose own
 * outcome is literally `decided` -- this class never proceeds from a
 * rejected, unconfigured, or otherwise incomplete decision.
 *
 * Like DecisionEngine, this class has no database: a Strategy Record is
 * a pure return value for the caller (eventually Explanation Engine /
 * Task Decomposer) to act on.
 */
final class StrategyPlanner
{
    /**
     * @param array{outcome: string, decision?: array{decision_id: string, selected_option: string, unresolved_limitations: array<int, string>}} $decisionResult DecisionEngine::decide()'s output
     * @param array{expected_output?: ?string} $goal GoalPlanner::planGoal()'s `goal` output
     * @param array<int, array{id: string, description: string, validation_note?: ?string}> $phases
     * @param array<int, array{id: string, phase_id: string, description: string}> $milestones
     * @return array{strategy: ?array<string, mixed>, outcome: string, error: ?string}
     */
    public function planStrategy(array $decisionResult, array $goal, array $phases, array $milestones = []): array
    {
        if (($decisionResult['outcome'] ?? null) !== 'decided' || !isset($decisionResult['decision'])) {
            return ['strategy' => null, 'outcome' => 'no_decision', 'error' => 'A decided Decision Record is required before strategy planning can begin.'];
        }

        if ($phases === []) {
            return ['strategy' => null, 'outcome' => 'invalid_strategy', 'error' => 'At least one strategic phase is required.'];
        }

        $phaseIds = array_column($phases, 'id');

        foreach (array_count_values($phaseIds) as $id => $count) {
            if ($count > 1) {
                return ['strategy' => null, 'outcome' => 'invalid_strategy', 'error' => sprintf('Duplicate phase identifier "%s".', $id)];
            }
        }

        foreach ($milestones as $milestone) {
            if (!in_array($milestone['phase_id'], $phaseIds, true)) {
                return ['strategy' => null, 'outcome' => 'invalid_strategy', 'error' => sprintf('Milestone "%s" references unknown phase "%s".', $milestone['id'], $milestone['phase_id'])];
            }
        }

        $decision = $decisionResult['decision'];
        $unresolvedLimitations = $decision['unresolved_limitations'] ?? [];
        $validationNotes = array_values(array_filter(array_column($phases, 'validation_note')));

        if ($unresolvedLimitations !== [] && $validationNotes === []) {
            return ['strategy' => null, 'outcome' => 'validation_required', 'error' => 'The Decision Record carries unresolved limitations; at least one phase must note where validation is strategically required.'];
        }

        $strategy = [
            'strategy_id' => 'strategy_' . bin2hex(random_bytes(12)),
            'objective' => sprintf('Implement: %s', $decision['selected_option']),
            'expected_outcome' => $goal['expected_output'] ?? null,
            'phases' => $phases,
            'proposed_milestones' => $milestones,
            'validation_notes' => $validationNotes,
            'carried_limitations' => $unresolvedLimitations,
            'decision_reference' => $decision['decision_id'],
        ];

        return ['strategy' => $strategy, 'outcome' => 'planned', 'error' => null];
    }
}
