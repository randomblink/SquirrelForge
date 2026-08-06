<?php

declare(strict_types=1);

namespace SquirrelForge\AiDriver;

/**
 * Selects the most appropriate Claude model for a request, per
 * 34_AIDRIVER/MODEL-ROUTER.md.
 *
 * This is a deliberately scoped subset of that spec: no governance/privacy
 * policy gate. `23_GOVERNANCE/POLICY-ENGINE.md` is real now as
 * `SqlitePolicyEngine`, and its `evaluate(requestId, context, category)`
 * is a closer fit here than most of this codebase's other unwired
 * governance references -- the spec names a concrete step ("Evaluate
 * privacy and governance constraints via POLICY-ENGINE.md") for it to
 * satisfy -- but wiring it in is still a separate, future decision, not
 * bundled into this pass. No coordination with a Cost Optimizer either:
 * `CostOptimizer` is real now too, but its methods
 * (`identifyReductionFromUtilization()`, `forecastBudgetImpact()`) analyze
 * aggregate utilization and budget trends, not a single route's cost --
 * there's no per-call "does this model fit the budget" method to call. No
 * model availability/health tracking (no per-model telemetry exists --
 * unlike ToolSelector, which has a real isHealthy() to check), and no
 * declarative 21_CONFIGURATION/MODEL-CONFIG.md-style external
 * configuration -- the registry below is a fixed, in-code table for the
 * four current Claude 5 models. It is also not wired into
 * Reasoner/LlmClientResolver/AgentPipelineModule: Reasoner takes one
 * LlmClientInterface fixed at construction per agent, and making model
 * selection dynamic per-call would mean changing that already-shipped
 * constructor/loop -- a separate, bigger decision left for a future pass.
 *
 * Routing strategies are limited to the two dimensions this class can
 * actually decide: 'lowest_cost' (by published input token price) and
 * 'highest_quality' (by Anthropic's own published capability ordering).
 * No 'lowest_latency' (no latency telemetry exists) or 'privacy_first'/
 * 'local_first' (no local-model infrastructure).
 */
final class ModelRouter
{
    private const MAX_HISTORY = 500;

    /**
     * @var array<int, array{id: string, context_window_tokens: int, input_cost_per_million: float, output_cost_per_million: float, tier: int}>
     */
    private const MODELS = [
        ['id' => 'claude-haiku-4-5', 'context_window_tokens' => 200_000,
            'input_cost_per_million' => 1.00, 'output_cost_per_million' => 5.00, 'tier' => 1],
        ['id' => 'claude-sonnet-5', 'context_window_tokens' => 1_000_000,
            'input_cost_per_million' => 3.00, 'output_cost_per_million' => 15.00, 'tier' => 2],
        ['id' => 'claude-opus-5', 'context_window_tokens' => 1_000_000,
            'input_cost_per_million' => 5.00, 'output_cost_per_million' => 25.00, 'tier' => 3],
        ['id' => 'claude-fable-5', 'context_window_tokens' => 1_000_000,
            'input_cost_per_million' => 10.00, 'output_cost_per_million' => 50.00, 'tier' => 4],
    ];

    private const STRATEGIES = ['lowest_cost', 'highest_quality'];

    /**
     * @var array<int, array{estimated_input_tokens: int, strategy: string, selected: ?string, outcome: string}>
     */
    private array $history = [];

    /**
     * @return array{selected: ?string, fallbacks: array<int, string>, rejected: array<int, array{id: string, reason: string}>, outcome: string}
     */
    public function route(int $estimatedInputTokens, string $strategy = 'lowest_cost'): array
    {
        $strategy = in_array($strategy, self::STRATEGIES, true) ? $strategy : 'lowest_cost';

        $candidates = [];
        $rejected = [];

        foreach (self::MODELS as $model) {
            if ($model['context_window_tokens'] < $estimatedInputTokens) {
                $rejected[] = ['id' => $model['id'], 'reason' => 'context_window_exceeded'];
                continue;
            }

            $candidates[] = $model;
        }

        usort($candidates, static fn(array $a, array $b): int => $strategy === 'lowest_cost'
            ? $a['input_cost_per_million'] <=> $b['input_cost_per_million']
            : $b['tier'] <=> $a['tier']);

        $ids = array_column($candidates, 'id');
        $selected = $ids[0] ?? null;
        $fallbacks = array_slice($ids, 1);
        $outcome = $selected !== null ? 'selected' : 'unsupported_context_size';

        $this->record($estimatedInputTokens, $strategy, $selected, $outcome);

        return ['selected' => $selected, 'fallbacks' => $fallbacks, 'rejected' => $rejected, 'outcome' => $outcome];
    }

    /**
     * @return array<int, array{estimated_input_tokens: int, strategy: string, selected: ?string, outcome: string}>
     */
    public function recentRoutingDecisions(int $limit = 50): array
    {
        return array_slice($this->history, -$limit);
    }

    private function record(int $estimatedInputTokens, string $strategy, ?string $selected, string $outcome): void
    {
        $this->history[] = [
            'estimated_input_tokens' => $estimatedInputTokens,
            'strategy' => $strategy,
            'selected' => $selected,
            'outcome' => $outcome,
        ];

        if (count($this->history) > self::MAX_HISTORY) {
            array_shift($this->history);
        }
    }
}
