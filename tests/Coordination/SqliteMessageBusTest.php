<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Coordination;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Communication\SqliteMessageBroker;
use SquirrelForge\Communication\SqliteMessageValidator;
use SquirrelForge\Coordination\SqliteFailureRecovery;
use SquirrelForge\Coordination\SqliteMessageBus;

final class SqliteMessageBusTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-message-bus-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function broker(): SqliteMessageBroker
    {
        $validator = new SqliteMessageValidator($this->tempPath('validator'));

        return new SqliteMessageBroker($this->tempPath('broker'), $validator);
    }

    private function bus(?SqliteMessageBroker $broker = null, ?SqliteFailureRecovery $failureRecovery = null): SqliteMessageBus
    {
        return new SqliteMessageBus($this->tempPath('bus'), $broker, $failureRecovery);
    }

    /**
     * @return array{sender: string, recipient: string, message_type: string, task_id: string, priority: string}
     */
    private function minimalMessage(array $overrides = []): array
    {
        return array_replace([
            'sender' => 'agent_planner',
            'recipient' => 'agent_developer',
            'message_type' => 'Task Assignment',
            'task_id' => 'task_1',
            'priority' => 'Medium',
        ], $overrides);
    }

    // --- routing-required field checks ---

    public function testMissingSenderIsRejected(): void
    {
        $bus = $this->bus();
        $message = $this->minimalMessage();
        unset($message['sender']);

        $result = $bus->send($message);

        $this->assertSame('Rejected', $result['status']);
        $this->assertNull($result['message_id']);
    }

    public function testUnrecognizedMessageTypeIsRejected(): void
    {
        $bus = $this->bus();

        $result = $bus->send($this->minimalMessage(['message_type' => 'Made Up Type']));

        $this->assertSame('Rejected', $result['status']);
        $this->assertStringContainsString('Message Types', $result['error']);
    }

    public function testMissingTaskIdIsRejected(): void
    {
        $bus = $this->bus();
        $message = $this->minimalMessage();
        unset($message['task_id']);

        $result = $bus->send($message);

        $this->assertSame('Rejected', $result['status']);
        $this->assertStringContainsString('task_id', $result['error']);
    }

    public function testMissingPriorityIsRejected(): void
    {
        $bus = $this->bus();
        $message = $this->minimalMessage();
        unset($message['priority']);

        $result = $bus->send($message);

        $this->assertSame('Rejected', $result['status']);
        $this->assertStringContainsString('priorities', $result['error']);
    }

    public function testUnrecognizedPriorityIsRejected(): void
    {
        $bus = $this->bus();

        $result = $bus->send($this->minimalMessage(['priority' => 'Urgent']));

        $this->assertSame('Rejected', $result['status']);
    }

    // --- dry run without a broker ---

    public function testSendWithoutABrokerIsADryRun(): void
    {
        $bus = $this->bus();

        $result = $bus->send($this->minimalMessage());

        $this->assertSame('Pending', $result['status']);
        $this->assertNotNull($result['message_id']);
    }

    // --- successful delivery ---

    public function testNonCriticalMessageDeliversDirectly(): void
    {
        $broker = $this->broker();
        $broker->registerRoute('agent_developer', static fn(array $m): array => ['received' => true]);
        $bus = $this->bus($broker);

        $result = $bus->send($this->minimalMessage());

        $this->assertSame('Delivered', $result['status']);
        $this->assertNull($result['error']);
    }

    public function testCriticalMessageRequiresAcknowledgment(): void
    {
        $broker = $this->broker();
        $broker->registerRoute('agent_developer', static fn(array $m): array => ['received' => true]);
        $bus = $this->bus($broker);

        $result = $bus->send($this->minimalMessage(['priority' => 'Critical']));

        $this->assertSame('Delivered Pending Acknowledgment', $result['status']);
    }

    public function testAcknowledgeMovesCriticalMessageToDelivered(): void
    {
        $broker = $this->broker();
        $broker->registerRoute('agent_developer', static fn(array $m): array => ['received' => true]);
        $bus = $this->bus($broker);
        $sent = $bus->send($this->minimalMessage(['priority' => 'Critical']));

        $ack = $bus->acknowledge($sent['message_id']);

        $this->assertSame('acknowledged', $ack['outcome']);
        $record = $bus->get($sent['message_id']);
        $this->assertSame('Delivered', $record['delivery_status']);
        $this->assertTrue($record['acknowledged']);
    }

    public function testAcknowledgeOnNonCriticalMessageIsNotRequired(): void
    {
        $broker = $this->broker();
        $broker->registerRoute('agent_developer', static fn(array $m): array => ['received' => true]);
        $bus = $this->bus($broker);
        $sent = $bus->send($this->minimalMessage());

        $ack = $bus->acknowledge($sent['message_id']);

        $this->assertSame('not_required', $ack['outcome']);
    }

    public function testAcknowledgeUnknownMessageIsNotFound(): void
    {
        $bus = $this->bus();

        $result = $bus->acknowledge('ghost');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testAcknowledgingTwiceIsIdempotent(): void
    {
        $broker = $this->broker();
        $broker->registerRoute('agent_developer', static fn(array $m): array => ['received' => true]);
        $bus = $this->bus($broker);
        $sent = $bus->send($this->minimalMessage(['priority' => 'Critical']));
        $bus->acknowledge($sent['message_id']);

        $result = $bus->acknowledge($sent['message_id']);

        $this->assertSame('already_acknowledged', $result['outcome']);
    }

    // --- failed delivery: composes FailureRecovery, never retries itself ---

    public function testNoRegisteredRouteFailsAndReportsCommunicationFailure(): void
    {
        $broker = $this->broker();
        $failureRecovery = new SqliteFailureRecovery($this->tempPath('recovery'));
        $bus = $this->bus($broker, $failureRecovery);

        $result = $bus->send($this->minimalMessage());

        $this->assertSame('Failed', $result['status']);
        $this->assertSame('Communication Failure', $result['recovery']['failure_type']);
    }

    public function testFailedDeliveryWithoutAFailureRecoveryNeverCrashes(): void
    {
        $broker = $this->broker();
        $bus = $this->bus($broker);

        $result = $bus->send($this->minimalMessage());

        $this->assertSame('Failed', $result['status']);
        $this->assertNull($result['recovery']);
    }

    // --- priority mapping ---

    public function testPriorityIsMappedToCommunicationVocabularyOnTheWire(): void
    {
        $broker = $this->broker();
        $seen = null;
        $broker->registerRoute('agent_developer', function (array $m) use (&$seen): array {
            $seen = $m['priority'];

            return ['received' => true];
        });
        $bus = $this->bus($broker);

        $bus->send($this->minimalMessage(['priority' => 'Medium']));

        $this->assertSame('normal', $seen);
    }

    // --- history ---

    public function testHistoryReturnsMessagesForATaskInOrder(): void
    {
        $broker = $this->broker();
        $broker->registerRoute('agent_developer', static fn(array $m): array => ['received' => true]);
        $bus = $this->bus($broker);
        $first = $bus->send($this->minimalMessage(['message_type' => 'Task Assignment']));
        $second = $bus->send($this->minimalMessage(['message_type' => 'Status Update']));
        $bus->send($this->minimalMessage(['task_id' => 'task_2']));

        $history = $bus->history('task_1');

        $this->assertCount(2, $history);
        $this->assertSame($first['message_id'], $history[0]['message_id']);
        $this->assertSame($second['message_id'], $history[1]['message_id']);
    }

    public function testRejectedMessagesAreNeverRecordedInHistory(): void
    {
        $bus = $this->bus();
        $bus->send($this->minimalMessage(['message_type' => 'Made Up Type']));

        $this->assertSame([], $bus->history('task_1'));
    }

    public function testGetUnknownMessageReturnsNull(): void
    {
        $bus = $this->bus();

        $this->assertNull($bus->get('ghost'));
    }
}
