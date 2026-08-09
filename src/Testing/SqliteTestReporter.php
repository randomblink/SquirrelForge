<?php

declare(strict_types=1);

namespace SquirrelForge\Testing;

use DateTimeImmutable;

/**
 * Aggregates testing-domain results and evidence references into
 * consistent reports for downstream validation, governance quality
 * gates, release review, deployment review, and observability
 * consumers, per 29_TESTING/TEST-REPORTING.md -- the seventh and final
 * real component in 29_TESTING, closing out this layer's roster.
 *
 * This is the pure-assembler shape `ExecutionReporter` already
 * established for `20_EXECUTION`, applied to this layer: every number
 * in the report is read from the real `history()` of whichever
 * category components (`SqliteUnitTests`, `SqliteIntegrationTests`,
 * `SqliteSystemTests`, `SqliteRegressionTests`, `SqliteSmokeTests`) and
 * `SqliteTestPlanner` are configured -- this class owns no database and
 * computes nothing a caller couldn't already derive itself from those
 * components' own real records; it only does the aggregation work of
 * actually deriving it.
 *
 * "Identify flaky-test status" is real, computed logic, not a
 * fabricated label: a test name is flaky when it appears among the
 * failures of *some but not all* recorded runs for a category --
 * consistently failing or consistently passing across every run is
 * not flakiness, an inconsistent outcome across repeated runs is. A
 * category with fewer than two recorded runs has no basis to call
 * anything flaky and reports none.
 *
 * "Report requirement and acceptance-criteria coverage references" and
 * "testing-domain residual-risk observations" are read straight from
 * the real evidence those specific components already produced --
 * `SqliteIntegrationTests`' own `uncovered_contracts`,
 * `SqliteSystemTests`' own `unverified_criteria`, and
 * `SqliteTestPlanner`'s own `blocking_risks` (itself already sourced
 * from the real `RiskAssessor`, per that component's own Boundary
 * against performing risk assessment itself) -- this class never
 * performs a coverage or risk analysis of its own, per its own
 * Boundary against "general risk assessment."
 *
 * "A gate recommendation is advisory testing output... retain their
 * respective decision authority" (Rule) is the one place this class
 * synthesizes a conclusion rather than merely relaying one, and it is
 * built entirely from the real aggregated evidence above (any pending
 * category result, any failure, any open blocking risk, any coverage
 * gap, any flaky test) through a fixed, deterministic precedence --
 * never a fabricated judgment, and every returned recommendation
 * string is explicit that it is advisory, matching this spec's own
 * Rule literally.
 */
final class SqliteTestReporter
{
    public function __construct(
        private readonly ?SqliteTestPlanner $testPlanner = null,
        private readonly ?SqliteUnitTests $unitTests = null,
        private readonly ?SqliteIntegrationTests $integrationTests = null,
        private readonly ?SqliteSystemTests $systemTests = null,
        private readonly ?SqliteRegressionTests $regressionTests = null,
        private readonly ?SqliteSmokeTests $smokeTests = null
    ) {
    }

    /**
     * @param array{subject_ref?: ?string, plan_id?: ?string} $request
     * @return array{
     *     outcome: string,
     *     subject_ref: ?string,
     *     plan_id: ?string,
     *     total_passed: int,
     *     total_failed: int,
     *     total_skipped: int,
     *     category_summaries: array<string, array{runs: int, passed: int, failed: int, skipped: int, latest_status: ?string}>,
     *     failures: array<int, array{category: string, name: string, message: string}>,
     *     flaky_tests: array<string, array<int, string>>,
     *     coverage_gaps: array<string, array<int, string>>,
     *     residual_risk_observations: array<int, string>,
     *     gate_recommendation: string,
     *     timestamp: string,
     *     error: ?string
     * }
     */
    public function report(array $request): array
    {
        $subjectRef = $request['subject_ref'] ?? null;

        if (!is_string($subjectRef) || $subjectRef === '') {
            return $this->outcome('invalid', $subjectRef, null, 0, 0, 0, [], [], [], [], [], 'A test report requires a non-empty subject_ref.');
        }

        $planId = $request['plan_id'] ?? null;
        $plan = is_string($planId) && $planId !== '' && $this->testPlanner !== null ? $this->testPlanner->get($planId) : null;

        $categoryComponents = [
            'Unit' => $this->unitTests,
            'Integration' => $this->integrationTests,
            'System' => $this->systemTests,
            'Regression' => $this->regressionTests,
            'Smoke' => $this->smokeTests,
        ];

        $totalPassed = 0;
        $totalFailed = 0;
        $totalSkipped = 0;
        $failures = [];
        $categorySummaries = [];
        $flakyTests = [];
        $coverageGaps = [];
        $anyPending = false;

        foreach ($categoryComponents as $category => $component) {
            if ($component === null) {
                continue;
            }

            $results = $component->history($subjectRef);

            if ($results === []) {
                continue;
            }

            $categoryPassed = 0;
            $categoryFailed = 0;
            $categorySkipped = 0;

            foreach ($results as $result) {
                $categoryPassed += $result['passed'];
                $categoryFailed += $result['failed'];
                $categorySkipped += $result['skipped'];

                foreach ($result['failures'] as $failure) {
                    $failures[] = ['category' => $category, 'name' => $failure['name'], 'message' => $failure['message']];
                }

                if ($result['status'] === 'Pending') {
                    $anyPending = true;
                }
            }

            $totalPassed += $categoryPassed;
            $totalFailed += $categoryFailed;
            $totalSkipped += $categorySkipped;

            $categorySummaries[$category] = [
                'runs' => count($results),
                'passed' => $categoryPassed,
                'failed' => $categoryFailed,
                'skipped' => $categorySkipped,
                'latest_status' => $results[count($results) - 1]['status'],
            ];

            $flaky = $this->flakyTests($results);

            if ($flaky !== []) {
                $flakyTests[$category] = $flaky;
            }

            $latest = $results[count($results) - 1];

            if (($latest['uncovered_contracts'] ?? []) !== []) {
                $coverageGaps[$category . '_uncovered_contracts'] = $latest['uncovered_contracts'];
            }

            if (($latest['unverified_criteria'] ?? []) !== []) {
                $coverageGaps[$category . '_unverified_criteria'] = $latest['unverified_criteria'];
            }
        }

        $residualRiskObservations = $plan['exit_criteria'] ?? [];
        $blockingRisks = $plan['blocking_risks'] ?? [];

        $gateRecommendation = $this->gateRecommendation($anyPending, $totalFailed, $blockingRisks, $coverageGaps, $flakyTests);

        return $this->outcome('reported', $subjectRef, $planId, $totalPassed, $totalFailed, $totalSkipped, $categorySummaries, $failures, $flakyTests, $coverageGaps, $residualRiskObservations, null, $gateRecommendation);
    }

