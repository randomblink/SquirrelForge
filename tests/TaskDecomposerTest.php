<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\TaskDecomposer;

final class TaskDecomposerTest extends TestCase
{
    private function validTask(array $overrides = []): array
    {
        return array_merge([
            'id' => 'task_a',
            'description' => 'Add the discount field to checkout',
            'expected_output' => 'A rendered discount field',
            'completion_condition' => 'Field renders and validates a code',
        ], $overrides);
    }

    public function testDecomposeWithNoTasksIsRejected(): void
    {
        $decomposer = new TaskDecomposer();

        $result = $decomposer->decompose([], []);

        $this->assertSame('no_tasks', $result['outcome']);
    }

    public function testDecomposeRejectsADuplicateTaskId(): void
    {
        $decomposer = new TaskDecomposer();

        $result = $decomposer->decompose([], [$this->validTask(), $this->validTask()]);

        $this->assertSame('invalid_graph', $result['outcome']);
        $this->assertStringContainsString('Duplicate', $result['error']);
    }

    public function testDecomposeRejectsAnUnknownDependency(): void
    {
        $decomposer = new TaskDecomposer();

        $result = $decomposer->decompose([], [$this->validTask(['dependencies' => ['task_ghost']])]);

        $this->assertSame('invalid_graph', $result['outcome']);
    }

    public function testDecomposeRejectsACycle(): void
    {
        $decomposer = new TaskDecomposer();

        $result = $decomposer->decompose([], [
            $this->validTask(['id' => 'task_a', 'dependencies' => ['task_b']]),
            $this->validTask(['id' => 'task_b', 'dependencies' => ['task_a']]),
        ]);

        $this->assertSame('invalid_graph', $result['outcome']);
        $this->assertStringContainsString('cycle', $result['error']);
    }

    public function testDecomposeWithCompleteTasksSucceeds(): void
    {
        $decomposer = new TaskDecomposer();

        $result = $decomposer->decompose([], [$this->validTask()]);

        $this->assertSame('decomposed', $result['outcome']);
        $this->assertSame([], $result['violations']);
        $this->assertSame([['task_a']], $result['levels']);
    }

    public function testDecomposeFlagsATaskMissingARequiredField(): void
    {
        $decomposer = new TaskDecomposer();
        $task = $this->validTask();
        unset($task['expected_output']);

        $result = $decomposer->decompose([], [$task]);

        $this->assertSame('requires_revision', $result['outcome']);
        $this->assertSame('task_size', $result['violations'][0]['rule']);
    }

    public function testDecomposeFlagsAPreClaimedCompletionStatus(): void
    {
        $decomposer = new TaskDecomposer();

        $result = $decomposer->decompose([], [$this->validTask(['status' => 'completed'])]);

        $this->assertSame('requires_revision', $result['outcome']);
        $this->assertSame('completion_boundary', $result['violations'][0]['rule']);
    }

    public function testDecomposeFlagsADomainNotActiveOnTheGoal(): void
    {
        $decomposer = new TaskDecomposer();

        $result = $decomposer->decompose(['active_domains' => []], [$this->validTask(['active_domain' => 'wordpress_plugin'])]);

        $this->assertSame('requires_revision', $result['outcome']);
        $this->assertSame('domain', $result['violations'][0]['rule']);
    }

    public function testDecomposeAllowsADomainThatIsActiveOnTheGoal(): void
    {
        $decomposer = new TaskDecomposer();

        $result = $decomposer->decompose(
            ['active_domains' => ['wordpress_plugin']],
            [$this->validTask(['active_domain' => 'wordpress_plugin'])]
        );

        $this->assertSame('decomposed', $result['outcome']);
    }

    public function testParallelEligibleLevelsRequiresBothSafetySignals(): void
    {
        $decomposer = new TaskDecomposer();

        $result = $decomposer->decompose([], [
            $this->validTask(['id' => 'task_a', 'mutates_shared_state' => false, 'ownership_defined' => true]),
            $this->validTask(['id' => 'task_b', 'mutates_shared_state' => false, 'ownership_defined' => true]),
        ]);

        $this->assertSame([['task_a', 'task_b']], $result['parallel_eligible_levels']);
    }

    public function testParallelEligibleLevelsExcludesATaskMissingOwnershipDefinition(): void
    {
        $decomposer = new TaskDecomposer();

        $result = $decomposer->decompose([], [
            $this->validTask(['id' => 'task_a', 'mutates_shared_state' => false, 'ownership_defined' => true]),
            $this->validTask(['id' => 'task_b', 'mutates_shared_state' => false, 'ownership_defined' => false]),
        ]);

        $this->assertSame([], $result['parallel_eligible_levels']);
    }

    public function testASingleTaskLevelIsNeverParallelEligible(): void
    {
        $decomposer = new TaskDecomposer();

        $result = $decomposer->decompose([], [
            $this->validTask(['id' => 'task_a', 'mutates_shared_state' => false, 'ownership_defined' => true]),
        ]);

        $this->assertSame([], $result['parallel_eligible_levels']);
    }
}
