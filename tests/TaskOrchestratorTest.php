<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Automation\TaskOrchestrator;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Resilience\SqliteFailureDetector;
use SquirrelForge\Resilience\SqliteRecoveryManager;
use SquirrelForge\Resilience\SqliteResilienceManager;
use SquirrelForge\Resilience\SqliteSelfHealingEngine;

final class TaskOrchestratorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-task-orchestrator-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    public function testLinearChainRunsInDependencyOrderAcrossThreeLevels(): void
    {
        $orchestrator = new TaskOrchestrator();
        $order = [];
        $dispatchers = ['agent' => function (array $task) use (&$order): string {
            $order[] = $task['id'];

            return 'ok';
        }];
        $tasks = [
            ['id' => 'a', 'dispatch_type' => 'agent'],
            ['id' => 'b', 'dispatch_type' => 'agent', 'depends_on' => ['a']],
            ['id' => 'c', 'dispatch_type' => 'agent', 'depends_on' => ['b']],
        ];

        $result = $orchestrator->orchestrate($tasks, $dispatchers);

        $this->assertSame('completed', $result['outcome']);
        $this->assertSame([['a'], ['b'], ['c']], $result['levels']);
        $this->assertSame(['a', 'b', 'c'], $order);
    }

    public function testIndependentTasksAreGroupedIntoTheSameParallelLevel(): void
    {
        $orchestrator = new TaskOrchestrator();
        $dispatchers = ['agent' => static fn(array $task): string => 'ok'];
        $tasks = [
            ['id' => 'a', 'dispatch_type' => 'agent'],
            ['id' => 'b', 'dispatch_type' => 'agent'],
            ['id' => 'c', 'dispatch_type' => 'agent', 'depends_on' => ['a', 'b']],
        ];

        $result = $orchestrator->orchestrate($tasks, $dispatchers);

        $this->assertCount(2, $result['levels']);
        $this->assertEqualsCanonicalizing(['a', 'b'], $result['levels'][0]);
        $this->assertSame(['c'], $result['levels'][1]);
    }

    public function testDuplicateTaskIdIsInvalid(): void
    {
        $orchestrator = new TaskOrchestrator();

        $result = $orchestrator->orchestrate([
            ['id' => 'a', 'dispatch_type' => 'agent'],
            ['id' => 'a', 'dispatch_type' => 'agent'],
        ], []);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('Duplicate', (string) $result['error']);
    }

    public function testDependencyOnAnUnknownTaskIsInvalid(): void
    {
        $orchestrator = new TaskOrchestrator();

        $result = $orchestrator->orchestrate([
            ['id' => 'a', 'dispatch_type' => 'agent', 'depends_on' => ['ghost']],
        ], []);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('unknown task', (string) $result['error']);
    }

    public function testACycleIsDetectedAndRejected(): void
    {
        $orchestrator = new TaskOrchestrator();

        $result = $orchestrator->orchestrate([
            ['id' => 'a', 'dispatch_type' => 'agent', 'depends_on' => ['b']],
            ['id' => 'b', 'dispatch_type' => 'agent', 'depends_on' => ['a']],
        ], []);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('cycle', (string) $result['error']);
    }

    public function testTaskWithNoRegisteredDispatcherFails(): void
    {
        $orchestrator = new TaskOrchestrator();

        $result = $orchestrator->orchestrate([['id' => 'a', 'dispatch_type' => 'agent']], []);

        $this->assertSame('partial', $result['outcome']);
        $this->assertSame('failed', $result['task_results']['a']['status']);
        $this->assertStringContainsString('No dispatcher', (string) $result['task_results']['a']['error']);
    }

    public function testAFailedTaskBlocksItsDependents(): void
    {
        $orchestrator = new TaskOrchestrator();
        $bWasDispatched = false;
        $dispatchers = ['agent' => function (array $task) use (&$bWasDispatched): string {
            if ($task['id'] === 'a') {
                throw new RuntimeException('a exploded');
            }

            $bWasDispatched = true;

            return 'ok';
        }];

        $result = $orchestrator->orchestrate([
            ['id' => 'a', 'dispatch_type' => 'agent'],
            ['id' => 'b', 'dispatch_type' => 'agent', 'depends_on' => ['a']],
        ], $dispatchers);

        $this->assertSame('partial', $result['outcome']);
        $this->assertSame('failed', $result['task_results']['a']['status']);
        $this->assertSame('blocked', $result['task_results']['b']['status']);
        $this->assertFalse($bWasDispatched);
    }

    public function testFailedTaskWithRecoveryRequestedDelegatesToResilienceLayer(): void
    {
        $failureDetector = new SqliteFailureDetector($this->tempPath('failures'));
        $selfHealing = new SqliteSelfHealingEngine($this->tempPath('healing'));
        $recoveryManager = new SqliteRecoveryManager($this->tempPath('recovery'));
        $resilience = new SqliteResilienceManager($this->tempPath('resilience'), $selfHealing, $recoveryManager);
        $orchestrator = new TaskOrchestrator($failureDetector, $resilience);
        $dispatchers = ['agent' => static function (): never {
            throw new RuntimeException('boom');
        }];

        $result = $orchestrator->orchestrate([['id' => 'a', 'dispatch_type' => 'agent']], $dispatchers, [
            'recovery' => ['recovery_strategy' => 'direct', 'recovery_options' => ['action' => static fn(): string => 'ok']],
        ]);

        $this->assertNotNull($result['task_results']['a']['recovery']);
        $this->assertSame('completed', $result['task_results']['a']['recovery']['outcome']);
    }

    public function testOrchestrateDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('task_orchestrator.completed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $orchestrator = new TaskOrchestrator(events: $events);
        $orchestrator->orchestrate([['id' => 'a', 'dispatch_type' => 'agent']], ['agent' => static fn(): string => 'ok']);

        $this->assertCount(1, $captured);
    }
}
