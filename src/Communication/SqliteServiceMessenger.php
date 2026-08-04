<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Coordinates service-to-service communication, per
 * 36_COMMUNICATION/SERVICE-MESSENGER.md: validated, tracked,
 * retry-aware messaging between internal services, integrations,
 * workflows, and infrastructure.
 *
 * This is a genuinely distinct spec from
 * `36_COMMUNICATION/COMMUNICATION-MANAGER.md` (already real as
 * `SqliteCommunicationManager`, which already routes its own
 * `service_message` type through `SqliteMessageBroker`): Message
 * Broker is direct, synchronous dispatch to a registered handler with
 * no queued, trackable delivery state -- it has no "Queued" concept at
 * all. This spec's own ten-state Delivery States list (Created,
 * Validated, Queued, Routed, Delivered, Acknowledged, Responded,
 * Retrying, Failed, Archived) matches `SqliteMessageQueueManager`'s
 * own real state machine almost exactly, so send() composes the queue
 * built last commit rather than the broker -- genuinely new
 * composition Communication Manager does not have, giving service
 * messages the durable, retry-tracked delivery this spec's own state
 * list describes.
 *
 * "Support request-response messaging" genuinely composes the real
 * `SqliteResponseHandler` when the caller sends a `response`-typed
 * message carrying an HTTP-shaped `response` payload (status_code +
 * body) -- the same real status-code classification Response Handler
 * already performs for external responses, reused here rather than
 * reimplemented. A `response` message without that shape is queued
 * like any other message, since not every internal response has an
 * HTTP status code to classify.
 *
 * "Validate service identities" and "verify communication permissions"
 * genuinely compose the real `SqliteMessageValidator` when injected --
 * the same identity/authorization gate `SqliteMessageBroker` already
 * uses for its own routing.
 *
 * Governance status is the fixed constant `ungoverned` since
 * Communication Governance has no code yet.
 *
 * Owns its own database (`Sqlite` prefix): the Audit Requirements name
 * a specific structured record shape every send() call records
 * unconditionally, including rejected and validation-failed messages.
 */
final class SqliteServiceMessenger
{
    private const MESSAGE_TYPES = [
        'request', 'response', 'status', 'health', 'workflow',
        'integration', 'api', 'infrastructure', 'error', 'governance',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteMessageValidator $validator = null,
        private readonly ?SqliteMessageQueueManager $queue = null,
        private readonly ?SqliteResponseHandler $responseHandler = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS service_messaging_operations (
                service_messaging_id TEXT PRIMARY KEY,
                source_service TEXT,
                destination_service TEXT,
                message_type TEXT NOT NULL,
                delivery_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{message_id?: string, source_service?: string, destination_service?: string, message_type?: string, correlation_id?: string, request_id?: ?string, workflow_id?: ?string, priority?: string, payload?: array<string, mixed>, response?: array{status_code: int, body: mixed}} $message
     * @param array{max_queue_length?: ?int, retry_limit?: ?int, retry_interval_seconds?: ?int} $options
     * @return array{service_messaging_id: string, outcome: string, delivery_status: string, error: ?string, result: mixed}
     */
    public function send(array $message, array $options = []): array
    {
        $source = $message['source_service'] ?? null;
        $destination = $message['destination_service'] ?? null;
        $messageType = $message['message_type'] ?? null;

        foreach (['source_service', 'destination_service', 'message_type'] as $field) {
            if (!array_key_exists($field, $message)) {
                return $this->finish($source, $destination, $messageType ?? 'unknown', 'not_applicable', 'rejected', sprintf('Required field "%s" is missing from the service message.', $field), null);
            }
        }

        if (!in_array($messageType, self::MESSAGE_TYPES, true)) {
            return $this->finish($source, $destination, $messageType, 'not_applicable', 'rejected', sprintf('"%s" is not a supported service message type.', $messageType), null);
        }

        if ($this->validator !== null) {
            $validation = $this->validator->validate([
                'message_id' => $message['message_id'] ?? null,
                'source' => $source,
                'destination' => $destination,
                'operation' => $messageType,
                'payload' => $message['payload'] ?? [],
            ]);

            if ($validation['status'] !== 'valid') {
                return $this->finish($source, $destination, $messageType, 'not_applicable', $validation['status'], $validation['error'], null);
            }
        }

        if ($messageType === 'response' && isset($message['response']['status_code']) && $this->responseHandler !== null) {
            $result = $this->responseHandler->process(
                ['status_code' => $message['response']['status_code'], 'body' => $message['response']['body'] ?? null, 'source' => $source, 'request_id' => $message['request_id'] ?? null]
            );

            return $this->finish($source, $destination, $messageType, $result['outcome'] === 'accepted' ? 'Responded' : 'Failed', $result['outcome'] === 'accepted' ? 'responded' : 'rejected', $result['error'], $result);
        }

        if ($this->queue === null) {
            return $this->finish($source, $destination, $messageType, 'not_applicable', 'rejected', 'Message Queue Manager is not configured.', null);
        }

        $result = $this->queue->enqueue($destination, 'service', [
            'message_id' => $message['message_id'] ?? null,
            'source' => $source,
            'destination' => $destination,
            'operation' => $messageType,
            'payload' => $message['payload'] ?? [],
        ], [
            'priority' => $message['priority'] ?? 'normal',
            'max_queue_length' => $options['max_queue_length'] ?? null,
            'retry_limit' => $options['retry_limit'] ?? null,
            'retry_interval_seconds' => $options['retry_interval_seconds'] ?? null,
        ]);

        return $this->finish($source, $destination, $messageType, $result['state'] ?? 'not_applicable', $result['outcome'] === 'accepted' ? 'queued' : 'rejected', $result['error'], $result);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $serviceMessagingId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM service_messaging_operations WHERE service_messaging_id = :id');
        $statement->execute(['id' => $serviceMessagingId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{service_messaging_id: string, outcome: string, delivery_status: string, error: ?string, result: mixed}
     */
    private function finish(
        ?string $source,
        ?string $destination,
        string $messageType,
        string $deliveryStatus,
        string $outcome,
        ?string $error,
        mixed $result
    ): array {
        $serviceMessagingId = 'service_msg_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO service_messaging_operations (
                service_messaging_id, source_service, destination_service, message_type,
                delivery_status, governance_status, final_outcome, error, created_at
            ) VALUES (
                :id, :source_service, :destination_service, :message_type,
                :delivery_status, :governance_status, :final_outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'id' => $serviceMessagingId,
            'source_service' => $source,
            'destination_service' => $destination,
            'message_type' => $messageType,
            'delivery_status' => $deliveryStatus,
            'governance_status' => 'ungoverned',
            'final_outcome' => $outcome,
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'service_messenger.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['service_messaging_id' => $serviceMessagingId, 'source_service' => $source, 'destination_service' => $destination, 'outcome' => $outcome]
        ));

        return ['service_messaging_id' => $serviceMessagingId, 'outcome' => $outcome, 'delivery_status' => $deliveryStatus, 'error' => $error, 'result' => $result];
    }
}
