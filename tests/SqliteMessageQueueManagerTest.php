<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Communication\SqliteMessageQueueManager;
use SquirrelForge\Communication\SqliteMessageValidator;

final class SqliteMessageQueueManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-message-queue-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function manager(): SqliteMessageQueueManager
    {
        return new SqliteMessageQueueManager($this->tempPath('queue'));
    }

    /**
     * @return array{source: string, destination: string, operation: string, payload: array<string, mixed>}
     */
    private function message(array $overrides = []): array
    {
        return array_replace([
            'source' => 'agent_1',
            'destination' => 'agent_2',
            'operation' => 'notify',
            'payload' => ['text' => 'hello'],
        ], $overrides);
    }

    public function testEnqueueRejectsAnUnsupportedQueueType(): void
    {
        $manager = $this->manager();

        $result = $manager->enqueue('queue_1', 'astrology', $this->message());

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testEnqueueThenDequeueRoundTrips(): void
    {
        $manager = $this->manager();
        $enqueued = $manager->enqueue('queue_1', 'standard', $this->message());

        $result = $manager->dequeue('queue_1', 'standard');

        $this->assertSame('accepted', $enqueued['outcome']);
        $this->assertSame('delivered', $result['outcome']);
        $this->assertSame($enqueued['message_id'], $result['message_id']);
        $this->assertSame(['text' => 'hello'], $result['payload']);
        $this->assertSame('Processing', $manager->get($result['message_id'])['state']);
    }

    public function testDequeueOnAnEmptyQueueReturnsEmpty(): void
    {
        $manager = $this->manager();

        $result = $manager->dequeue('queue_1', 'standard');

        $this->assertSame('empty', $result['outcome']);
    }

    public function testFifoQueuePreservesInsertionOrder(): void
    {
        $manager = $this->manager();
        $first = $manager->enqueue('queue_1', 'fifo', $this->message(['payload' => ['n' => 1]]));
        $manager->enqueue('queue_1', 'fifo', $this->message(['payload' => ['n' => 2]]));

        $result = $manager->dequeue('queue_1', 'fifo');

        $this->assertSame($first['message_id'], $result['message_id']);
        $this->assertSame(['n' => 1], $result['payload']);
    }

    public function testPriorityQueueDeliversTheHighestPriorityFirst(): void
    {
        $manager = $this->manager();
        $manager->enqueue('queue_1', 'priority', $this->message(['payload' => ['n' => 1]]), ['priority' => 'low']);
        $critical = $manager->enqueue('queue_1', 'priority', $this->message(['payload' => ['n' => 2]]), ['priority' => 'critical']);

        $result = $manager->dequeue('queue_1', 'priority');

        $this->assertSame($critical['message_id'], $result['message_id']);
    }

    public function testEnqueueRejectsAnUnrecognizedPriority(): void
    {
        $manager = $this->manager();

        $result = $manager->enqueue('queue_1', 'standard', $this->message(), ['priority' => 'astrology']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testDelayedMessageIsNotEligibleUntilItsAvailableTime(): void
    {
        $manager = $this->manager();
        $enqueued = $manager->enqueue('queue_1', 'delayed', $this->message(), ['delay_seconds' => 3600]);

        $result = $manager->dequeue('queue_1', 'delayed');

        $this->assertSame('Scheduled', $enqueued['state']);
        $this->assertSame('empty', $result['outcome']);
    }

    public function testEnqueueEnforcesMaximumQueueLength(): void
    {
        $manager = $this->manager();
        $manager->enqueue('queue_1', 'standard', $this->message(), ['max_queue_length' => 1]);

        $result = $manager->enqueue('queue_1', 'standard', $this->message(), ['max_queue_length' => 1]);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testIdempotencyKeyPreventsADuplicateEnqueue(): void
    {
        $manager = $this->manager();
        $first = $manager->enqueue('queue_1', 'standard', $this->message(), ['idempotency_key' => 'idem_1']);

        $second = $manager->enqueue('queue_1', 'standard', $this->message(), ['idempotency_key' => 'idem_1']);

        $this->assertSame('already_queued', $second['outcome']);
        $this->assertSame($first['message_id'], $second['message_id']);
    }

    public function testAcknowledgeMarksTheMessageAcknowledged(): void
    {
        $manager = $this->manager();
        $enqueued = $manager->enqueue('queue_1', 'standard', $this->message());

        $result = $manager->acknowledge($enqueued['message_id']);

        $this->assertSame('acknowledged', $result['outcome']);
        $this->assertSame('Acknowledged', $manager->get($enqueued['message_id'])['state']);
    }

    public function testRetryReschedulesTheMessageUntilTheLimitIsExceeded(): void
    {
        $manager = $this->manager();
        $enqueued = $manager->enqueue('queue_1', 'retry', $this->message(), ['retry_limit' => 1, 'retry_interval_seconds' => 0]);

        $first = $manager->retry($enqueued['message_id'], 'transient failure');
        $second = $manager->retry($enqueued['message_id'], 'transient failure again');

        $this->assertSame('Retrying', $first['state']);
        $this->assertSame('Dead-lettered', $manager->get($enqueued['message_id'])['state']);
        $this->assertNotNull($second);
    }

    public function testDeadLetterPreservesTheMessageRatherThanDeletingIt(): void
    {
        $manager = $this->manager();
        $enqueued = $manager->enqueue('queue_1', 'standard', $this->message());

        $manager->deadLetter($enqueued['message_id'], 'destination unavailable');

        $record = $manager->get($enqueued['message_id']);
        $this->assertSame('Dead-lettered', $record['state']);
        $this->assertSame('destination unavailable', $record['dead_letter_reason']);
    }

    public function testArchiveRequiresAcknowledgedOrDeadLetteredFirst(): void
    {
        $manager = $this->manager();
        $enqueued = $manager->enqueue('queue_1', 'standard', $this->message());

        $result = $manager->archive($enqueued['message_id']);

        $this->assertSame('blocked', $result['outcome']);
    }

    public function testArchiveSucceedsAfterAcknowledgement(): void
    {
        $manager = $this->manager();
        $enqueued = $manager->enqueue('queue_1', 'standard', $this->message());
        $manager->acknowledge($enqueued['message_id']);

        $result = $manager->archive($enqueued['message_id']);

        $this->assertSame('archived', $result['outcome']);
        $this->assertSame('Archived', $manager->get($enqueued['message_id'])['state']);
    }

    public function testExpireOverdueDeadLettersExpiredMessages(): void
    {
        $manager = $this->manager();
        $enqueued = $manager->enqueue('queue_1', 'standard', $this->message(), ['expires_in_seconds' => -1]);

        $expired = $manager->expireOverdue('queue_1');

        $this->assertSame([$enqueued['message_id']], $expired);
        $this->assertSame('Dead-lettered', $manager->get($enqueued['message_id'])['state']);
    }

    public function testQueueHealthReportsRealDepthAndDeadLetterCounts(): void
    {
        $manager = $this->manager();
        $manager->enqueue('queue_1', 'standard', $this->message());
        $deadLettered = $manager->enqueue('queue_1', 'standard', $this->message());
        $manager->deadLetter($deadLettered['message_id'], 'unavailable');

        $health = $manager->queueHealth('queue_1');

        $this->assertSame(1, $health['depth']);
        $this->assertSame(1, $health['dead_lettered']);
    }

    public function testEnqueueRejectsAnInvalidMessageWhenAValidatorIsConfigured(): void
    {
        $validator = new SqliteMessageValidator($this->tempPath('validator'));
        $manager = new SqliteMessageQueueManager($this->tempPath('queue'), $validator);

        $result = $manager->enqueue('queue_1', 'standard', ['operation' => 'notify']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testEnqueueAcceptsAValidMessageWhenAValidatorIsConfigured(): void
    {
        $validator = new SqliteMessageValidator($this->tempPath('validator'));
        $manager = new SqliteMessageQueueManager($this->tempPath('queue'), $validator);

        $result = $manager->enqueue('queue_1', 'standard', $this->message());

        $this->assertSame('accepted', $result['outcome']);
    }
}
