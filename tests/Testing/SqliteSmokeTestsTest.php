<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Testing;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Testing\SqliteSmokeTests;
use SquirrelForge\Testing\SqliteTestPlanner;

final class SqliteSmokeTestsTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-smoke-tests-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function smokeTests(?SqliteTestPlanner $planner = null): SqliteSmokeTests
    {
        return new SqliteSmokeTests($this->tempPath('db'), $planner);
    }

    private function testPlanner(?string $path = null): SqliteTestPlanner
    {
        return new SqliteTestPlanner($path ?? $this->tempPath('planner'));
    }

    /**
     * @return array{subject_ref: string, build_ref: string, environment_ref: string}
     */
    private function minimalRequest(array $overrides = []): array
    {
        return array_replace([
            'subject_ref' => 'release_candidate_1',
            'build_ref' => 'build_42',
            'environment_ref' => 'staging',
        ], $overrides);
    }

    // --- required preconditions ---

    public function testMissingBuildRefIsInvalid(): void
    {
        $smokeTests = $this->smokeTests();
        $request = $this->minimalRequest();
        unset($request['build_ref']);

        $result = $smokeTests->run($request);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMissingEnvironmentRefIsInvalid(): void
    {
        $smokeTests = $this->smokeTests();
        $request = $this->minimalRequest();
        unset($request['environment_ref']);

        $result = $smokeTests->run($request);

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- plan cross-check ---

    public function testPlanWithoutSmokeCoverageIsRejected(): void
    {
        $plannerPath = $this->tempPath('planner');
        $planner = $this->testPlanner($plannerPath);
        $database = new PDO('sqlite:' . $plannerPath);
        $database->exec("INSERT INTO test_plans (plan_id, subject_ref, acceptance_criteria_json, categories_json, risk_driven_coverage_json, entry_criteria_json, exit_criteria_json, blocking_risks_json, created_at) VALUES ('plan_no_smoke', 'release_candidate_1', '[\"x\"]', '[\"Unit\"]', '[]', '[]', '[]', '[]', '2026-01-01T00:00:00+00:00')");
        $smokeTests = $this->smokeTests($planner);

        $result = $smokeTests->run($this->minimalRequest(['plan_id' => 'plan_no_smoke']));

        $this->assertSame('rejected', $result['outcome']);
    }

    // --- dry run ---

    public function testWithoutARunSuiteClosureIsPending(): void
    {
        $smokeTests = $this->smokeTests();

        $result = $smokeTests->run($this->minimalRequest());

        $this->assertSame('Pending', $result['status']);
        $this->assertFalse($result['should_stop_further_testing']);
    }

    // --- classification ---

    public function testAllPassingIsPassed(): void
    {
        $smokeTests = $this->smokeTests();

        $result = $smokeTests->run($this->minimalRequest(), runSuite: static fn(?array $plan): array => ['passed' => 5, 'failed' => 0]);

        $this->assertSame('Passed', $result['status']);
        $this->assertFalse($result['should_stop_further_testing']);
    }

    public function testZeroExecutedIsFailed(): void
    {
        $smokeTests = $this->smokeTests();

        $result = $smokeTests->run($this->minimalRequest(), runSuite: static fn(?array $plan): array => ['passed' => 0, 'failed' => 0]);

        $this->assertSame('Failed', $result['status']);
    }

    // --- should_stop_further_testing ---

    public function testCriticalFailureStopsFurtherTesting(): void
    {
        $smokeTests = $this->smokeTests();

        $result = $smokeTests->run(
            $this->minimalRequest(),
            runSuite: static fn(?array $plan): array => ['passed' => 3, 'failed' => 1, 'critical_failure' => true]
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertTrue($result['should_stop_further_testing']);
    }

    public function testNonCriticalFailureDoesNotStopFurtherTesting(): void
    {
        $smokeTests = $this->smokeTests();

        $result = $smokeTests->run(
            $this->minimalRequest(),
            runSuite: static fn(?array $plan): array => ['passed' => 3, 'failed' => 1, 'critical_failure' => false]
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertFalse($result['should_stop_further_testing']);
    }

    public function testFailureDefaultsToStoppingWhenCriticalityIsUnstated(): void
    {
        $smokeTests = $this->smokeTests();

        $result = $smokeTests->run($this->minimalRequest(), runSuite: static fn(?array $plan): array => ['passed' => 3, 'failed' => 1]);

        $this->assertTrue($result['should_stop_further_testing']);
    }

    public function testPassingResultNeverStopsFurtherTesting(): void
    {
        $smokeTests = $this->smokeTests();

        $result = $smokeTests->run($this->minimalRequest(), runSuite: static fn(?array $plan): array => ['passed' => 3, 'failed' => 0, 'critical_failure' => true]);

        $this->assertFalse($result['should_stop_further_testing']);
    }

    public function testSuiteThrowingAlwaysStopsFurtherTesting(): void
    {
        $smokeTests = $this->smokeTests();

        $result = $smokeTests->run($this->minimalRequest(), runSuite: static function (?array $plan): array {
            throw new RuntimeException('environment unreachable');
        });

        $this->assertSame('Failed', $result['status']);
        $this->assertTrue($result['should_stop_further_testing']);
        $this->assertSame('environment unreachable', $result['failures'][0]['message']);
    }

    // --- get() / history() ---

    public function testGetReturnsTheRecordedResult(): void
    {
        $smokeTests = $this->smokeTests();
        $ran = $smokeTests->run($this->minimalRequest(), runSuite: static fn(?array $plan): array => ['passed' => 2, 'failed' => 0]);

        $record = $smokeTests->get($ran['result_id']);

        $this->assertSame('build_42', $record['build_ref']);
        $this->assertSame('staging', $record['environment_ref']);
    }

    public function testGetUnknownResultReturnsNull(): void
    {
        $smokeTests = $this->smokeTests();

        $this->assertNull($smokeTests->get('ghost'));
    }

    public function testHistoryReturnsEveryRunForASubject(): void
    {
        $smokeTests = $this->smokeTests();
        $smokeTests->run($this->minimalRequest(), runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0]);
        $smokeTests->run($this->minimalRequest(['build_ref' => 'build_43']), runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0]);

        $history = $smokeTests->history('release_candidate_1');

        $this->assertCount(2, $history);
        $this->assertSame('build_42', $history[0]['build_ref']);
        $this->assertSame('build_43', $history[1]['build_ref']);
    }
}
