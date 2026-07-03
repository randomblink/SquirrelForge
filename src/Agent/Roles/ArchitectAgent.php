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

    protected function process(array $context): array
    {
        $goal = $this->requireField($context, 'goal');

        $blueprint = [
            'goal' => $goal,
            'project_type' => $context['project_type'] ?? 'Other',
            'components' => $context['components'] ?? [],
            'dependencies' => $context['dependencies'] ?? [],
            'risks' => $context['risks'] ?? [],
            'primary_workflow' => $context['primary_workflow'] ?? null,
            'supporting_workflows' => $context['supporting_workflows'] ?? [],
            'output' => $context['expected_output'] ?? null,
        ];

        return [
            'blueprint' => $blueprint,
            'status' => 'Complete',
            'next_stage' => 'planner',
        ];
    }
}
