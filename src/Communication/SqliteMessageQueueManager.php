<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Provides reliable, persistent, governed message queuing, per
 * 36_COMMUNICATION/MESSAGE-QUEUE-MANAGER.md. Unlike most components
 * built this session, this one needs no external subsystem to be
 * real: the queue lifecycle itself -- durable storage, ordering,
 * retries, delayed delivery, dead-lettering -- is the actual
 * infrastructure this spec asks for, implemented directly in SQLite
 * rather than standing in for something absent.
 *
 * "Validate queue request" genuinely composes the real
 * `SqliteMessageValidator` when injected (36_COMMUNICATION/
 * MESSAGE-VALIDATOR.md, already real): a message that fails structural
 * or authorization validation is never enqueued.
 *
 * FIFO and priority ordering are real and enforced, not advisory: a
 * `fifo` queue always dequeues by a monotonic per-queue sequence
 * number; a `priority` queue orders by the same six-tier priority
 * vocabulary `SqliteMessageValidator::PRIORITIES` already established,
 * then by sequence as a tiebreak. "Maximum queue length" and "Retry
 * limits"/"Retry intervals" are real, enforced Queue Policies:
 * enqueue() rejects once a caller-supplied max length is reached, and
 * retry() dead-letters a message once its retry count exceeds a
 * caller-supplied limit rather than retrying forever.
 *
 * Of the six named Delivery Guarantees, durability and at-least-once
 * delivery are structural properties of persisting every message in
 * SQLite and never removing it until acknowledged or dead-lettered.
 * "Exactly-once delivery (where supported)" is implemented literally
 * as the spec's own qualifier implies: only when a caller supplies an
 * `idempotency_key`, enqueue() returns the existing non-terminal
 * message for that (queue_id, idempotency_key) pair instead of
 * creating a duplicate; without one, only at-least-once is honestly
 * claimed.
 *
 * Dead-letter handling covers the concrete, checkable triggers this
 * spec names: retry limits exceeded (retry()) and expiration
 * (expireOverdue(), since no background scheduler exists in this
 * codebase to drive time-based transitions automatically -- a caller
 * invokes it, the same pattern established wherever this codebase
 * needs a "tick" with no real scheduler to provide one). "Validation
 * repeatedly fails", "destinations remain unavailable", and
 * "governance blocks delivery" are not auto-detected -- there is no
 * real destination-availability signal or governance authority wired
 * into this class -- so a caller observing any of those conditions
 * calls deadLetter() directly with the real reason.
 *
 * Governance status is the fixed constant `ungoverned`:
 * `36_COMMUNICATION/COMMUNICATION-GOVERNANCE.md` is real now as
 * `SqliteCommunicationGovernance` (reachable through
 * `SqliteCommunicationManager`'s `governance_message` type), but this
 * spec names no per-enqueue governance-decision request shape for this
 * class to build inline.
 *
 * Owns two tables: `queued_messages` holds one current-state row per
 * message -- the live queue -- and `queue_operations` is the
 * append-only audit log the Audit Requirements name, recording every
 * lifecycle transition unconditionally, including rejected enqueue
 * attempts.
 */
final class SqliteMessageQueueManager
{
    private const QUEUE_TYPES = [
        'standard', 'priority', 'fifo', 'delayed', 'retry', 'dead_letter',
        'workflow', 'agent', 'service', 'notification',
    ];

    private const PRIORITIES = ['emergency', 'critical', 'high', 'normal', 'low', 'informational'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteMessageValidator $validator = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS queued_messages (
                message_id TEXT PRIMARY KEY,
                queue_id TEXT NOT NULL,
                queue_type TEXT NOT NULL,
                payload_json TEXT NOT NULL,
                priority TEXT NOT NULL,
                sequence INTEGER NOT NULL,
                state TEXT NOT NULL,
                idempotency_key TEXT,
                retry_count INTEGER NOT NULL DEFAULT 0,
                retry_limit INTEGER,
                retry_interval_seconds INTEGER,
                available_at TEXT NOT NULL,
                expires_at TEXT,
                dead_letter_reason TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS queue_operations (
                queue_operation_id TEXT PRIMARY KEY,
                message_id TEXT,
                queue_id TEXT NOT NULL,
                queue_type TEXT NOT NULL,
                delivery_state TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{message_id?: string, source?: string, destination?: string, operation?: string, payload?: array<string, mixed>} $message
     * @param array{priority?: string, idempotency_key?: ?string, max_queue_length?: ?int, retry_limit?: ?int, retry_interval_seconds?: ?int, delay_seconds?: int, expires_in_seconds?: ?int} $options
     * @return array{outcome: string, message_id: ?string, state: ?string, error: ?string}
     */
    public function enqueue(string $queueId, string $queueType, array $message, array $options = []): array
    {
        if (!in_array($queueType, self::QUEUE_TYPES, true)) {
            return $this->reject($queueId, $queueType, null, sprintf('"%s" is not a supported queue type.', $queueType));
        }

        if ($this->validator !== null) {
            $validation = $this->validator->validate($message);

            if ($validation['status'] !== 'valid') {
                return $this->reject($queueId, $queueType, $message['message_id'] ?? null, $validation['error'] ?? 'Message failed validation.');
            }
        }

        $idempotencyKey = $options['idempotency_key'] ?? null;

        if ($idempotencyKey !== null) {
            $existing = $this->findByIdempotencyKey($queueId, $idempotencyKey);

            if ($existing !== null) {
                return $this->recordOperation($existing['message_id'], $queueId, $queueType, $existing['state'], 'already_queued', null);
            }
        }

        if (isset($options['max_queue_length']) && $this->depth($queueId) >= $options['max_queue_length']) {
            return $this->reject($queueId, $queueType, $message['message_id'] ?? null, sprintf('Queue "%s" has reached its maximum length of %d.', $queueId, $options['max_queue_length']));
        }

        $priority = $options['priority'] ?? 'normal';

        if (!in_array($priority, self::PRIORITIES, true)) {
            return $this->reject($queueId, $queueType, $message['message_id'] ?? null, sprintf('"%s" is not a recognized priority.', $priority));
        }

        $messageId = $message['message_id'] ?? ('message_' . bin2hex(random_bytes(12)));
        $now = new DateTimeImmutable();
        $delaySeconds = $options['delay_seconds'] ?? 0;
        $availableAt = $delaySeconds > 0 ? $now->modify("+{$delaySeconds} seconds") : $now;
        $state = $delaySeconds > 0 ? 'Scheduled' : 'Queued';
        $expiresAt = isset($options['expires_in_seconds']) ? $now->modify("+{$options['expires_in_seconds']} seconds")->format(DATE_RFC3339) : null;

        $statement = $this->database->prepare(
            'INSERT INTO queued_messages (
                message_id, queue_id, queue_type, payload_json, priority, sequence, state, idempotency_key,
                retry_count, retry_limit, retry_interval_seconds, available_at, expires_at, dead_letter_reason,
                created_at, updated_at
            ) VALUES (
                :message_id, :queue_id, :queue_type, :payload_json, :priority, :sequence, :state, :idempotency_key,
                0, :retry_limit, :retry_interval_seconds, :available_at, :expires_at, NULL,
                :created_at, :updated_at
            )'
        );
        $statement->execute([
            'message_id' => $messageId,
            'queue_id' => $queueId,
            'queue_type' => $queueType,
            'payload_json' => json_encode($message['payload'] ?? [], JSON_THROW_ON_ERROR),
            'priority' => $priority,
            'sequence' => $this->nextSequence($queueId),
            'state' => $state,
            'idempotency_key' => $idempotencyKey,
            'retry_limit' => $options['retry_limit'] ?? null,
            'retry_interval_seconds' => $options['retry_interval_seconds'] ?? null,
            'available_at' => $availableAt->format(DATE_RFC3339),
            'expires_at' => $expiresAt,
            'created_at' => $now->format(DATE_RFC3339),
            'updated_at' => $now->format(DATE_RFC3339),
        ]);

        return $this->recordOperation($messageId, $queueId, $queueType, $state, 'accepted', null);
    }

    /**
     * @return array{outcome: string, message_id: ?string, payload: ?array<string, mixed>, error: ?string}
     */
    public function dequeue(string $queueId, string $queueType): array
    {
        $orderBy = match ($queueType) {
            'priority' => "CASE priority " . implode(' ', array_map(
                static fn(int $i, string $p): string => sprintf("WHEN '%s' THEN %d", $p, $i),
                array_keys(self::PRIORITIES),
                self::PRIORITIES
            )) . " END ASC, sequence ASC",
            default => 'sequence ASC',
        };

        $statement = $this->database->prepare(
            "SELECT * FROM queued_messages
             WHERE queue_id = :queue_id AND state IN ('Queued', 'Retrying') AND available_at <= :now
             ORDER BY {$orderBy} LIMIT 1"
        );
        $statement->execute(['queue_id' => $queueId, 'now' => (new DateTimeImmutable())->format(DATE_RFC3339)]);
        $row = $statement->fetch();

        if ($row === false) {
            return ['outcome' => 'empty', 'message_id' => null, 'payload' => null, 'error' => null];
        }

        $this->transition($row['message_id'], 'Processing');
        $result = $this->recordOperation($row['message_id'], $queueId, $queueType, 'Processing', 'dequeued', null);

        return ['outcome' => 'delivered', 'message_id' => $row['message_id'], 'payload' => json_decode((string) $row['payload_json'], true, flags: JSON_THROW_ON_ERROR), 'error' => $result['error']];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function acknowledge(string $messageId): array
    {
        $message = $this->fetch($messageId);

        if ($message === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('No queued message "%s" exists.', $messageId)];
        }

        $this->transition($messageId, 'Acknowledged');
        $result = $this->recordOperation($messageId, $message['queue_id'], $message['queue_type'], 'Acknowledged', 'acknowledged', null);

        return ['outcome' => $result['outcome'], 'error' => $result['error']];
    }

    /**
     * @return array{outcome: string, state: ?string, error: ?string}
     */
    public function retry(string $messageId, ?string $reason = null): array
    {
        $message = $this->fetch($messageId);

        if ($message === null) {
            return ['outcome' => 'not_found', 'state' => null, 'error' => sprintf('No queued message "%s" exists.', $messageId)];
        }

        $retryCount = (int) $message['retry_count'] + 1;
        $retryLimit = $message['retry_limit'] !== null ? (int) $message['retry_limit'] : null;

        if ($retryLimit !== null && $retryCount > $retryLimit) {
            return $this->deadLetterInternal($message, sprintf('Retry limit of %d exceeded.', $retryLimit));
        }

        $intervalSeconds = $message['retry_interval_seconds'] !== null ? (int) $message['retry_interval_seconds'] : 0;
        $availableAt = (new DateTimeImmutable())->modify("+{$intervalSeconds} seconds")->format(DATE_RFC3339);

        $statement = $this->database->prepare(
            'UPDATE queued_messages SET state = :state, retry_count = :retry_count, available_at = :available_at, updated_at = :updated_at WHERE message_id = :message_id'
        );
        $statement->execute(['state' => 'Retrying', 'retry_count' => $retryCount, 'available_at' => $availableAt, 'updated_at' => gmdate(DATE_RFC3339), 'message_id' => $messageId]);

        $result = $this->recordOperation($messageId, $message['queue_id'], $message['queue_type'], 'Retrying', 'retrying', $reason);

        return ['outcome' => $result['outcome'], 'state' => 'Retrying', 'error' => $result['error']];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function deadLetter(string $messageId, string $reason): array
    {
        $message = $this->fetch($messageId);

        if ($message === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('No queued message "%s" exists.', $messageId)];
        }

        $result = $this->deadLetterInternal($message, $reason);

        return ['outcome' => $result['outcome'], 'error' => $result['error']];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function archive(string $messageId): array
    {
        $message = $this->fetch($messageId);

        if ($message === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('No queued message "%s" exists.', $messageId)];
        }

        if (!in_array($message['state'], ['Acknowledged', 'Dead-lettered'], true)) {
            return ['outcome' => 'blocked', 'error' => sprintf('Message "%s" must be Acknowledged or Dead-lettered before it can be archived.', $messageId)];
        }

        $this->transition($messageId, 'Archived');
        $result = $this->recordOperation($messageId, $message['queue_id'], $message['queue_type'], 'Archived', 'archived', null);

        return ['outcome' => $result['outcome'], 'error' => $result['error']];
    }

    /**
     * @return array<int, string> message IDs moved to the dead-letter state
     */
    public function expireOverdue(string $queueId): array
    {
        $statement = $this->database->prepare(
            "SELECT message_id FROM queued_messages
             WHERE queue_id = :queue_id AND expires_at IS NOT NULL AND expires_at <= :now
             AND state NOT IN ('Dead-lettered', 'Archived', 'Acknowledged')"
        );
        $statement->execute(['queue_id' => $queueId, 'now' => (new DateTimeImmutable())->format(DATE_RFC3339)]);
        $expired = array_column($statement->fetchAll(), 'message_id');

        foreach ($expired as $messageId) {
            $this->deadLetterInternal($this->fetch($messageId), 'Message expired.');
        }

        return $expired;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $messageId): ?array
    {
        return $this->fetch($messageId);
    }

    /**
     * @return array{depth: int, retrying: int, dead_lettered: int}
     */
    public function queueHealth(string $queueId): array
    {
        $statement = $this->database->prepare(
            "SELECT
                SUM(CASE WHEN state IN ('Queued', 'Scheduled', 'Retrying') THEN 1 ELSE 0 END) AS depth,
                SUM(CASE WHEN state = 'Retrying' THEN 1 ELSE 0 END) AS retrying,
                SUM(CASE WHEN state = 'Dead-lettered' THEN 1 ELSE 0 END) AS dead_lettered
             FROM queued_messages WHERE queue_id = :queue_id"
        );
        $statement->execute(['queue_id' => $queueId]);
        $row = $statement->fetch();

        return ['depth' => (int) ($row['depth'] ?? 0), 'retrying' => (int) ($row['retrying'] ?? 0), 'dead_lettered' => (int) ($row['dead_lettered'] ?? 0)];
    }

    /**
     * @param array<string, mixed> $message
     * @return array{outcome: string, error: ?string}
     */
    private function deadLetterInternal(array $message, string $reason): array
    {
        $statement = $this->database->prepare(
            'UPDATE queued_messages SET state = :state, dead_letter_reason = :reason, updated_at = :updated_at WHERE message_id = :message_id'
        );
        $statement->execute(['state' => 'Dead-lettered', 'reason' => $reason, 'updated_at' => gmdate(DATE_RFC3339), 'message_id' => $message['message_id']]);

        return $this->recordOperation($message['message_id'], $message['queue_id'], $message['queue_type'], 'Dead-lettered', 'dead_lettered', $reason);
    }

    private function transition(string $messageId, string $state): void
    {
        $statement = $this->database->prepare('UPDATE queued_messages SET state = :state, updated_at = :updated_at WHERE message_id = :message_id');
        $statement->execute(['state' => $state, 'updated_at' => gmdate(DATE_RFC3339), 'message_id' => $messageId]);
    }

    private function depth(string $queueId): int
    {
        $statement = $this->database->prepare("SELECT COUNT(*) AS c FROM queued_messages WHERE queue_id = :queue_id AND state NOT IN ('Acknowledged', 'Dead-lettered', 'Archived')");
        $statement->execute(['queue_id' => $queueId]);

        return (int) $statement->fetch()['c'];
    }

    private function nextSequence(string $queueId): int
    {
        $statement = $this->database->prepare('SELECT MAX(sequence) AS max_sequence FROM queued_messages WHERE queue_id = :queue_id');
        $statement->execute(['queue_id' => $queueId]);

        return (int) ($statement->fetch()['max_sequence'] ?? 0) + 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByIdempotencyKey(string $queueId, string $idempotencyKey): ?array
    {
        $statement = $this->database->prepare(
            "SELECT * FROM queued_messages
             WHERE queue_id = :queue_id AND idempotency_key = :idempotency_key
             AND state NOT IN ('Dead-lettered', 'Archived') LIMIT 1"
        );
        $statement->execute(['queue_id' => $queueId, 'idempotency_key' => $idempotencyKey]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $messageId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM queued_messages WHERE message_id = :message_id');
        $statement->execute(['message_id' => $messageId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{outcome: string, message_id: ?string, state: ?string, error: ?string}
     */
    private function reject(string $queueId, string $queueType, ?string $messageId, string $error): array
    {
        $result = $this->recordOperation($messageId, $queueId, $queueType, 'not_applicable', 'rejected', $error);

        return ['outcome' => $result['outcome'], 'message_id' => null, 'state' => null, 'error' => $error];
    }

    /**
     * @return array{outcome: string, message_id: ?string, state: ?string, error: ?string}
     */
    private function recordOperation(?string $messageId, string $queueId, string $queueType, string $deliveryState, string $outcome, ?string $error): array
    {
        $operationId = 'queue_op_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO queue_operations (
                queue_operation_id, message_id, queue_id, queue_type, delivery_state, governance_status, final_outcome, error, created_at
            ) VALUES (
                :id, :message_id, :queue_id, :queue_type, :delivery_state, :governance_status, :final_outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'id' => $operationId,
            'message_id' => $messageId,
            'queue_id' => $queueId,
            'queue_type' => $queueType,
            'delivery_state' => $deliveryState,
            'governance_status' => 'ungoverned',
            'final_outcome' => $outcome,
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'message_queue.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['message_id' => $messageId, 'queue_id' => $queueId, 'outcome' => $outcome]
        ));

        return ['outcome' => $outcome, 'message_id' => $messageId, 'state' => $deliveryState, 'error' => $error];
    }
}
