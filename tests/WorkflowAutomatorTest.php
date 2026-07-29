<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Automation\ApprovalGate;
use SquirrelForge\Automation\WorkflowAutomator;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Resilience\SqliteFailureDetector;
use SquirrelForge\Resilience\SqliteResilienceManager;
use SquirrelForge\Resilience\SqliteRecoveryManager;
use SquirrelForge\Resilience\SqliteSelfHealingEngine;
use SquirrelForge\Workflow\CallbackStep;
use SquirrelForge\Workflow\StepWorkflow;
use SquirrelForge\Workflow\WorkflowEngine;

final class WorkflowAutomatorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-workflow-automator-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function successfulEngine(): WorkflowEngine
    {
        $engine = new WorkflowEngine();
        $engine->register(new StepWorkflow(
            'deploy',
            'Deploy the service',
            static fn(array $c): bool => true,
            [new CallbackStep('step_1', 'Step One', static fn(array $c): array => $c)]
        ));

        return $engine;
    }

    private function failingEngine(): WorkflowEngine
    {
        $engine = new WorkflowEngine();
        $engine->register(new StepWorkflow(
            'deploy',
            'Deploy the service',
            static fn(array $c): bool => true,
            [new CallbackStep('step_1', 'Step One', static function (): never {
                throw new RuntimeException('deploy failed');
            })]
        ));

        return $engine;
    }

    public function testWithoutAWorkflowEngineConfiguredHandoffIsRejected(): void
    {
        $automator = new WorkflowAutomator();

        $result = $automator->handoff('auto_1', []);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('not configured', (string) $result['error']);
    }

    public function testABlockedApprovalGatePreventsExecutionEntirely(): void
    {
        $engineWasCalled = false;
        $engine = new WorkflowEngine();
        $engine->register(new StepWorkflow('deploy', 'd', static function (array $c) use (&$engineWasCalled): bool {
            $engineWasCalled = true;

            return true;
        }, [new CallbackStep('step_1', 'Step One', static fn(array $c): array => $c)]));

        $automator = new WorkflowAutomator($engine, new ApprovalGate());

        $result = $automator->handoff('auto_1', [], ['required_categories' => ['security']]);

        $this->assertSame('blocked', $result['outcome']);
        $this->assertSame('incomplete', $result['gate_outcome']);
        $this->assertFalse($engineWasCalled);
    }

    public function testAPassingApprovalGateAllowsExecution(): void
    {
        $automator = new WorkflowAutomator($this->successfulEngine(), new ApprovalGate());

        $result = $automator->handoff('auto_1', [], ['required_categories' => ['security'], 'approval_references' => [
            'security' => ['status' => 'approved'],
        ]]);

        $this->assertSame('completed', $result['outcome']);
        $this->assertSame('pass', $result['gate_outcome']);
    }

    public function testSuccessfulExecutionReturnsStatusAndCheckpoints(): void
    {
        $automator = new WorkflowAutomator($this->successfulEngine());

        $result = $automator->handoff('auto_1', []);

        $this->assertSame('completed', $result['outcome']);
        $this->assertSame('success', $result['status']);
        $this->assertNotEmpty($result['checkpoints']);
    }

    public function testNoSupportingWorkflowIsRejected(): void
    {
        $engine = new WorkflowEngine();
        $engine->register(new StepWorkflow('deploy', 'd', static fn(array $c): bool => false, []));
        $automator = new WorkflowAutomator($engine);

        $result = $automator->handoff('auto_1', []);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testFailedExecutionReturnsCheckpointsAndRollbackWithoutRecovery(): void
    {
        $automator = new WorkflowAutomator($this->failingEngine());

        $result = $automator->handoff('auto_1', []);

        $this->assertSame('failed', $result['outcome']);
        $this->assertStringContainsString('deploy failed', (string) $result['error']);
        $this->assertNotNull($result['rollback']);
        $this->assertNull($result['recovery']);
    }

    public function testFailedExecutionWithRecoveryRequestedDelegatesToResilienceLayer(): void
    {
        $failureDetector = new SqliteFailureDetector($this->tempPath('failures'));
        $selfHealing = new SqliteSelfHealingEngine($this->tempPath('healing'));
        $recovery = new SqliteRecoveryManager($this->tempPath('recovery'));
        $resilience = new SqliteResilienceManager($this->tempPath('resilience'), $selfHealing, $recovery);
        $automator = new WorkflowAutomator($this->failingEngine(), failureDetector: $failureDetector, resilienceManager: $resilience);

        $result = $automator->handoff('auto_1', [], [
            'recovery' => [
                'recovery_strategy' => 'direct',
                'recovery_options' => ['action' => static fn(): string => 'recovered'],
            ],
        ]);

        $this->assertSame('failed', $result['outcome']);
        $this->assertNotNull($result['recovery']);
        $this->assertSame('completed', $result['recovery']['outcome']);
        $this->assertSame('recovery', $result['recovery']['coordinated_component']);
    }

    public function testHandoffDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('workflow_automator.completed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $automator = new WorkflowAutomator($this->successfulEngine(), events: $events);
        $automator->handoff('auto_1', []);

        $this->assertCount(1, $captured);
    }
}
