<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

/**
 * Implements the Agent Architect role from `16_AGENTS/AGENT-ARCHITECT.md`.
 *
 * Produces the Architecture Blueprint (goal, project type, components,
 * dependencies, risks, primary/supporting workflows, expected output) and
 * hands off to the Planner stage.
 */
final class ArchitectAgent extends AbstractRoleAgent
{
    public function stage(): string
    {
        return 'architect';
    }

    public function getName(): string
    {
        return 'Agent Architect';
    }

    public function getDescription(): string
    {
        return 'Designs the structure, architecture, and technical direction for a requested solution.';
    }

    /**
     * Maps each Architecture Blueprint field to the context key a caller
     * would use to supply it explicitly.
     */
    private const FIELD_MAP = [
        'project_type' => 'project_type',
        'components' => 'components',
        'dependencies' => 'dependencies',
        'risks' => 'risks',
        'primary_workflow' => 'primary_workflow',
        'supporting_workflows' => 'supporting_workflows',
        'output' => 'expected_output',
    ];

    protected function process(array $context): array
    {
        $goal = $this->requireField($context, 'goal');

        $values = [];
        $missing = [];

        foreach (self::FIELD_MAP as $blueprintKey => $contextKey) {
            if (array_key_exists($contextKey, $context) && $context[$contextKey] !== null) {
                $values[$blueprintKey] = $context[$contextKey];
            } else {
                $missing[] = $blueprintKey;
            }
        }

        if ($missing !== []) {
            $reasoned = $this->reason(
                'Read the goal and any known fields, then determine the architecture ' .
                'blueprint fields the caller did not already supply, per the Architecture ' .
                'Blueprint model in 16_AGENTS/AGENT-ARCHITECT.md.',
                $missing,
                ['goal' => $goal, 'known_fields' => $values]
            );

            foreach ($missing as $blueprintKey) {
                $values[$blueprintKey] = $reasoned[$blueprintKey] ?? $this->defaultFor($blueprintKey);
            }
        }

        $blueprint = [
            'goal' => $goal,
            'project_type' => $values['project_type'],
            'components' => $values['components'],
            'dependencies' => $values['dependencies'],
            'risks' => $values['risks'],
            'primary_workflow' => $values['primary_workflow'],
            'supporting_workflows' => $values['supporting_workflows'],
            'output' => $values['output'],
        ];

        return [
            'blueprint' => $blueprint,
            'status' => 'Complete',
            'next_stage' => 'planner',
        ];
    }

    private function defaultFor(string $blueprintKey): mixed
    {
        return match ($blueprintKey) {
            'components', 'dependencies', 'risks', 'supporting_workflows' => [],
            'project_type' => 'Other',
            default => null,
        };
    }
}
