<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

/**
 * Implements the Agent Performance role from `16_AGENTS/AGENT-PERFORMANCE.md`.
 *
 * Mirrors the Security stage's outcome model: critical findings fail the
 * stage, non-critical findings warn but still proceed.
 */
final class PerformanceAgent extends AbstractRoleAgent
{
    public function stage(): string
    {
        return 'performance';
    }

    public function getName(): string
    {
        return 'Agent Performance';
    }

    public function getDescription(): string
    {
        return 'Evaluates implementations for efficiency, scalability, and resource usage.';
    }

    protected function process(array $context): array
    {
        $this->requireHistory($context, 'security');

        $findings = $context['performance_findings'] ?? [];
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
            'performance' => [
                'findings' => $findings,
            ],
            'status' => $status,
            'next_stage' => $status !== 'Failed' ? 'documentation' : null,
        ];
    }
}
