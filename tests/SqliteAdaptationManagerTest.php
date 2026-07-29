<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Learning\SqliteAdaptationManager;
use SquirrelForge\Learning\SqliteLearningGovernance;
use SquirrelForge\Reasoning\SqliteRiskAssessor;
use SquirrelForge\Resilience\SqliteFailureDetector;
use SquirrelForge\Resilience\SqliteRecoveryManager;
use SquirrelForge\Resilience\SqliteResilienceManager;
use SquirrelForge\Resilience\SqliteSelfHealingEngine;
use SquirrelForge\Workflow\CallbackStep;
use SquirrelForge\Workflow\StepWorkflow;
use SquirrelForge\Workflow\WorkflowEngine;

final class SqliteAdaptationManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-adaptation-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function successfulEngine(): WorkflowEngine
    {
        $engine = new WorkflowEngine();
        $engine->register(new StepWorkflow('adapt', 'd', static fn(array $c): bool => true, [new CallbackStep('step_1', 'Step One', static fn(array $c): array => $c)]));

        return $engine;
    }

    private function failingEngine(): WorkflowEngine
    {
        $engine = new WorkflowEngine();
        $engine->register(new StepWorkflow('adapt', 'd', static fn(array $c): bool => true, [new CallbackStep('step_1', 'Step One', static function (): never {
            throw new RuntimeException('adapt failed');
        })]));

        return $engine;
    }

    public function testCreatePlanRequiresGovernanceApproval(): void
    {
        $governance = new SqliteLearningGovernance($this->tempPath('governance'));
        $manager = new SqliteAdaptationManager($this->tempPath('main'), $governance);

        $result = $manager->createPlan('proposal_1', ['rollback_plan' => 'revert config']);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('not been approved', (string) $result['error']);
    }

    public function testCreatePlanSucceedsWhenGovernanceApproved(): void
    {
        $governance = new SqliteLearningGovernance($this->tempPath('governance'));
        $governance->review('proposal_1');
        $manager = new SqliteAdaptationManager($this->tempPath('main'), $governance);

        $result = $manager->createPlan('proposal_1', ['rollback_plan' => 'revert config']);

        $this->assertTrue($result['found']);
        $this->assertSame('planned', $manager->get($result['plan_id'])['status']);
    }

    public function testCreatePlanRequiresANonEmptyRollbackPlan(): void
    {
        $manager = new SqliteAdaptationManager($this->tempPath('main'));

        $result = $manager->createPlan('proposal_1', ['rollback_plan' => '']);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('rollback plan', (string) $result['error']);
    }

    public function testCreatePlanBlockedByUnmitigatedCriticalRisk(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $riskAssessor->assess('proposal_1', 'security', 'critical issue', 'high', 'high');
        $manager = new SqliteAdaptationManager($this->tempPath('main'), riskAssessor: $riskAssessor);

        $result = $manager->createPlan('proposal_1', ['rollback_plan' => 'revert config']);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('critical risk', (string) $result['error']);
    }

    public function testExecuteOnUnknownPlanIsNotFound(): void
    {
        $manager = new SqliteAdaptationManager($this->tempPath('main'));

        $result = $manager->execute('plan_ghost', []);

        $this->assertFalse($result['found']);
    }

    public function testExecuteSucceedsAndTransitionsToImplemented(): void
    {
        $manager = new SqliteAdaptationManager($this->tempPath('main'), workflowEngine: $this->successfulEngine());
        $created = $manager->createPlan('proposal_1', ['rollback_plan' => 'revert config']);

        $result = $manager->execute($created['plan_id'], []);

        $this->assertTrue($result['found']);
        $this->assertSame('implemented', $result['status']);
    }

    public function testExecuteCannotRunAPlanThatIsNotPlanned(): void
    {
        $manager = new SqliteAdaptationManager($this->tempPath('main'), workflowEngine: $this->successfulEngine());
        $created = $manager->createPlan('proposal_1', ['rollback_plan' => 'revert config']);
        $manager->execute($created['plan_id'], []);

        $result = $manager->execute($created['plan_id'], []);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('implemented', (string) $result['error']);
    }

    public function testExecuteFailureTransitionsToFailedAndCanRequestRecovery(): void
    {
        $failureDetector = new SqliteFailureDetector($this->tempPath('failures'));
        $selfHealing = new SqliteSelfHealingEngine($this->tempPath('healing'));
        $recoveryManager = new SqliteRecoveryManager($this->tempPath('recovery'));
        $resilience = new SqliteResilienceManager($this->tempPath('resilience'), $selfHealing, $recoveryManager);
        $manager = new SqliteAdaptationManager($this->tempPath('main'), workflowEngine: $this->failingEngine(), failureDetector: $failureDetector, resilienceManager: $resilience);
        $created = $manager->createPlan('proposal_1', ['rollback_plan' => 'revert config']);

        $result = $manager->execute($created['plan_id'], [], [
            'recovery' => ['recovery_strategy' => 'direct', 'recovery_options' => ['action' => static fn(): string => 'ok']],
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertNotNull($result['recovery']);
        $this->assertSame('completed', $result['recovery']['outcome']);
    }

    public function testRequestValidationCannotValidateAPlanThatIsNotImplemented(): void
    {
        $manager = new SqliteAdaptationManager($this->tempPath('main'));
        $created = $manager->createPlan('proposal_1', ['rollback_plan' => 'revert config']);

        $result = $manager->requestValidation($created['plan_id'], 'passed');

        $this->assertFalse($result['found']);
    }

    public function testRequestValidationPassedTransitionsToValidated(): void
    {
        $manager = new SqliteAdaptationManager($this->tempPath('main'), workflowEngine: $this->successfulEngine());
        $created = $manager->createPlan('proposal_1', ['rollback_plan' => 'revert config']);
        $manager->execute($created['plan_id'], []);

        $result = $manager->requestValidation($created['plan_id'], 'passed');

        $this->assertSame('validated', $result['status']);
    }

    public function testRequestValidationFailedTransitionsToFailedAndCanRequestRecovery(): void
    {
        $failureDetector = new SqliteFailureDetector($this->tempPath('failures'));
        $selfHealing = new SqliteSelfHealingEngine($this->tempPath('healing'));
        $recoveryManager = new SqliteRecoveryManager($this->tempPath('recovery'));
        $resilience = new SqliteResilienceManager($this->tempPath('resilience'), $selfHealing, $recoveryManager);
        $manager = new SqliteAdaptationManager($this->tempPath('main'), workflowEngine: $this->successfulEngine(), failureDetector: $failureDetector, resilienceManager: $resilience);
        $created = $manager->createPlan('proposal_1', ['rollback_plan' => 'revert config']);
        $manager->execute($created['plan_id'], []);

        $result = $manager->requestValidation($created['plan_id'], 'failed', [
            'recovery' => ['recovery_strategy' => 'direct', 'recovery_options' => ['action' => static fn(): string => 'ok']],
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertNotNull($result['recovery']);
    }

    public function testRequestValidationPendingLeavesStatusImplemented(): void
    {
        $manager = new SqliteAdaptationManager($this->tempPath('main'), workflowEngine: $this->successfulEngine());
        $created = $manager->createPlan('proposal_1', ['rollback_plan' => 'revert config']);
        $manager->execute($created['plan_id'], []);

        $result = $manager->requestValidation($created['plan_id'], 'pending');

        $this->assertSame('implemented', $result['status']);
    }

    public function testRequestValidationRejectsUnknownResult(): void
    {
        $manager = new SqliteAdaptationManager($this->tempPath('main'), workflowEngine: $this->successfulEngine());
        $created = $manager->createPlan('proposal_1', ['rollback_plan' => 'revert config']);
        $manager->execute($created['plan_id'], []);

        $result = $manager->requestValidation($created['plan_id'], 'maybe');

        $this->assertFalse($result['found']);
    }

    public function testListFiltersByProposalAndStatus(): void
    {
        $manager = new SqliteAdaptationManager($this->tempPath('main'), workflowEngine: $this->successfulEngine());
        $planA = $manager->createPlan('proposal_1', ['rollback_plan' => 'x']);
        $manager->createPlan('proposal_2', ['rollback_plan' => 'y']);
        $manager->execute($planA['plan_id'], []);

        $this->assertCount(1, $manager->list(['proposal_id' => 'proposal_1']));
        $this->assertCount(1, $manager->list(['status' => 'implemented']));
        $this->assertCount(1, $manager->list(['status' => 'planned']));
    }

    public function testPlannedPlanDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('adaptation.planned', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $manager = new SqliteAdaptationManager($this->tempPath('main'), events: $events);
        $manager->createPlan('proposal_1', ['rollback_plan' => 'x']);

        $this->assertCount(1, $captured);
    }
}
