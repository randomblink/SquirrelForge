<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

/**
 * Implements the Agent Reviewer role from `16_AGENTS/AGENT-REVIEWER.md`.
 *
 * Verifies completed work against the plan. No issues is `APPROVED`. Each
 * reported issue may optionally carry a "severity" (critical, high, medium,
 * low) and a "requires_specialist" flag; an issue without a "severity" is
 * treated conservatively, the same as a critical/high one, since the
 * absence of evidence that it's minor is not evidence that it's safe to
 * proceed. Any issue flagged "requires_specialist" returns
 * `SPECIALIST_REVIEW_REQUIRED` (which still hands off to Security next --
 * the specialist review the flag calls for). Otherwise, a critical/high or
 * severity-less issue returns `REVISION_REQUIRED` and stops the handoff;
 * only low/medium-severity issues return `APPROVED_WITH_LIMITATIONS` and
 * still proceed. `BLOCKED` and `REJECTED` are not produced automatically.
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
                'List any issues found; return an empty array if none. Each issue object ' .
                'may include "severity" (one of: critical, high, medium, low) and ' .
                '"requires_specialist" (boolean, for security/performance/accessibility/' .
                'governance escalation).',
                ['issues'],
                ['implementation' => $developer['implementation'] ?? []]
            );

            $issues = $reasoned['issues'] ?? [];
        }

        $status = self::evaluateIssues($issues);

        return [
            'review' => [
                'issues' => $issues,
            ],
            'status' => $status,
            'next_stage' => in_array($status, ['APPROVED', 'APPROVED_WITH_LIMITATIONS', 'SPECIALIST_REVIEW_REQUIRED'], true)
                ? 'security'
                : null,
        ];
    }

    /**
     * @param array<int, mixed> $issues
     */
    private static function evaluateIssues(array $issues): string
    {
        if ($issues === []) {
            return 'APPROVED';
        }

        $requiresSpecialist = false;
        $hasBlockingIssue = false;

        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                // A plain-string issue carries no severity evidence; treat
                // it the same as an unknown/critical severity rather than
                // assume it's minor.
                $hasBlockingIssue = true;

                continue;
            }

            if (($issue['requires_specialist'] ?? false) === true) {
                $requiresSpecialist = true;
            }

            if (!in_array($issue['severity'] ?? null, ['low', 'medium'], true)) {
                $hasBlockingIssue = true;
            }
        }

        return match (true) {
            $requiresSpecialist => 'SPECIALIST_REVIEW_REQUIRED',
            $hasBlockingIssue => 'REVISION_REQUIRED',
            default => 'APPROVED_WITH_LIMITATIONS',
        };
    }
}
