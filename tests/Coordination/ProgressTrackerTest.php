<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Coordination;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Coordination\ProgressTracker;

final class ProgressTrackerTest extends TestCase
{
    /**
     * @return array<int, array{task_id: string}>
     */
    private function plan(array $taskIds): array
    {
        return array_map(static fn(string $id): array => ['task_id' => $id], $taskIds);
    }

    public function testEmptyPlanHasZeroCompletionAndNoTasks(): void
    {
        $tracker = new ProgressTracker();

        $result = $tracker->aggregate([]);

        $this->assertSame(0, $result['total_tasks']);
        $this->assertSame(0.0, $result['completion_percentage']);
    }

    public function testTaskWithNoRecordedStatusIsNotStartedAndPending(): void
    {
        $tracker = new ProgressTracker();

        $result = $tracker->aggregate($this->plan(['t1']));

        $this->assertSame(1, $result['total_tasks']);
        $this->assertSame(1, $result['pending_tasks']);
        $this->assertSame(0, $result['completed_tasks']);
    }

    public function testCompletedTaskIsCounted(): void
    {
        $tracker = new ProgressTracker();

        $result = $tracker->aggregate($this->plan(['t1']), ['t1' => ['status' => 'COMPLETED']]);

        $this->assertSame(1, $result['completed_tasks']);
        $this->assertSame(100.0, $result['completion_percentage']);
    }

    public function testCompletionPercentageIsComputedAcrossMultipleTasks(): void
    {
        $tracker = new ProgressTracker();

        $result = $tracker->aggregate(
            $this->plan(['t1', 't2', 't3', 't4']),
            ['t1' => ['status' => 'COMPLETED'], 't2' => ['status' => 'COMPLETED']]
        );

        $this->assertSame(2, $result['completed_tasks']);
        $this->assertSame(4, $result['total_tasks']);
        $this->assertSame(50.0, $result['completion_percentage']);
    }

    #[DataProvider('pendingStatusProvider')]
    public function testEachPendingStateIsCountedAsPending(string $status): void
    {
        $tracker = new ProgressTracker();

        $result = $tracker->aggregate($this->plan(['t1']), ['t1' => ['status' => $status]]);

        $this->assertSame(1, $result['pending_tasks']);
        $this->assertSame(0, $result['completed_tasks']);
        $this->assertSame([], $result['blocked_tasks']);
    }

    public static function pendingStatusProvider(): array
    {
        return [
            ['NOT_STARTED'], ['READY'], ['ROUTED'], ['IN_PROGRESS'], ['WAITING'], ['VALIDATION_PENDING'],
        ];
    }

    public function testBlockedStateIsSurfacedWithReason(): void
    {
        $tracker = new ProgressTracker();

        $result = $tracker->aggregate(
            $this->plan(['t1']),
            ['t1' => ['status' => 'BLOCKED', 'blocker_reason' => 'waiting on dependency d1']]
        );

        $this->assertCount(1, $result['blocked_tasks']);
        $this->assertSame('t1', $result['blocked_tasks'][0]['task_id']);
        $this->assertSame('waiting on dependency d1', $result['blocked_tasks'][0]['reason']);
        $this->assertSame(0, $result['pending_tasks']);
    }

    public function testValidationFailedIsAlsoABlockedState(): void
    {
        $tracker = new ProgressTracker();

        $result = $tracker->aggregate($this->plan(['t1']), ['t1' => ['status' => 'VALIDATION_FAILED']]);

        $this->assertCount(1, $result['blocked_tasks']);
    }

    public function testCompletedTaskOutputReferenceIsCaptured(): void
    {
        $tracker = new ProgressTracker();

        $result = $tracker->aggregate($this->plan(['t1']), ['t1' => ['status' => 'COMPLETED', 'output_ref' => 'artifact_1']]);

        $this->assertSame(['t1' => 'artifact_1'], $result['completed_task_outputs']);
    }

    public function testCompletedTaskWithNoOutputRefIsOmittedNotFabricated(): void
    {
        $tracker = new ProgressTracker();

        $result = $tracker->aggregate($this->plan(['t1']), ['t1' => ['status' => 'COMPLETED']]);

        $this->assertSame([], $result['completed_task_outputs']);
    }

    public function testUnrecognizedStatusIsFlaggedAndCountedAsPending(): void
    {
        $tracker = new ProgressTracker();

        $result = $tracker->aggregate($this->plan(['t1']), ['t1' => ['status' => 'MADE_UP_STATUS']]);

        $this->assertCount(1, $result['unrecognized_statuses']);
        $this->assertSame('t1', $result['unrecognized_statuses'][0]['task_id']);
        $this->assertSame('MADE_UP_STATUS', $result['unrecognized_statuses'][0]['status']);
        $this->assertSame(1, $result['pending_tasks']);
        $this->assertSame(0, $result['completed_tasks']);
    }

    public function testUsesStepIdWhenTaskIdIsAbsent(): void
    {
        $tracker = new ProgressTracker();

        $result = $tracker->aggregate([['step_id' => 's1']], ['s1' => ['status' => 'COMPLETED']]);

        $this->assertSame(1, $result['completed_tasks']);
    }

    public function testStepsWithNoIdentifierAreSkipped(): void
    {
        $tracker = new ProgressTracker();

        $result = $tracker->aggregate([[], ['task_id' => 't1']]);

        $this->assertSame(1, $result['total_tasks']);
    }

    public function testMixedPlanAggregatesAllCategoriesCorrectly(): void
    {
        $tracker = new ProgressTracker();

        $result = $tracker->aggregate(
            $this->plan(['t1', 't2', 't3', 't4', 't5']),
            [
                't1' => ['status' => 'COMPLETED', 'output_ref' => 'artifact_1'],
                't2' => ['status' => 'IN_PROGRESS'],
                't3' => ['status' => 'BLOCKED', 'blocker_reason' => 'missing tool'],
                't4' => ['status' => 'VALIDATION_FAILED', 'blocker_reason' => 'output rejected'],
                // t5 absent -> NOT_STARTED
            ]
        );

        $this->assertSame(5, $result['total_tasks']);
        $this->assertSame(1, $result['completed_tasks']);
        $this->assertSame(2, $result['pending_tasks']);
        $this->assertCount(2, $result['blocked_tasks']);
        $this->assertSame(20.0, $result['completion_percentage']);
    }

    public function testAggregateRecomputesFreshEachCallRatherThanCaching(): void
    {
        $tracker = new ProgressTracker();
        $plan = $this->plan(['t1']);

        $first = $tracker->aggregate($plan, ['t1' => ['status' => 'IN_PROGRESS']]);
        $second = $tracker->aggregate($plan, ['t1' => ['status' => 'COMPLETED']]);

        $this->assertSame(0, $first['completed_tasks']);
        $this->assertSame(1, $second['completed_tasks']);
    }
}
