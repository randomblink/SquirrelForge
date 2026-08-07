<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Execution;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Execution\SqliteExecutionLogger;

final class SqliteExecutionLoggerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-execution-logger-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function logger(): SqliteExecutionLogger
    {
        return new SqliteExecutionLogger($this->tempPath('db'));
    }

    /**
     * @return array{execution_id: string, actor: string, action_type: string, outcome: string}
     */
    private function minimalEntry(array $overrides = []): array
    {
        return array_replace([
            'execution_id' => 'exec_1',
            'actor' => 'agent_1',
            'action_type' => 'dispatch',
            'outcome' => 'success',
        ], $overrides);
    }

    public function testRecordRequiresExecutionId(): void
    {
        $logger = $this->logger();
        $entry = $this->minimalEntry();
        unset($entry['execution_id']);

        $result = $logger->record($entry);

        $this->assertSame('invalid_entry', $result['outcome']);
        $this->assertStringContainsString('execution_id', $result['error']);
    }

    public function testRecordRequiresActor(): void
    {
        $logger = $this->logger();
        $entry = $this->minimalEntry();
        unset($entry['actor']);

        $result = $logger->record($entry);

        $this->assertSame('invalid_entry', $result['outcome']);
        $this->assertStringContainsString('actor', $result['error']);
    }

    public function testRecordRequiresActionType(): void
    {
        $logger = $this->logger();
        $entry = $this->minimalEntry();
        unset($entry['action_type']);

        $result = $logger->record($entry);

        $this->assertSame('invalid_entry', $result['outcome']);
    }

    public function testRecordRequiresOutcome(): void
    {
        $logger = $this->logger();
        $entry = $this->minimalEntry();
        unset($entry['outcome']);

        $result = $logger->record($entry);

        $this->assertSame('invalid_entry', $result['outcome']);
    }

    public function testRecordSucceedsAndIsRetrievable(): void
    {
        $logger = $this->logger();

        $result = $logger->record($this->minimalEntry([
            'task_id' => 'task_1',
            'inputs' => ['file' => 'a.txt'],
            'duration_ms' => 42.5,
            'checkpoint_id' => 'checkpoint_1',
        ]));

        $this->assertSame('recorded', $result['outcome']);
        $this->assertNotNull($result['log_ref']);

        $record = $logger->get($result['log_ref']);
        $this->assertSame('exec_1', $record['execution_id']);
        $this->assertSame('task_1', $record['task_id']);
        $this->assertSame(['file' => 'a.txt'], $record['inputs']);
        $this->assertSame(42.5, $record['duration_ms']);
        $this->assertSame('checkpoint_1', $record['checkpoint_id']);
    }

    // --- redaction ---

    public function testBuiltInPatternRedactsCommonSecretShapedKeys(): void
    {
        $logger = $this->logger();

        $result = $logger->record($this->minimalEntry([
            'inputs' => ['password' => 'hunter2', 'api_key' => 'sk-abc', 'credential_ref' => 'cred_1', 'username' => 'bob'],
        ]));

        $record = $logger->get($result['log_ref']);
        $this->assertSame('[REDACTED]', $record['inputs']['password']);
        $this->assertSame('[REDACTED]', $record['inputs']['api_key']);
        $this->assertSame('[REDACTED]', $record['inputs']['credential_ref']);
        $this->assertSame('bob', $record['inputs']['username']);
    }

    public function testCallerDeclaredFieldsAreRedactedToo(): void
    {
        $logger = $this->logger();

        $result = $logger->record(
            $this->minimalEntry(['inputs' => ['patient_name' => 'Jane Doe', 'note' => 'ok']]),
            ['redact_fields' => ['patient_name']]
        );

        $record = $logger->get($result['log_ref']);
        $this->assertSame('[REDACTED]', $record['inputs']['patient_name']);
        $this->assertSame('ok', $record['inputs']['note']);
    }

    public function testRecordWithNoInputsStoresAnEmptyArray(): void
    {
        $logger = $this->logger();

        $result = $logger->record($this->minimalEntry());

        $record = $logger->get($result['log_ref']);
        $this->assertSame([], $record['inputs']);
    }

    // --- get() / history() / search() ---

    public function testGetUnknownLogRefReturnsNull(): void
    {
        $logger = $this->logger();

        $this->assertNull($logger->get('ghost'));
    }

    public function testHistoryReturnsEntriesForOneExecutionInOrder(): void
    {
        $logger = $this->logger();
        $first = $logger->record($this->minimalEntry(['action_type' => 'dispatch']))['log_ref'];
        $second = $logger->record($this->minimalEntry(['action_type' => 'checkpoint']))['log_ref'];
        $logger->record($this->minimalEntry(['execution_id' => 'exec_2']));

        $history = $logger->history('exec_1');

        $this->assertCount(2, $history);
        $this->assertSame($first, $history[0]['log_ref']);
        $this->assertSame($second, $history[1]['log_ref']);
    }

    public function testSearchFiltersByOutcome(): void
    {
        $logger = $this->logger();
        $logger->record($this->minimalEntry(['outcome' => 'success']));
        $failed = $logger->record($this->minimalEntry(['outcome' => 'failure', 'error_category' => 'timeout']))['log_ref'];

        $results = $logger->search(['outcome' => 'failure']);

        $this->assertCount(1, $results);
        $this->assertSame($failed, $results[0]['log_ref']);
        $this->assertSame('timeout', $results[0]['error_category']);
    }

    public function testSearchWithNoFiltersReturnsEverything(): void
    {
        $logger = $this->logger();
        $logger->record($this->minimalEntry());
        $logger->record($this->minimalEntry(['execution_id' => 'exec_2']));

        $this->assertCount(2, $logger->search());
    }

    public function testEntriesAreImmutableThroughThisClassApiSurface(): void
    {
        $this->assertFalse(method_exists(SqliteExecutionLogger::class, 'update'));
        $this->assertFalse(method_exists(SqliteExecutionLogger::class, 'delete'));
    }
}
