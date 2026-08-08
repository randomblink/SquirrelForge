<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Execution;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Execution\ActionDispatcher;
use SquirrelForge\Execution\FailureHandler;
use SquirrelForge\Execution\SqliteExecutionLogger;

final class ActionDispatcherTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-action-dispatcher-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{workflow_ref: string, action_type: string, target_ref: string}
     */
    private function minimalAction(array $overrides = []): array
    {
        return array_replace([
            'workflow_ref' => 'wf_1',
            'action_type' => 'Development',
            'target_ref' => 'agent_developer_1',
        ], $overrides);
    }

    // --- shape / prerequisites ---

    public function testMissingTargetRefIsPrerequisiteFailure(): void
    {
        $dispatcher = new ActionDispatcher();
        $action = $this->minimalAction();
        unset($action['target_ref']);

        $result = $dispatcher->dispatch($action);

        $this->assertSame('Failed', $result['status']);
        $this->assertStringContainsString('target_ref', $result['error']);
    }

    public function testUnmetPrerequisiteIsFailed(): void
    {
        $dispatcher = new ActionDispatcher();

        $result = $dispatcher->dispatch($this->minimalAction([
            'prerequisites' => [['name' => 'branch_created', 'met' => false]],
        ]));

        $this->assertSame('Failed', $result['status']);
        $this->assertStringContainsString('branch_created', $result['error']);
    }

    public function testAllPrerequisitesMetProceedsToDispatch(): void
    {
        $dispatcher = new ActionDispatcher();

        $result = $dispatcher->dispatch(
            $this->minimalAction(['prerequisites' => [['name' => 'branch_created', 'met' => true]]]),
            dispatchTarget: static fn(string $target, array $action): array => ['status' => 'Complete']
        );

        $this->assertSame('Complete', $result['status']);
    }

    // --- dry run ---

    public function testDispatchWithoutATargetClosureIsADryRun(): void
    {
        $dispatcher = new ActionDispatcher();

        $result = $dispatcher->dispatch($this->minimalAction());

        $this->assertSame('Dispatched', $result['status']);
        $this->assertNull($result['result']);
        $this->assertNull($result['error']);
    }

    // --- successful outcomes ---

    public function testSuccessfulDispatchReturnsCompleteWithResult(): void
    {
        $dispatcher = new ActionDispatcher();

        $result = $dispatcher->dispatch(
            $this->minimalAction(),
            dispatchTarget: static fn(string $target, array $action): array => ['status' => 'Complete', 'result' => ['pr' => 42]]
        );

        $this->assertSame('Complete', $result['status']);
        $this->assertSame(['pr' => 42], $result['result']);
    }

    public function testAsyncRunningStatusIsRespected(): void
    {
        $dispatcher = new ActionDispatcher();

        $result = $dispatcher->dispatch(
            $this->minimalAction(),
            dispatchTarget: static fn(string $target, array $action): array => ['status' => 'Running']
        );

        $this->assertSame('Running', $result['status']);
    }

    public function testMissingStatusWithNoErrorDefaultsToComplete(): void
    {
        $dispatcher = new ActionDispatcher();

        $result = $dispatcher->dispatch(
            $this->minimalAction(),
            dispatchTarget: static fn(string $target, array $action): array => ['result' => 'ok']
        );

        $this->assertSame('Complete', $result['status']);
    }

    public function testTargetRefIsForwardedToTheClosure(): void
    {
        $dispatcher = new ActionDispatcher();
        $seenTarget = null;

        $dispatcher->dispatch(
            $this->minimalAction(['target_ref' => 'agent_developer_9']),
            dispatchTarget: function (string $target, array $action) use (&$seenTarget): array {
                $seenTarget = $target;

                return ['status' => 'Complete'];
            }
        );

        $this->assertSame('agent_developer_9', $seenTarget);
    }

    // --- failure classification ---

    public function testClosureReportingErrorIsExecutionFailure(): void
    {
        $dispatcher = new ActionDispatcher(null, new FailureHandler());

        $result = $dispatcher->dispatch(
            $this->minimalAction(),
            dispatchTarget: static fn(string $target, array $action): array => ['status' => 'Failed', 'error' => 'agent rejected the task'],
            recoveryRequest: static fn(array $record): array => ['authorized' => false]
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('agent rejected the task', $result['error']);
        $this->assertSame('not_authorized', $result['recovery']['outcome']);
    }

    public function testClosureThrowingIsDispatchFailureNotAnUncaughtException(): void
    {
        $dispatcher = new ActionDispatcher();

        $result = $dispatcher->dispatch(
            $this->minimalAction(),
            dispatchTarget: static function (string $target, array $action): array {
                throw new RuntimeException('target unreachable');
            }
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('target unreachable', $result['error']);
    }

    // --- FailureHandler composition ---

    public function testFailureIsReportedThroughFailureHandler(): void
    {
        $failureHandler = new FailureHandler();
        $dispatcher = new ActionDispatcher(null, $failureHandler);
        $seenRecord = null;

        $dispatcher->dispatch(
            $this->minimalAction(),
            dispatchTarget: static function (): array {
                throw new RuntimeException('unreachable');
            },
            recoveryRequest: function (array $record) use (&$seenRecord): array {
                $seenRecord = $record;

                return ['authorized' => true, 'operation' => 'Retry'];
            }
        );

        $this->assertSame('Dispatch Failure', $seenRecord['failure_type']);
        $this->assertSame('action_dispatcher', $seenRecord['reporting_component']);
    }

    public function testAuthorizedRetryIsRoutedThroughFailureHandler(): void
    {
        $failureHandler = new FailureHandler();
        $dispatcher = new ActionDispatcher(null, $failureHandler);
        $routed = null;

        $result = $dispatcher->dispatch(
            $this->minimalAction(),
            dispatchTarget: static function (): array {
                throw new RuntimeException('unreachable');
            },
            recoveryRequest: static fn(array $record): array => ['authorized' => true, 'operation' => 'Retry'],
            route: function (string $op, string $target, array $record) use (&$routed): void {
                $routed = [$op, $target];
            }
        );

        $this->assertSame('routed', $result['recovery']['outcome']);
        $this->assertSame(['Retry', 'action_dispatcher'], $routed);
    }

    public function testFailureWithoutARecoveryRequestNeverCallsForward(): void
    {
        $failureHandler = new FailureHandler();
        $dispatcher = new ActionDispatcher(null, $failureHandler);

        $result = $dispatcher->dispatch(
            $this->minimalAction(),
            dispatchTarget: static function (): array {
                throw new RuntimeException('unreachable');
            }
        );

        $this->assertNull($result['recovery']);
    }

    public function testFailureWithoutAFailureHandlerNeverCrashes(): void
    {
        $dispatcher = new ActionDispatcher();

        $result = $dispatcher->dispatch(
            $this->minimalAction(),
            dispatchTarget: static function (): array {
                throw new RuntimeException('unreachable');
            },
            recoveryRequest: static fn(array $record): array => ['authorized' => true, 'operation' => 'Retry']
        );

        $this->assertSame('Failed', $result['status']);
        $this->assertNull($result['recovery']);
    }

    // --- logging composition ---

    public function testDispatchRecordsThroughTheLogger(): void
    {
        $logger = new SqliteExecutionLogger($this->tempPath('db'));
        $dispatcher = new ActionDispatcher($logger);

        $dispatcher->dispatch($this->minimalAction(), dispatchTarget: static fn(): array => ['status' => 'Complete']);

        $history = $logger->history('wf_1');
        $this->assertCount(1, $history);
        $this->assertSame('Complete', $history[0]['outcome']);
        $this->assertSame('action_dispatcher', $history[0]['actor']);
    }

    public function testActionIdIsGeneratedWhenOmitted(): void
    {
        $dispatcher = new ActionDispatcher();

        $result = $dispatcher->dispatch($this->minimalAction());

        $this->assertNotNull($result['action_id']);
        $this->assertNotSame('', $result['action_id']);
    }

    public function testWorksWithoutALoggerOrFailureHandler(): void
    {
        $dispatcher = new ActionDispatcher(null, null);

        $result = $dispatcher->dispatch($this->minimalAction(), dispatchTarget: static fn(): array => ['status' => 'Complete']);

        $this->assertSame('Complete', $result['status']);
    }
}
