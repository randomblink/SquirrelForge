<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Testing;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Testing\SqliteTestPlanner;
use SquirrelForge\Testing\SqliteUnitTests;

final class SqliteUnitTestsTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-unit-tests-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function unitTests(?SqliteTestPlanner $planner = null): SqliteUnitTests
    {
        return new SqliteUnitTests($this->tempPath('db'), $planner);
    }

    private function testPlanner(?string $path = null): SqliteTestPlanner
    {
        return new SqliteTestPlanner($path ?? $this->tempPath('planner'));
    }

    /**
     * Directly inserts a plan row lacking Unit coverage into the given
     * planner database -- SqliteTestPlanner::plan() itself always
     * includes Unit, so this simulates a hand-authored or externally
     * produced plan record to exercise SqliteUnitTests' own real
     * rejection check.
     */
    private function insertPlanWithoutUnitCoverage(string $path, string $planId, string $subjectRef): void
    {
        $database = new PDO('sqlite:' . $path);
        $statement = $database->prepare(
            'INSERT INTO test_plans (plan_id, subject_ref, acceptance_criteria_json, interface_contracts_json, categories_json, risk_driven_coverage_json, entry_criteria_json, exit_criteria_json, blocking_risks_json, created_at)
             VALUES (:plan_id, :subject_ref, :ac, :ic, :categories, :rdc, :entry, :exit, :blocking, :created_at)'
        );
        $statement->execute([
            'plan_id' => $planId,
            'subject_ref' => $subjectRef,
            'ac' => '["x"]',
            'ic' => '[]',
            'categories' => '["Integration"]',
            'rdc' => '[]',
            'entry' => '[]',
            'exit' => '[]',
            'blocking' => '[]',
            'created_at' => gmdate(DATE_ATOM),
        ]);
    }

    // --- required fields ---

    public function testMissingSubjectRefIsInvalid(): void
    {
        $unitTests = $this->unitTests();

        $result = $unitTests->run([]);

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- plan cross-check ---

    public function testUnknownPlanIdIsInvalid(): void
    {
        $planner = $this->testPlanner();
        $unitTests = $this->unitTests($planner);

        $result = $unitTests->run(['subject_ref' => 'feature_x', 'plan_id' => 'ghost']);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testPlanWithoutUnitCoverageIsRejected(): void
    {
        $plannerPath = $this->tempPath('planner');
        $planner = $this->testPlanner($plannerPath);
        $this->insertPlanWithoutUnitCoverage($plannerPath, 'plan_no_unit', 'feature_x');
        $unitTests = $this->unitTests($planner);

        $result = $unitTests->run(['subject_ref' => 'feature_x', 'plan_id' => 'plan_no_unit']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('does not require Unit coverage', $result['error']);
    }

    public function testPlanWithUnitCoverageIsAccepted(): void
    {
        $planner = $this->testPlanner();
        $planned = $planner->plan(['subject_ref' => 'feature_x', 'acceptance_criteria' => ['x']]);
        $unitTests = $this->unitTests($planner);

        $result = $unitTests->run(['subject_ref' => 'feature_x', 'plan_id' => $planned['plan_id']]);

        $this->assertNotSame('rejected', $result['outcome']);
    }

    public function testWithoutAPlanIdNoCrossCheckHappens(): void
    {
        $planner = $this->testPlanner();
        $unitTests = $this->unitTests($planner);

        $result = $unitTests->run(['subject_ref' => 'feature_x']);

        $this->assertSame('recorded', $result['outcome']);
    }

    // --- dry run ---

    public function testWithoutARunSuiteClosureIsPending(): void
    {
        $unitTests = $this->unitTests();

        $result = $unitTests->run(['subject_ref' => 'feature_x']);

        $this->assertSame('Pending', $result['status']);
        $this->assertSame(0, $result['passed']);
    }

    // --- classification ---

    public function testAllPassingIsPassed(): void
    {
        $unitTests = $this->unitTests();

        $result = $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 12, 'failed' => 0, 'skipped' => 1]);

        $this->assertSame('Passed', $result['status']);
        $this->assertSame(12, $result['passed']);
    }

    public function testAnyFailureIsFailed(): void
    {
        $unitTests = $this->unitTests();

        $result = $unitTests->run(
            ['subject_ref' => 'feature_x'],
            runSuite: static fn(?array $plan): array => ['passed' => 10, 'failed' => 1, 'failures' => [['name' => 'test_x', 'message' => 'assertion failed']]]
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertSame([['name' => 'test_x', 'message' => 'assertion failed']], $result['failures']);
    }

    public function testZeroTestsExecutedIsFailedNotPassed(): void
    {
        $unitTests = $this->unitTests();

        $result = $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 0, 'failed' => 0, 'skipped' => 0]);

        $this->assertSame('Failed', $result['status']);
    }

    public function testAllSkippedButNoneFailedIsStillPassed(): void
    {
        $unitTests = $this->unitTests();

        $result = $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 5, 'failed' => 0, 'skipped' => 3]);

        $this->assertSame('Passed', $result['status']);
    }

    public function testSuiteThrowingIsFailedNotAnUncaughtException(): void
    {
        $unitTests = $this->unitTests();

        $result = $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static function (?array $plan): array {
            throw new RuntimeException('phpunit binary not found');
        });

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('phpunit binary not found', $result['failures'][0]['message']);
    }

    public function testCoverageRefIsCarriedForward(): void
    {
        $unitTests = $this->unitTests();

        $result = $unitTests->run(
            ['subject_ref' => 'feature_x'],
            runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0, 'coverage_ref' => 'coverage_report_1']
        );

        $this->assertSame('coverage_report_1', $result['coverage_ref']);
    }

    public function testSuiteReceivesTheResolvedPlan(): void
    {
        $planner = $this->testPlanner();
        $planned = $planner->plan(['subject_ref' => 'feature_x', 'acceptance_criteria' => ['x']]);
        $unitTests = $this->unitTests($planner);
        $seen = null;

        $unitTests->run(
            ['subject_ref' => 'feature_x', 'plan_id' => $planned['plan_id']],
            runSuite: function (?array $plan) use (&$seen): array {
                $seen = $plan;

                return ['passed' => 1, 'failed' => 0];
            }
        );

        $this->assertSame($planned['plan_id'], $seen['plan_id']);
    }

    // --- get() / history() ---

    public function testGetReturnsTheRecordedResult(): void
    {
        $unitTests = $this->unitTests();
        $ran = $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 3, 'failed' => 0]);

        $record = $unitTests->get($ran['result_id']);

        $this->assertSame('Passed', $record['status']);
        $this->assertSame(3, $record['passed']);
    }

    public function testGetUnknownResultReturnsNull(): void
    {
        $unitTests = $this->unitTests();

        $this->assertNull($unitTests->get('ghost'));
    }

    public function testHistoryReturnsEveryRunForASubject(): void
    {
        $unitTests = $this->unitTests();
        $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0]);
        $unitTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 0, 'failed' => 1]);

        $history = $unitTests->history('feature_x');

        $this->assertCount(2, $history);
        $this->assertSame('Passed', $history[0]['status']);
        $this->assertSame('Failed', $history[1]['status']);
    }
}
