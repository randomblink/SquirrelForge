<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

use SquirrelForge\Agent\Roles\Support\FindingsEvaluator;

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
        $security = $this->requireHistory($context, 'security');

        if (array_key_exists('performance_findings', $context)) {
            $findings = $context['performance_findings'];
        } else {
            $reasoned = $this->reason(
                'Review the approved implementation for performance issues per the ' .
                'checklist in 16_AGENTS/AGENT-PERFORMANCE.md (execution, database, assets, ' .
                'memory, scalability). Each finding needs a "severity" (one of: critical, ' .
                'high, medium, low) and a "summary". Return an empty array if you find nothing.',
                ['findings'],
                [
                    'implementation' => $context['history']['developer']['implementation'] ?? [],
                    'security' => $security['security'] ?? [],
                ]
            );

            $findings = $reasoned['findings'] ?? [];
        }

        $status = FindingsEvaluator::evaluate($findings);

        return [
            'performance' => [
                'findings' => $findings,
            ],
            'status' => $status,
            'next_stage' => $status !== 'Failed' ? 'documentation' : null,
        ];
    }
}
