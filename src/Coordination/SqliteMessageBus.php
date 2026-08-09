<?php

declare(strict_types=1);

namespace SquirrelForge\Coordination;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Communication\SqliteMessageBroker;

/**
 * Delivers pipeline stage-to-stage messages between agents, preserving
 * execution context and task correlation across a handoff, per
 * 17_COORDINATION/MESSAGE-BUS.md -- the third real component in
 * 17_COORDINATION.
 *
 * "The Bus rides on `36_COMMUNICATION`'s underlying transport, message
 * validation, and security rather than re-implementing them" (Purpose)
 * is genuine composition of the already-real `SqliteMessageBroker`
 * (which itself composes `SqliteMessageValidator` and never bypasses
 * it) -- this class never re-validates structure, authenticates a
 * sender, or authorizes a destination itself; those stay
 * `36_COMMUNICATION`'s job entirely. What this class owns is exactly
 * what the Purpose grants: confirming this spec's own routing-required
 * fields (sender, recipient, a recognized Message Type, and a Task ID,
 * since every one of this spec's seven pipeline Message Types
 * describes task-scoped content) are present *before* handing off to
 * the broker -- "must not be handed off as if it were already
 * routing-ready" (Inputs) is a real, checked guard, not documentation.
 *
 * This spec's own Priority vocabulary (`Low`/`Medium`/`High`/`Critical`)
 * is real and distinct from `36_COMMUNICATION`'s own six-value
 * priority set (`emergency`/`critical`/`high`/`normal`/`low`/
 * `informational`) -- `send()` maps between them explicitly
 * (`Medium` -> `normal`) rather than assuming they're interchangeable,
 * the same "different owners, different real vocabularies" reasoning
 * `SqliteFailureRecovery` already applies to `FailureHandler`'s
 * Failure Types.
 *
 * "Require explicit acknowledgment for Critical-priority messages"
 * is real, checked state, not merely a returned flag: a `Critical`
 * message that the broker reports `delivered` lands at `Delivered
 * Pending Acknowledgment`, not `Delivered`, until a separate
 * `acknowledge()` call confirms it -- "no task state may change merely
 * because a message was sent" (Rule) is honored by that intermediate
 * state existing at all.
 *
 * "Hand a failed delivery to `FAILURE-RECOVERY.md` rather than
 * retrying or escalating independently" composes the already-real
 * `SqliteFailureRecovery::decide()` directly, reporting the failure as
 * a `Communication Failure` -- the exact type that component's own
 * documentation already names for `MESSAGE-BUS.md` delivery failures.
 * This class never retries a delivery itself; whatever `decide()`
 * returns is passed straight back to the caller as evidence, never
 * acted on here.
 *
 * SQLite-backed for "record message history for traceability"
 * (Responsibilities) and to track acknowledgment state across the
 * separate `send()`/`acknowledge()` calls -- distinct from whatever
 * `SqliteMessageBroker` separately persists about its own routing
 * operations, the same "two different actors recording two different
 * aspects of one event" shape `ActionDispatcher`/`FailureHandler`
 * already established in `20_EXECUTION`.
 */
final class SqliteMessageBus
{
    private const MESSAGE_TYPES = [
        'Task Assignment', 'Status Update', 'Validation Result', 'Review Feedback',
        'Dependency Alert', 'Error Report', 'Completion Notice',
    ];

    private const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

