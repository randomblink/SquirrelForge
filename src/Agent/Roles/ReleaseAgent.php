<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles;

/**
 * Implements the Agent Release role from `16_AGENTS/AGENT-RELEASE.md`.
 *
 * The terminal stage of the pipeline. Verifies every prior quality gate
 * (review, security, performance, documentation) actually passed before
 * marking the release "Ready"; otherwise the release is put on "Hold" and
 * the outstanding gates are reported.
 */
final class ReleaseAgent extends AbstractRoleAgent
{
    public function stage(): string
    {
        return 'release';
    }

    public function getName(): string
    {
        return 'Agent Release';
    }

    public function getDescription(): string
    {
        return 'Prepares validated work for production and confirms release readiness.';
    }

    protected function process(array $context): array
    {
        $review = $this->requireHistory($context, 'reviewer');
        $security = $this->requireHistory($context, 'security');
        $performance = $this->requireHistory($context, 'performance');
        $documentation = $this->requireHistory($context, 'documentation');

        $gates = [
            'review' => ($review['status'] ?? null) === 'Approved',
            'security' => in_array($security['status'] ?? null, ['Approved', 'Warning'], true),
            'performance' => in_array($performance['status'] ?? null, ['Approved', 'Warning'], true),
            'documentation' => ($documentation['status'] ?? null) === 'Complete',
        ];

        $outstanding = array_keys(array_filter($gates, static fn (bool $passed): bool => !$passed));

        $status = $outstanding === [] ? 'Ready' : 'Hold';

        return [
            'release' => [
                'gates' => $gates,
                'outstanding' => $outstanding,
            ],
            'status' => $status,
            'next_stage' => null,
        ];
    }
}
