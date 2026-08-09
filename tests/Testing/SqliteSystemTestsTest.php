<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Testing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Reasoning\SqliteRiskAssessor;
use SquirrelForge\Testing\SqliteSystemTests;
use SquirrelForge\Testing\SqliteTestPlanner;

final class SqliteSystemTestsTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-system-tests-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function systemTests(?SqliteTestPlanner $planner = null): SqliteSystemTests
    {
        return new SqliteSystemTests($this->tempPath('db'), $planner);
    }

    private function testPlanner(): SqliteTestPlanner
    {
        return new SqliteTestPlanner($this->tempPath('planner'));
    }

    /**
     * A plan reaches the System category only when a critical risk is
     * on file for its option (SqliteTestPlanner's own real logic) --
     * this builds one, rather than fabricating a plan record that
     * bypasses that real composition.
     */
    private function testPlannerWithSystemCoverage(): SqliteTestPlanner
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $riskAssessor->assess('feature_x', 'technical', 'End-to-end path is unproven', 'high', 'high');

        return new SqliteTestPlanner($this->tempPath('planner'), $riskAssessor);
    }

    /**
     * @return array{subject_ref: string, environment_ref: string}
     */
    private function minimalRequest(array $overrides = []): array
    {
        return array_replace(['subject_ref' => 'feature_x', 'environment_ref' => 'staging'], $overrides);
    }

    // --- required preconditions ---

    public function testMissingSubjectRefIsInvalid(): void
    {
        $systemTests = $this->systemTests();
        $request = $this->minimalRequest();
        unset($request['subject_ref']);

        $result = $systemTests->run($request);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMissingEnvironmentRefIsInvalid(): void
    {
        $systemTests = $this->systemTests();
        $request = $this->minimalRequest();
        unset($request['environment_ref']);

        $result = $systemTests->run($request);

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- plan cross-check ---

    public function testUnknownPlanIdIsInvalid(): void
    {
        $planner = $this->testPlanner();
        $systemTests = $this->systemTests($planner);

        $result = $systemTests->run($this->minimalRequest(['plan_id' => 'ghost']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testPlanWithoutSystemCoverageIsRejected(): void
    {
        $planner = $this->testPlanner();
        $planned = $planner->plan(['subject_ref' => 'feature_x', 'acceptance_criteria' => ['x']]);
        $systemTests = $this->systemTests($planner);

        $result = $systemTests->run($this->minimalRequest(['plan_id' => $planned['plan_id']]));

        $this->assertSame('rejected', $result['outcome']);
    }

    // --- dry run ---

    public function testWithoutARunSuiteClosureIsPending(): void
    {
        $systemTests = $this->systemTests();

        $result = $systemTests->run($this->minimalRequest());

        $this->assertSame('Pending', $result['status']);
    }

    // --- classification ---

    public function testAllPassingIsPassed(): void
    {
        $systemTests = $this->systemTests();

        $result = $systemTests->run($this->minimalRequest(), runSuite: static fn(?array $plan): array => ['passed' => 4, 'failed' => 0]);

        $this->assertSame('Passed', $result['status']);
    }

    public function testZeroExecutedIsFailed(): void
    {
        $systemTests = $this->systemTests();

        $result = $systemTests->run($this->minimalRequest(), runSuite: static fn(?array $plan): array => ['passed' => 0, 'failed' => 0]);

        $this->assertSame('Failed', $result['status']);
    }

    public function testSuiteThrowingIsFailedNotAnUncaughtException(): void
    {
        $systemTests = $this->systemTests();

        $result = $systemTests->run($this->minimalRequest(), runSuite: static function (?array $plan): array {
            throw new RuntimeException('environment provisioning failed');
        });

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('environment provisioning failed', $result['failures'][0]['message']);
    }

    // --- acceptance-criteria coverage against the real plan ---

    public function testFullyVerifiedCriteriaLeaveNoneUnverified(): void
    {
        $planner = $this->testPlannerWithSystemCoverage();
        $planned = $planner->plan(['subject_ref' => 'feature_x', 'acceptance_criteria' => ['Users can export CSV.', 'Export completes under 5s.'], 'option_reference' => 'feature_x']);
        $systemTests = $this->systemTests($planner);

        $result = $systemTests->run(
            $this->minimalRequest(['plan_id' => $planned['plan_id']]),
            runSuite: static fn(?array $plan): array => ['passed' => 2, 'failed' => 0, 'verified_criteria' => ['Users can export CSV.', 'Export completes under 5s.']]
        );

        $this->assertSame([], $result['unverified_criteria']);
    }

    public function testPartiallyVerifiedCriteriaSurfaceTheGap(): void
    {
        $planner = $this->testPlannerWithSystemCoverage();
        $planned = $planner->plan(['subject_ref' => 'feature_x', 'acceptance_criteria' => ['Users can export CSV.', 'Export completes under 5s.'], 'option_reference' => 'feature_x']);
        $systemTests = $this->systemTests($planner);

        $result = $systemTests->run(
            $this->minimalRequest(['plan_id' => $planned['plan_id']]),
            runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0, 'verified_criteria' => ['Users can export CSV.']]
        );

        $this->assertSame(['Export completes under 5s.'], $result['unverified_criteria']);
        $this->assertSame(['Users can export CSV.'], $result['verified_criteria']);
    }

    public function testWithoutAPlanNoCriteriaAreExpected(): void
    {
        $systemTests = $this->systemTests();

        $result = $systemTests->run($this->minimalRequest(), runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0]);

        $this->assertSame([], $result['unverified_criteria']);
    }

    // --- scenario coverage: reported, never assumed ---

    public function testScenarioCoverageIsCarriedForwardVerbatim(): void
    {
        $systemTests = $this->systemTests();

        $result = $systemTests->run(
            $this->minimalRequest(),
            runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0, 'scenario_coverage' => ['persistence', 'restoration']]
        );

        $this->assertSame(['persistence', 'restoration'], $result['scenario_coverage']);
    }

    public function testScenarioCoverageDefaultsToEmpty(): void
    {
        $systemTests = $this->systemTests();

        $result = $systemTests->run($this->minimalRequest(), runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0]);

        $this->assertSame([], $result['scenario_coverage']);
    }

    // --- get() / history() ---

    public function testGetReturnsTheRecordedResult(): void
    {
        $systemTests = $this->systemTests();
        $ran = $systemTests->run($this->minimalRequest(), runSuite: static fn(?array $plan): array => ['passed' => 2, 'failed' => 0]);

        $record = $systemTests->get($ran['result_id']);

        $this->assertSame('staging', $record['environment_ref']);
    }

    public function testGetUnknownResultReturnsNull(): void
    {
        $systemTests = $this->systemTests();

        $this->assertNull($systemTests->get('ghost'));
    }

    public function testHistoryReturnsEveryRunForASubject(): void
    {
        $systemTests = $this->systemTests();
        $systemTests->run($this->minimalRequest(), runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0]);
        $systemTests->run($this->minimalRequest(['environment_ref' => 'production_like']), runSuite: static fn(?array $plan): array => ['passed' => 0, 'failed' => 1]);

        $history = $systemTests->history('feature_x');

        $this->assertCount(2, $history);
        $this->assertSame('staging', $history[0]['environment_ref']);
        $this->assertSame('production_like', $history[1]['environment_ref']);
    }
}
