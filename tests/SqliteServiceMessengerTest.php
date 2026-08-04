<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Communication\SqliteMessageQueueManager;
use SquirrelForge\Communication\SqliteMessageValidator;
use SquirrelForge\Communication\SqliteResponseHandler;
use SquirrelForge\Communication\SqliteServiceMessenger;

final class SqliteServiceMessengerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-service-messenger-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{0: SqliteServiceMessenger, 1: SqliteMessageQueueManager}
     */
    private function fullyWiredMessenger(): array
    {
        $validator = new SqliteMessageValidator($this->tempPath('validator'));
        $queue = new SqliteMessageQueueManager($this->tempPath('queue'));
        $responseHandler = new SqliteResponseHandler($this->tempPath('response'));
        $messenger = new SqliteServiceMessenger($this->tempPath('messenger'), $validator, $queue, $responseHandler);

        return [$messenger, $queue];
    }

    /**
     * @return array{source_service: string, destination_service: string, message_type: string, payload: array<string, mixed>}
     */
    private function message(array $overrides = []): array
    {
        return array_replace([
            'source_service' => 'billing_service',
            'destination_service' => 'notification_service',
            'message_type' => 'request',
            'payload' => ['amount' => 100],
        ], $overrides);
    }

    public function testSendRejectsAMissingRequiredField(): void
    {
        [$messenger] = $this->fullyWiredMessenger();

        $result = $messenger->send(['source_service' => 'billing_service']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testSendRejectsAnUnsupportedMessageType(): void
    {
        [$messenger] = $this->fullyWiredMessenger();

        $result = $messenger->send($this->message(['message_type' => 'astrology']));

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testSendQueuesAValidRequestMessage(): void
    {
        [$messenger, $queue] = $this->fullyWiredMessenger();

        $result = $messenger->send($this->message());

        $this->assertSame('queued', $result['outcome']);
        $this->assertSame('Queued', $result['delivery_status']);
        $this->assertSame(1, $queue->queueHealth('notification_service')['depth']);
        $this->assertNotNull($messenger->get($result['service_messaging_id']));
    }

    public function testSendRejectsAnInvalidMessageWhenValidatorIsConfigured(): void
    {
        [$messenger] = $this->fullyWiredMessenger();

        $result = $messenger->send($this->message(['source_service' => '']));

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testSendWithoutAQueueManagerIsRejected(): void
    {
        $messenger = new SqliteServiceMessenger($this->tempPath('messenger'));

        $result = $messenger->send($this->message());

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testSendProcessesAResponseMessageThroughTheRealResponseHandler(): void
    {
        [$messenger] = $this->fullyWiredMessenger();

        $result = $messenger->send($this->message([
            'message_type' => 'response',
            'response' => ['status_code' => 200, 'body' => ['ok' => true]],
        ]));

        $this->assertSame('responded', $result['outcome']);
        $this->assertSame('Responded', $result['delivery_status']);
        $this->assertSame('success', $result['result']['category']);
    }

    public function testSendMarksAFailedResponseAsFailed(): void
    {
        [$messenger] = $this->fullyWiredMessenger();

        $result = $messenger->send($this->message([
            'message_type' => 'response',
            'response' => ['status_code' => 503, 'body' => []],
        ]));

        $this->assertSame('responded', $result['outcome']);
        $this->assertSame('service_unavailable', $result['result']['category']);
    }

    public function testResponseMessageWithoutStatusCodeFallsBackToQueuing(): void
    {
        [$messenger, $queue] = $this->fullyWiredMessenger();

        $result = $messenger->send($this->message(['message_type' => 'response', 'payload' => ['status' => 'complete']]));

        $this->assertSame('queued', $result['outcome']);
        $this->assertSame(1, $queue->queueHealth('notification_service')['depth']);
    }

    public function testEveryOperationIsRecordedIncludingRejections(): void
    {
        [$messenger] = $this->fullyWiredMessenger();

        $result = $messenger->send($this->message());
        $record = $messenger->get($result['service_messaging_id']);

        $this->assertSame('queued', $record['final_outcome']);
        $this->assertSame('billing_service', $record['source_service']);
        $this->assertSame('notification_service', $record['destination_service']);
        $this->assertSame('ungoverned', $record['governance_status']);
    }
}
