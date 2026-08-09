<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Engine;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\SqliteStateManager;

final class SqliteStateManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-state-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function manager(): SqliteStateManager
    {
        return new SqliteStateManager($this->tempPath('db'));
    }

    // --- initialize() ---

    public function testMissingRequestIdIsInvalid(): void
    {
        $manager = $this->manager();

        $result = $manager->initialize('', 'goal_1');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testInitializeStartsAtRequested(): void
    {
        $manager = $this->manager();

        $result = $manager->initialize('req_1', 'goal_1');

        $this->assertSame('initialized', $result['outcome']);
        $this->assertSame('REQUESTED', $result['lifecycle_phase']);
    }

    public function testDoubleInitializeIsInvalid(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->initialize('req_1', 'goal_1');

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- advancePhase(): sequential-only ---

    public function testUninitializedRequestCannotAdvance(): void
    {
        $manager = $this->manager();

        $result = $manager->advancePhase('ghost', 'BOOTSTRAPPING');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testSequentialAdvanceSucceeds(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->advancePhase('req_1', 'BOOTSTRAPPING');

        $this->assertSame('transitioned', $result['outcome']);
        $this->assertSame('BOOTSTRAPPING', $result['lifecycle_phase']);
    }

    public function testSkippingAGateIsRejected(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->advancePhase('req_1', 'INTAKE');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('may not skip a required gate', $result['error']);
        $this->assertSame('REQUESTED', $result['lifecycle_phase']);
    }

    public function testUnrecognizedPhaseIsRejected(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->advancePhase('req_1', 'MADE_UP_PHASE');

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testFullSequenceReachesComplete(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $sequence = [
            'BOOTSTRAPPING', 'INTAKE', 'CONTEXT_LOADING', 'ROUTING', 'REASONING', 'PLANNING',
            'PERMISSION_REVIEW', 'EXECUTION_HANDOFF', 'VALIDATION', 'REVIEW', 'REPORTING',
            'OBSERVABILITY_RECORDING', 'MEMORY_UPDATE', 'RETENTION', 'COMPLETE',
        ];

        $last = null;
        foreach ($sequence as $phase) {
            $last = $manager->advancePhase('req_1', $phase);
            $this->assertSame('transitioned', $last['outcome'], "advancing to {$phase} failed");
        }

        $this->assertSame('COMPLETE', $last['lifecycle_phase']);
    }

    public function testCompleteIsTerminal(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $this->driveToPhase($manager, 'req_1', 'RETENTION');
        $manager->advancePhase('req_1', 'COMPLETE');

        $result = $manager->advancePhase('req_1', 'BOOTSTRAPPING');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('terminal', $result['error']);
    }

    // --- exception phases ---

    public function testAnyActivePhaseMayEnterBlocked(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $manager->advancePhase('req_1', 'BOOTSTRAPPING');

        $result = $manager->advancePhase('req_1', 'BLOCKED');

        $this->assertSame('transitioned', $result['outcome']);
        $this->assertSame('BLOCKED', $result['lifecycle_phase']);
    }

    public function testForwardPhaseFromBlockedIsRejected(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $manager->advancePhase('req_1', 'BOOTSTRAPPING');
        $manager->advancePhase('req_1', 'BLOCKED');

        $result = $manager->advancePhase('req_1', 'INTAKE');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('resolveBlocker()', $result['error']);
    }

    public function testFailedIsTerminal(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $manager->advancePhase('req_1', 'FAILED');

        $result = $manager->advancePhase('req_1', 'BOOTSTRAPPING');

        $this->assertSame('rejected', $result['outcome']);
    }

    // --- resolveBlocker(): returns to the preserved responsible phase ---

    public function testResolveBlockerReturnsToThePreservedPhase(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $manager->advancePhase('req_1', 'BOOTSTRAPPING');
        $manager->advancePhase('req_1', 'INTAKE');
        $manager->advancePhase('req_1', 'BLOCKED');

        $result = $manager->resolveBlocker('req_1');

        $this->assertSame('transitioned', $result['outcome']);
        $this->assertSame('INTAKE', $result['lifecycle_phase']);
    }

    public function testResolveBlockerRejectsWhenNotBlockedOrRecovering(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->resolveBlocker('req_1');

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testRecoveryRequiredPreservesTheInterruptedPhaseToo(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $manager->advancePhase('req_1', 'BOOTSTRAPPING');
        $manager->advancePhase('req_1', 'INTAKE');
        $manager->advancePhase('req_1', 'RECOVERY_REQUIRED');

        $result = $manager->resolveBlocker('req_1');

        $this->assertSame('INTAKE', $result['lifecycle_phase']);
    }

    // --- recordBlocker(): named condition, responsible phase, next safe action ---

    public function testRecordBlockerRequiresReasonAndNextSafeAction(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->recordBlocker('req_1', '', '');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testRecordBlockerEntersBlockedAndRecordsEvidence(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->recordBlocker('req_1', 'missing credential', 'obtain credential from operator');

        $this->assertSame('transitioned', $result['outcome']);
        $state = $manager->currentState('req_1');
        $this->assertSame('missing credential', $state['blocker_reason']);
        $this->assertSame('obtain credential from operator', $state['next_safe_action']);
    }

    // --- task state: real guards ---

    public function testTaskCannotMoveToInProgressWithoutSatisfiedDependencies(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->recordTaskState('req_1', 'task_1', 'IN_PROGRESS', false);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('dependencies are satisfied', $result['error']);
    }

    public function testTaskMovesToInProgressWhenDependenciesSatisfied(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->recordTaskState('req_1', 'task_1', 'IN_PROGRESS', true);

        $this->assertSame('transitioned', $result['outcome']);
    }

    public function testDependenciesSatisfiedFlagIsPreservedAcrossLaterTransitions(): void
    {
        // Regression: a later transition must not silently reset an earlier waiver.
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $manager->recordTaskState('req_1', 'task_1', 'IN_PROGRESS', true);

        $result = $manager->recordTaskState('req_1', 'task_1', 'WAITING');
        $backToProgress = $manager->recordTaskState('req_1', 'task_1', 'IN_PROGRESS');

        $this->assertSame('transitioned', $result['outcome']);
        $this->assertSame('transitioned', $backToProgress['outcome']);
    }

    public function testTaskCannotCompleteWithoutAnAcceptedValidationDecision(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->recordTaskState('req_1', 'task_1', 'COMPLETED');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('ACCEPTED', $result['error']);
    }

    public function testUnrecognizedTaskStateIsInvalid(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->recordTaskState('req_1', 'task_1', 'MADE_UP_STATE');

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- recordValidationDecision(): the literal 7-row Required State Effect table ---

    public function testUnrecognizedDecisionIsInvalid(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->recordValidationDecision('req_1', 'task_1', 'MADE_UP_DECISION');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testAcceptedCompletesTheTask(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->recordValidationDecision('req_1', 'task_1', 'ACCEPTED');

        $this->assertSame('recorded', $result['outcome']);
        $state = $manager->currentState('req_1');
        $this->assertSame('COMPLETED', $state['tasks'][0]['state']);
    }

    public function testAcceptedWithLimitationsWithoutPolicyPermissionDoesNotComplete(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $manager->recordValidationDecision('req_1', 'task_1', 'ACCEPTED_WITH_LIMITATIONS');

        $state = $manager->currentState('req_1');
        $this->assertNotSame('COMPLETED', $state['tasks'][0]['state']);
    }

    public function testAcceptedWithLimitationsWithPolicyPermissionCompletes(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $manager->recordValidationDecision('req_1', 'task_1', 'ACCEPTED_WITH_LIMITATIONS', ['policy_permits_limitations' => true]);

        $state = $manager->currentState('req_1');
        $this->assertSame('COMPLETED', $state['tasks'][0]['state']);
    }

    public function testRepairRequiredMovesTaskToValidationFailedAndRegressesLifecycle(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $manager->advancePhase('req_1', 'BOOTSTRAPPING');
        $manager->advancePhase('req_1', 'INTAKE');

        $result = $manager->recordValidationDecision('req_1', 'task_1', 'REPAIR_REQUIRED', ['responsible_phase' => 'INTAKE']);

        $this->assertSame('INTAKE', $result['lifecycle_phase']);
        $state = $manager->currentState('req_1');
        $this->assertSame('VALIDATION_FAILED', $state['tasks'][0]['state']);
    }

    public function testClarificationRequiredMovesTaskToWaitingWithResumeCondition(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $manager->recordValidationDecision('req_1', 'task_1', 'CLARIFICATION_REQUIRED', ['resume_condition' => 'confirm target environment']);

        $state = $manager->currentState('req_1');
        $this->assertSame('WAITING', $state['tasks'][0]['state']);
        $this->assertSame('confirm target environment', $state['tasks'][0]['resume_condition']);
    }

    public function testBlockedDecisionBlocksBothTaskAndLifecycle(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->recordValidationDecision('req_1', 'task_1', 'BLOCKED', ['next_safe_action' => 'escalate to governance']);

        $this->assertSame('BLOCKED', $result['lifecycle_phase']);
        $state = $manager->currentState('req_1');
        $this->assertSame('BLOCKED', $state['tasks'][0]['state']);
        $this->assertSame('escalate to governance', $state['next_safe_action']);
    }

    public function testRecoveryRequiredMovesLifecycleToRecoveryRequired(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->recordValidationDecision('req_1', 'task_1', 'RECOVERY_REQUIRED');

        $this->assertSame('RECOVERY_REQUIRED', $result['lifecycle_phase']);
    }

    public function testRejectedMovesLifecycleToFailedByDefault(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->recordValidationDecision('req_1', 'task_1', 'REJECTED');

        $this->assertSame('FAILED', $result['lifecycle_phase']);
    }

    public function testRejectedHonorsAnExplicitTerminalOverride(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->recordValidationDecision('req_1', 'task_1', 'REJECTED', ['terminal_override' => 'BLOCKED']);

        $this->assertSame('BLOCKED', $result['lifecycle_phase']);
    }

    // --- assignOwner(): Single Ownership Rule ---

    public function testFirstAssignmentSucceedsWithoutAnExpectedOwner(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->assignOwner('req_1', 'task_1', 'agent_a');

        $this->assertSame('assigned', $result['outcome']);
    }

    public function testReassignmentWithoutTheCorrectCurrentOwnerIsRejected(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $manager->assignOwner('req_1', 'task_1', 'agent_a');

        $result = $manager->assignOwner('req_1', 'task_1', 'agent_b');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('currently owned by "agent_a"', $result['error']);
    }

    public function testReassignmentWithTheCorrectCurrentOwnerSucceedsAsAHandoff(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $manager->assignOwner('req_1', 'task_1', 'agent_a');

        $result = $manager->assignOwner('req_1', 'task_1', 'agent_b', 'agent_a');

        $this->assertSame('assigned', $result['outcome']);
        $this->assertSame('agent_b', $result['owner']);
    }

    // --- invalidateStale() ---

    public function testInvalidateStaleWithNoExistingTaskIsInvalid(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');

        $result = $manager->invalidateStale('req_1', 'ghost_task', 'acceptance criterion changed');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testInvalidateStaleRecordsAndAppendsALimitation(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $manager->recordValidationDecision('req_1', 'task_1', 'ACCEPTED');

        $result = $manager->invalidateStale('req_1', 'task_1', 'acceptance criterion changed');

        $this->assertSame('invalidated', $result['outcome']);
        $state = $manager->currentState('req_1');
        $this->assertSame('STALE', $state['tasks'][0]['last_validation_decision']);
        $this->assertNotEmpty($state['limitations']);
    }

    // --- completion must not erase limitations ---

    public function testCompletionNeverErasesEarlierLimitations(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $manager->recordValidationDecision('req_1', 'task_1', 'ACCEPTED');
        $manager->invalidateStale('req_1', 'task_1', 'dependency changed');

        // Re-accept after the stale invalidation -- task completes again.
        $manager->recordValidationDecision('req_1', 'task_1', 'ACCEPTED');

        $state = $manager->currentState('req_1');
        $this->assertNotEmpty($state['limitations']);
        $this->assertSame('COMPLETED', $state['tasks'][0]['state']);
    }

    // --- currentState() / history() ---

    public function testCurrentStateIsNullForAnUnknownRequest(): void
    {
        $manager = $this->manager();

        $this->assertNull($manager->currentState('ghost'));
    }

    public function testCurrentStateReturnsTasksAndLimitations(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $manager->recordTaskState('req_1', 'task_1', 'READY');

        $state = $manager->currentState('req_1');

        $this->assertSame('req_1', $state['request_id']);
        $this->assertCount(1, $state['tasks']);
        $this->assertSame('READY', $state['tasks'][0]['state']);
    }

    public function testHistoryRecordsEveryEventInOrder(): void
    {
        $manager = $this->manager();
        $manager->initialize('req_1', 'goal_1');
        $manager->advancePhase('req_1', 'BOOTSTRAPPING');
        $manager->recordTaskState('req_1', 'task_1', 'READY');

        $history = $manager->history('req_1');

        $this->assertSame('initialized', $history[0]['event_type']);
        $this->assertSame('phase_transition', $history[1]['event_type']);
        $this->assertSame('task_transition', $history[2]['event_type']);
    }

    /**
     * Drives a request from REQUESTED to the target phase, one valid
     * sequential hop at a time.
     */
    private function driveToPhase(SqliteStateManager $manager, string $requestId, string $targetPhase): void
    {
        $sequence = [
            'BOOTSTRAPPING', 'INTAKE', 'CONTEXT_LOADING', 'ROUTING', 'REASONING', 'PLANNING',
            'PERMISSION_REVIEW', 'EXECUTION_HANDOFF', 'VALIDATION', 'REVIEW', 'REPORTING',
            'OBSERVABILITY_RECORDING', 'MEMORY_UPDATE', 'RETENTION', 'COMPLETE',
        ];

        foreach ($sequence as $phase) {
            $manager->advancePhase($requestId, $phase);

            if ($phase === $targetPhase) {
                return;
            }
        }
    }
}
