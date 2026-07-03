<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

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
        $this->requireHistory($context, 'reviewer');

        $findings = $context['security_findings'] ?? [];
        $hasCritical = false;

        foreach ($findings as $finding) {
            if (($finding['severity'] ?? null) === 'critical') {
                $hasCritical = true;
                break;
            }
        }

        $status = match (true) {
            $hasCritical => 'Failed',
            $findings !== [] => 'Warning',
            default => 'Approved',
        };

        return [
            'security' => [
                'findings' => $findings,
            ],
            'status' => $status,
            'next_stage' => $status !== 'Failed' ? 'performance' : null,
        ];
    }
}
