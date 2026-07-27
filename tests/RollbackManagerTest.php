<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Workflow\CallbackStep;
use SquirrelForge\Workflow\RollbackManager;

final class RollbackManagerTest extends TestCase
{
    public function testEmptyInputReportsSuccessfulWithNothingReversed(): void
    {
        $manager = new RollbackManager();

        $result = $manager->rollback([]);

        $this->assertSame('successful', $result['status']);
        $this->assertSame([], $result['reversed']);
        $this->assertSame([], $result['notes']);
    }

    public function testStepsAreReversedInLifoOrder(): void
    {
        $order = [];

        $steps = [
            [
                'step' => new CallbackStep('first', 'First', fn(array $c): array => [], function () use (&$order): void {
                    $order[] = 'first';
                }),
                'context' => [],
            ],
            [
                'step' => new CallbackStep('second', 'Second', fn(array $c): array => [], function () use (&$order): void {
                    $order[] = 'second';
                }),
                'context' => [],
            ],
        ];

        $manager = new RollbackManager();
        $result = $manager->rollback($steps);

        $this->assertSame('successful', $result['status']);
        $this->assertSame(['second', 'first'], $result['reversed']);
        $this->assertSame(['second', 'first'], $order);
    }

    public function testOneRollbackFailureIsReportedAsPartialWithoutStoppingOthers(): void
    {
        $steps = [
            [
                'step' => new CallbackStep('first', 'First', fn(array $c): array => []),
                'context' => [],
            ],
            [
                'step' => new CallbackStep('second', 'Second', fn(array $c): array => [], function (): void {
                    throw new \RuntimeException('cannot reverse');
                }),
                'context' => [],
            ],
        ];

        $manager = new RollbackManager();
        $result = $manager->rollback($steps);

        $this->assertSame('partial', $result['status']);
        $this->assertSame(['first'], $result['reversed']);
        $this->assertCount(1, $result['notes']);
        $this->assertStringContainsString('second', $result['notes'][0]);
    }

    public function testAllRollbacksFailingIsReportedAsFailed(): void
    {
        $steps = [
            [
                'step' => new CallbackStep('first', 'First', fn(array $c): array => [], function (): void {
                    throw new \RuntimeException('boom');
                }),
                'context' => [],
            ],
        ];

        $manager = new RollbackManager();
        $result = $manager->rollback($steps);

        $this->assertSame('failed', $result['status']);
        $this->assertSame([], $result['reversed']);
        $this->assertCount(1, $result['notes']);
    }
}
