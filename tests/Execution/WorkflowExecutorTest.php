<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Execution;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Execution\ActionDispatcher;
use SquirrelForge\Execution\ExecutionMonitor;
use SquirrelForge\Execution\FailureHandler;
use SquirrelForge\Execution\SqliteCheckpointManager;
use SquirrelForge\Execution\SqliteExecutionLogger;
use SquirrelForge\Execution\WorkflowExecutor;

final class WorkflowExecutorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-workflow-executor-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function logger(): SqliteExecutionLogger
    {
        return new SqliteExecutionLogger($this->tempPath('db'));
    }

    private function checkpointManager(): SqliteCheckpointManager
    {
        return new SqliteCheckpointManager($this->tempPath('checkpoints'));
    }

    // --- basic sequencing ---

    public function testEmptyStepsCompletesTrivially(): void
    {
        $executor = new WorkflowExecutor();

        $result = $executor->execute('wf_1', []);

        $this->assertSame('Completed', $result['workflow_status']);
        $this->assertSame([], $result['steps']);
    }

    public function testStepsWithoutADispatcherIsADryRunAndPasses(): void
    {
        $executor = new WorkflowExecutor();

        $result = $executor->execute('wf_1', [['step_id' => 's1']]);

        $this->assertSame('Completed', $result['workflow_status']);
        $this->assertSame('Running', $result['steps'][0]['status']);
    }

    public function testBlockedStepIsSkippedWithStopConditionsAsJustification(): void
    {
        $executor = new WorkflowExecutor();

        $result = $executor->execute('wf_1', [
            ['step_id' => 's1', 'status' => 'blocked', 'stop_conditions' => ['Blocked by dependency "d1".']],
        ]);

        $this->assertSame('Completed', $result['workflow_status']);
        $this->assertSame('Skipped', $result['steps'][0]['status']);
        $this->assertStringContainsString('d1', $result['steps'][0]['error']);
    }

    public function testBlockedStepDoesNotHaltRemainingSteps(): void
    {
        $executor = new WorkflowExecutor(new ActionDispatcher());

        $result = $executor->execute('wf_1', [
            ['step_id' => 's1', 'status' => 'blocked', 'stop_conditions' => []],
            ['step_id' => 's2'],
        ], dispatchTarget: static fn(): array => ['status' => 'Complete']);

        $this->assertCount(2, $result['steps']);
        $this->assertSame('Skipped', $result['steps'][0]['status']);
        $this->assertSame('Passed', $result['steps'][1]['status']);
    }

    public function testSuccessfulStepMapsCompleteToPassed(): void
    {
        $executor = new WorkflowExecutor(new ActionDispatcher());

        $result = $executor->execute('wf_1', [['step_id' => 's1']], dispatchTarget: static fn(): array => ['status' => 'Complete']);

        $this->assertSame('Passed', $result['steps'][0]['status']);
        $this->assertSame('Completed', $result['workflow_status']);
    }

    // --- failure handling ---

    public function testUnrecoveredFailureHaltsAndStopsFurtherSteps(): void
    {
        $executor = new WorkflowExecutor(new ActionDispatcher());

        $result = $executor->execute('wf_1', [
            ['step_id' => 's1'],
            ['step_id' => 's2'],
        ], dispatchTarget: static function (string $target, array $action): array {
            if ($action['action_id'] === 's1') {
                throw new RuntimeException('unreachable');
            }

            return ['status' => 'Complete'];
        });

        $this->assertSame('Halted', $result['workflow_status']);
        $this->assertCount(1, $result['steps']);
        $this->assertSame('Failed', $result['steps'][0]['status']);
        $this->assertNotNull($result['error']);
    }

    public function testAuthorizedSkipRecoveryContinuesPastTheFailure(): void
    {
        $failureHandler = new FailureHandler();
        $executor = new WorkflowExecutor(new ActionDispatcher(null, $failureHandler));

        $result = $executor->execute(
            'wf_1',
            [['step_id' => 's1'], ['step_id' => 's2']],
            dispatchTarget: static function (string $target, array $action): array {
                if ($action['action_id'] === 's1') {
                    throw new RuntimeException('unreachable');
                }

                return ['status' => 'Complete'];
            },
            recoveryRequest: static fn(array $record): array => ['authorized' => true, 'operation' => 'Skip', 'target_component' => 'workflow_executor']
        );

        $this->assertSame('Completed', $result['workflow_status']);
        $this->assertCount(2, $result['steps']);
        $this->assertSame('Skipped', $result['steps'][0]['status']);
        $this->assertSame('Passed', $result['steps'][1]['status']);
    }

    public function testDispatchFailureIsNotDoubleReportedToFailureHandler(): void
    {
        $logger = $this->logger();
        $failureHandler = new FailureHandler($logger);
        $executor = new WorkflowExecutor(new ActionDispatcher($logger, $failureHandler), null, $failureHandler, null, $logger);

        $executor->execute(
            'wf_1',
            [['step_id' => 's1']],
            dispatchTarget: static function (): array {
                throw new RuntimeException('unreachable');
            },
            recoveryRequest: static fn(array $record): array => ['authorized' => false]
        );

        $intakeEntries = $logger->search(['execution_id' => 'wf_1', 'action_type' => 'failure_intake']);
        $this->assertCount(1, $intakeEntries);
        $this->assertSame('action_dispatcher', $intakeEntries[0]['actor']);
    }

    // --- checkpoints ---

    public function testStepWithoutACheckpointFieldNeverTouchesCheckpointManager(): void
    {
        $checkpointManager = $this->checkpointManager();
        $executor = new WorkflowExecutor(new ActionDispatcher(), $checkpointManager);

        $executor->execute('wf_1', [['step_id' => 's1']], dispatchTarget: static fn(): array => ['status' => 'Complete']);

        $this->assertSame([], $checkpointManager->history('wf_1'));
    }

    public function testPassingCheckpointKeepsTheStepPassed(): void
    {
        $checkpointManager = $this->checkpointManager();
        $executor = new WorkflowExecutor(new ActionDispatcher(), $checkpointManager);

        $result = $executor->execute(
            'wf_1',
            [['step_id' => 's1', 'checkpoint' => 'draft_complete']],
            dispatchTarget: static fn(): array => ['status' => 'Complete'],
            checkpointEvidence: static fn(array $step): array => [
                'validation_items' => [['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'PASSED']],
            ]
        );

        $this->assertSame('Passed', $result['steps'][0]['status']);
        $this->assertSame('Completed', $result['workflow_status']);
        $this->assertCount(1, $checkpointManager->history('wf_1'));
        $this->assertSame('Complete', $checkpointManager->history('wf_1')[0]['status']);
    }

    public function testFailingValidationCheckpointHaltsAndReportsValidationFailure(): void
    {
        $checkpointManager = $this->checkpointManager();
        $failureHandler = new FailureHandler();
        $executor = new WorkflowExecutor(new ActionDispatcher(), $checkpointManager, $failureHandler);
        $seenRecord = null;

        $result = $executor->execute(
            'wf_1',
            [['step_id' => 's1', 'checkpoint' => 'draft_complete']],
            dispatchTarget: static fn(): array => ['status' => 'Complete'],
            checkpointEvidence: static fn(array $step): array => [
                'validation_items' => [['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'FAILED', 'waivable' => false]],
            ],
            recoveryRequest: function (array $record) use (&$seenRecord): array {
                $seenRecord = $record;

                return ['authorized' => false];
            }
        );

        $this->assertSame('Halted', $result['workflow_status']);
        $this->assertSame('Failed', $result['steps'][0]['status']);
        $this->assertSame('Validation Failure', $seenRecord['failure_type']);
        $this->assertSame('REJECTED', $seenRecord['source_classification_ref']);
    }

    public function testFailingRuleCheckpointReportsRuleFailure(): void
    {
        $checkpointManager = $this->checkpointManager();
        $failureHandler = new FailureHandler();
        $executor = new WorkflowExecutor(new ActionDispatcher(), $checkpointManager, $failureHandler);
        $seenRecord = null;

        $executor->execute(
            'wf_1',
            [['step_id' => 's1', 'checkpoint' => 'draft_complete']],
            dispatchTarget: static fn(): array => ['status' => 'Complete'],
            checkpointEvidence: static fn(array $step): array => [
                'rules' => [['id' => 'r1', 'source' => 'domain', 'condition' => ['type' => 'boolean', 'field' => 'ok', 'equals' => true]]],
                'rule_context' => ['ok' => false],
            ],
            recoveryRequest: function (array $record) use (&$seenRecord): array {
                $seenRecord = $record;

                return ['authorized' => false];
            }
        );

        $this->assertSame('Rule Failure', $seenRecord['failure_type']);
        $this->assertSame('Failed', $seenRecord['source_classification_ref']);
    }

    // --- ExecutionMonitor / logger composition ---

    public function testStepsAreTrackedThroughTheExecutionMonitor(): void
    {
        $logger = $this->logger();
        $monitor = new ExecutionMonitor($logger);
        $executor = new WorkflowExecutor(new ActionDispatcher(), null, null, $monitor);

        $executor->execute('wf_1', [['step_id' => 's1']], dispatchTarget: static fn(): array => ['status' => 'Complete']);

        $monitorEntries = $logger->search(['execution_id' => 'wf_1', 'action_type' => 'monitor_tick']);
        $this->assertCount(1, $monitorEntries);
        $this->assertSame('Complete', $monitorEntries[0]['outcome']);
    }

    public function testWorkflowCompletionIsRecordedThroughTheLogger(): void
    {
        $logger = $this->logger();
        $executor = new WorkflowExecutor(new ActionDispatcher(), null, null, null, $logger);

        $executor->execute('wf_1', [['step_id' => 's1']], dispatchTarget: static fn(): array => ['status' => 'Complete']);

        $completionEntries = $logger->search(['execution_id' => 'wf_1', 'action_type' => 'workflow_completion']);
        $this->assertCount(1, $completionEntries);
        $this->assertSame('Completed', $completionEntries[0]['outcome']);
    }

    public function testWorksWithNoComposedComponentsAtAll(): void
    {
        $executor = new WorkflowExecutor();

        $result = $executor->execute('wf_1', [['step_id' => 's1'], ['step_id' => 's2']]);

        $this->assertSame('Completed', $result['workflow_status']);
        $this->assertCount(2, $result['steps']);
    }

    public function testStepWithoutAStepIdIsSkippedFromTheResults(): void
    {
        $executor = new WorkflowExecutor();

        $result = $executor->execute('wf_1', [['step_id' => ''], ['step_id' => 's1']]);

        $this->assertCount(1, $result['steps']);
        $this->assertSame('s1', $result['steps'][0]['step_id']);
    }
}
