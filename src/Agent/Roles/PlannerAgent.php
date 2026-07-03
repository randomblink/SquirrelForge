<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

/**
 * Implements the Agent Planner role from `16_AGENTS/AGENT-PLANNER.md`.
 *
 * Converts the Architecture Blueprint into an ordered Execution Plan and
 * hands off to the Developer stage.
 */
final class PlannerAgent extends AbstractRoleAgent
{
    public function stage(): string
    {
        return 'planner';
    }

    public function getName(): string
    {
        return 'Agent Planner';
    }

    public function getDescription(): string
    {
        return 'Converts the architecture blueprint into an ordered execution plan.';
    }

    protected function process(array $context): array
    {
        // A blueprint must have been produced before planning can begin.
        $this->requireHistory($context, 'architect');

        $phases = $context['phases'] ?? [];

        $plan = array_map(
            static function (array $phase): array {
                return [
                    'phase' => $phase['phase'] ?? null,
                    'task' => $phase['task'] ?? null,
                    'agent' => $phase['agent'] ?? null,
                    'dependencies' => $phase['dependencies'] ?? [],
                    'validation' => $phase['validation'] ?? null,
                    'status' => $phase['status'] ?? 'Pending',
                ];
            },
            $phases
        );

        if ($plan === []) {
            throw new \InvalidArgumentException(
                'PlannerAgent requires at least one phase in context field "phases".'
            );
        }

        return [
            'plan' => $plan,
            'status' => 'Complete',
            'next_stage' => 'developer',
        ];
    }
}
