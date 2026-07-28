<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\AgentRegistry;
use SquirrelForge\Agent\CallbackAgent;
use SquirrelForge\Communication\SqliteAgentCommunicator;
use SquirrelForge\Communication\SqliteMessageBroker;
use SquirrelForge\Communication\SqliteMessageValidator;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Resilience\RetryManager;

final class SqliteAgentCommunicatorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-agent-comm-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function agent(string $id): CallbackAgent
    {
        return new CallbackAgent($id, $id, 'A test agent', static fn(): bool => true, static fn(array $c): array => $c);
    }

    /**
     * @return array{0: AgentRegistry, 1: SqliteMessageBroker, 2: SqliteAgentCommunicator}
     */
    private function makeCommunicator(?EventBusInterface $events = null): array
    {
        $agents = new AgentRegistry();
        $validator = new SqliteMessageValidator($this->tempPath('validator'));
        $broker = new SqliteMessageBroker($this->tempPath('broker'), $validator, new RetryManager(fn() => null));
        $communicator = new SqliteAgentCommunicator($this->tempPath('communicator'), $agents, $broker, $events);

        return [$agents, $broker, $communicator];
    }

    public function testMessageBetweenTwoRegisteredAgentsIsDelivered(): void
    {
        [$agents, $broker, $communicator] = $this->makeCommunicator();
        $agents->register($this->agent('agent_1'));
        $agents->register($this->agent('agent_2'));
        $received = [];
        $broker->registerRoute('agent_2', function (array $message) use (&$received): void {
            $received[] = $message;
        });

        $result = $communicator->send('agent_1', 'agent_2', 'goal', ['objective' => 'ship it']);

        $this->assertSame('delivered', $result['delivery_status']);
        $this->assertSame('goal', $result['message_type']);
        $this->assertNotNull($result['broker_ref']);
        $this->assertSame(['objective' => 'ship it'], $received[0]['payload']['body']);
    }

    public function testUnsupportedMessageTypeIsRejectedWithoutInvolvingTheBroker(): void
    {
        [$agents, $broker, $communicator] = $this->makeCommunicator();
        $agents->register($this->agent('agent_1'));
        $agents->register($this->agent('agent_2'));
        $handlerCalled = false;
        $broker->registerRoute('agent_2', function () use (&$handlerCalled): void {
            $handlerCalled = true;
        });

        $result = $communicator->send('agent_1', 'agent_2', 'small_talk');

        $this->assertSame('rejected', $result['delivery_status']);
        $this->assertNull($result['broker_ref']);
        $this->assertFalse($handlerCalled);
    }

    public function testUnknownSourceAgentIsRejected(): void
    {
        [$agents, , $communicator] = $this->makeCommunicator();
        $agents->register($this->agent('agent_2'));

        $result = $communicator->send('agent_ghost', 'agent_2', 'goal');

        $this->assertSame('rejected', $result['delivery_status']);
        $this->assertStringContainsString('Unknown source agent', (string) $result['error']);
    }

    public function testUnknownDestinationAgentIsRejected(): void
    {
        [$agents, , $communicator] = $this->makeCommunicator();
        $agents->register($this->agent('agent_1'));

        $result = $communicator->send('agent_1', 'agent_ghost', 'goal');

        $this->assertSame('rejected', $result['delivery_status']);
        $this->assertStringContainsString('Unknown destination agent', (string) $result['error']);
    }

    public function testUnregisteredRouteOnAnOtherwiseValidMessageFails(): void
    {
        [$agents, , $communicator] = $this->makeCommunicator();
        $agents->register($this->agent('agent_1'));
        $agents->register($this->agent('agent_2'));

        $result = $communicator->send('agent_1', 'agent_2', 'status_update');

        $this->assertSame('failed', $result['delivery_status']);
        $this->assertNotNull($result['broker_ref']);
    }

    public function testSentMessageDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('agent_message.sent', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        [$agents, $broker, $communicator] = $this->makeCommunicator($events);
        $agents->register($this->agent('agent_1'));
        $agents->register($this->agent('agent_2'));
        $broker->registerRoute('agent_2', static fn(): string => 'ok');

        $result = $communicator->send('agent_1', 'agent_2', 'goal');

        $this->assertCount(1, $captured);
        $this->assertSame($result['communication_ref'], $captured[0]->getPayload()['communication_ref']);
    }

    public function testCommunicationRetrievesAPersistedRecordByRef(): void
    {
        [$agents, $broker, $communicator] = $this->makeCommunicator();
        $agents->register($this->agent('agent_1'));
        $agents->register($this->agent('agent_2'));
        $broker->registerRoute('agent_2', static fn(): string => 'ok');
        $created = $communicator->send('agent_1', 'agent_2', 'goal');

        $fetched = $communicator->communication($created['communication_ref']);

        $this->assertNotNull($fetched);
        $this->assertSame('delivered', $fetched['delivery_status']);
    }

    public function testCommunicationReturnsNullForUnknownRef(): void
    {
        [, , $communicator] = $this->makeCommunicator();

        $this->assertNull($communicator->communication('agent_communication_does_not_exist'));
    }

    public function testRecentOrdersMostRecentFirstAndRespectsLimit(): void
    {
        [$agents, $broker, $communicator] = $this->makeCommunicator();
        $agents->register($this->agent('agent_1'));
        $agents->register($this->agent('agent_2'));
        $broker->registerRoute('agent_2', static fn(): string => 'ok');

        $communicator->send('agent_1', 'agent_2', 'goal');
        $communicator->send('agent_1', 'agent_2', 'status_update');

        $recent = $communicator->recent(1);

        $this->assertCount(1, $recent);
        $this->assertSame('status_update', $recent[0]['message_type']);
    }
}
