<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\AutomationValidator;
use SquirrelForge\Automation\SqliteAutomationGovernance;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

final class SqliteAutomationGovernanceTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-automation-governance-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function readyDefinition(): array
    {
        return [
            'success_criteria' => ['x'],
            'failure_handling' => 'y',
            'references' => [
                'test' => true, 'platform_validation' => true, 'security' => true,
                'policy' => true, 'observability_coverage' => true, 'recovery_plan' => true,
            ],
        ];
    }

    public function testExemptClassIsApprovedWithoutInvokingAnyOtherChecks(): void
    {
        $governance = new SqliteAutomationGovernance($this->tempPath('main'), exemptClasses: ['low_risk']);

        $result = $governance->review('auto_1', ['automation_class' => 'low_risk']);

        $this->assertSame('approved', $result['outcome']);
        $this->assertStringContainsString('exempt', $result['rationale']);
    }

    public function testNotReadyValidationIsRejected(): void
    {
        $governance = new SqliteAutomationGovernance($this->tempPath('main'), validator: new AutomationValidator());

        $result = $governance->review('auto_1', ['definition' => ['triggers' => [
            ['id' => 't1', 'condition' => ['type' => 'boolean', 'field' => 'a', 'equals' => true]],
            ['id' => 't1', 'condition' => ['type' => 'boolean', 'field' => 'b', 'equals' => true]],
        ]]]);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testRequiresRevisionValidationIsDeferred(): void
    {
        $governance = new SqliteAutomationGovernance($this->tempPath('main'), validator: new AutomationValidator());

        $result = $governance->review('auto_1', ['definition' => []]);

        $this->assertSame('deferred', $result['outcome']);
    }

    public function testUnmitigatedCriticalRiskIsRejected(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $riskAssessor->assess('auto_1', 'security', 'critical issue', 'high', 'high');
        $governance = new SqliteAutomationGovernance($this->tempPath('main'), riskAssessor: $riskAssessor);

        $result = $governance->review('auto_1');

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testMitigatedCriticalRiskNoLongerBlocksApproval(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $critical = $riskAssessor->assess('auto_1', 'security', 'critical issue', 'high', 'high');
        $riskAssessor->markMitigated($critical['risk_id']);
        $governance = new SqliteAutomationGovernance($this->tempPath('main'), riskAssessor: $riskAssessor);

        $result = $governance->review('auto_1');

        $this->assertSame('approved', $result['outcome']);
    }

    public function testDeniedSecurityDecisionIsRejected(): void
    {
        $governance = new SqliteAutomationGovernance($this->tempPath('main'));

        $result = $governance->review('auto_1', ['security_decision' => 'denied']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testPendingComplianceFindingIsDeferred(): void
    {
        $governance = new SqliteAutomationGovernance($this->tempPath('main'));

        $result = $governance->review('auto_1', ['compliance_finding' => 'pending']);

        $this->assertSame('deferred', $result['outcome']);
    }

    public function testReadyWithConditionsValidationIsConditionedAndCarriesTheUnsatisfiedReferences(): void
    {
        $definition = $this->readyDefinition();
        $definition['references']['recovery_plan'] = false;
        $governance = new SqliteAutomationGovernance($this->tempPath('main'), validator: new AutomationValidator());

        $result = $governance->review('auto_1', ['definition' => $definition]);

        $this->assertSame('conditioned', $result['outcome']);
        $this->assertSame(['recovery_plan'], $result['conditions']);
    }

    public function testFullyCleanReviewIsApproved(): void
    {
        $governance = new SqliteAutomationGovernance(
            $this->tempPath('main'),
            validator: new AutomationValidator(),
            riskAssessor: new SqliteRiskAssessor($this->tempPath('risk'))
        );

        $result = $governance->review('auto_1', [
            'definition' => $this->readyDefinition(),
            'security_decision' => 'approved',
            'compliance_finding' => 'approved',
            'policy_result' => 'approved',
        ]);

        $this->assertSame('approved', $result['outcome']);
    }

    public function testRestrictSuspendAndRetireAreRecordedAsLifecycleActions(): void
    {
        $governance = new SqliteAutomationGovernance($this->tempPath('main'));
        $governance->review('auto_1');

        $restricted = $governance->restrict('auto_1', 'staging only');
        $this->assertSame('restricted', $governance->currentStatus('auto_1')['outcome']);

        $suspended = $governance->suspend('auto_1', 'incident in progress');
        $this->assertSame('suspended', $governance->currentStatus('auto_1')['outcome']);

        $retired = $governance->retire('auto_1', 'superseded');
        $this->assertSame('retired', $governance->currentStatus('auto_1')['outcome']);

        $this->assertNotNull($restricted['record_id']);
        $this->assertNotNull($suspended['record_id']);
        $this->assertNotNull($retired['record_id']);
    }

    public function testGrantExceptionRequiresJustificationAndGrantor(): void
    {
        $governance = new SqliteAutomationGovernance($this->tempPath('main'));

        $result = $governance->grantException('auto_1', '', 'user_1');

        $this->assertFalse($result['found']);
    }

    public function testGrantExceptionRecordsApprovalWithRationale(): void
    {
        $governance = new SqliteAutomationGovernance($this->tempPath('main'));

        $result = $governance->grantException('auto_1', 'urgent hotfix', 'user_1');

        $this->assertTrue($result['found']);
        $current = $governance->currentStatus('auto_1');
        $this->assertSame('approved', $current['outcome']);
        $this->assertStringContainsString('user_1', $current['rationale']);
    }

    public function testHistoryReturnsRecordsInChronologicalOrder(): void
    {
        $governance = new SqliteAutomationGovernance($this->tempPath('main'));
        $governance->review('auto_1');
        $governance->restrict('auto_1', 'staging only');
        $governance->retire('auto_1', 'done');

        $history = $governance->history('auto_1');

        $this->assertCount(3, $history);
        $this->assertSame('approved', $history[0]['outcome']);
        $this->assertSame('restricted', $history[1]['outcome']);
        $this->assertSame('retired', $history[2]['outcome']);
    }

    public function testCurrentStatusReturnsNullForAnUnknownAutomation(): void
    {
        $governance = new SqliteAutomationGovernance($this->tempPath('main'));

        $this->assertNull($governance->currentStatus('auto_ghost'));
    }

    public function testReviewDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('automation_governance.approved', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $governance = new SqliteAutomationGovernance($this->tempPath('main'), events: $events);
        $governance->review('auto_1');

        $this->assertCount(1, $captured);
    }
}
