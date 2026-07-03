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
        $developer = $this->requireHistory($context, 'developer');

        if (array_key_exists('issues', $context)) {
            $issues = $context['issues'];
        } else {
            $reasoned = $this->reason(
                'Review the completed implementation against the checklist in ' .
                '16_AGENTS/AGENT-REVIEWER.md (completeness, quality, compliance, risk). ' .
                'List any issues found; return an empty array if none.',
                ['issues'],
                ['implementation' => $developer['implementation'] ?? []]
            );

            $issues = $reasoned['issues'] ?? [];
        }

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
