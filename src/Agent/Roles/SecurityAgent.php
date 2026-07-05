<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

use SquirrelForge\Agent\Roles\Support\FindingsEvaluator;

/**
 * Implements the Agent Security role from `16_AGENTS/AGENT-SECURITY.md`.
 *
 * Any finding marked "critical" returns `REMEDIATION_REQUIRED` and blocks
 * release. Non-critical findings return `APPROVED_WITH_LIMITATIONS` but
 * still allow the pipeline to proceed; no findings is `APPROVED` -- matching
 * the documented Security Outcome table. `BLOCKED` and `REJECTED` are not
 * produced by this automatic evaluation; they require a judgment call
 * (missing context, unacceptable posture) that findings severity alone
 * cannot establish.
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

        $status = FindingsEvaluator::evaluate($findings, [
            'critical' => 'REMEDIATION_REQUIRED',
            'nonCritical' => 'APPROVED_WITH_LIMITATIONS',
            'clean' => 'APPROVED',
        ]);

        return [
            'security' => [
                'findings' => $findings,
            ],
            'status' => $status,
            'next_stage' => in_array($status, ['APPROVED', 'APPROVED_WITH_LIMITATIONS'], true) ? 'performance' : null,
        ];
    }
}
