<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Memory\EpisodicMemory;
use SquirrelForge\Memory\InMemoryStore;
use SquirrelForge\Memory\WorkingMemory;

final class WorkingMemoryTest extends TestCase
{
    public function testInitializeAndSnapshotRoundTripCacheableFields(): void
    {
        $memory = new WorkingMemory();
        $memory->initialize('task_1', ['active_goal' => 'goal_1', 'current_task' => 'task_1']);

        $snapshot = $memory->snapshot('task_1');

        $this->assertSame('goal_1', $snapshot['active_goal']);
        $this->assertSame('task_1', $snapshot['current_task']);
        $this->assertSame([], $snapshot['temporary_decisions']);
    }

    public function testInitializeSilentlyDropsUnrecognizedFields(): void
    {
        $memory = new WorkingMemory();
        $memory->initialize('task_1', ['active_goal' => 'goal_1', 'made_up_field' => 'x']);

        $snapshot = $memory->snapshot('task_1');

        $this->assertArrayNotHasKey('made_up_field', $snapshot);
    }

    public function testSnapshotForAnUnknownTaskReturnsNull(): void
    {
        $memory = new WorkingMemory();

        $this->assertNull($memory->snapshot('task_ghost'));
    }

    public function testRefreshOnAnUninitializedTaskIsRejected(): void
    {
        $memory = new WorkingMemory();

        $result = $memory->refresh('task_1', ['active_goal' => 'goal_1']);

        $this->assertSame('not_initialized', $result['outcome']);
    }

    public function testRefreshRejectsAnUncacheableField(): void
    {
        $memory = new WorkingMemory();
        $memory->initialize('task_1');

        $result = $memory->refresh('task_1', ['made_up_field' => 'x']);

        $this->assertSame('invalid_field', $result['outcome']);
    }

    public function testRefreshMergesNewValuesWithoutLosingExistingOnes(): void
    {
        $memory = new WorkingMemory();
        $memory->initialize('task_1', ['active_goal' => 'goal_1']);

        $memory->refresh('task_1', ['current_task' => 'task_1']);

        $snapshot = $memory->snapshot('task_1');
        $this->assertSame('goal_1', $snapshot['active_goal']);
        $this->assertSame('task_1', $snapshot['current_task']);
    }

    public function testRecordTemporaryDecisionOnAnUninitializedTaskIsRejected(): void
    {
        $memory = new WorkingMemory();

        $result = $memory->recordTemporaryDecision('task_1', 'chosen_discount_type', 'percentage');

        $this->assertSame('not_initialized', $result['outcome']);
    }

    public function testRecordTemporaryDecisionAppearsInTheSnapshot(): void
    {
        $memory = new WorkingMemory();
        $memory->initialize('task_1');

        $memory->recordTemporaryDecision('task_1', 'chosen_discount_type', 'percentage');

        $this->assertSame(['chosen_discount_type' => 'percentage'], $memory->snapshot('task_1')['temporary_decisions']);
    }

    public function testIsStaleComparesTheCachedValueAgainstTheAuthoritativeOne(): void
    {
        $memory = new WorkingMemory();
        $memory->initialize('task_1', ['active_goal' => 'goal_1']);

        $this->assertFalse($memory->isStale('task_1', 'active_goal', 'goal_1'));
        $this->assertTrue($memory->isStale('task_1', 'active_goal', 'goal_2'));
    }

    public function testHandOffOnAnUninitializedTaskIsRejected(): void
    {
        $memory = new WorkingMemory();

        $result = $memory->handOff('task_1', ['outcome_summary' => 'done', 'validation_result_reference' => 'ACCEPTED']);

        $this->assertSame('not_initialized', $result['outcome']);
    }

    public function testHandOffWithoutEpisodicMemoryIsNotConfigured(): void
    {
        $memory = new WorkingMemory();
        $memory->initialize('task_1', ['active_goal' => 'goal_1', 'current_task' => 'task_1']);

        $result = $memory->handOff('task_1', ['outcome_summary' => 'done', 'validation_result_reference' => 'ACCEPTED']);

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testHandOffGenuinelyRecordsToEpisodicMemoryAndClearsTheSnapshot(): void
    {
        $episodicMemory = new EpisodicMemory(new InMemoryStore());
        $memory = new WorkingMemory($episodicMemory);
        $memory->initialize('task_1', [
            'active_goal' => 'goal_1',
            'active_workflow' => 'BUG-FIX-WORKFLOW',
            'active_agent' => 'agent_dev',
            'current_task' => 'task_1',
        ]);
        $memory->recordTemporaryDecision('task_1', 'chosen_discount_type', 'percentage');

        $result = $memory->handOff('task_1', ['outcome_summary' => 'Discount field shipped.', 'validation_result_reference' => 'ACCEPTED']);

        $this->assertSame('recorded', $result['outcome']);
        $recorded = $episodicMemory->retrieve($result['memory_id']);
        $this->assertSame('task_1', $recorded['task_reference']);
        $this->assertSame('goal_1', $recorded['goal_reference']);
        $this->assertSame('BUG-FIX-WORKFLOW', $recorded['execution_plan_reference']);
        $this->assertSame(['agent_dev'], $recorded['agents_involved']);
        $this->assertNull($memory->snapshot('task_1'));
    }

    public function testHandOffPreservesTheSnapshotWhenEpisodicMemoryRejectsTheEntry(): void
    {
        $episodicMemory = new EpisodicMemory(new InMemoryStore());
        $memory = new WorkingMemory($episodicMemory);
        // No active_goal supplied, so Episodic Memory will reject the entry.
        $memory->initialize('task_1', ['current_task' => 'task_1']);

        $result = $memory->handOff('task_1', ['outcome_summary' => 'done', 'validation_result_reference' => 'ACCEPTED']);

        $this->assertSame('invalid_entry', $result['outcome']);
        $this->assertNotNull($memory->snapshot('task_1'));
    }

    public function testClearRemovesTheSnapshotWithoutAHandOff(): void
    {
        $memory = new WorkingMemory();
        $memory->initialize('task_1', ['active_goal' => 'goal_1']);

        $memory->clear('task_1');

        $this->assertNull($memory->snapshot('task_1'));
    }
}
