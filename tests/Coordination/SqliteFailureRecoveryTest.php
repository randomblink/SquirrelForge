<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Coordination;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Coordination\SqliteFailureRecovery;
use SquirrelForge\Engine\TaskRouter;

final class SqliteFailureRecoveryTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-failure-recovery-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function recovery(?TaskRouter $taskRouter = null): SqliteFailureRecovery
    {
        return new SqliteFailureRecovery($this->tempPath('db'), $taskRouter);
    }

    // --- classification: FailureHandler's own vocabulary mapped ---

    public function testValidationFailureMapsDirectly(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Validation Failure']);

        $this->assertSame('Validation Failure', $result['failure_type']);
        $this->assertSame('Rollback', $result['operation']);
    }

    public function testRuleFailureMapsToValidationFailure(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Rule Failure']);

        $this->assertSame('Validation Failure', $result['failure_type']);
    }

    public function testTimeoutFailureMapsToAgentFailure(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Timeout Failure']);

        $this->assertSame('Agent Failure', $result['failure_type']);
    }

    public function testExecutionFailureMapsToAgentFailure(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Execution Failure']);

        $this->assertSame('Agent Failure', $result['failure_type']);
    }

    public function testPrerequisiteFailureMapsToResourceFailure(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Prerequisite Failure']);

        $this->assertSame('Resource Failure', $result['failure_type']);
    }

    public function testDispatchFailureMapsToCommunicationFailure(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Dispatch Failure']);

        $this->assertSame('Communication Failure', $result['failure_type']);
    }

    public function testDirectlySuppliedRecoveryVocabularyIsRespected(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Workflow Failure']);

        $this->assertSame('Workflow Failure', $result['failure_type']);
    }

    public function testUnrecognizedFailureTypeIsUnknown(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Made Up Failure']);

        $this->assertSame('Unknown Failure', $result['failure_type']);
    }

    public function testMissingFailureTypeIsUnknown(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['action_ref' => 'action_1']);

        $this->assertSame('Unknown Failure', $result['failure_type']);
    }

    // --- strategies ---

    public function testDependencyFailureRetriesAfterReloadingDependencies(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Dependency Failure']);

        $this->assertTrue($result['authorized']);
        $this->assertSame('Retry', $result['operation']);
        $this->assertSame('Reload dependencies', $result['strategy']);
    }

    public function testCommunicationFailureRetries(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Communication Failure']);

        $this->assertSame('Retry', $result['operation']);
        $this->assertSame('Retry the operation', $result['strategy']);
    }

    public function testWorkflowFailureIsBlockedWithNoOperation(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Workflow Failure']);

        $this->assertFalse($result['authorized']);
        $this->assertNull($result['operation']);
        $this->assertSame('BLOCKED', $result['state_action']);
    }

    public function testUnknownFailureIsAlsoBlocked(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['action_ref' => 'action_1']);

        $this->assertFalse($result['authorized']);
        $this->assertSame('BLOCKED', $result['state_action']);
    }

    // --- Agent Failure: TaskRouter composition ---

    public function testAgentFailureRequestsReassignmentThroughTaskRouter(): void
    {
        $taskRouter = new TaskRouter();
        $recovery = $this->recovery($taskRouter);

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Agent Failure']);

        $this->assertSame('Retry', $result['operation']);
        $this->assertSame('Request reassignment through the Task Router', $result['strategy']);
    }

    public function testAgentFailureNeverCrashesWithoutATaskRouter(): void
    {
        $recovery = $this->recovery(null);

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Agent Failure']);

        $this->assertTrue($result['authorized']);
    }

    // --- escalation rules ---

    public function testRetryLimitExceededEscalates(): void
    {
        $recovery = $this->recovery();
        $failure = ['action_ref' => 'action_1', 'failure_type' => 'Dependency Failure'];

        $recovery->decide($failure, ['max_retries' => 2]);
        $recovery->decide($failure, ['max_retries' => 2]);
        $result = $recovery->decide($failure, ['max_retries' => 2]);

        $this->assertSame('Escalate', $result['operation']);
        $this->assertStringContainsString('Retry limit', $result['reason']);
    }

    public function testEscalationStaysEscalatedOnANextCall(): void
    {
        $recovery = $this->recovery();
        $failure = ['action_ref' => 'action_1', 'failure_type' => 'Dependency Failure'];

        $recovery->decide($failure, ['max_retries' => 1]);
        $recovery->decide($failure, ['max_retries' => 1]);
        $result = $recovery->decide($failure, ['max_retries' => 1]);

        $this->assertSame('Escalate', $result['operation']);
    }

    public function testCriticalDependencyUnresolvedEscalatesImmediately(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(
            ['action_ref' => 'action_1', 'failure_type' => 'Dependency Failure'],
            ['critical_dependency_unresolved' => true]
        );

        $this->assertSame('Escalate', $result['operation']);
        $this->assertStringContainsString('critical dependency', $result['reason']);
    }

    public function testSecurityRiskEscalatesImmediately(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(
            ['action_ref' => 'action_1', 'failure_type' => 'Validation Failure'],
            ['security_or_integrity_at_risk' => true]
        );

        $this->assertSame('Escalate', $result['operation']);
    }

    public function testHumanApprovalRequiredEscalatesImmediately(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(
            ['action_ref' => 'action_1', 'failure_type' => 'Validation Failure'],
            ['human_approval_required' => true]
        );

        $this->assertSame('Escalate', $result['operation']);
    }

    public function testDifferentFailureTypesHaveIndependentRetryCounts(): void
    {
        $recovery = $this->recovery();
        $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Dependency Failure'], ['max_retries' => 1]);
        $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Dependency Failure'], ['max_retries' => 1]);

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Communication Failure'], ['max_retries' => 1]);

        $this->assertSame('Retry', $result['operation']);
    }

    // --- STATE-MANAGER hand-off ---

    public function testApplyStateReceivesRecoveryRequiredFirst(): void
    {
        $recovery = $this->recovery();
        $seen = [];

        $recovery->decide(
            ['action_ref' => 'action_1', 'failure_type' => 'Dependency Failure'],
            [],
            function (string $taskId, string $state) use (&$seen): void {
                $seen[] = [$taskId, $state];
            }
        );

        $this->assertSame([['action_1', 'RECOVERY_REQUIRED']], $seen);
    }

    public function testApplyStateReceivesBlockedWhenNoStrategyApplies(): void
    {
        $recovery = $this->recovery();
        $seen = [];

        $recovery->decide(
            ['action_ref' => 'action_1', 'failure_type' => 'Workflow Failure'],
            [],
            function (string $taskId, string $state) use (&$seen): void {
                $seen[] = $state;
            }
        );

        $this->assertSame(['RECOVERY_REQUIRED', 'BLOCKED'], $seen);
    }

    // --- recovery record persistence ---

    public function testRecoveryRecordIsPersistedAndRetrievable(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Dependency Failure']);

        $record = $recovery->get($result['recovery_record_ref']);
        $this->assertSame('action_1', $record['task_id']);
        $this->assertSame('Dependency Failure', $record['failure_type']);
        $this->assertSame('Recovered', $record['outcome']);
    }

    public function testGetUnknownRecoveryReturnsNull(): void
    {
        $recovery = $this->recovery();

        $this->assertNull($recovery->get('ghost'));
    }

    public function testHistoryReturnsAllRecordsForATaskInOrder(): void
    {
        $recovery = $this->recovery();
        $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Dependency Failure'], ['max_retries' => 5]);
        $recovery->decide(['action_ref' => 'action_1', 'failure_type' => 'Communication Failure'], ['max_retries' => 5]);

        $history = $recovery->history('action_1');

        $this->assertCount(2, $history);
        $this->assertSame('Dependency Failure', $history[0]['failure_type']);
        $this->assertSame('Communication Failure', $history[1]['failure_type']);
    }

    public function testUsesExecutionRefWhenActionRefIsAbsent(): void
    {
        $recovery = $this->recovery();

        $result = $recovery->decide(['execution_ref' => 'exec_1', 'failure_type' => 'Dependency Failure']);
        $record = $recovery->get($result['recovery_record_ref']);

        $this->assertSame('exec_1', $record['task_id']);
    }
}
