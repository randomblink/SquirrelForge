<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Workflow\CallbackStep;
use SquirrelForge\Workflow\StepWorkflow;
use SquirrelForge\Workflow\WorkflowExecutionException;

final class StepWorkflowTest extends TestCase
{
    public function testStepsRunInOrderAndMergeContext(): void
    {
        $workflow = new StepWorkflow(
            'multi-step',
            'Runs two steps in order.',
            fn(array $context): bool => true,
            [
                new CallbackStep(
                    'add-a',
                    'Add A',
                    fn(array $context): array => ['a' => 1]
                ),
                new CallbackStep(
                    'add-b',
                    'Add B',
                    fn(array $context): array => ['b' => $context['a'] + 1]
                ),
            ]
        );

        $result = $workflow->execute(['start' => true]);

        $this->assertSame('success', $result['status']);
        $this->assertSame(1, $result['context']['a']);
        $this->assertSame(2, $result['context']['b']);
        $this->assertCount(2, $result['checkpoints']);
        $this->assertSame('complete', $result['checkpoints'][0]->status);
        $this->assertSame('complete', $result['checkpoints'][1]->status);
    }

    public function testFailureRollsBackCompletedStepsInReverseOrder(): void
    {
        $log = [];

        $workflow = new StepWorkflow(
            'rollback-on-failure',
            'Second step fails after first step completes.',
            fn(array $context): bool => true,
            [
                new CallbackStep(
                    'reserve',
                    'Reserve',
                    fn(array $context): array => ['reserved' => true],
                    function (array $context) use (&$log): void {
                        $log[] = 'reserve-rolled-back';
                    }
                ),
                new CallbackStep(
                    'charge',
                    'Charge',
                    function (array $context): array {
                        throw new \RuntimeException('charge declined');
                    }
                ),
            ]
        );

        try {
            $workflow->execute([]);
            self::fail('Expected WorkflowExecutionException.');
        } catch (WorkflowExecutionException $e) {
            $this->assertSame('charge', $e->failedStepId);
            $this->assertCount(2, $e->checkpoints);
            $this->assertSame('complete', $e->checkpoints[0]->status);
            $this->assertSame('failed', $e->checkpoints[1]->status);
            $this->assertSame('successful', $e->rollback['status']);
            $this->assertSame(['reserve'], $e->rollback['reversed']);
            $this->assertSame(['reserve-rolled-back'], $log);
        }
    }

    public function testRollbackFailureOnOneStepDoesNotBlockReversingOthers(): void
    {
        $workflow = new StepWorkflow(
            'partial-rollback',
            'One rollback fails, the other should still run.',
            fn(array $context): bool => true,
            [
                new CallbackStep(
                    'first',
                    'First',
                    fn(array $context): array => ['first' => true],
                    function (array $context): void {
                        throw new \RuntimeException('rollback storage unavailable');
                    }
                ),
                new CallbackStep(
                    'second',
                    'Second',
                    fn(array $context): array => ['second' => true]
                ),
                new CallbackStep(
                    'third',
                    'Third',
                    function (array $context): array {
                        throw new \RuntimeException('third step failed');
                    }
                ),
            ]
        );

        try {
            $workflow->execute([]);
            self::fail('Expected WorkflowExecutionException.');
        } catch (WorkflowExecutionException $e) {
            $this->assertSame('partial', $e->rollback['status']);
            $this->assertSame(['second'], $e->rollback['reversed']);
            $this->assertCount(1, $e->rollback['notes']);
        }
    }
}
