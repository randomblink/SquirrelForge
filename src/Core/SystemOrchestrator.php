<?php

declare(strict_types=1);

namespace SquirrelForge\Core;

use SquirrelForge\Reasoning\AIDriver;
use SquirrelForge\Reasoning\DecisionEngine;
use SquirrelForge\Reasoning\ExplanationEngine;
use SquirrelForge\Reasoning\ReflectionEngine;
use SquirrelForge\Reasoning\StrategyPlanner;

/**
 * Coordinates SquirrelForge's real reasoning cycle -- LLM invocation,
 * decision selection, strategy planning, explanation, and post-task
 * reflection -- per 00_CORE/SYSTEM-ORCHESTRATOR.md.
 *
 * This is a DELIBERATELY SCOPED implementation, not the full spec. A
 * second, independent audit pass found `AIDriver`, `ReflectionEngine`,
 * and `ExplanationEngine` all real, tested, and all three explicitly
 * naming `SYSTEM-ORCHESTRATOR.md` as their own consumer -- yet no such
 * class existed anywhere in this codebase. `SYSTEM-ORCHESTRATOR.md`
 * itself describes something far larger: coordinating startup,
 * shutdown, health-checking, and routing across *all thirty* numbered
 * layers, global correlation/trace identifiers, and cross-cutting
 * governance/observability gates. Building that literally in one pass
 * would mean either a multi-session undertaking or a shallow wrapper
 * built only to clear an orphan-class flag, not a genuinely useful
 * coordinator -- so, with the user's explicit direction, this class
 * covers exactly the real reasoning-cycle slice those three waiting
 * components need, and defers everything else the spec describes,
 * the same honest-partial-scope precedent this codebase already set
 * for `26_INTEGRATIONS`'s deliberately scoped Notification Manager
 * slice.
 *
 * "Domain managers remain authoritative for their own state and
 * operations" and "must not replace a domain manager as the
 * authoritative owner" are upheld literally: this class never
 * reimplements `DecisionEngine`/`StrategyPlanner`/`ExplanationEngine`/
 * `ReflectionEngine`/`AIDriver`'s own logic -- it only sequences real
 * calls to them, in the order `19_REASONING`'s own layer already
 * defines (Decision -> Strategy -> Explanation), and returns exactly
 * what each one decided.
 *
 * "Enforce readiness... gates" and "Pause or terminate processing when
 * a required control fails" are real, checked behavior in
 * `reasonAndExplain()`: strategy planning is never attempted from a
 * Decision Record that isn't genuinely `decided`, and explanation is
 * never attempted from a Strategy Record that isn't genuinely
 * `planned` -- each stage's own real outcome gates whether the next
 * stage runs at all, and a partial result (whichever stages actually
 * completed) is always returned rather than silently dropped.
 */
final class SystemOrchestrator
{
    public function __construct(
        private readonly ?AIDriver $aiDriver = null,
        private readonly ?DecisionEngine $decisionEngine = null,
        private readonly ?StrategyPlanner $strategyPlanner = null,
        private readonly ?ExplanationEngine $explanationEngine = null,
        private readonly ?ReflectionEngine $reflectionEngine = null
    ) {
    }

    /**
     * Coordinates the raw LLM reasoning mechanism -- a genuine
     * composition of `AIDriver::reason()`, never a re-derivation of
     * prompt compilation or provider mechanics.
     *
     * @param array{system_prompt?: string, evidence_tiers?: array<int, string>, expected_fields?: array<int, string>} $options
     * @return array{result: mixed, raw_response: ?string, prompt: ?string, prompt_hash: ?string, outcome: string, error: ?string}
     */
    public function think(array $options = []): array
    {
        if ($this->aiDriver === null) {
            return ['result' => null, 'raw_response' => null, 'prompt' => null, 'prompt_hash' => null, 'outcome' => 'not_configured', 'error' => 'AI Driver is not configured.'];
        }

        return $this->aiDriver->reason($options);
    }

