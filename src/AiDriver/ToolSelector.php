<?php

declare(strict_types=1);

namespace SquirrelForge\AiDriver;

use SquirrelForge\Contracts\ToolInterface;
use SquirrelForge\Tools\ToolRegistry;

/**
 * Selects which registered tool best satisfies a required capability, per
 * 34_AIDRIVER/TOOL-SELECTOR.md.
 *
 * This is a deliberately scoped subset of that spec: no permission/
 * authorization check (21_CONFIGURATION/PERMISSIONS.md is a declarative
 * policy document by its own spec's design, "not a decision engine" --
 * it has no code to check against, and none is expected). No governance
 * policy compliance either: `23_GOVERNANCE/POLICY-ENGINE.md` is real now
 * as `SqlitePolicyEngine`, and the spec does name it as a governance
 * input here, but wiring its generic `evaluate()` in is a separate,
 * future decision, not bundled into this pass. No cost/latency/
 * performance-based ranking (no per-tool telemetry exists to rank on --
 * ordering among healthy candidates is registration order only). No
 * coordination with a Model Router either: `ModelRouter` is real now,
 * but its own docblock is explicit that it has no per-model availability/
 * health tracking, so it still can't supply the "AI model availability"
 * signal this spec's own selection criteria ask for. And no handoff to a
 * separate Action Dispatcher -- tools are still executed exactly where
 * they are today, via ToolRegistry::get()->execute() inside
 * SquirrelForge\Llm\Reasoner's own tool-use loop, since
 * 20_EXECUTION/ACTION-DISPATCHER.md doesn't exist as a distinct component.
 *
 * What is real and enforced: capability-tag matching, and the spec's own
 * explicit rule that an unhealthy tool must never be selected -- every
 * ToolInterface already has a genuine isHealthy() via HealthCheckInterface.
 */
final class ToolSelector
{
    private const MAX_HISTORY = 500;

    /**
     * @var array<int, array{capabilities: array<int, string>, selected: ?string, fallbacks: array<int, string>, outcome: string}>
     */
    private array $history = [];

    public function __construct(
        private readonly ToolRegistry $tools
    ) {
    }

    /**
     * @param array<int, string> $requiredCapabilities empty means no specific
     *                                                  requirement -- every
     *                                                  registered tool is a
     *                                                  candidate.
     * @return array{selected: ?string, fallbacks: array<int, string>, rejected: array<int, array{id: string, reason: string}>, outcome: string}
     */
    public function select(array $requiredCapabilities): array
    {
        $healthy = [];
        $rejected = [];

        foreach ($this->tools->all() as $tool) {
            if (!$this->matchesCapabilities($tool, $requiredCapabilities)) {
                continue;
            }

            if (!$tool->isHealthy()) {
                $rejected[] = ['id' => $tool->getId(), 'reason' => 'unhealthy'];
                continue;
            }

            $healthy[] = $tool->getId();
        }

        $selected = $healthy[0] ?? null;
        $fallbacks = array_slice($healthy, 1);
        $outcome = $selected !== null ? 'selected' : 'unsupported_capability';

        $this->record($requiredCapabilities, $selected, $fallbacks, $outcome);

        return ['selected' => $selected, 'fallbacks' => $fallbacks, 'rejected' => $rejected, 'outcome' => $outcome];
    }

    /**
     * @return array<int, array{capabilities: array<int, string>, selected: ?string, fallbacks: array<int, string>, outcome: string}>
     */
    public function recentSelections(int $limit = 50): array
    {
        return array_slice($this->history, -$limit);
    }

    /**
     * @param array<int, string> $requiredCapabilities
     */
    private function matchesCapabilities(ToolInterface $tool, array $requiredCapabilities): bool
    {
        if ($requiredCapabilities === []) {
            return true;
        }

        return array_intersect($requiredCapabilities, $tool->capabilities()) !== [];
    }

    /**
     * @param array<int, string> $requiredCapabilities
     * @param array<int, string> $fallbacks
     */
    private function record(array $requiredCapabilities, ?string $selected, array $fallbacks, string $outcome): void
    {
        $this->history[] = [
            'capabilities' => $requiredCapabilities,
            'selected' => $selected,
            'fallbacks' => $fallbacks,
            'outcome' => $outcome,
        ];

        if (count($this->history) > self::MAX_HISTORY) {
            array_shift($this->history);
        }
    }
}
