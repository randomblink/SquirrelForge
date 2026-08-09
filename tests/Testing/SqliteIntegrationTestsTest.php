<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Testing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Testing\SqliteIntegrationTests;
use SquirrelForge\Testing\SqliteTestPlanner;

final class SqliteIntegrationTestsTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-integration-tests-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function integrationTests(?SqliteTestPlanner $planner = null): SqliteIntegrationTests
    {
        return new SqliteIntegrationTests($this->tempPath('db'), $planner);
    }

    private function testPlanner(): SqliteTestPlanner
    {
        return new SqliteTestPlanner($this->tempPath('planner'));
    }

    // --- required fields ---

    public function testMissingSubjectRefIsInvalid(): void
    {
        $integrationTests = $this->integrationTests();

        $result = $integrationTests->run([]);

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- plan cross-check ---

    public function testUnknownPlanIdIsInvalid(): void
    {
        $planner = $this->testPlanner();
        $integrationTests = $this->integrationTests($planner);

        $result = $integrationTests->run(['subject_ref' => 'feature_x', 'plan_id' => 'ghost']);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testPlanWithoutIntegrationCoverageIsRejected(): void
    {
        $planner = $this->testPlanner();
        $planned = $planner->plan(['subject_ref' => 'feature_x', 'acceptance_criteria' => ['x']]);
        $integrationTests = $this->integrationTests($planner);

        $result = $integrationTests->run(['subject_ref' => 'feature_x', 'plan_id' => $planned['plan_id']]);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testPlanWithIntegrationCoverageIsAccepted(): void
    {
        $planner = $this->testPlanner();
        $planned = $planner->plan(['subject_ref' => 'feature_x', 'acceptance_criteria' => ['x'], 'interface_contracts' => ['ExportApi']]);
        $integrationTests = $this->integrationTests($planner);

        $result = $integrationTests->run(['subject_ref' => 'feature_x', 'plan_id' => $planned['plan_id']]);

        $this->assertNotSame('rejected', $result['outcome']);
    }

    // --- dry run ---

    public function testWithoutARunSuiteClosureIsPending(): void
    {
        $integrationTests = $this->integrationTests();

        $result = $integrationTests->run(['subject_ref' => 'feature_x']);

        $this->assertSame('Pending', $result['status']);
    }

    public function testDryRunStillReportsRequiredContractsFromThePlan(): void
    {
        $planner = $this->testPlanner();
        $planned = $planner->plan(['subject_ref' => 'feature_x', 'acceptance_criteria' => ['x'], 'interface_contracts' => ['ExportApi', 'ReportingApi']]);
        $integrationTests = $this->integrationTests($planner);

        $result = $integrationTests->run(['subject_ref' => 'feature_x', 'plan_id' => $planned['plan_id']]);

        $this->assertSame(['ExportApi', 'ReportingApi'], $result['uncovered_contracts']);
    }

    // --- classification ---

    public function testAllPassingIsPassed(): void
    {
        $integrationTests = $this->integrationTests();

        $result = $integrationTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 4, 'failed' => 0]);

        $this->assertSame('Passed', $result['status']);
    }

    public function testAnyFailureIsFailed(): void
    {
        $integrationTests = $this->integrationTests();

        $result = $integrationTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 3, 'failed' => 1]);

        $this->assertSame('Failed', $result['status']);
    }

    public function testZeroExecutedIsFailed(): void
    {
        $integrationTests = $this->integrationTests();

        $result = $integrationTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 0, 'failed' => 0]);

        $this->assertSame('Failed', $result['status']);
    }

    public function testSuiteThrowingIsFailedNotAnUncaughtException(): void
    {
        $integrationTests = $this->integrationTests();

        $result = $integrationTests->run(['subject_ref' => 'feature_x'], runSuite: static function (?array $plan): array {
            throw new RuntimeException('downstream service unreachable');
        });

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('downstream service unreachable', $result['failures'][0]['message']);
    }

    // --- contract coverage ---

    public function testFullyCoveredContractsLeaveNoneUncovered(): void
    {
        $planner = $this->testPlanner();
        $planned = $planner->plan(['subject_ref' => 'feature_x', 'acceptance_criteria' => ['x'], 'interface_contracts' => ['ExportApi', 'ReportingApi']]);
        $integrationTests = $this->integrationTests($planner);

        $result = $integrationTests->run(
            ['subject_ref' => 'feature_x', 'plan_id' => $planned['plan_id']],
            runSuite: static fn(?array $plan): array => ['passed' => 2, 'failed' => 0, 'covered_contracts' => ['ExportApi', 'ReportingApi']]
        );

        $this->assertSame([], $result['uncovered_contracts']);
    }

    public function testPartiallyCoveredContractsSurfaceTheGap(): void
    {
        $planner = $this->testPlanner();
        $planned = $planner->plan(['subject_ref' => 'feature_x', 'acceptance_criteria' => ['x'], 'interface_contracts' => ['ExportApi', 'ReportingApi']]);
        $integrationTests = $this->integrationTests($planner);

        $result = $integrationTests->run(
            ['subject_ref' => 'feature_x', 'plan_id' => $planned['plan_id']],
            runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0, 'covered_contracts' => ['ExportApi']]
        );

        $this->assertSame(['ReportingApi'], $result['uncovered_contracts']);
        $this->assertSame(['ExportApi'], $result['covered_contracts']);
    }

    public function testWithoutAPlanNoContractsAreExpected(): void
    {
        $integrationTests = $this->integrationTests();

        $result = $integrationTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0]);

        $this->assertSame([], $result['uncovered_contracts']);
    }

    // --- scenario coverage is only ever reported, never assumed ---

    public function testScenarioCoverageIsCarriedForwardVerbatim(): void
    {
        $integrationTests = $this->integrationTests();

        $result = $integrationTests->run(
            ['subject_ref' => 'feature_x'],
            runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0, 'scenario_coverage' => ['permission_denial', 'timeout']]
        );

        $this->assertSame(['permission_denial', 'timeout'], $result['scenario_coverage']);
    }

    public function testScenarioCoverageDefaultsToEmptyNeverAssumed(): void
    {
        $integrationTests = $this->integrationTests();

        $result = $integrationTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0]);

        $this->assertSame([], $result['scenario_coverage']);
    }

    // --- get() / history() ---

    public function testGetReturnsTheRecordedResult(): void
    {
        $integrationTests = $this->integrationTests();
        $ran = $integrationTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 2, 'failed' => 0]);

        $record = $integrationTests->get($ran['result_id']);

        $this->assertSame('Passed', $record['status']);
    }

    public function testGetUnknownResultReturnsNull(): void
    {
        $integrationTests = $this->integrationTests();

        $this->assertNull($integrationTests->get('ghost'));
    }

    public function testHistoryReturnsEveryRunForASubject(): void
    {
        $integrationTests = $this->integrationTests();
        $integrationTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 1, 'failed' => 0]);
        $integrationTests->run(['subject_ref' => 'feature_x'], runSuite: static fn(?array $plan): array => ['passed' => 0, 'failed' => 1]);

        $history = $integrationTests->history('feature_x');

        $this->assertCount(2, $history);
        $this->assertSame('Passed', $history[0]['status']);
        $this->assertSame('Failed', $history[1]['status']);
    }
}
