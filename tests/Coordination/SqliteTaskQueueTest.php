<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Coordination;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Coordination\SqliteHandoffProtocol;
use SquirrelForge\Coordination\SqlitePriorityManager;
use SquirrelForge\Coordination\SqliteTaskQueue;

final class SqliteTaskQueueTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-task-queue-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function priorityManager(): SqlitePriorityManager
    {
        return new SqlitePriorityManager($this->tempPath('priority'), null);
    }

    private function queue(?SqlitePriorityManager $priorityManager = null, ?SqliteHandoffProtocol $handoffProtocol = null): SqliteTaskQueue
    {
        return new SqliteTaskQueue($this->tempPath('queue'), $priorityManager, $handoffProtocol);
    }

    private function assignPriority(SqlitePriorityManager $manager, string $taskId, string $urgency): void
    {
        $manager->assign(['task_id' => $taskId, 'factors' => ['urgency' => $urgency]]);
    }

    // --- routing confirmation gate ---

    public function testMissingTaskIdIsInvalid(): void
    {
        $queue = $this->queue();

        $result = $queue->enqueue(['task_router_status' => 'ROUTED']);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testNotRoutedIsRefused(): void
    {
        $queue = $this->queue();

        $result = $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'BLOCKED']);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('ROUTED', $result['error']);
    }

    public function testMissingTaskRouterStatusIsRefused(): void
    {
        $queue = $this->queue();

        $result = $queue->enqueue(['task_id' => 't1']);

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- priority requirement ---

    public function testNoPriorityRecordIsRefused(): void
    {
        $priorityManager = $this->priorityManager();
        $queue = $this->queue($priorityManager);

        $result = $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'ROUTED']);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('Priority Manager', $result['error']);
    }

    public function testEnqueueSucceedsWithARealPriority(): void
    {
        $priorityManager = $this->priorityManager();
        $this->assignPriority($priorityManager, 't1', 'high');
        $queue = $this->queue($priorityManager);

        $result = $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'ROUTED']);

        $this->assertSame('queued', $result['outcome']);
        $this->assertNotNull($result['priority']);
    }

    public function testWithoutAPriorityManagerIsAlwaysRefused(): void
    {
        $queue = $this->queue();

        $result = $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'ROUTED']);

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- duplicate prevention ---

    public function testDuplicateEnqueueIsSkipped(): void
    {
        $priorityManager = $this->priorityManager();
        $this->assignPriority($priorityManager, 't1', 'high');
        $queue = $this->queue($priorityManager);
        $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'ROUTED']);

        $result = $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'ROUTED']);

        $this->assertSame('skipped', $result['outcome']);
    }

    public function testReenqueueingAnAlreadyDequeuedTaskIsSkipped(): void
    {
        $priorityManager = $this->priorityManager();
        $this->assignPriority($priorityManager, 't1', 'high');
        $queue = $this->queue($priorityManager);
        $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'ROUTED']);
        $queue->dequeue();

        $result = $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'ROUTED']);

        $this->assertSame('skipped', $result['outcome']);
    }

    public function testDifferentTasksDoNotCollide(): void
    {
        $priorityManager = $this->priorityManager();
        $this->assignPriority($priorityManager, 't1', 'high');
        $this->assignPriority($priorityManager, 't2', 'high');
        $queue = $this->queue($priorityManager);
        $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'ROUTED']);

        $result = $queue->enqueue(['task_id' => 't2', 'task_router_status' => 'ROUTED']);

        $this->assertSame('queued', $result['outcome']);
    }

    // --- ordering ---

    public function testQueuedOrdersByPriorityRank(): void
    {
        $priorityManager = $this->priorityManager();
        $this->assignPriority($priorityManager, 't_low', 'none');
        $this->assignPriority($priorityManager, 't_critical', 'critical');
        $this->assignPriority($priorityManager, 't_medium', 'medium');
        $queue = $this->queue($priorityManager);
        $queue->enqueue(['task_id' => 't_low', 'task_router_status' => 'ROUTED']);
        $queue->enqueue(['task_id' => 't_critical', 'task_router_status' => 'ROUTED']);
        $queue->enqueue(['task_id' => 't_medium', 'task_router_status' => 'ROUTED']);

        $ordered = $queue->queued();

        $this->assertSame(['t_critical', 't_medium', 't_low'], array_column($ordered, 'task_id'));
    }

    public function testEqualPriorityBreaksTiesByEnqueueOrder(): void
    {
        $priorityManager = $this->priorityManager();
        $this->assignPriority($priorityManager, 't_first', 'medium');
        $this->assignPriority($priorityManager, 't_second', 'medium');
        $queue = $this->queue($priorityManager);
        $queue->enqueue(['task_id' => 't_first', 'task_router_status' => 'ROUTED']);
        $queue->enqueue(['task_id' => 't_second', 'task_router_status' => 'ROUTED']);

        $ordered = $queue->queued();

        $this->assertSame(['t_first', 't_second'], array_column($ordered, 'task_id'));
    }

    // --- dequeue() ---

    public function testDequeueFromAnEmptyQueueIsEmpty(): void
    {
        $queue = $this->queue();

        $result = $queue->dequeue();

        $this->assertSame('empty', $result['outcome']);
    }

    public function testDequeueWithoutAHandoffProtocolStillDequeues(): void
    {
        $priorityManager = $this->priorityManager();
        $this->assignPriority($priorityManager, 't1', 'high');
        $queue = $this->queue($priorityManager);
        $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'ROUTED']);

        $result = $queue->dequeue();

        $this->assertSame('dequeued', $result['outcome']);
        $this->assertNull($result['handoff']);
        $this->assertSame('t1', $result['task_id']);
    }

    public function testDequeuedEntryLeavesTheQueuedSet(): void
    {
        $priorityManager = $this->priorityManager();
        $this->assignPriority($priorityManager, 't1', 'high');
        $queue = $this->queue($priorityManager);
        $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'ROUTED']);

        $queue->dequeue();

        $this->assertSame([], $queue->queued());
    }

    public function testDequeueWithoutANextAgentNeverCallsHandoffProtocol(): void
    {
        $priorityManager = $this->priorityManager();
        $this->assignPriority($priorityManager, 't1', 'high');
        $handoffProtocol = new SqliteHandoffProtocol($this->tempPath('handoff'));
        $queue = $this->queue($priorityManager, $handoffProtocol);
        $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'ROUTED']);

        $result = $queue->dequeue();

        $this->assertNull($result['handoff']);
    }

    public function testDequeueHandsOffThroughHandoffProtocol(): void
    {
        $priorityManager = $this->priorityManager();
        $this->assignPriority($priorityManager, 't1', 'high');
        $handoffProtocol = new SqliteHandoffProtocol($this->tempPath('handoff'));
        $queue = $this->queue($priorityManager, $handoffProtocol);
        $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'ROUTED']);

        $result = $queue->dequeue(
            ['current_agent' => 'coordinator', 'next_agent' => 'agent_developer'],
            requestAcceptance: static fn(array $h): array => ['accepted' => true]
        );

        $this->assertSame('Accepted', $result['handoff']['outcome']);
        $this->assertSame($result['handoff']['handoff_id'], $queue->get($result['entry_id'])['handoff_ref']);
    }

    public function testDequeuesHighestPriorityFirst(): void
    {
        $priorityManager = $this->priorityManager();
        $this->assignPriority($priorityManager, 't_low', 'none');
        $this->assignPriority($priorityManager, 't_critical', 'critical');
        $queue = $this->queue($priorityManager);
        $queue->enqueue(['task_id' => 't_low', 'task_router_status' => 'ROUTED']);
        $queue->enqueue(['task_id' => 't_critical', 'task_router_status' => 'ROUTED']);

        $result = $queue->dequeue();

        $this->assertSame('t_critical', $result['task_id']);
    }

    // --- get() / history() ---

    public function testGetUnknownEntryReturnsNull(): void
    {
        $queue = $this->queue();

        $this->assertNull($queue->get('ghost'));
    }

    public function testHistoryReturnsEveryEntryForATask(): void
    {
        $priorityManager = $this->priorityManager();
        $this->assignPriority($priorityManager, 't1', 'high');
        $queue = $this->queue($priorityManager);
        $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'ROUTED']);
        $queue->enqueue(['task_id' => 't1', 'task_router_status' => 'ROUTED']);

        $history = $queue->history('t1');

        $this->assertCount(2, $history);
        $this->assertSame('QUEUED', $history[0]['position_state']);
        $this->assertSame('SKIPPED', $history[1]['position_state']);
    }
}
