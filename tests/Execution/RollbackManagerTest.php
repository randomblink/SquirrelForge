<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Execution;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Execution\RollbackManager;
use SquirrelForge\Execution\SqliteCheckpointManager;
use SquirrelForge\Execution\SqliteExecutionLogger;

final class RollbackManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-rollback-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{workflow_ref: string, authorization_ref: string, scope: string}
     */
    private function minimalRequest(array $overrides = []): array
    {
        return array_replace([
            'workflow_ref' => 'wf_1',
            'authorization_ref' => 'recovery_decision_1',
            'scope' => 'Stage',
        ], $overrides);
    }

    // --- authorization / shape ---

    public function testRefusesWithoutAuthorizationRef(): void
    {
        $manager = new RollbackManager();
        $request = $this->minimalRequest();
        unset($request['authorization_ref']);

        $result = $manager->rollback($request);

        $this->assertSame('Failed', $result['status']);
        $this->assertStringContainsString('authorization_ref', $result['error']);
    }

    public function testRefusesWithoutWorkflowRef(): void
    {
        $manager = new RollbackManager();
        $request = $this->minimalRequest();
        unset($request['workflow_ref']);

        $result = $manager->rollback($request);

        $this->assertSame('Failed', $result['status']);
    }

    public function testRejectsUnknownScope(): void
    {
        $manager = new RollbackManager();

        $result = $manager->rollback($this->minimalRequest(['scope' => 'Universe']));

        $this->assertSame('Failed', $result['status']);
        $this->assertStringContainsString('Rollback Levels', $result['error']);
    }

    // --- without a CheckpointManager (dry run integrity check) ---

    public function testWithoutACheckpointManagerIntegrityIsNeverFabricated(): void
    {
        $manager = new RollbackManager();

        $result = $manager->rollback($this->minimalRequest());

        $this->assertSame('Successful', $result['status']);
    }

    // --- with a CheckpointManager: locating the target checkpoint ---

    public function testNoCompleteCheckpointExistsIsFailed(): void
    {
        $checkpoints = new SqliteCheckpointManager($this->tempPath('checkpoints'));
        $manager = new RollbackManager($checkpoints);

        $result = $manager->rollback($this->minimalRequest());

        $this->assertSame('Failed', $result['status']);
        $this->assertStringContainsString('No Complete checkpoint', $result['error']);
    }

    public function testUsesTheLatestCompleteCheckpointWhenNoneSupplied(): void
    {
        $checkpoints = new SqliteCheckpointManager($this->tempPath('checkpoints'));
        $checkpointId = $checkpoints->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];
        $checkpoints->confirm($checkpointId, [['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'PASSED']]);
        $manager = new RollbackManager($checkpoints);

        $result = $manager->rollback(
            $this->minimalRequest(),
            checkpointEvidence: static fn(string $scope): array => ['validation_items' => [['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'PASSED']]]
        );

        $this->assertSame($checkpointId, $result['checkpoint_ref']);
    }

    public function testExplicitTargetCheckpointOverridesLatestComplete(): void
    {
        $checkpoints = new SqliteCheckpointManager($this->tempPath('checkpoints'));
        $earlier = $checkpoints->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];
        $checkpoints->confirm($earlier, [['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'PASSED']]);
        $manager = new RollbackManager($checkpoints);

        $result = $manager->rollback(
            $this->minimalRequest(['target_checkpoint_id' => 'checkpoint_manual_1']),
            checkpointEvidence: static fn(string $scope): array => ['validation_items' => [['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'PASSED']]]
        );

        $this->assertSame('checkpoint_manual_1', $result['checkpoint_ref']);
    }

    // --- integrity confirmation is the hard gate ---

    public function testFailedIntegrityConfirmationFailsTheWholeRollback(): void
    {
        $checkpoints = new SqliteCheckpointManager($this->tempPath('checkpoints'));
        $checkpointId = $checkpoints->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];
        $checkpoints->confirm($checkpointId, [['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'PASSED']]);
        $manager = new RollbackManager($checkpoints);

        $result = $manager->rollback(
            $this->minimalRequest(),
            [['action_id' => 'a1']],
            reverseAction: static fn(array $action): array => ['reversed' => true],
            checkpointEvidence: static fn(string $scope): array => [
                'validation_items' => [['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'FAILED', 'waivable' => false]],
            ]
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertSame(['a1'], $result['reversed_actions']);
    }

    public function testPassingIntegrityConfirmationCreatesARollbackRestoredCheckpoint(): void
    {
        $checkpoints = new SqliteCheckpointManager($this->tempPath('checkpoints'));
        $checkpointId = $checkpoints->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];
        $checkpoints->confirm($checkpointId, [['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'PASSED']]);
        $manager = new RollbackManager($checkpoints);

        $manager->rollback(
            $this->minimalRequest(),
            checkpointEvidence: static fn(string $scope): array => ['validation_items' => [['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'PASSED']]]
        );

        $history = $checkpoints->history('wf_1');
        $this->assertCount(2, $history);
        $this->assertSame('rollback_restored:Stage', $history[1]['stage']);
        $this->assertSame('Complete', $history[1]['status']);
    }

    // --- action reversal ---

    public function testNonReversibleActionsAreRecordedNotFailed(): void
    {
        $manager = new RollbackManager();

        $result = $manager->rollback($this->minimalRequest(), [['action_id' => 'a1', 'reversible' => false]]);

        $this->assertSame('Successful', $result['status']);
        $this->assertSame(['a1'], $result['not_reversible_actions']);
        $this->assertSame([], $result['failed_actions']);
    }

    public function testSuccessfulReversalIsSuccessful(): void
    {
        $manager = new RollbackManager();

        $result = $manager->rollback(
            $this->minimalRequest(),
            [['action_id' => 'a1'], ['action_id' => 'a2']],
            reverseAction: static fn(array $action): array => ['reversed' => true]
        );

        $this->assertSame('Successful', $result['status']);
        $this->assertSame(['a1', 'a2'], $result['reversed_actions']);
    }

    public function testFailedReversalIsPartialNotFailed(): void
    {
        $manager = new RollbackManager();

        $result = $manager->rollback(
            $this->minimalRequest(),
            [['action_id' => 'a1'], ['action_id' => 'a2']],
            reverseAction: static fn(array $action): array => ['reversed' => $action['action_id'] === 'a1']
        );

        $this->assertSame('Partial', $result['status']);
        $this->assertSame(['a1'], $result['reversed_actions']);
        $this->assertSame(['a2'], $result['failed_actions']);
    }

    public function testReverseActionThrowingIsAFailureNotAnUncaughtException(): void
    {
        $manager = new RollbackManager();

        $result = $manager->rollback(
            $this->minimalRequest(),
            [['action_id' => 'a1']],
            reverseAction: static function (array $action): array {
                throw new RuntimeException('cannot undo');
            }
        );

        $this->assertSame('Partial', $result['status']);
        $this->assertSame(['a1'], $result['failed_actions']);
    }

    public function testOmittingReverseActionAttemptsNothing(): void
    {
        $manager = new RollbackManager();

        $result = $manager->rollback($this->minimalRequest(), [['action_id' => 'a1']]);

        $this->assertSame([], $result['reversed_actions']);
        $this->assertSame([], $result['failed_actions']);
    }

    // --- logging composition ---

    public function testRollbackIsRecordedThroughTheLogger(): void
    {
        $logger = new SqliteExecutionLogger($this->tempPath('db'));
        $manager = new RollbackManager(null, $logger);

        $result = $manager->rollback($this->minimalRequest());

        $history = $logger->history('wf_1');
        $this->assertCount(1, $history);
        $this->assertSame('Successful', $history[0]['outcome']);
        $this->assertSame('rollback_manager', $history[0]['actor']);
        $this->assertSame($result['rollback_id'], $history[0]['task_id']);
    }

    public function testWorksWithoutALoggerOrCheckpointManager(): void
    {
        $manager = new RollbackManager(null, null);

        $result = $manager->rollback($this->minimalRequest());

        $this->assertSame('Successful', $result['status']);
        $this->assertNotNull($result['rollback_id']);
    }
}
