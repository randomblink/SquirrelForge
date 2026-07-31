<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\ExecutionPlanner;

final class ExecutionPlannerTest extends TestCase
{
    private function task(array $overrides = []): array
    {
        return array_merge(['id' => 'task_a'], $overrides);
    }

    public function testPlanWithAnUnreadyWorkflowSelectionIsNotReady(): void
    {
        $planner = new ExecutionPlanner();

        $result = $planner->plan([$this->task()], [['task_a']], [], [], ['status' => 'UNSUPPORTED', 'primary_workflow' => null]);

        $this->assertSame('not_ready', $result['outcome']);
        $this->assertSame([], $result['steps']);
    }

    public function testPlanBuildsASequencedStepForEachTaskInLevelOrder(): void
    {
        $planner = new ExecutionPlanner();
        $tasks = [$this->task(['id' => 'task_a']), $this->task(['id' => 'task_b'])];

        $result = $planner->plan($tasks, [['task_a'], ['task_b']], [], [], ['status' => 'SELECTED', 'primary_workflow' => 'BUG-FIX-WORKFLOW']);

        $this->assertSame('planned', $result['outcome']);
        $this->assertSame(1, $result['steps'][0]['sequence']);
        $this->assertSame(2, $result['steps'][1]['sequence']);
        $this->assertSame('BUG-FIX-WORKFLOW', $result['steps'][0]['workflow']);
        $this->assertSame('planned', $result['steps'][0]['status']);
    }

    public function testATaskWithAWorkflowOverrideUsesItsOwnWorkflow(): void
    {
        $planner = new ExecutionPlanner();

        $result = $planner->plan(
            [$this->task(['workflow' => 'SECURITY-REVIEW-WORKFLOW'])],
            [['task_a']],
            [],
            [],
            ['status' => 'SELECTED', 'primary_workflow' => 'BUG-FIX-WORKFLOW']
        );

        $this->assertSame('SECURITY-REVIEW-WORKFLOW', $result['steps'][0]['workflow']);
    }

    public function testABlockedTaskGetsARealDerivedStopCondition(): void
    {
        $planner = new ExecutionPlanner();

        $result = $planner->plan(
            [$this->task()],
            [['task_a']],
            [],
            [['dependency_id' => 'dep_missing_tool', 'required_by' => 'task_a']],
            ['status' => 'SELECTED', 'primary_workflow' => 'BUG-FIX-WORKFLOW']
        );

        $this->assertSame('planned_with_blockers', $result['outcome']);
        $this->assertSame('blocked', $result['steps'][0]['status']);
        $this->assertStringContainsString('dep_missing_tool', $result['steps'][0]['stop_conditions'][0]);
    }

    public function testParallelEligibleTasksShareAParallelGroup(): void
    {
        $planner = new ExecutionPlanner();
        $tasks = [$this->task(['id' => 'task_a']), $this->task(['id' => 'task_b'])];

        $result = $planner->plan($tasks, [['task_a', 'task_b']], [['task_a', 'task_b']], [], ['status' => 'SELECTED', 'primary_workflow' => 'WF']);

        $this->assertNotNull($result['steps'][0]['parallel_group']);
        $this->assertSame($result['steps'][0]['parallel_group'], $result['steps'][1]['parallel_group']);
    }

    public function testAParallelGroupIsDissolvedWhenOneMemberBecomesBlocked(): void
    {
        $planner = new ExecutionPlanner();
        $tasks = [$this->task(['id' => 'task_a']), $this->task(['id' => 'task_b'])];

        $result = $planner->plan(
            $tasks,
            [['task_a', 'task_b']],
            [['task_a', 'task_b']],
            [['dependency_id' => 'dep_x', 'required_by' => 'task_b']],
            ['status' => 'SELECTED', 'primary_workflow' => 'WF']
        );

        $stepA = current(array_filter($result['steps'], static fn(array $s): bool => $s['task_id'] === 'task_a'));
        $stepB = current(array_filter($result['steps'], static fn(array $s): bool => $s['task_id'] === 'task_b'));
        $this->assertNull($stepA['parallel_group']);
        $this->assertSame('blocked', $stepB['status']);
    }

    public function testAHighRiskTaskWithoutARecoveryPathIsBlocked(): void
    {
        $planner = new ExecutionPlanner();

        $result = $planner->plan(
            [$this->task(['risk' => 'high'])],
            [['task_a']],
            [],
            [],
            ['status' => 'SELECTED', 'primary_workflow' => 'WF']
        );

        $this->assertSame('planned_with_blockers', $result['outcome']);
        $this->assertSame('blocked', $result['steps'][0]['status']);
        $this->assertCount(1, $result['violations']);
        $this->assertSame('recovery_planning', $result['violations'][0]['rule']);
    }

    public function testAHighRiskTaskWithAnUnconfirmedRecoveryPathIsBlocked(): void
    {
        $planner = new ExecutionPlanner();

        $result = $planner->plan(
            [$this->task(['risk' => 'critical', 'recovery_path' => 'rollback via git revert'])],
            [['task_a']],
            [],
            [],
            ['status' => 'SELECTED', 'primary_workflow' => 'WF']
        );

        $this->assertSame('blocked', $result['steps'][0]['status']);
        $this->assertStringContainsString('not confirmed available', $result['violations'][0]['reason']);
    }

    public function testAHighRiskTaskWithAConfirmedRecoveryPathIsPlanned(): void
    {
        $planner = new ExecutionPlanner();

        $result = $planner->plan(
            [$this->task(['risk' => 'critical', 'recovery_path' => 'rollback via git revert', 'recovery_available' => true])],
            [['task_a']],
            [],
            [],
            ['status' => 'SELECTED', 'primary_workflow' => 'WF']
        );

        $this->assertSame('planned', $result['outcome']);
        $this->assertSame('planned', $result['steps'][0]['status']);
        $this->assertSame([], $result['violations']);
    }

    public function testALowRiskTaskNeverRequiresARecoveryPath(): void
    {
        $planner = new ExecutionPlanner();

        $result = $planner->plan(
            [$this->task(['risk' => 'low'])],
            [['task_a']],
            [],
            [],
            ['status' => 'SELECTED', 'primary_workflow' => 'WF']
        );

        $this->assertSame('planned', $result['outcome']);
        $this->assertSame([], $result['violations']);
    }
}
