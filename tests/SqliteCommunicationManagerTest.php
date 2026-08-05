<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\AgentRegistry;
use SquirrelForge\Agent\CallbackAgent;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Communication\NotificationChannelRegistry;
use SquirrelForge\Communication\SqliteAgentCommunicator;
use SquirrelForge\Communication\SqliteCommunicationGovernance;
use SquirrelForge\Communication\SqliteCommunicationManager;
use SquirrelForge\Communication\SqliteConversationManager;
use SquirrelForge\Communication\SqliteMessageArchiver;
use SquirrelForge\Communication\SqliteMessageBroker;
use SquirrelForge\Communication\SqliteMessageQueueManager;
use SquirrelForge\Communication\SqliteMessageValidator;
use SquirrelForge\Communication\SqliteNotificationManager;
use SquirrelForge\Communication\SqliteServiceMessenger;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Resilience\RetryManager;
use SquirrelForge\Tests\Support\FakeNotificationChannel;

final class SqliteCommunicationManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-comm-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{0: SqliteMessageBroker, 1: SqliteNotificationManager, 2: AgentRegistry, 3: SqliteAgentCommunicator, 4: EventBus, 5: SqliteCommunicationManager}
     */
    private function makeManager(): array
    {
        $validator = new SqliteMessageValidator($this->tempPath('validator'));
        $broker = new SqliteMessageBroker($this->tempPath('broker'), $validator, new RetryManager(fn() => null));

        $channels = new NotificationChannelRegistry();
        $channels->register(new FakeNotificationChannel('test', [['delivered' => true]]));
        $notifications = new SqliteNotificationManager($this->tempPath('notifications'), $channels);

        $agents = new AgentRegistry();
        $agentCommunicator = new SqliteAgentCommunicator($this->tempPath('agent-comm'), $agents, $broker);

        $events = new EventBus();
        $manager = new SqliteCommunicationManager($this->tempPath('manager'), $broker, $notifications, $agentCommunicator, $events);

        return [$broker, $notifications, $agents, $agentCommunicator, $events, $manager];
    }

    public function testAgentMessageRoutesThroughAgentCommunicator(): void
    {
        [$broker, , $agents, , , $manager] = $this->makeManager();
        $agents->register(new CallbackAgent('agent_1', 'agent_1', 'd', static fn(): bool => true, static fn(array $c): array => $c));
        $agents->register(new CallbackAgent('agent_2', 'agent_2', 'd', static fn(): bool => true, static fn(array $c): array => $c));
        $broker->registerRoute('agent_2', static fn(): string => 'ok');

        $result = $manager->coordinate('agent_message', [
            'source_agent' => 'agent_1',
            'destination_agent' => 'agent_2',
            'message_type' => 'goal',
        ]);

        $this->assertSame('delivered', $result['delivery_status']);
        $this->assertSame('agent_1', $result['source']);
        $this->assertSame('agent_2', $result['destination']);
    }

    public function testNotificationRoutesThroughNotificationManager(): void
    {
        [, , , , , $manager] = $this->makeManager();

        $result = $manager->coordinate('notification', [
            'recipient' => 'user_1',
            'channel' => 'test',
            'priority' => 'normal',
        ]);

        $this->assertSame('delivered', $result['delivery_status']);
        $this->assertSame('user_1', $result['destination']);
    }

    public function testEventDispatchesThroughTheRealEventBusAndIsAlwaysDispatched(): void
    {
        [, , , , $events, $manager] = $this->makeManager();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('order.created', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $result = $manager->coordinate('event', ['name' => 'order.created', 'body' => ['order_id' => 42]]);

        $this->assertSame('dispatched', $result['delivery_status']);
        $this->assertCount(1, $captured);
        $this->assertSame(42, $captured[0]->getPayload()['order_id']);
    }

    public function testGenericServiceMessageRoutesThroughMessageBroker(): void
    {
        [$broker, , , , , $manager] = $this->makeManager();
        $delivered = [];
        $broker->registerRoute('billing-service', function (array $message) use (&$delivered): void {
            $delivered[] = $message;
        });

        $result = $manager->coordinate('service_message', [
            'source' => 'order-service',
            'destination' => 'billing-service',
            'operation' => 'charge',
        ]);

        $this->assertSame('delivered', $result['delivery_status']);
        $this->assertCount(1, $delivered);
    }

    public function testUnsupportedTypeEscalatesWithoutTouchingAnyComponent(): void
    {
        [$broker, , , , , $manager] = $this->makeManager();
        $handlerCalled = false;
        $broker->registerRoute('anything', function () use (&$handlerCalled): void {
            $handlerCalled = true;
        });

        $result = $manager->coordinate('system_message', ['source' => 'kernel', 'destination' => 'anything', 'operation' => 'shutdown']);

        $this->assertSame('escalated', $result['delivery_status']);
        $this->assertFalse($handlerCalled);
    }

    public function testGovernanceMessageIsRejectedWithoutAConfiguredGovernanceComponent(): void
    {
        [, , , , , $manager] = $this->makeManager();

        $result = $manager->coordinate('governance_message', ['communication_component' => 'agent_communication', 'policy_context' => ['ready' => true], 'reviewer' => 'operator_1']);

        $this->assertSame('rejected', $result['delivery_status']);
        $this->assertStringContainsString('Communication Governance', $result['error']);
    }

    public function testGovernanceMessageRoutesToTheRealCommunicationGovernanceAndRecordsItsDecision(): void
    {
        $validator = new SqliteMessageValidator($this->tempPath('validator'));
        $broker = new SqliteMessageBroker($this->tempPath('broker'), $validator, new RetryManager(fn() => null));
        $channels = new NotificationChannelRegistry();
        $channels->register(new FakeNotificationChannel('test', [['delivered' => true]]));
        $notifications = new SqliteNotificationManager($this->tempPath('notifications'), $channels);
        $agents = new AgentRegistry();
        $agentCommunicator = new SqliteAgentCommunicator($this->tempPath('agent-comm'), $agents, $broker);
        $events = new EventBus();
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true], 'effect' => 'allow']);
        $governance = new SqliteCommunicationGovernance($this->tempPath('governance'), $policyEngine);
        $manager = new SqliteCommunicationManager($this->tempPath('manager'), $broker, $notifications, $agentCommunicator, $events, $governance);

        $result = $manager->coordinate('governance_message', ['communication_component' => 'agent_communication', 'policy_context' => ['ready' => true], 'reviewer' => 'operator_1']);

        $this->assertSame('reviewed', $result['delivery_status']);
        $this->assertSame('Approve', $result['governance_status']);
    }

    public function testServiceMessagingIsRejectedWithoutAConfiguredServiceMessenger(): void
    {
        [, , , , , $manager] = $this->makeManager();

        $result = $manager->coordinate('service_messaging', ['source_service' => 'a', 'destination_service' => 'b', 'message_type' => 'request']);

        $this->assertSame('rejected', $result['delivery_status']);
        $this->assertStringContainsString('Service Messenger', $result['error']);
    }

    public function testServiceMessagingRoutesToTheRealServiceMessenger(): void
    {
        [$broker, $notifications, , $agentCommunicator, $events, $manager] = $this->makeManager();
        $queue = new SqliteMessageQueueManager($this->tempPath('queue'));
        $serviceMessenger = new SqliteServiceMessenger($this->tempPath('service-messenger'), queue: $queue);
        $manager = new SqliteCommunicationManager($this->tempPath('manager'), $broker, $notifications, $agentCommunicator, $events, serviceMessenger: $serviceMessenger);

        $result = $manager->coordinate('service_messaging', ['source_service' => 'a', 'destination_service' => 'b', 'message_type' => 'request']);

        $this->assertSame('Queued', $result['delivery_status']);
    }

    public function testMessageArchiveIsRejectedWithoutAConfiguredArchiver(): void
    {
        [, , , , , $manager] = $this->makeManager();

        $result = $manager->coordinate('message_archive', ['archive_type' => 'user_message', 'record' => ['source' => 'a'], 'retention_classification' => 'standard']);

        $this->assertSame('rejected', $result['delivery_status']);
        $this->assertStringContainsString('Message Archiver', $result['error']);
    }

    public function testMessageArchiveRoutesToTheRealMessageArchiver(): void
    {
        [$broker, $notifications, , $agentCommunicator, $events] = $this->makeManager();
        $archiver = new SqliteMessageArchiver($this->tempPath('archiver'));
        $manager = new SqliteCommunicationManager($this->tempPath('manager'), $broker, $notifications, $agentCommunicator, $events, archiver: $archiver);

        $result = $manager->coordinate('message_archive', ['archive_type' => 'user_message', 'record' => ['source' => 'a'], 'retention_classification' => 'standard']);

        $this->assertSame('archived', $result['delivery_status']);
        $this->assertNotNull($result['destination']);
    }

    public function testStartConversationIsRejectedWithoutAConfiguredConversationManager(): void
    {
        [, , , , , $manager] = $this->makeManager();

        $result = $manager->coordinate('start_conversation', ['participants' => ['agent_1', 'agent_2'], 'conversation_type' => 'workflow_coordination']);

        $this->assertSame('rejected', $result['delivery_status']);
        $this->assertStringContainsString('Conversation Manager', $result['error']);
    }

    public function testStartConversationThenAppendConversationMessageRouteToTheRealConversationManager(): void
    {
        [$broker, $notifications, , $agentCommunicator, $events] = $this->makeManager();
        $conversations = new SqliteConversationManager($this->tempPath('conversations'));
        $manager = new SqliteCommunicationManager($this->tempPath('manager'), $broker, $notifications, $agentCommunicator, $events, conversations: $conversations);

        $started = $manager->coordinate('start_conversation', ['participants' => ['agent_1', 'agent_2'], 'conversation_type' => 'workflow_coordination']);
        $appended = $manager->coordinate('append_conversation_message', ['conversation_ref' => $started['destination'], 'participant' => 'agent_1', 'content' => ['text' => 'hello']]);

        $this->assertSame('active', $started['delivery_status']);
        $this->assertSame('appended', $appended['delivery_status']);
    }

    public function testUnknownTypeIsRejected(): void
    {
        [, , , , , $manager] = $this->makeManager();

        $result = $manager->coordinate('carrier_pigeon', ['body' => 'hi']);

        $this->assertSame('rejected', $result['delivery_status']);
        $this->assertSame('invalid', $result['validation_status']);
    }

    public function testMissingRequiredFieldIsRejectedBeforeRoutingIsAttempted(): void
    {
        [$broker, , , , , $manager] = $this->makeManager();
        $handlerCalled = false;
        $broker->registerRoute('billing-service', function () use (&$handlerCalled): void {
            $handlerCalled = true;
        });

        $result = $manager->coordinate('service_message', ['source' => 'order-service', 'destination' => 'billing-service']);

        $this->assertSame('rejected', $result['delivery_status']);
        $this->assertStringContainsString('operation', (string) $result['error']);
        $this->assertFalse($handlerCalled);
    }

    public function testFinishedCoordinationDispatchesEvent(): void
    {
        [, , , , $events, $manager] = $this->makeManager();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('communication.finished', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $result = $manager->coordinate('notification', ['recipient' => 'user_1', 'channel' => 'test', 'priority' => 'normal']);

        $this->assertCount(1, $captured);
        $this->assertSame($result['communication_ref'], $captured[0]->getPayload()['communication_ref']);
    }

    public function testOperationRetrievesAPersistedRecordByRef(): void
    {
        [, , , , , $manager] = $this->makeManager();
        $created = $manager->coordinate('notification', ['recipient' => 'user_1', 'channel' => 'test', 'priority' => 'normal']);

        $fetched = $manager->operation($created['communication_ref']);

        $this->assertNotNull($fetched);
        $this->assertSame('delivered', $fetched['delivery_status']);
    }

    public function testOperationReturnsNullForUnknownRef(): void
    {
        [, , , , , $manager] = $this->makeManager();

        $this->assertNull($manager->operation('communication_does_not_exist'));
    }

    public function testRecentOrdersMostRecentFirstAndRespectsLimit(): void
    {
        [, , , , , $manager] = $this->makeManager();
        $manager->coordinate('notification', ['recipient' => 'user_1', 'channel' => 'test', 'priority' => 'normal']);
        $manager->coordinate('event', ['name' => 'order.created']);

        $recent = $manager->recent(1);

        $this->assertCount(1, $recent);
        $this->assertSame('event', $recent[0]['type']);
    }
}
