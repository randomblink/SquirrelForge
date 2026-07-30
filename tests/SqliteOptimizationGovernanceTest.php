<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Optimization\OptimizationValidator;
use SquirrelForge\Optimization\SqliteOptimizationGovernance;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

final class SqliteOptimizationGovernanceTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-optimization-governance-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function readyProposal(): array
    {
        return ['proposal_id' => 'proposal_1', 'opportunity' => ['type' => 'recurring_issue', 'evidence' => ['count' => 5]], 'success_criteria' => ['x']];
    }

    /**
     * @return array<string, mixed>
     */
    private function readyValidatorOptions(): array
    {
        return [
            'baseline_reference' => 'b1',
            'comparison_method' => 'before_after',
            'rollback_plan' => 'revert',
            'references' => ['test_evidence' => true, 'platform_validation' => true, 'policy_result' => true, 'security_decision' => true],
        ];
    }

    public function testNotReadyValidationIsRejected(): void
    {
        $governance = new SqliteOptimizationGovernance($this->tempPath('main'), new OptimizationValidator());

        $result = $governance->review('proposal_1', ['success_criteria' => ['x']]);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testRequiresRevisionValidationPassesThroughDirectly(): void
    {
        $governance = new SqliteOptimizationGovernance($this->tempPath('main'), new OptimizationValidator());

        $result = $governance->review('proposal_1', $this->readyProposal(), ['validator_options' => []]);

        $this->assertSame('requires_revision', $result['outcome']);
    }

    public function testFullyReadyValidationProceedsToApproval(): void
    {
        $governance = new SqliteOptimizationGovernance($this->tempPath('main'), new OptimizationValidator());

        $result = $governance->review('proposal_1', $this->readyProposal(), ['validator_options' => $this->readyValidatorOptions()]);

        $this->assertSame('approved', $result['outcome']);
    }

    public function testUnmitigatedCriticalRiskIsRejected(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $riskAssessor->assess('proposal_1', 'security', 'critical issue', 'high', 'high');
        $governance = new SqliteOptimizationGovernance($this->tempPath('main'), riskAssessor: $riskAssessor);

        $result = $governance->review('proposal_1', $this->readyProposal());

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testExplicitPolicyResultDeniedIsRejected(): void
    {
        $governance = new SqliteOptimizationGovernance($this->tempPath('main'));

        $result = $governance->review('proposal_1', $this->readyProposal(), ['policy_result' => 'denied']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testPolicyEngineDenialRejectsWhenNoExplicitPolicyResultIsGiven(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'workflow', 'priority' => 1, 'condition' => ['type' => 'boolean', 'field' => 'x', 'equals' => true], 'effect' => 'deny']);
        $governance = new SqliteOptimizationGovernance($this->tempPath('main'), policyEngine: $policyEngine);

        $result = $governance->review('proposal_1', $this->readyProposal(), ['policy_context' => ['x' => true]]);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testPendingComplianceFindingIsDeferred(): void
    {
        $governance = new SqliteOptimizationGovernance($this->tempPath('main'));

        $result = $governance->review('proposal_1', $this->readyProposal(), ['compliance_finding' => 'pending']);

        $this->assertSame('deferred', $result['outcome']);
    }

    public function testValidatorDeferredPassesThroughAsDeferred(): void
    {
        $governance = new SqliteOptimizationGovernance($this->tempPath('main'), new OptimizationValidator());
        $options = $this->readyValidatorOptions();
        $options['references']['test_evidence'] = 'pending';

        $result = $governance->review('proposal_1', $this->readyProposal(), ['validator_options' => $options]);

        $this->assertSame('deferred', $result['outcome']);
    }

    public function testValidatorReadyWithConditionsIsConditionedAndCarriesTheUnsatisfiedReferences(): void
    {
        $governance = new SqliteOptimizationGovernance($this->tempPath('main'), new OptimizationValidator());
        $options = $this->readyValidatorOptions();
        $options['references']['security_decision'] = false;

        $result = $governance->review('proposal_1', $this->readyProposal(), ['validator_options' => $options]);

        $this->assertSame('conditioned', $result['outcome']);
        $this->assertSame(['security_decision'], $result['conditions']);
    }

    public function testFullyCleanReviewWithNoOptionsIsApproved(): void
    {
        $governance = new SqliteOptimizationGovernance($this->tempPath('main'));

        $result = $governance->review('proposal_1', []);

        $this->assertSame('approved', $result['outcome']);
    }

    public function testRestrictAndRetireAreRecordedAsLifecycleActions(): void
    {
        $governance = new SqliteOptimizationGovernance($this->tempPath('main'));
        $governance->review('proposal_1', []);

        $restricted = $governance->restrict('proposal_1', 'limited rollout');
        $this->assertSame('restricted', $governance->currentStatus('proposal_1')['outcome']);

        $retired = $governance->retire('proposal_1', 'superseded');
        $this->assertSame('retired', $governance->currentStatus('proposal_1')['outcome']);

        $this->assertNotNull($restricted['record_id']);
        $this->assertNotNull($retired['record_id']);
    }

    public function testHistoryReturnsRecordsInChronologicalOrder(): void
    {
        $governance = new SqliteOptimizationGovernance($this->tempPath('main'));
        $governance->review('proposal_1', []);
        $governance->retire('proposal_1', 'done');

        $history = $governance->history('proposal_1');

        $this->assertCount(2, $history);
        $this->assertSame('approved', $history[0]['outcome']);
        $this->assertSame('retired', $history[1]['outcome']);
    }

    public function testCurrentStatusReturnsNullForAnUnknownProposal(): void
    {
        $governance = new SqliteOptimizationGovernance($this->tempPath('main'));

        $this->assertNull($governance->currentStatus('proposal_ghost'));
    }

    public function testReviewDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('optimization_governance.approved', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $governance = new SqliteOptimizationGovernance($this->tempPath('main'), events: $events);
        $governance->review('proposal_1', []);

        $this->assertCount(1, $captured);
    }
}
