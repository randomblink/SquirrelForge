<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Testing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Testing\SqliteRegressionTests;
use SquirrelForge\Testing\SqliteTestPlanner;

final class SqliteRegressionTestsTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-regression-tests-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function regressionTests(?SqliteTestPlanner $planner = null): SqliteRegressionTests
    {
        return new SqliteRegressionTests($this->tempPath('db'), $planner);
    }

    private function testPlanner(): SqliteTestPlanner
    {
        return new SqliteTestPlanner($this->tempPath('planner'));
    }

    // --- required fields ---

    public function testMissingSubjectRefIsInvalid(): void
    {
        $regressionTests = $this->regressionTests();

        $result = $regressionTests->run([]);

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- plan cross-check ---

    public function testUnknownPlanIdIsInvalid(): void
    {
        $planner = $this->testPlanner();
        $regressionTests = $this->regressionTests($planner);

        $result = $regressionTests->run(['subject_ref' => 'feature_x', 'plan_id' => 'ghost']);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testPlanWithoutRegressionCoverageIsRejected(): void
    {
        $planner = $this->testPlanner();
        $planned = $planner->plan(['subject_ref' => 'feature_x', 'acceptance_criteria' => ['x']]);
        $regressionTests = $this->regressionTests($planner);

        $result = $regressionTests->run(['subject_ref' => 'feature_x', 'plan_id' => $planned['plan_id']]);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testPlanWithRegressionCoverageIsAccepted(): void
    {
        $planner = $this->testPlanner();
        $planned = $planner->plan(['subject_ref' => 'feature_x', 'acceptance_criteria' => ['x'], 'is_change' => true]);
        $regressionTests = $this->regressionTests($planner);

        $result = $regressionTests->run(['subject_ref' => 'feature_x', 'plan_id' => $planned['plan_id']]);

        $this->assertNotSame('rejected', $result['outcome']);
    }

    // --- dry run ---

    public function testWithoutARunSuiteClosureIsPending(): void
    {
        $regressionTests = $this->regressionTests();

        $result = $regressionTests->run(['subject_ref' => 'feature_x']);

        $this->assertSame('Pending', $result['status']);
    }

    // --- classification ---

    public function testAllPassingIsPassed(): void
    {
        $regressionTests = $this->regressionTests();

        $result = $regressionTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 5, 'failed' => 0]);

        $this->assertSame('Passed', $result['status']);
    }

    public function testZeroExecutedIsFailed(): void
    {
        $regressionTests = $this->regressionTests();

        $result = $regressionTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 0, 'failed' => 0]);

        $this->assertSame('Failed', $result['status']);
    }

    public function testSuiteThrowingIsFailedNotAnUncaughtException(): void
    {
        $regressionTests = $this->regressionTests();

        $result = $regressionTests->run(['subject_ref' => 'feature_x'], runSuite: static function (?array $plan): array {
            throw new RuntimeException('baseline fixture missing');
        });

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('baseline fixture missing', $result['failures'][0]['message']);
    }

    // --- baseline comparison: real, self-referential composition ---

    public function testFirstRunHasNoBaselineAndNoComparisonNoise(): void
    {
        $regressionTests = $this->regressionTests();

        $result = $regressionTests->run(
            ['subject_ref' => 'feature_x'],
            runSuite: static fn(?array $plan): array => ['passed' => 2, 'failed' => 1, 'failures' => [['name' => 'test_a', 'message' => 'boom']]]
        );

        $this->assertNull($result['baseline_result_id']);
        $this->assertSame([], $result['new_failures']);
        $this->assertSame([], $result['persistent_failures']);
        $this->assertSame([], $result['newly_fixed']);
    }

    public function testSecondRunUsesFirstAsBaseline(): void
    {
        $regressionTests = $this->regressionTests();
        $first = $regressionTests->run(
            ['subject_ref' => 'feature_x'],
            runSuite: static fn(?array $plan): array => ['passed' => 2, 'failed' => 1, 'failures' => [['name' => 'test_a', 'message' => 'boom']]]
        );

        $result = $regressionTests->run(
            ['subject_ref' => 'feature_x'],
            runSuite: static fn(?array $plan): array => ['passed' => 2, 'failed' => 1, 'failures' => [['name' => 'test_a', 'message' => 'still boom']]]
        );

        $this->assertSame($first['result_id'], $result['baseline_result_id']);
    }

    public function testNewFailureNotInBaselineIsAGenuineRegression(): void
    {
        $regressionTests = $this->regressionTests();
        $regressionTests->run(
            ['subject_ref' => 'feature_x'],
            runSuite: static fn(?array $plan): array => ['passed' => 3, 'failed' => 0]
        );

        $result = $regressionTests->run(
            ['subject_ref' => 'feature_x'],
            runSuite: static fn(?array $plan): array => ['passed' => 2, 'failed' => 1, 'failures' => [['name' => 'test_b', 'message' => 'newly broken']]]
        );

        $this->assertSame(['test_b'], $result['new_failures']);
        $this->assertSame([], $result['persistent_failures']);
    }

    public function testFailureInBothRunsIsPersistentNotNew(): void
    {
        $regressionTests = $this->regressionTests();
        $regressionTests->run(
            ['subject_ref' => 'feature_x'],
            runSuite: static fn(?array $plan): array => ['passed' => 2, 'failed' => 1, 'failures' => [['name' => 'test_a', 'message' => 'known issue']]]
        );

        $result = $regressionTests->run(
            ['subject_ref' => 'feature_x'],
            runSuite: static fn(?array $plan): array => ['passed' => 2, 'failed' => 1, 'failures' => [['name' => 'test_a', 'message' => 'still there']]]
        );

        $this->assertSame(['test_a'], $result['persistent_failures']);
        $this->assertSame([], $result['new_failures']);
    }

    public function testFailureInBaselineButNotNowIsNewlyFixed(): void
    {
        $regressionTests = $this->regressionTests();
        $regressionTests->run(
            ['subject_ref' => 'feature_x'],
            runSuite: static fn(?array $plan): array => ['passed' => 2, 'failed' => 1, 'failures' => [['name' => 'test_a', 'message' => 'was broken']]]
        );

        $result = $regressionTests->run(
            ['subject_ref' => 'feature_x'],
            runSuite: static fn(?array $plan): array => ['passed' => 3, 'failed' => 0]
        );

        $this->assertSame(['test_a'], $result['newly_fixed']);
    }

    public function testBaselineUsesTheMostRecentPriorRunNotTheFirstEver(): void
    {
        $regressionTests = $this->regressionTests();
        $regressionTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 1, 'failures' => [['name' => 'test_a', 'message' => 'x']]]);
        $second = $regressionTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 2, 'failed' => 0]);

        $result = $regressionTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 1, 'failures' => [['name' => 'test_a', 'message' => 'x']]]);

        // test_a is a "new" failure relative to run #2 (which had none), not "persistent"
        // relative to run #1 -- confirms the baseline is the immediately preceding run.
        $this->assertSame($second['result_id'], $result['baseline_result_id']);
        $this->assertSame(['test_a'], $result['new_failures']);
    }

    // --- evidence carried forward ---

    public function testDefectHistoryAndChangeImpactAreCarriedForward(): void
    {
        $regressionTests = $this->regressionTests();

        $result = $regressionTests->run([
            'subject_ref' => 'feature_x',
            'defect_history' => ['BUG-101'],
            'change_impact' => ['ExportController'],
        ]);
        $record = $regressionTests->get($result['result_id']);

        $this->assertSame(['BUG-101'], $record['defect_history']);
        $this->assertSame(['ExportController'], $record['change_impact']);
    }

    // --- get() / history() ---

    public function testGetUnknownResultReturnsNull(): void
    {
        $regressionTests = $this->regressionTests();

        $this->assertNull($regressionTests->get('ghost'));
    }

    public function testHistoryReturnsEveryRunForASubjectInOrder(): void
    {
        $regressionTests = $this->regressionTests();
        $regressionTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0]);
        $regressionTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 0, 'failed' => 1]);

        $history = $regressionTests->history('feature_x');

        $this->assertCount(2, $history);
        $this->assertSame('Passed', $history[0]['status']);
        $this->assertSame('Failed', $history[1]['status']);
    }
}
