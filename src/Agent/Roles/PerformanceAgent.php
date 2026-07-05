<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

use SquirrelForge\Agent\Roles\Support\FindingsEvaluator;

/**
 * Implements the Agent Performance role from `16_AGENTS/AGENT-PERFORMANCE.md`.
 *
 * Mirrors the Security stage's finding-severity model, with the Performance
 * role's own outcome vocabulary: a critical finding returns
 * `REVISION_REQUIRED` (a performance defect requiring implementation
 * changes) and blocks the handoff; non-critical findings return
 * `OPTIMIZATION_RECOMMENDED` (non-blocking improvement) and still proceed;
 * no findings is `APPROVED`. `APPROVED_WITH_LIMITATIONS` and
 * `VALIDATION_REQUIRED` are not produced by this automatic evaluation --
 * they describe measurement or evidence gaps that a findings list alone
 * cannot establish.
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

        $status = FindingsEvaluator::evaluate($findings, [
            'critical' => 'REVISION_REQUIRED',
            'nonCritical' => 'OPTIMIZATION_RECOMMENDED',
            'clean' => 'APPROVED',
        ]);

        return [
            'performance' => [
                'findings' => $findings,
            ],
            'status' => $status,
            'next_stage' => in_array($status, ['APPROVED', 'APPROVED_WITH_LIMITATIONS', 'OPTIMIZATION_RECOMMENDED'], true)
                ? 'documentation'
                : null,
        ];
    }
}