    /**
     * Coordinates Decision -> Strategy -> Explanation in real sequence,
     * gating each stage on the previous stage's own real outcome
     * rather than assuming success.
     *
     * @param array<int, array{option: string, advantages?: array<string, array<int, string>>, disadvantages?: array<string, array<int, string>>, matrix_score?: array{weighted_total: ?float, disqualified: bool, disqualification_reason: ?string}, rules?: array<int, array<string, mixed>>, rule_context?: array<string, mixed>, confidence?: ?float}> $options
     * @param array{historical_context?: mixed, knowledge_query?: string} $decisionContext
     * @param array{primary_goal?: ?string, expected_output?: ?string} $goal
     * @param array<int, array{id: string, description: string, validation_note?: ?string}> $phases
     * @param array<int, array{id: string, phase_id: string, description: string}> $milestones
     * @return array{
     *     decision: ?array<string, mixed>,
     *     strategy: ?array<string, mixed>,
     *     explanation: ?array<string, mixed>,
     *     outcome: string,
     *     error: ?string
     * }
     */
    public function reasonAndExplain(array $options, array $decisionContext, array $goal, array $phases, array $milestones = []): array
    {
        if ($this->decisionEngine === null) {
            return $this->result(null, null, null, 'not_configured', 'Decision Engine is not configured.');
        }

        $decisionResult = $this->decisionEngine->decide($options, $decisionContext);

        if ($decisionResult['outcome'] !== 'decided') {
            return $this->result(null, null, null, 'decision_failed', $decisionResult['error']);
        }

        if ($this->strategyPlanner === null) {
            return $this->result($decisionResult['decision'], null, null, 'not_configured', 'Strategy Planner is not configured.');
        }

        $strategyResult = $this->strategyPlanner->planStrategy($decisionResult, $goal, $phases, $milestones);

        if ($strategyResult['outcome'] !== 'planned') {
            return $this->result($decisionResult['decision'], null, null, 'strategy_failed', $strategyResult['error']);
        }

        if ($this->explanationEngine === null) {
            return $this->result($decisionResult['decision'], $strategyResult['strategy'], null, 'not_configured', 'Explanation Engine is not configured.');
        }

        $explanationResult = $this->explanationEngine->explain($decisionResult, $strategyResult, $goal);

        if ($explanationResult['outcome'] !== 'explained') {
            return $this->result($decisionResult['decision'], $strategyResult['strategy'], null, 'explanation_failed', $explanationResult['error']);
        }

        return $this->result($decisionResult['decision'], $strategyResult['strategy'], $explanationResult['explanation'], 'coordinated', null);
    }

    /**
     * Coordinates post-completion review -- a genuine composition of
     * `ReflectionEngine::reflectOnTask()`.
     *
     * @param array{primary_goal?: ?string, acceptance_criteria?: array<int, string>, acceptance_criteria_met?: array<string, bool>, prior_issues?: array<int, array{description: string}>, recurrence_threshold?: int} $options
     * @return array{reflection: array<string, mixed>, outcome: string, error: ?string}
     */
    public function reflectOnCompletedTask(string $taskReference, array $options = []): array
    {
        if ($this->reflectionEngine === null) {
            return ['reflection' => [], 'outcome' => 'not_configured', 'error' => 'Reflection Engine is not configured.'];
        }

        return $this->reflectionEngine->reflectOnTask($taskReference, $options);
    }

    /**
     * @return array{decision: ?array<string, mixed>, strategy: ?array<string, mixed>, explanation: ?array<string, mixed>, outcome: string, error: ?string}
     */
    private function result(?array $decision, ?array $strategy, ?array $explanation, string $outcome, ?string $error): array
    {
        return ['decision' => $decision, 'strategy' => $strategy, 'explanation' => $explanation, 'outcome' => $outcome, 'error' => $error];
    }
}
