<?php

declare(strict_types=1);

namespace SquirrelForge\Agent\Roles\Support;

/**
 * The severity-scanning rule `SecurityAgent` and `PerformanceAgent` both
 * need: any "critical" finding is blocking; any other non-empty findings
 * list is non-blocking but must still be disclosed; no findings at all is a
 * clean pass.
 *
 * The two roles use different outcome vocabularies for those same three
 * cases (`16_AGENTS/AGENT-SECURITY.md` vs `16_AGENTS/AGENT-PERFORMANCE.md`),
 * so the specific status string for each case is supplied by the caller
 * rather than hardcoded here.
 */
final class FindingsEvaluator
{
    /**
     * @param array<int, array<string, mixed>> $findings
     * @param array{critical: string, nonCritical: string, clean: string} $outcomes
     *        Status string to return for each case: a critical finding is
     *        present, only non-critical findings are present, or the
     *        findings list is empty.
     */
    public static function evaluate(array $findings, array $outcomes): string
    {
        foreach ($findings as $finding) {
            if (($finding['severity'] ?? null) === 'critical') {
                return $outcomes['critical'];
            }
        }

        return $findings !== [] ? $outcomes['nonCritical'] : $outcomes['clean'];
    }
}
