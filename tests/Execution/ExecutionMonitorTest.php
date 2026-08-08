<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Execution;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Execution\ExecutionMonitor;
use SquirrelForge\Execution\SqliteExecutionLogger;

final class ExecutionMonitorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-execution-monitor-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    // --- classification ---

    public function testNoSignalsAtAllIsQueued(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->track('exec_1', 'action_1', []);

        $this->assertSame('Queued', $result['status']);
        $this->assertSame([], $result['health_signals']);
    }

    public function testExplicitQueuedFlag(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->track('exec_1', 'action_1', ['queued' => true]);

        $this->assertSame('Queued', $result['status']);
    }

    public function testErrorSignalIsFailed(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->track('exec_1', 'action_1', ['error' => 'boom']);

        $this->assertSame('Failed', $result['status']);
        $this->assertSame(['Error'], $result['health_signals']);
    }

    public function testElapsedOverTimeoutIsStalled(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->track('exec_1', 'action_1', ['elapsed_ms' => 5000, 'timeout_ms' => 3000]);

        $this->assertSame('Stalled', $result['status']);
        $this->assertSame(['Timeout'], $result['health_signals']);
    }

    public function testErrorOutranksTimeout(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->track('exec_1', 'action_1', ['error' => 'boom', 'elapsed_ms' => 5000, 'timeout_ms' => 3000]);

        $this->assertSame('Failed', $result['status']);
    }

    public function testDependencyBlockedIsWaiting(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->track('exec_1', 'action_1', ['dependency_blocked' => true]);

        $this->assertSame('Waiting', $result['status']);
        $this->assertSame(['Dependency Block'], $result['health_signals']);
    }

    public function testTimeoutOutranksDependencyBlocked(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->track('exec_1', 'action_1', ['elapsed_ms' => 5000, 'timeout_ms' => 3000, 'dependency_blocked' => true]);

        $this->assertSame('Stalled', $result['status']);
    }

    public function testCompletionSignalIsComplete(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->track('exec_1', 'action_1', ['completion_signal' => true]);

        $this->assertSame('Complete', $result['status']);
        $this->assertSame(['Completion'], $result['health_signals']);
    }

    public function testErrorOutranksCompletionSignal(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->track('exec_1', 'action_1', ['completion_signal' => true, 'error' => 'boom']);

        $this->assertSame('Failed', $result['status']);
    }

    public function testProgressWithinExpectedIsRunningWithProgressSignal(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->track('exec_1', 'action_1', ['progress_ratio' => 0.5, 'expected_progress_ratio' => 0.5]);

        $this->assertSame('Running', $result['status']);
        $this->assertSame(['Progress'], $result['health_signals']);
    }

    public function testProgressBehindExpectedIsRunningWithDelaySignal(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->track('exec_1', 'action_1', ['progress_ratio' => 0.1, 'expected_progress_ratio' => 0.5]);

        $this->assertSame('Running', $result['status']);
        $this->assertSame(['Delay'], $result['health_signals']);
    }

    public function testProgressWithinToleranceIsNotDelayed(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->track('exec_1', 'action_1', ['progress_ratio' => 0.45, 'expected_progress_ratio' => 0.5, 'progress_tolerance' => 0.1]);

        $this->assertSame(['Progress'], $result['health_signals']);
    }

    // --- reporting ---

    public function testFailedStatusInvokesReportFailureClosure(): void
    {
        $monitor = new ExecutionMonitor();
        $seen = null;

        $result = $monitor->track('exec_1', 'action_1', ['error' => 'boom'], function (array $finding) use (&$seen): void {
            $seen = $finding;
        });

        $this->assertTrue($result['escalated']);
        $this->assertSame('Failed', $seen['status']);
        $this->assertSame('action_1', $seen['action_ref']);
    }

    public function testRunningStatusNeverInvokesReportFailureClosure(): void
    {
        $monitor = new ExecutionMonitor();
        $invoked = false;

        $result = $monitor->track('exec_1', 'action_1', ['progress_ratio' => 0.5, 'expected_progress_ratio' => 0.5], function () use (&$invoked): void {
            $invoked = true;
        });

        $this->assertFalse($invoked);
        $this->assertFalse($result['escalated']);
    }

    public function testEscalatedIsFalseWithoutAReportFailureClosure(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->track('exec_1', 'action_1', ['error' => 'boom']);

        $this->assertFalse($result['escalated']);
    }

    // --- markRetrying() ---

    public function testMarkRetryingRequiresAuthorization(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->markRetrying('exec_1', 'action_1', '');

        $this->assertSame('unauthorized', $result['outcome']);
    }

    public function testMarkRetryingSucceedsWithAuthorization(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->markRetrying('exec_1', 'action_1', 'failure_handler_1');

        $this->assertSame('recorded', $result['outcome']);
    }

    // --- history composition ---

    public function testTrackRecordsHistoryThroughTheLogger(): void
    {
        $logger = new SqliteExecutionLogger($this->tempPath('db'));
        $monitor = new ExecutionMonitor($logger);

        $monitor->track('exec_1', 'action_1', ['error' => 'boom']);

        $history = $logger->history('exec_1');
        $this->assertCount(1, $history);
        $this->assertSame('Failed', $history[0]['outcome']);
        $this->assertSame('execution_monitor', $history[0]['actor']);
    }

    public function testEscalatedTrackRecordsTheErrorCategory(): void
    {
        $logger = new SqliteExecutionLogger($this->tempPath('db'));
        $monitor = new ExecutionMonitor($logger);

        $monitor->track('exec_1', 'action_1', ['error' => 'boom'], static fn(array $finding) => null);

        $history = $logger->history('exec_1');
        $this->assertSame('reported_to_failure_handler', $history[0]['error_category']);
    }

    public function testMarkRetryingRecordsHistoryThroughTheLogger(): void
    {
        $logger = new SqliteExecutionLogger($this->tempPath('db'));
        $monitor = new ExecutionMonitor($logger);

        $monitor->markRetrying('exec_1', 'action_1', 'failure_handler_1');

        $history = $logger->history('exec_1');
        $this->assertCount(1, $history);
        $this->assertSame('Retrying', $history[0]['outcome']);
        $this->assertSame('failure_handler_1', $history[0]['actor']);
    }

    public function testWorksWithoutALogger(): void
    {
        $monitor = new ExecutionMonitor(null);

        $result = $monitor->track('exec_1', 'action_1', ['completion_signal' => true]);

        $this->assertSame('Complete', $result['status']);
    }

    public function testTrackNeverExposesResultValueOfCompletedAction(): void
    {
        $monitor = new ExecutionMonitor();

        $result = $monitor->track('exec_1', 'action_1', ['completion_signal' => true]);

        $this->assertArrayNotHasKey('result', $result);
    }
}
