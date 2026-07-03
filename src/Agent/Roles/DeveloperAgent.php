<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

/**
 * Implements the Agent Developer role from `16_AGENTS/AGENT-DEVELOPER.md`.
 *
 * Records the implementation status of each planned task. The overall stage
 * is only "Complete" (and only then does it hand off to the Reviewer) once
 * every completed task reports "Complete"; any "Blocked" task blocks the
 * whole pipeline rather than silently proceeding.
 */
final class DeveloperAgent extends AbstractRoleAgent
{
    public function stage(): string
    {
        return 'developer';
    }

    public function getName(): string
    {
        return 'Agent Developer';
    }

    public function getDescription(): string
    {
        return 'Implements the execution plan by producing validated deliverables.';
    }

    protected function process(array $context): array
    {
        $this->requireHistory($context, 'planner');

        $tasks = $context['tasks_completed'] ?? [];

        if ($tasks === []) {
            throw new \InvalidArgumentException(
                'DeveloperAgent requires context field "tasks_completed".'
            );
        }

        $hasBlocked = false;
        $allComplete = true;

        foreach ($tasks as $task) {
            $status = $task['status'] ?? 'Pending';

            if ($status === 'Blocked') {
                $hasBlocked = true;
            }

            if ($status !== 'Complete') {
                $allComplete = false;
            }
        }

        $status = match (true) {
            $hasBlocked => 'Blocked',
            $allComplete => 'Complete',
            default => 'In Progress',
        };

        return [
            'implementation' => [
                'tasks' => $tasks,
            ],
            'status' => $status,
            'next_stage' => $status === 'Complete' ? 'reviewer' : null,
        ];
    }
}
