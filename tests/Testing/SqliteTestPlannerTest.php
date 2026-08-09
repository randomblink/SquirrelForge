<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Testing;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Reasoning\SqliteRiskAssessor;
use SquirrelForge\Testing\SqliteTestPlanner;

final class SqliteTestPlannerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-test-planner-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function planner(?SqliteRiskAssessor $riskAssessor = null): SqliteTestPlanner
    {
        return new SqliteTestPlanner($this->tempPath('db'), $riskAssessor);
    }

    private function riskAssessor(): SqliteRiskAssessor
    {
        return new SqliteRiskAssessor($this->tempPath('risk'));
    }

    /**
     * @return array{subject_ref: string, acceptance_criteria: array<int, string>}
     */
    private function minimalRequest(array $overrides = []): array
    {
        return array_replace([
            'subject_ref' => 'feature_x',
            'acceptance_criteria' => ['Users can export a report as CSV.'],
        ], $overrides);
    }

    // --- required fields ---

    public function testMissingSubjectRefIsInvalid(): void
    {
        $planner = $this->planner();
        $request = $this->minimalRequest();
        unset($request['subject_ref']);

        $result = $planner->plan($request);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testNoAcceptanceCriteriaIsInvalid(): void
    {
        $planner = $this->planner();

        $result = $planner->plan($this->minimalRequest(['acceptance_criteria' => []]));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('acceptance criterion', $result['error']);
    }

    // --- category mapping ---

    public function testMinimalRequestAlwaysIncludesUnit(): void
    {
        $planner = $this->planner();

        $result = $planner->plan($this->minimalRequest());

        $this->assertSame(['Unit'], $result['categories']);
    }

    public function testInterfaceContractsAddIntegration(): void
    {
        $planner = $this->planner();

        $result = $planner->plan($this->minimalRequest(['interface_contracts' => ['ExportApi']]));

        $this->assertSame(['Unit', 'Integration'], $result['categories']);
    }

    public function testIsChangeFlagAddsRegression(): void
    {
        $planner = $this->planner();

        $result = $planner->plan($this->minimalRequest(['is_change' => true]));

        $this->assertSame(['Unit', 'Regression'], $result['categories']);
    }

    public function testChangeImpactAloneAddsRegression(): void
    {
        $planner = $this->planner();

        $result = $planner->plan($this->minimalRequest(['change_impact' => ['ExportController']]));

        $this->assertContains('Regression', $result['categories']);
    }

    // --- RiskAssessor composition ---

    public function testHighRiskRequiresNegativeAndBoundaryCoverage(): void
    {
        $riskAssessor = $this->riskAssessor();
        $riskAssessor->assess('feature_x', 'technical', 'Complex export logic', 'high', 'high');
        $planner = $this->planner($riskAssessor);

        $result = $planner->plan($this->minimalRequest(['option_reference' => 'feature_x']));

        $this->assertContains('negative', $result['risk_driven_coverage']);
        $this->assertContains('boundary', $result['risk_driven_coverage']);
    }

    public function testSecurityRiskRequiresPermissionFailureCoverage(): void
    {
        $riskAssessor = $this->riskAssessor();
        $riskAssessor->assess('feature_x', 'security', 'Export may leak restricted data', 'medium', 'medium');
        $planner = $this->planner($riskAssessor);

        $result = $planner->plan($this->minimalRequest(['option_reference' => 'feature_x']));

        $this->assertContains('permission_failure', $result['risk_driven_coverage']);
    }

    public function testOperationalRiskRequiresRecoveryScenarioCoverage(): void
    {
        $riskAssessor = $this->riskAssessor();
        $riskAssessor->assess('feature_x', 'operational', 'Export job may fail mid-run', 'medium', 'medium');
        $planner = $this->planner($riskAssessor);

        $result = $planner->plan($this->minimalRequest(['option_reference' => 'feature_x']));

        $this->assertContains('recovery_scenario', $result['risk_driven_coverage']);
    }

    public function testCriticalRiskAddsSystemAndSmokeCategories(): void
    {
        $riskAssessor = $this->riskAssessor();
        $riskAssessor->assess('feature_x', 'security', 'Critical data exposure', 'high', 'high');
        $planner = $this->planner($riskAssessor);

        $result = $planner->plan($this->minimalRequest(['option_reference' => 'feature_x']));

        $this->assertContains('System', $result['categories']);
        $this->assertContains('Smoke', $result['categories']);
    }

    public function testLowRiskAddsNoRiskDrivenCoverage(): void
    {
        $riskAssessor = $this->riskAssessor();
        $riskAssessor->assess('feature_x', 'technical', 'Minor formatting difference', 'low', 'low');
        $planner = $this->planner($riskAssessor);

        $result = $planner->plan($this->minimalRequest(['option_reference' => 'feature_x']));

        $this->assertSame([], $result['risk_driven_coverage']);
    }

    public function testMitigatedRiskIsExcludedFromCoverageDriving(): void
    {
        $riskAssessor = $this->riskAssessor();
        $assessed = $riskAssessor->assess('feature_x', 'security', 'Was risky', 'high', 'high');
        $riskAssessor->markMitigated($assessed['risk_id']);
        $planner = $this->planner($riskAssessor);

        $result = $planner->plan($this->minimalRequest(['option_reference' => 'feature_x']));

        $this->assertSame([], $result['risk_driven_coverage']);
    }

    public function testWithoutAnOptionReferenceNoRiskLookupHappens(): void
    {
        $riskAssessor = $this->riskAssessor();
        $riskAssessor->assess('feature_x', 'security', 'x', 'high', 'high');
        $planner = $this->planner($riskAssessor);

        $result = $planner->plan($this->minimalRequest());

        $this->assertSame([], $result['risk_driven_coverage']);
    }

    public function testWithoutARiskAssessorNeverCrashes(): void
    {
        $planner = $this->planner();

        $result = $planner->plan($this->minimalRequest(['option_reference' => 'feature_x']));

        $this->assertSame('planned', $result['outcome']);
        $this->assertSame([], $result['risk_driven_coverage']);
    }

    // --- exit criteria / blocking risks (canProceed composition) ---

    public function testOpenCriticalRiskIsSurfacedAsABlockingRisk(): void
    {
        $riskAssessor = $this->riskAssessor();
        $assessed = $riskAssessor->assess('feature_x', 'security', 'critical exposure', 'high', 'high');
        $planner = $this->planner($riskAssessor);

        $result = $planner->plan($this->minimalRequest(['option_reference' => 'feature_x']));

        $this->assertSame([$assessed['risk_id']], $result['blocking_risks']);
        $this->assertStringContainsString($assessed['risk_id'], $result['exit_criteria'][1]);
    }

    public function testNoOpenCriticalRiskLeavesExitCriteriaClean(): void
    {
        $riskAssessor = $this->riskAssessor();
        $planner = $this->planner($riskAssessor);

        $result = $planner->plan($this->minimalRequest(['option_reference' => 'feature_x']));

        $this->assertSame([], $result['blocking_risks']);
        $this->assertStringContainsString('No open critical risk', $result['exit_criteria'][1]);
    }

    public function testNeverDecidesOrBlocksItselfOnBlockingRisks(): void
    {
        $riskAssessor = $this->riskAssessor();
        $riskAssessor->assess('feature_x', 'security', 'critical exposure', 'high', 'high');
        $planner = $this->planner($riskAssessor);

        $result = $planner->plan($this->minimalRequest(['option_reference' => 'feature_x']));

        // The plan is still produced (outcome=planned); this class only reports the blocking
        // reference, it never itself refuses to produce a plan or claims a decision.
        $this->assertSame('planned', $result['outcome']);
    }

    // --- entry criteria ---

    public function testEntryCriteriaNameDeclaredEnvironmentsAndFixtures(): void
    {
        $planner = $this->planner();

        $result = $planner->plan($this->minimalRequest([
            'environment_refs' => ['staging'],
            'fixture_refs' => ['sample_dataset_1'],
        ]));

        $this->assertStringContainsString('staging', $result['entry_criteria'][0]);
        $this->assertStringContainsString('sample_dataset_1', $result['entry_criteria'][1]);
    }

    public function testEntryCriteriaAreHonestWhenNothingWasDeclared(): void
    {
        $planner = $this->planner();

        $result = $planner->plan($this->minimalRequest());

        $this->assertStringContainsString('must still be confirmed', $result['entry_criteria'][0]);
    }

    // --- get() / history() ---

    public function testGetReturnsThePlanJustProduced(): void
    {
        $planner = $this->planner();
        $planned = $planner->plan($this->minimalRequest());

        $record = $planner->get($planned['plan_id']);

        $this->assertSame('feature_x', $record['subject_ref']);
        $this->assertSame(['Users can export a report as CSV.'], $record['acceptance_criteria']);
    }

    public function testGetUnknownPlanReturnsNull(): void
    {
        $planner = $this->planner();

        $this->assertNull($planner->get('ghost'));
    }

    public function testHistoryReturnsEveryPlanForASubject(): void
    {
        $planner = $this->planner();
        $planner->plan($this->minimalRequest());
        $planner->plan($this->minimalRequest(['is_change' => true]));

        $history = $planner->history('feature_x');

        $this->assertCount(2, $history);
        $this->assertSame(['Unit'], $history[0]['categories']);
        $this->assertSame(['Unit', 'Regression'], $history[1]['categories']);
    }
}