    /** Maps this spec's own Priority vocabulary onto 36_COMMUNICATION's real, distinct one. */
    private const PRIORITY_MAP = ['Low' => 'low', 'Medium' => 'normal', 'High' => 'high', 'Critical' => 'critical'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteMessageBroker $broker = null,
        private readonly ?SqliteFailureRecovery $failureRecovery = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS message_bus_history (
                message_id TEXT PRIMARY KEY,
                sender TEXT NOT NULL,
                recipient TEXT NOT NULL,
                message_type TEXT NOT NULL,
                task_id TEXT NOT NULL,
                priority TEXT NOT NULL,
                delivery_status TEXT NOT NULL,
                broker_ref TEXT,
                ack_required INTEGER NOT NULL,
                acknowledged INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Messaging Process steps 1-6.
     *
     * @param array{sender?: ?string, recipient?: ?string, message_type?: ?string, task_id?: ?string, payload?: array<string, mixed>, priority?: ?string} $message
     * @param array{max_retries?: int, critical_dependency_unresolved?: bool, security_or_integrity_at_risk?: bool, human_approval_required?: bool} $recoveryOptions forwarded to SqliteFailureRecovery::decide() on a failed delivery.
     * @return array{
     *     message_id: ?string,
     *     status: string,
     *     sender: ?string,
     *     recipient: ?string,
     *     message_type: ?string,
     *     task_id: ?string,
     *     priority: ?string,
     *     error: ?string,
     *     recovery: ?array<string, mixed>
     * }
     */
    public function send(array $message, array $recoveryOptions = []): array
    {
        $sender = $message['sender'] ?? null;
        $recipient = $message['recipient'] ?? null;
        $messageType = $message['message_type'] ?? null;
        $taskId = $message['task_id'] ?? null;
        $priority = $message['priority'] ?? null;

        if (!$this->present($sender) || !$this->present($recipient) || !is_string($messageType) || !in_array($messageType, self::MESSAGE_TYPES, true)) {
            return $this->result(null, 'Rejected', $sender, $recipient, $messageType, $taskId, $priority, 'Message requires a non-empty sender, recipient, and one of this spec\'s named Message Types.', null);
        }

        if (!$this->present($taskId)) {
            return $this->result(null, 'Rejected', $sender, $recipient, $messageType, $taskId, $priority, sprintf('"%s" is a task-related message type and requires a task_id.', $messageType), null);
        }

        if (!is_string($priority) || !in_array($priority, self::PRIORITIES, true)) {
            return $this->result(null, 'Rejected', $sender, $recipient, $messageType, $taskId, $priority, 'Message requires one of this spec\'s named priorities (Low/Medium/High/Critical).', null);
        }

        $messageId = 'message_' . bin2hex(random_bytes(12));
        $ackRequired = $priority === 'Critical';

        if ($this->broker === null) {
            $this->recordHistory($messageId, $sender, $recipient, $messageType, $taskId, $priority, 'Pending', null, $ackRequired);

            return $this->result($messageId, 'Pending', $sender, $recipient, $messageType, $taskId, $priority, null, null);
        }

        $brokerResult = $this->broker->route([
            'message_id' => $messageId,
            'source' => $sender,
            'destination' => $recipient,
            'operation' => $messageType,
            'payload' => array_merge($message['payload'] ?? [], ['task_id' => $taskId]),
            'priority' => self::PRIORITY_MAP[$priority],
        ]);

        if ($brokerResult['delivery_status'] !== 'delivered') {
            $this->recordHistory($messageId, $sender, $recipient, $messageType, $taskId, $priority, 'Failed', $brokerResult['broker_ref'], false);

            $recovery = $this->failureRecovery?->decide(
                [
                    'action_ref' => $taskId,
                    'failure_type' => 'Communication Failure',
                    'observed_condition' => $brokerResult['error'] ?? sprintf('Message delivery reported status "%s".', $brokerResult['delivery_status']),
                ],
                $recoveryOptions
            );

            return $this->result($messageId, 'Failed', $sender, $recipient, $messageType, $taskId, $priority, $brokerResult['error'], $recovery);
        }

        $status = $ackRequired ? 'Delivered Pending Acknowledgment' : 'Delivered';
        $this->recordHistory($messageId, $sender, $recipient, $messageType, $taskId, $priority, $status, $brokerResult['broker_ref'], $ackRequired);

        return $this->result($messageId, $status, $sender, $recipient, $messageType, $taskId, $priority, null, null);
    }

    /**
     * Confirms explicit acknowledgment of a Critical-priority message.
     *
     * @return array{outcome: string, message_id: string, error: ?string}
     */
    public function acknowledge(string $messageId): array
    {
        $record = $this->get($messageId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'message_id' => $messageId, 'error' => sprintf('Message "%s" does not exist.', $messageId)];
        }

        if (!$record['ack_required']) {
            return ['outcome' => 'not_required', 'message_id' => $messageId, 'error' => 'This message does not require explicit acknowledgment.'];
        }

        if ($record['acknowledged']) {
            return ['outcome' => 'already_acknowledged', 'message_id' => $messageId, 'error' => null];
        }

        $statement = $this->database->prepare(
            "UPDATE message_bus_history SET acknowledged = 1, delivery_status = 'Delivered', updated_at = :updated_at WHERE message_id = :message_id"
        );
        $statement->execute(['updated_at' => (new DateTimeImmutable())->format(DATE_ATOM), 'message_id' => $messageId]);

        return ['outcome' => 'acknowledged', 'message_id' => $messageId, 'error' => null];
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $messageId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM message_bus_history WHERE message_id = :message_id');
        $statement->execute(['message_id' => $messageId]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * "Record message history for traceability" -- every message
     * involving a task, in the order it was sent.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $taskId): array
    {
        $statement = $this->database->prepare('SELECT * FROM message_bus_history WHERE task_id = :task_id ORDER BY rowid ASC');
        $statement->execute(['task_id' => $taskId]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    private function recordHistory(
        string $messageId,
        string $sender,
        string $recipient,
        string $messageType,
        string $taskId,
        string $priority,
        string $status,
        ?string $brokerRef,
        bool $ackRequired
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $statement = $this->database->prepare(
            'INSERT INTO message_bus_history
                (message_id, sender, recipient, message_type, task_id, priority, delivery_status, broker_ref, ack_required, acknowledged, created_at, updated_at)
             VALUES
                (:message_id, :sender, :recipient, :message_type, :task_id, :priority, :delivery_status, :broker_ref, :ack_required, 0, :created_at, :updated_at)'
        );
        $statement->execute([
            'message_id' => $messageId,
            'sender' => $sender,
            'recipient' => $recipient,
            'message_type' => $messageType,
            'task_id' => $taskId,
            'priority' => $priority,
            'delivery_status' => $status,
            'broker_ref' => $brokerRef,
            'ack_required' => $ackRequired ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['ack_required'] = (bool) $row['ack_required'];
        $row['acknowledged'] = (bool) $row['acknowledged'];

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{
     *     message_id: ?string,
     *     status: string,
     *     sender: ?string,
     *     recipient: ?string,
     *     message_type: ?string,
     *     task_id: ?string,
     *     priority: ?string,
     *     error: ?string,
     *     recovery: ?array<string, mixed>
     * }
     */
    private function result(
        ?string $messageId,
        string $status,
        mixed $sender,
        mixed $recipient,
        mixed $messageType,
        mixed $taskId,
        mixed $priority,
        ?string $error,
        ?array $recovery
    ): array {
        return [
            'message_id' => $messageId,
            'status' => $status,
            'sender' => is_string($sender) ? $sender : null,
            'recipient' => is_string($recipient) ? $recipient : null,
            'message_type' => is_string($messageType) ? $messageType : null,
            'task_id' => is_string($taskId) ? $taskId : null,
            'priority' => is_string($priority) ? $priority : null,
            'error' => $error,
            'recovery' => $recovery,
        ];
    }
}
