<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

use SquirrelForge\Agent\Roles\Support\FindingsEvaluator;

/**
 * Implements the Agent Security role from `16_AGENTS/AGENT-SECURITY.md`.
 *
 * Any finding marked "critical" fails the stage and blocks release. Non-
 * critical findings produce a "Warning" but still allow the pipeline to
 * proceed, matching the documented Security Outcome table.
 */
final class SecurityAgent extends AbstractRoleAgent
{
    public function stage(): string
    {
        return 'security';
    }

    public function getName(): string
    {
        return 'Agent Security';
    }

    public function getDescription(): string
    {
        return 'Verifies that implementations follow security best practices.';
    }

    protected function process(array $context): array
    {
        $reviewer = $this->requireHistory($context, 'reviewer');

        if (array_key_exists('security_findings', $context)) {
            $findings = $context['security_findings'];
        } else {
            $reasoned = $this->reason(
                'Review the approved implementation for security issues per the checklist ' .
                'in 16_AGENTS/AGENT-SECURITY.md (authentication, input validation, output ' .
                'escaping, WordPress security, data protection). Each finding needs a ' .
                '"severity" (one of: critical, high, medium, low) and a "summary". ' .
                'Return an empty array if you find nothing.',
                ['findings'],
                [
                    'implementation' => $context['history']['developer']['implementation'] ?? [],
                    'review' => $reviewer['review'] ?? [],
                ]
            );

            $findings = $reasoned['findings'] ?? [];
        }

        $status = FindingsEvaluator::evaluate($findings);

        return [
            'security' => [
                'findings' => $findings,
            ],
            'status' => $status,
            'next_stage' => $status !== 'Failed' ? 'performance' : null,
        ];
    }
}
