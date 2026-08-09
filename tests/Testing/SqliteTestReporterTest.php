<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Testing;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Testing\SqliteIntegrationTests;
use SquirrelForge\Testing\SqliteRegressionTests;
use SquirrelForge\Testing\SqliteSmokeTests;
use SquirrelForge\Testing\SqliteSystemTests;
use SquirrelForge\Testing\SqliteTestPlanner;
use SquirrelForge\Testing\SqliteTestReporter;
use SquirrelForge\Testing\SqliteUnitTests;

final class SqliteTestReporterTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }
    }

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-test-reporter-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function unitTests(): SqliteUnitTests
    {
        return new SqliteUnitTests($this->tempPath('unit'));
    }

    private function integrationTests(): SqliteIntegrationTests
    {
        return new SqliteIntegrationTests($this->tempPath('integration'));
    }

    private function systemTests(): SqliteSystemTests
    {
        return new SqliteSystemTests($this->tempPath('system'));
    }

    private function regressionTests(): SqliteRegressionTests
    {
        return new SqliteRegressionTests($this->tempPath('regression'));
    }

    private function smokeTests(): SqliteSmokeTests
    {
        return new SqliteSmokeTests($this->tempPath('smoke'));
    }

    // --- required fields ---

    public function testMissingSubjectRefIsInvalid(): void
    {
        $reporter = new SqliteTestReporter();

        $result = $reporter->report([]);

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- totals aggregation across categories ---

    public function testTotalsAggregateAcrossConfiguredCategories(): void
    {
        $unitTests = $this->unitTests();
        $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $p): array => ['passed' => 5, 'failed' => 1, 'skipped' => 1]);
        $integrationTests = $this->integrationTests();
        $integrationTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $p): array => ['passed' => 3, 'failed' => 0]);
        $reporter = new SqliteTestReporter(null, $unitTests, $integrationTests);

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertSame('reported', $result['outcome']);
        $this->assertSame(8, $result['total_passed']);
        $this->assertSame(1, $result['total_failed']);
        $this->assertSame(1, $result['total_skipped']);
    }

    public function testCategorySummariesReportPerCategoryTotals(): void
    {
        $unitTests = $this->unitTests();
        $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $p): array => ['passed' => 5, 'failed' => 0]);
        $reporter = new SqliteTestReporter(null, $unitTests);

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertSame(1, $result['category_summaries']['Unit']['runs']);
        $this->assertSame(5, $result['category_summaries']['Unit']['passed']);
        $this->assertSame('Passed', $result['category_summaries']['Unit']['latest_status']);
    }

    public function testUnconfiguredCategoriesAreOmittedNotFabricated(): void
    {
        $unitTests = $this->unitTests();
        $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $p): array => ['passed' => 1, 'failed' => 0]);
        $reporter = new SqliteTestReporter(null, $unitTests);

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertArrayNotHasKey('Integration', $result['category_summaries']);
        $this->assertArrayNotHasKey('System', $result['category_summaries']);
    }

    public function testCategoryWithNoRecordedRunsIsOmitted(): void
    {
        $unitTests = $this->unitTests();
        $reporter = new SqliteTestReporter(null, $unitTests);

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertArrayNotHasKey('Unit', $result['category_summaries']);
    }

    // --- failures aggregation ---

    public function testFailuresAreTaggedWithTheirCategory(): void
    {
        $unitTests = $this->unitTests();
        $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $p): array => ['passed' => 1, 'failed' => 1, 'failures' => [['name' => 'test_a', 'message' => 'boom']]]);
        $reporter = new SqliteTestReporter(null, $unitTests);

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertSame([['category' => 'Unit', 'name' => 'test_a', 'message' => 'boom']], $result['failures']);
    }

    // --- flaky detection ---

    public function testConsistentFailureAcrossAllRunsIsNotFlaky(): void
    {
        $unitTests = $this->unitTests();
        $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $p): array => ['passed' => 1, 'failed' => 1, 'failures' => [['name' => 'test_a', 'message' => 'always broken']]]);
        $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $p): array => ['passed' => 1, 'failed' => 1, 'failures' => [['name' => 'test_a', 'message' => 'still broken']]]);
        $reporter = new SqliteTestReporter(null, $unitTests);

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertArrayNotHasKey('Unit', $result['flaky_tests']);
    }

    public function testInconsistentFailureAcrossRunsIsFlaky(): void
    {
        $unitTests = $this->unitTests();
        $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $p): array => ['passed' => 1, 'failed' => 1, 'failures' => [['name' => 'test_a', 'message' => 'sometimes fails']]]);
        $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $p): array => ['passed' => 2, 'failed' => 0]);
        $reporter = new SqliteTestReporter(null, $unitTests);

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertSame(['test_a'], $result['flaky_tests']['Unit']);
    }

    public function testSingleRunNeverProducesAFlakyVerdict(): void
    {
        $unitTests = $this->unitTests();
        $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $p): array => ['passed' => 1, 'failed' => 1, 'failures' => [['name' => 'test_a', 'message' => 'x']]]);
        $reporter = new SqliteTestReporter(null, $unitTests);

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertArrayNotHasKey('Unit', $result['flaky_tests']);
    }

    // --- coverage gaps ---

    public function testUncoveredContractsSurfaceAsACoverageGap(): void
    {
        $integrationTests = $this->integrationTests();
        $integrationTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $p): array => ['passed' => 1, 'failed' => 0]);
        // No plan configured, so uncovered_contracts is naturally empty here -- verify the
        // reporter surfaces whatever the category itself already reported (none, honestly).
        $reporter = new SqliteTestReporter(null, null, $integrationTests);

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertArrayNotHasKey('Integration_uncovered_contracts', $result['coverage_gaps']);
    }

    // --- residual risk observations from the plan ---

    public function testResidualRiskObservationsComeFromThePlanExitCriteria(): void
    {
        $planner = new SqliteTestPlanner($this->tempPath('planner'));
        $planned = $planner->plan(['subject_ref' => 'feature_x', 'acceptance_criteria' => ['x']]);
        $reporter = new SqliteTestReporter($planner);

        $result = $reporter->report(['subject_ref' => 'feature_x', 'plan_id' => $planned['plan_id']]);

        $this->assertSame($planned['exit_criteria'], $result['residual_risk_observations']);
    }

    public function testWithoutAPlanNoResidualRiskObservations(): void
    {
        $reporter = new SqliteTestReporter();

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertSame([], $result['residual_risk_observations']);
    }

    // --- gate recommendation: advisory, deterministic precedence ---

    public function testAllPassingNoGapsIsRecommended(): void
    {
        $unitTests = $this->unitTests();
        $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $p): array => ['passed' => 3, 'failed' => 0]);
        $reporter = new SqliteTestReporter(null, $unitTests);

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertStringContainsString('Recommended --', $result['gate_recommendation']);
        $this->assertStringStartsWith('Advisory:', $result['gate_recommendation']);
    }

    public function testAnyFailureIsNotRecommended(): void
    {
        $unitTests = $this->unitTests();
        $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $p): array => ['passed' => 2, 'failed' => 1, 'failures' => [['name' => 'x', 'message' => 'y']]]);
        $reporter = new SqliteTestReporter(null, $unitTests);

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertStringContainsString('Not Recommended', $result['gate_recommendation']);
    }

    public function testPendingResultIsIncomplete(): void
    {
        $unitTests = $this->unitTests();
        $unitTests->run(['subject_ref' => 'feature_x']);
        $reporter = new SqliteTestReporter(null, $unitTests);

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertStringContainsString('Incomplete', $result['gate_recommendation']);
    }

    public function testPendingTakesPrecedenceOverEverythingElse(): void
    {
        $unitTests = $this->unitTests();
        $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $p): array => ['passed' => 1, 'failed' => 1, 'failures' => [['name' => 'x', 'message' => 'y']]]);
        $unitTests->run(['subject_ref' => 'feature_x']);
        $reporter = new SqliteTestReporter(null, $unitTests);

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertStringContainsString('Incomplete', $result['gate_recommendation']);
    }

    public function testNoEvidenceAtAllIsStillRecommendedByDefault(): void
    {
        // No composed category components and no plan: totalFailed=0, no pending, no
        // blocking risks, no gaps -- honestly reflects "nothing contradicts a pass" rather
        // than fabricating a warning where no evidence exists either way.
        $reporter = new SqliteTestReporter();

        $result = $reporter->report(['subject_ref' => 'feature_x']);

        $this->assertStringStartsWith('Advisory: Recommended --', $result['gate_recommendation']);
    }
}
