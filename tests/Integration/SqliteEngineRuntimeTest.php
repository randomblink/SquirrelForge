<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\SqliteEngineRuntime;

final class SqliteEngineRuntimeTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-engine-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testExecutionSurvivesRuntimeRestart(): void
    {
        $firstRuntime = new SqliteEngineRuntime($this->databasePath);
        $submitted = $this->submit($firstRuntime);
        unset($firstRuntime);

        $restartedRuntime = new SqliteEngineRuntime($this->databasePath);
        $result = $restartedRuntime->result($submitted['execution_ref']);
        $validation = $restartedRuntime->validation($submitted['execution_ref']);

        $this->assertSame('COMPLETE', $result['status']);
        $this->assertSame('ACCEPTED', $result['validation_decision']);
        $this->assertSame('ACCEPTED', $validation['decision']);
        $this->assertNotEmpty($validation['completed_at']);
    }

    public function testIndependentRuntimesShareIdempotencyConstraint(): void
    {
        $firstRuntime = new SqliteEngineRuntime($this->databasePath);
        $secondRuntime = new SqliteEngineRuntime($this->databasePath);

        $first = $this->submit($firstRuntime);
        $second = $this->submit($secondRuntime);

        $this->assertSame($first['execution_ref'], $second['execution_ref']);
    }

    public function testInputAndCancellationPersistAcrossRestart(): void
    {
        $runtime = new SqliteEngineRuntime($this->databasePath);
        $submitted = $this->submit($runtime);
        $receipt = $runtime->provideInput(
            $submitted['execution_ref'],
            ['answer' => 'Continue.'],
            'identity_1',
            'permission:allowed',
            'correlation_1'
        );
        $runtime->cancel(
            $submitted['execution_ref'],
            'User cancelled.',
            'identity_1',
            'permission:allowed',
            'correlation_1'
        );
        unset($runtime);

        $restartedRuntime = new SqliteEngineRuntime($this->databasePath);
        $result = $restartedRuntime->result($submitted['execution_ref']);

        $this->assertStringStartsWith('input_receipt_', $receipt['receipt_ref']);
        $this->assertSame('CANCELLED', $result['status']);
        $this->assertNull($result['validation_decision']);
    }

    private function submit(SqliteEngineRuntime $runtime): array
    {
        return $runtime->submit(
            ['request_id' => 'request_1', 'goal' => 'Run persistent workflow.'],
            'project_1',
            'identity_1',
            'permission:allowed',
            'idempotency_1',
            'correlation_1'
        );
    }
}
