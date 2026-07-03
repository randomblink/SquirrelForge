<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles\Support;

/**
 * The severity-outcome rule `SecurityAgent` and `PerformanceAgent` both
 * implemented identically: any "critical" finding fails the stage; any
 * other non-empty findings list warns but still lets the pipeline proceed;
 * no findings at all is a clean approval.
 */
final class FindingsEvaluator
{
    /**
     * @param array<int, array<string, mixed>> $findings
     */
    public static function evaluate(array $findings): string
    {
        foreach ($findings as $finding) {
            if (($finding['severity'] ?? null) === 'critical') {
                return 'Failed';
            }
        }

        return $findings !== [] ? 'Warning' : 'Approved';
    }
}
