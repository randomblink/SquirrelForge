<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

/**
 * Implements the Agent Reviewer role from `16_AGENTS/AGENT-REVIEWER.md`.
 *
 * Verifies completed work against the plan. Any reported issue returns
 * "Revision Required" and stops the handoff; a clean review is "Approved"
 * and proceeds to Security.
 */
final class ReviewerAgent extends AbstractRoleAgent
{
    public function stage(): string
    {
        return 'reviewer';
    }

    public function getName(): string
    {
        return 'Agent Reviewer';
    }

    public function getDescription(): string
    {
        return 'Verifies that completed work meets project standards before release.';
    }

    protected function process(array $context): array
    {
        $this->requireHistory($context, 'developer');

        $issues = $context['issues'] ?? [];
        $status = $issues === [] ? 'Approved' : 'Revision Required';

        return [
            'review' => [
                'issues' => $issues,
            ],
            'status' => $status,
            'next_stage' => $status === 'Approved' ? 'security' : null,
        ];
    }
}
