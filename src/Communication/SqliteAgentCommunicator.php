<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Agent\AgentRegistry;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Coordinates agent-to-agent communication, per
 * 36_COMMUNICATION/AGENT-COMMUNICATOR.md.
 *
 * This component's real value over calling SqliteMessageBroker directly
 * is agent-specific identity validation: both the source and destination
 * must be agents actually registered in the real src/Agent/AgentRegistry,
 * not just well-formed message endpoints. Delivery itself is delegated
 * entirely to SqliteMessageBroker, which already enforces "must never
 * bypass message validation" through its required SqliteMessageValidator
 * dependency -- duplicating that check here would just be redundant, not
 * more correct.
 *
 * This is a deliberately scoped subset of the spec: only the ten listed
 * "Supported Agent Message Types" are accepted (an unrecognized type is
 * rejected outright, per "must never allow unauthorized agent
 * communication"), and delivery states collapse to whatever
 * SqliteMessageBroker reports (`delivered`/`rejected`/`blocked`/`failed`/
 * `escalated`) rather than the spec's ten-state lifecycle -- there's no
 * separate queue/acknowledgment/processing layer to produce
 * Queued/Acknowledged/Processing/Responded states from. Like
 * SqliteMessageValidator and SqliteMessageBroker, every send() call is
 * persisted regardless of outcome, since recording agent communication
 * activity -- including rejected and unauthorized attempts -- is this
 * component's actual audit job, not an afterthought. Governance status
 * is the fixed constant `ungoverned` since 23_GOVERNANCE has no code.
 */
final class SqliteAgentCommunicator
{
    private const MESSAGE_TYPES = [
        'goal', 'task_delegation', 'context_sharing', 'status_update', 'result_summary',
        'error_report', 'collaboration_request', 'coordination_event', 'escalation', 'governance',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly AgentRegistry $agents,
        private readonly SqliteMessageBroker $broker,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS agent_communications (
                communication_ref TEXT PRIMARY KEY,
                source_agent TEXT NOT NULL,
                destination_agent TEXT NOT NULL,
                message_type TEXT NOT NULL,
                delivery_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                broker_ref TEXT,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{goal_id?: ?string, workflow_id?: ?string, priority?: string, correlation_id?: string, access_token?: ?string, permission_ref?: ?string, max_attempts?: int} $options
     * @return array{communication_ref: string, source_agent: string, destination_agent: string, message_type: string, delivery_status: string, governance_status: string, broker_ref: ?string, error: ?string}
     */
    public function send(string $sourceAgentId, string $destinationAgentId, string $messageType, array $payload = [], array $options = []): array
    {
        if (!in_array($messageType, self::MESSAGE_TYPES, true)) {
            return $this->persist(
                $sourceAgentId,
                $destinationAgentId,
                $messageType,
                'rejected',
                null,
                sprintf('Unsupported agent message type "%s".', $messageType)
            );
        }

        if ($this->agents->get($sourceAgentId) === null) {
            return $this->persist($sourceAgentId, $destinationAgentId, $messageType, 'rejected', null, sprintf('Unknown source agent "%s".', $sourceAgentId));
        }

        if ($this->agents->get($destinationAgentId) === null) {
            return $this->persist($sourceAgentId, $destinationAgentId, $messageType, 'rejected', null, sprintf('Unknown destination agent "%s".', $destinationAgentId));
        }

        $message = [
            'message_id' => 'agent_message_' . bin2hex(random_bytes(12)),
            'source' => $sourceAgentId,
            'destination' => $destinationAgentId,
            'operation' => $messageType,
            'payload' => [
                'body' => $payload,
                'goal_id' => $options['goal_id'] ?? null,
                'workflow_id' => $options['workflow_id'] ?? null,
            ],
            ...isset($options['priority']) ? ['priority' => $options['priority']] : [],
        ];

        $result = $this->broker->route($message, [
            'access_token' => $options['access_token'] ?? null,
            'permission_ref' => $options['permission_ref'] ?? null,
            'correlation_id' => $options['correlation_id'] ?? null,
            'max_attempts' => $options['max_attempts'] ?? 3,
        ]);

        return $this->persist(
            $sourceAgentId,
            $destinationAgentId,
            $messageType,
            $result['delivery_status'],
            $result['broker_ref'],
            $result['error']
        );
    }

    /**
     * @return array{communication_ref: string, source_agent: string, destination_agent: string, message_type: string, delivery_status: string, governance_status: string, broker_ref: ?string, error: ?string}
     */
    private function persist(
        string $sourceAgentId,
        string $destinationAgentId,
        string $messageType,
        string $deliveryStatus,
        ?string $brokerRef,
        ?string $error
    ): array {
        $communicationRef = 'agent_communication_' . bin2hex(random_bytes(12));
        $createdAt = gmdate(DATE_RFC3339);
        $governanceStatus = 'ungoverned';

        $statement = $this->database->prepare(
            'INSERT INTO agent_communications (
                communication_ref, source_agent, destination_agent, message_type,
                delivery_status, governance_status, broker_ref, error, created_at
            ) VALUES (
                :communication_ref, :source_agent, :destination_agent, :message_type,
                :delivery_status, :governance_status, :broker_ref, :error, :created_at
            )'
        );
        $statement->execute([
            'communication_ref' => $communicationRef,
            'source_agent' => $sourceAgentId,
            'destination_agent' => $destinationAgentId,
            'message_type' => $messageType,
            'delivery_status' => $deliveryStatus,
            'governance_status' => $governanceStatus,
            'broker_ref' => $brokerRef,
            'error' => $error,
            'created_at' => $createdAt,
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'agent_message.sent',
            new DateTimeImmutable(),
            self::class,
            ['communication_ref' => $communicationRef, 'message_type' => $messageType, 'delivery_status' => $deliveryStatus]
        ));

        return [
            'communication_ref' => $communicationRef,
            'source_agent' => $sourceAgentId,
            'destination_agent' => $destinationAgentId,
            'message_type' => $messageType,
            'delivery_status' => $deliveryStatus,
            'governance_status' => $governanceStatus,
            'broker_ref' => $brokerRef,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function communication(string $communicationRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM agent_communications WHERE communication_ref = :communication_ref'
        );
        $statement->execute(['communication_ref' => $communicationRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM agent_communications ORDER BY rowid DESC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
