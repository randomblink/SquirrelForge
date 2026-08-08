<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Execution;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Execution\ActionDispatcher;
use SquirrelForge\Execution\ExecutionEngine;
use SquirrelForge\Execution\FailureHandler;
use SquirrelForge\Execution\SqliteCheckpointManager;
use SquirrelForge\Execution\SqliteExecutionLogger;
use SquirrelForge\Execution\SqliteResultCollector;
use SquirrelForge\Execution\WorkflowExecutor;

final class ExecutionEngineTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-execution-engine-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function checkpointManager(): SqliteCheckpointManager
    {
        return new SqliteCheckpointManager($this->tempPath('checkpoints'));
    }

    private function passingValidationItem(): array
    {
        return ['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'PASSED'];
    }

    private function failingValidationItem(): array
    {
        return ['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'FAILED', 'waivable' => false];
    }

    // --- dependency gating ---

    public function testUnresolvedDependencyBlockersRefuseToBegin(): void
    {
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()));

        $result = $engine->run('wf_1', [], [['dependency_id' => 'd1', 'required_by' => 's1']]);

        $this->assertSame('Blocked', $result['status']);
        $this->assertStringContainsString('dependency blockers', $result['error']);
    }

    // --- dry run without a WorkflowExecutor ---

    public function testWithoutAWorkflowExecutorIsPending(): void
    {
        $engine = new ExecutionEngine();

        $result = $engine->run('wf_1', [['step_id' => 's1']]);

        $this->assertSame('Pending', $result['status']);
    }

    // --- resume checkpoint ---

    public function testResumeRequestsTheLatestCompleteCheckpoint(): void
    {
        $checkpoints = $this->checkpointManager();
        $checkpointId = $checkpoints->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];
        $checkpoints->confirm($checkpointId, [$this->passingValidationItem()]);
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()), $checkpoints);

        $result = $engine->run('wf_1', [], [], resume: true);

        $this->assertSame($checkpointId, $result['resume_checkpoint']['checkpoint_id']);
    }

    public function testNotResumingNeverRequestsAResumeCheckpoint(): void
    {
        $checkpoints = $this->checkpointManager();
        $checkpointId = $checkpoints->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];
        $checkpoints->confirm($checkpointId, [$this->passingValidationItem()]);
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()), $checkpoints);

        $result = $engine->run('wf_1', [], [], resume: false);

        $this->assertNull($result['resume_checkpoint']);
    }

    // --- halted workflow execution ---

    public function testHaltedWorkflowExecutionIsFailed(): void
    {
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()));

        $result = $engine->run(
            'wf_1',
            [['step_id' => 's1']],
            dispatchTarget: static fn(): array => ['status' => 'Failed', 'error' => 'boom']
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertNotNull($result['error']);
    }

    // --- terminal validation decision mapping ---

    public function testAcceptedValidationIsComplete(): void
    {
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()));

        $result = $engine->run(
            'wf_1',
            [['step_id' => 's1']],
            dispatchTarget: static fn(): array => ['status' => 'Complete'],
            finalValidationEvidence: ['validation_items' => [$this->passingValidationItem()]]
        );

        $this->assertSame('Complete', $result['status']);
        $this->assertSame('ACCEPTED', $result['validation_decision']);
    }

    public function testRejectedValidationIsRejected(): void
    {
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()));

        $result = $engine->run(
            'wf_1',
            [['step_id' => 's1']],
            dispatchTarget: static fn(): array => ['status' => 'Complete'],
            finalValidationEvidence: ['validation_items' => [$this->failingValidationItem()]]
        );

        $this->assertSame('Rejected', $result['status']);
        $this->assertSame('REJECTED', $result['validation_decision']);
    }

    public function testRepairRequiredMapsToRepairRequiredStatus(): void
    {
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()));

        $result = $engine->run(
            'wf_1',
            [['step_id' => 's1']],
            dispatchTarget: static fn(): array => ['status' => 'Complete'],
            finalValidationEvidence: [
                'validation_items' => [['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'FAILED', 'waivable' => true, 'repairable' => true]],
                'validation_options' => ['remaining_attempts' => 1],
            ]
        );

        $this->assertSame('Repair Required', $result['status']);
        $this->assertSame('REPAIR_REQUIRED', $result['validation_decision']);
    }

    public function testAcceptedWithLimitationsIsComplete(): void
    {
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()));

        $result = $engine->run(
            'wf_1',
            [['step_id' => 's1']],
            dispatchTarget: static fn(): array => ['status' => 'Complete'],
            finalValidationEvidence: [
                'validation_items' => [['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'WAIVED', 'waivable' => true]],
            ]
        );

        $this->assertSame('Complete', $result['status']);
        $this->assertSame('ACCEPTED_WITH_LIMITATIONS', $result['validation_decision']);
    }

    public function testClarificationRequiredMapsToBlocked(): void
    {
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()));

        $result = $engine->run(
            'wf_1',
            [['step_id' => 's1']],
            dispatchTarget: static fn(): array => ['status' => 'Complete'],
            finalValidationEvidence: ['validation_options' => ['clarification_needed' => true]]
        );

        $this->assertSame('Blocked', $result['status']);
        $this->assertSame('CLARIFICATION_REQUIRED', $result['validation_decision']);
    }

    // --- STATE-MANAGER hand-off ---

    public function testApplyStateReceivesTheRealDecisionVerbatim(): void
    {
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()));
        $seen = null;

        $engine->run(
            'wf_1',
            [['step_id' => 's1']],
            dispatchTarget: static fn(): array => ['status' => 'Complete'],
            finalValidationEvidence: ['validation_items' => [$this->passingValidationItem()]],
            applyState: function (string $workflowRef, string $decision, array $context) use (&$seen): void {
                $seen = [$workflowRef, $decision];
            }
        );

        $this->assertSame(['wf_1', 'ACCEPTED'], $seen);
    }

    // --- FailureHandler routing for REPAIR_REQUIRED/BLOCKED/RECOVERY_REQUIRED ---

    public function testBlockedDecisionIsRoutedThroughFailureHandler(): void
    {
        $failureHandler = new FailureHandler();
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()), null, null, $failureHandler);
        $seenRecord = null;

        $engine->run(
            'wf_1',
            [['step_id' => 's1']],
            dispatchTarget: static fn(): array => ['status' => 'Complete'],
            finalValidationEvidence: ['validation_items' => [['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'UNAVAILABLE', 'waivable' => false]]],
            recoveryRequest: function (array $record) use (&$seenRecord): array {
                $seenRecord = $record;

                return ['authorized' => false];
            }
        );

        $this->assertSame('Validation Failure', $seenRecord['failure_type']);
        $this->assertSame('BLOCKED', $seenRecord['source_classification_ref']);
    }

    public function testAcceptedDecisionNeverRoutesThroughFailureHandler(): void
    {
        $failureHandler = new FailureHandler();
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()), null, null, $failureHandler);
        $invoked = false;

        $engine->run(
            'wf_1',
            [['step_id' => 's1']],
            dispatchTarget: static fn(): array => ['status' => 'Complete'],
            finalValidationEvidence: ['validation_items' => [$this->passingValidationItem()]],
            recoveryRequest: function () use (&$invoked): array {
                $invoked = true;

                return ['authorized' => false];
            }
        );

        $this->assertFalse($invoked);
    }

    // --- SqliteResultCollector composition ---

    public function testResultSetIsAssembledFromTheResultCollector(): void
    {
        $results = new SqliteResultCollector($this->tempPath('results'));
        $results->collect(['execution_ref' => 'wf_1', 'workflow_step_ref' => 's1', 'subject_ref' => 'artifact_1']);
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()), null, $results);

        $result = $engine->run('wf_1', [['step_id' => 's1']], dispatchTarget: static fn(): array => ['status' => 'Complete']);

        $this->assertCount(1, $result['result_set']);
    }

    public function testHaltedExecutionNeverAssemblesAResultSet(): void
    {
        $results = new SqliteResultCollector($this->tempPath('results'));
        $results->collect(['execution_ref' => 'wf_1', 'workflow_step_ref' => 's1', 'subject_ref' => 'artifact_1']);
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()), null, $results);

        $result = $engine->run(
            'wf_1',
            [['step_id' => 's1']],
            dispatchTarget: static fn(): array => ['status' => 'Failed', 'error' => 'boom']
        );

        $this->assertSame([], $result['result_set']);
    }

    // --- logging composition ---

    public function testRunIsRecordedThroughTheLogger(): void
    {
        $logger = new SqliteExecutionLogger($this->tempPath('logger'));
        $engine = new ExecutionEngine(new WorkflowExecutor(new ActionDispatcher()), null, null, null, $logger);

        $engine->run('wf_1', [['step_id' => 's1']], dispatchTarget: static fn(): array => ['status' => 'Complete'], finalValidationEvidence: ['validation_items' => [$this->passingValidationItem()]]);

        $entries = $logger->search(['execution_id' => 'wf_1', 'action_type' => 'execution_run']);
        $this->assertCount(1, $entries);
        $this->assertSame('Complete', $entries[0]['outcome']);
    }

    public function testWorksWithNoComposedComponentsAtAll(): void
    {
        $engine = new ExecutionEngine();

        $result = $engine->run('wf_1');

        $this->assertSame('Pending', $result['status']);
    }
}
