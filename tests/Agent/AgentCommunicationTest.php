<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Agent;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\AgentCommunication;
use SquirrelForge\Agent\AgentRegistry;
use SquirrelForge\Agent\CallbackAgent;
use SquirrelForge\Communication\SqliteAgentCommunicator;
use SquirrelForge\Communication\SqliteMessageBroker;
use SquirrelForge\Communication\SqliteMessageValidator;
use SquirrelForge\Coordination\SqliteMessageBus;

final class AgentCommunicationTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-agent-communication-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestFor(string $messageType, array $content, array $overrides = []): array
    {
        return array_replace([
            'sender_role' => 'planner',
            'recipient_role' => 'developer',
            'message_type' => $messageType,
            'content' => $content,
            'priority' => 'Normal',
            'permitted_senders' => ['planner'],
            'permitted_recipients' => ['developer'],
        ], $overrides);
    }

    // --- shape validation ---

    public function testMissingSenderRoleIsInvalid(): void
    {
        $result = (new AgentCommunication())->send($this->requestFor('Request', $this->contentFor('Request'), ['sender_role' => '']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMissingRecipientRoleIsInvalid(): void
    {
        $result = (new AgentCommunication())->send($this->requestFor('Request', $this->contentFor('Request'), ['recipient_role' => null]));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUnrecognizedMessageTypeIsInvalid(): void
    {
        $result = (new AgentCommunication())->send($this->requestFor('MadeUpType', []));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('Message Types', $result['error']);
    }

    // --- fail-closed participation rules ---

    public function testAbsentPermittedSendersIsRejectedNotAssumedOpen(): void
    {
        $request = $this->requestFor('Request', $this->contentFor('Request'));
        unset($request['permitted_senders']);

        $result = (new AgentCommunication())->send($request);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('No participation rule is defined', $result['error']);
    }

    public function testEmptyPermittedRecipientsIsRejectedNotAssumedOpen(): void
    {
        $result = (new AgentCommunication())->send($this->requestFor('Request', $this->contentFor('Request'), ['permitted_recipients' => []]));

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testSenderRoleNotInPermittedListIsRejected(): void
    {
        $result = (new AgentCommunication())->send($this->requestFor('Request', $this->contentFor('Request'), ['sender_role' => 'auditor']));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('not a permitted sender', $result['error']);
    }

    public function testRecipientRoleNotInPermittedListIsRejected(): void
    {
        $result = (new AgentCommunication())->send($this->requestFor('Request', $this->contentFor('Request'), ['recipient_role' => 'auditor']));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('not a permitted recipient', $result['error']);
    }

    // --- required content, per message type ---

    /**
     * @return array<int, array{0: string}>
     */
    public static function messageTypeProvider(): array
    {
        return [['Request'], ['Response'], ['Notification'], ['Command'], ['Status'], ['Event'], ['Alert']];
    }

    #[DataProvider('messageTypeProvider')]
    public function testMessageWithFullRequiredContentIsSent(string $messageType): void
    {
        $result = (new AgentCommunication())->send($this->requestFor($messageType, $this->contentFor($messageType)));

        $this->assertSame('sent', $result['outcome']);
        $this->assertSame($messageType, $result['message_type']);
    }

    #[DataProvider('messageTypeProvider')]
    public function testMessageMissingRequiredContentIsRejected(string $messageType): void
    {
        $result = (new AgentCommunication())->send($this->requestFor($messageType, []));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('missing required content', $result['error']);
    }

    // --- priority ---

    public function testMissingPriorityIsInvalid(): void
    {
        $request = $this->requestFor('Request', $this->contentFor('Request'));
        unset($request['priority']);

        $result = (new AgentCommunication())->send($request);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUnrecognizedPriorityIsInvalid(): void
    {
        $result = (new AgentCommunication())->send($this->requestFor('Request', $this->contentFor('Request'), ['priority' => 'Urgent']));

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- governance flag ---

    public function testGovernanceRequiredDefaultsFalse(): void
    {
        $result = (new AgentCommunication())->send($this->requestFor('Request', $this->contentFor('Request')));

        $this->assertFalse($result['governance_required']);
    }

    public function testGovernanceRequiredIsCarriedWhenDeclared(): void
    {
        $result = (new AgentCommunication())->send($this->requestFor('Request', $this->contentFor('Request'), ['governance_required' => true]));

        $this->assertTrue($result['governance_required']);
    }

    // --- delivery: no target configured is a dry run ---

    public function testNoDeliverySelectionIsADryRunWithNoDeliveryResult(): void
    {
        $result = (new AgentCommunication())->send($this->requestFor('Request', $this->contentFor('Request')));

        $this->assertSame('sent', $result['outcome']);
        $this->assertNull($result['delivery_result']);
    }

    public function testPipelineDeliveryWithNoMessageBusComposedIsADryRun(): void
    {
        $result = (new AgentCommunication())->send($this->requestFor('Status', $this->contentFor('Status'), [
            'delivery' => 'pipeline',
            'pipeline' => ['task_id' => 'task_1', 'message_bus_type' => 'Status Update'],
        ]));

        $this->assertNull($result['delivery_result']);
    }

    // --- delivery: real SqliteMessageBus composition ---

    public function testPipelineDeliveryHandsOffToARealMessageBus(): void
    {
        $bus = new SqliteMessageBus($this->tempPath('bus'));
        $communication = new AgentCommunication($bus);

        $result = $communication->send($this->requestFor('Status', $this->contentFor('Status'), [
            'delivery' => 'pipeline',
            'pipeline' => ['task_id' => 'task_1', 'message_bus_type' => 'Status Update'],
        ]));

        $this->assertSame('sent', $result['outcome']);
        $this->assertNotNull($result['delivery_result']);
        $this->assertSame('Pending', $result['delivery_result']['status']);
        $this->assertSame('task_1', $result['delivery_result']['task_id']);
    }

    public function testPipelineDeliveryMapsThisSpecsPriorityOntoMessageBussRealVocabulary(): void
    {
        $bus = new SqliteMessageBus($this->tempPath('bus'));
        $communication = new AgentCommunication($bus);

        $result = $communication->send($this->requestFor('Status', $this->contentFor('Status'), [
            'priority' => 'Normal',
            'delivery' => 'pipeline',
            'pipeline' => ['task_id' => 'task_1', 'message_bus_type' => 'Status Update'],
        ]));

        // AgentCommunication's own "Normal" maps onto SqliteMessageBus's real "Medium".
        $this->assertSame('Medium', $result['delivery_result']['priority']);
    }

    public function testPipelineDeliveryRejectedByMessageBusIsSurfacedNotHidden(): void
    {
        $bus = new SqliteMessageBus($this->tempPath('bus'));
        $communication = new AgentCommunication($bus);

        // "Made Up Type" is not one of SqliteMessageBus's own real Message Types.
        $result = $communication->send($this->requestFor('Status', $this->contentFor('Status'), [
            'delivery' => 'pipeline',
            'pipeline' => ['task_id' => 'task_1', 'message_bus_type' => 'Made Up Type'],
        ]));

        $this->assertSame('sent', $result['outcome']);
        $this->assertSame('Rejected', $result['delivery_result']['status']);
    }

    // --- delivery: real SqliteAgentCommunicator composition ---

    private function agent(string $id): CallbackAgent
    {
        return new CallbackAgent($id, $id, 'A test agent', static fn(): bool => true, static fn(array $c): array => $c);
    }

    public function testAgentDeliveryHandsOffToARealAgentCommunicator(): void
    {
        $agents = new AgentRegistry();
        $agents->register($this->agent('agent_1'));
        $agents->register($this->agent('agent_2'));
        $validator = new SqliteMessageValidator($this->tempPath('validator'));
        $broker = new SqliteMessageBroker($this->tempPath('broker'), $validator);
        $communicator = new SqliteAgentCommunicator($this->tempPath('communicator'), $agents, $broker);
        $received = [];
        $broker->registerRoute('agent_2', function (array $message) use (&$received): void {
            $received[] = $message;
        });

        $communication = new AgentCommunication(null, $communicator);

        $result = $communication->send($this->requestFor('Request', $this->contentFor('Request'), [
            'delivery' => 'agent',
            'agent' => ['source_agent_id' => 'agent_1', 'destination_agent_id' => 'agent_2', 'agent_communicator_type' => 'task_delegation'],
        ]));

        $this->assertSame('sent', $result['outcome']);
        $this->assertSame('delivered', $result['delivery_result']['delivery_status']);
        $this->assertSame('task_delegation', $result['delivery_result']['message_type']);
        $this->assertCount(1, $received);
    }

    public function testAgentDeliveryToAnUnregisteredAgentIsRejectedByTheRealCommunicator(): void
    {
        $agents = new AgentRegistry();
        $agents->register($this->agent('agent_1'));
        $validator = new SqliteMessageValidator($this->tempPath('validator'));
        $broker = new SqliteMessageBroker($this->tempPath('broker'), $validator);
        $communicator = new SqliteAgentCommunicator($this->tempPath('communicator'), $agents, $broker);
        $communication = new AgentCommunication(null, $communicator);

        $result = $communication->send($this->requestFor('Request', $this->contentFor('Request'), [
            'delivery' => 'agent',
            'agent' => ['source_agent_id' => 'agent_1', 'destination_agent_id' => 'ghost', 'agent_communicator_type' => 'task_delegation'],
        ]));

        $this->assertSame('sent', $result['outcome']);
        $this->assertSame('rejected', $result['delivery_result']['delivery_status']);
        $this->assertStringContainsString('Unknown destination agent', $result['delivery_result']['error']);
    }

    /**
     * @return array<string, mixed>
     */
    private function contentFor(string $messageType): array
    {
        return match ($messageType) {
            'Request' => ['requesting_role' => 'planner', 'requested_action' => 'review the design', 'reason' => 'needs sign-off before build'],
            'Response' => ['correlation_ref' => 'request_1', 'result' => 'approved'],
            'Notification' => ['change' => 'spec updated', 'relevance' => 'affects your active task'],
            'Command' => ['instruction' => 'halt deployment', 'scope' => 'production', 'permission_ref' => 'perm_1'],
            'Status' => ['current_state' => 'ACTIVE', 'change_since_last' => 'none'],
            'Event' => ['occurrence' => 'checkpoint reached', 'affected_refs' => ['task_1']],
            'Alert' => ['condition' => 'disk usage above threshold', 'severity' => 'High', 'required_response' => 'free up space'],
            default => [],
        };
    }
}