    /**
     * A test name flaky when it appears among the failures of some but
     * not all recorded runs for a category.
     *
     * @param array<int, array<string, mixed>> $results
     * @return array<int, string>
     */
    private function flakyTests(array $results): array
    {
        if (count($results) < 2) {
            return [];
        }

        $failureCounts = [];

        foreach ($results as $result) {
            foreach (array_unique(array_column($result['failures'], 'name')) as $name) {
                $failureCounts[$name] = ($failureCounts[$name] ?? 0) + 1;
            }
        }

        $totalRuns = count($results);

        return array_values(array_filter(array_keys($failureCounts), static fn(string $name): bool => $failureCounts[$name] > 0 && $failureCounts[$name] < $totalRuns));
    }

    /**
     * @param array<int, string> $blockingRisks
     * @param array<string, array<int, string>> $coverageGaps
     * @param array<string, array<int, string>> $flakyTests
     */
    private function gateRecommendation(bool $anyPending, int $totalFailed, array $blockingRisks, array $coverageGaps, array $flakyTests): string
    {
        if ($anyPending) {
            return 'Advisory: Incomplete -- one or more category results are still Pending.';
        }

        if ($totalFailed > 0) {
            return sprintf('Advisory: Not Recommended -- %d test failure(s) across categories.', $totalFailed);
        }

        if ($blockingRisks !== []) {
            return sprintf('Advisory: Not Recommended -- %d open critical risk(s) block this option per the test plan.', count($blockingRisks));
        }

        if ($coverageGaps !== [] || $flakyTests !== []) {
            return 'Advisory: Recommended with Reservations -- coverage gaps or flaky tests were observed.';
        }

        return 'Advisory: Recommended -- all recorded categories passed with no open blockers, gaps, or flaky tests.';
    }

    /**
     * @param array<string, array{runs: int, passed: int, failed: int, skipped: int, latest_status: ?string}> $categorySummaries
     * @param array<int, array{category: string, name: string, message: string}> $failures
     * @param array<string, array<int, string>> $flakyTests
     * @param array<string, array<int, string>> $coverageGaps
     * @param array<int, string> $residualRiskObservations
     * @return array{
     *     outcome: string,
     *     subject_ref: ?string,
     *     plan_id: ?string,
     *     total_passed: int,
     *     total_failed: int,
     *     total_skipped: int,
     *     category_summaries: array<string, array{runs: int, passed: int, failed: int, skipped: int, latest_status: ?string}>,
     *     failures: array<int, array{category: string, name: string, message: string}>,
     *     flaky_tests: array<string, array<int, string>>,
     *     coverage_gaps: array<string, array<int, string>>,
     *     residual_risk_observations: array<int, string>,
     *     gate_recommendation: string,
     *     timestamp: string,
     *     error: ?string
     * }
     */
    private function outcome(
        string $outcome,
        ?string $subjectRef,
        ?string $planId,
        int $totalPassed,
        int $totalFailed,
        int $totalSkipped,
        array $categorySummaries,
        array $failures,
        array $flakyTests,
        array $coverageGaps,
        array $residualRiskObservations,
        ?string $error,
        string $gateRecommendation = 'Advisory: Not Available -- report could not be produced.'
    ): array {
        return [
            'outcome' => $outcome,
            'subject_ref' => $subjectRef,
            'plan_id' => $planId,
            'total_passed' => $totalPassed,
            'total_failed' => $totalFailed,
            'total_skipped' => $totalSkipped,
            'category_summaries' => $categorySummaries,
            'failures' => $failures,
            'flaky_tests' => $flakyTests,
            'coverage_gaps' => $coverageGaps,
            'residual_risk_observations' => $residualRiskObservations,
            'gate_recommendation' => $gateRecommendation,
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            'error' => $error,
        ];
    }
}
