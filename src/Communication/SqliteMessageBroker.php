<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Resilience\RetryManager;

/**
 * Routes a message to a registered destination handler, per
 * 36_COMMUNICATION/MESSAGE-BROKER.md.
 *
 * The spec's central safety rule is that the broker "must never ...
 * bypass validation", so a SqliteMessageValidator is a required
 * dependency, not an optional one like RecoveryManager's verifier --
 * every route() call is validated first, and a message the validator
 * rejects or blocks is never looked up or delivered, regardless of
 * whether a handler is registered for its destination.
 *
 * This is a deliberately scoped subset of the spec: only "Direct
 * routing" (a message goes to exactly one registered destination
 * handler) is supported of the ten listed routing types -- topic,
 * queue, and broadcast routing all need a real Message Queue Manager /
 * Event Bus topic model this framework doesn't have yet, so the
 * persisted `routing_rule` is always the fixed value `direct`.
 * Retry-on-delivery-failure delegates to RetryManager, the same real
 * component Recovery Manager and Self-Healing Engine already use, per
 * the spec's "Support retry behavior" responsibility. Governance status
 * is the fixed constant `ungoverned` since 23_GOVERNANCE has no code.
 */
final class SqliteMessageBroker
{
    /**
     * @var array<string, Closure>
     */
    private array $routes = [];

    private PDO $database;
    private RetryManager $retryManager;

    public function __construct(
        string $databasePath,
        private readonly SqliteMessageValidator $validator,
        ?RetryManager $retryManager = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->retryManager = $retryManager ?? new RetryManager();

        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS message_broker_operations (
                broker_ref TEXT PRIMARY KEY,
                message_id TEXT,
                source TEXT,
                destination TEXT,
                routing_rule TEXT NOT NULL,
                delivery_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Registers the handler that owns delivery to a given destination.
     * Only destinations registered this way can ever receive a message.
     *
     * @param Closure(array<string, mixed>): mixed $handler
     */
    public function registerRoute(string $destination, Closure $handler): void
    {
        $this->routes[$destination] = $handler;
    }

    /**
     * @param array{message_id?: string, source?: string, destination?: string, operation?: string, payload?: array<string, mixed>, priority?: string} $message
     * @param array{access_token?: ?string, permission_ref?: ?string, correlation_id?: string, max_attempts?: int} $options
     * @return array{broker_ref: ?string, message_id: ?string, source: ?string, destination: ?string, routing_rule: string, delivery_status: string, governance_status: ?string, error: ?string}
     */
    public function route(array $message, array $options = []): array
    {
        $messageId = $message['message_id'] ?? null;
        $source = $message['source'] ?? null;
        $destination = $message['destination'] ?? null;

        $validation = $this->validator->validate($message, [
            'access_token' => $options['access_token'] ?? null,
            'permission_ref' => $options['permission_ref'] ?? null,
            'correlation_id' => $options['correlation_id'] ?? null,
        ]);

        if ($validation['status'] !== 'valid') {
            return $this->persist(
                $messageId,
                $source,
                $destination,
                $validation['status'] === 'blocked' ? 'blocked' : 'rejected',
                $validation['error']
            );
        }

        $handler = $this->routes[$destination] ?? null;

        if ($handler === null) {
            return $this->persist($messageId, $source, $destination, 'failed', sprintf('No route registered for destination "%s".', $destination));
        }

        $result = $this->retryManager->execute(
            static fn(): mixed => $handler($message),
            ['max_attempts' => $options['max_attempts'] ?? 3]
        );

        if ($result['status'] === 'successful') {
            return $this->persist($messageId, $source, $destination, 'delivered', null);
        }

        return $this->persist(
            $messageId,
            $source,
            $destination,
            $result['status'] === 'escalated' ? 'escalated' : 'failed',
            $result['error']
        );
    }

    /**
     * @return array{broker_ref: string, message_id: ?string, source: ?string, destination: ?string, routing_rule: string, delivery_status: string, governance_status: string, error: ?string}
     */
    private function persist(
        ?string $messageId,
        ?string $source,
        ?string $destination,
        string $deliveryStatus,
        ?string $error
    ): array {
        $brokerRef = 'message_broker_' . bin2hex(random_bytes(12));
        $createdAt = gmdate(DATE_RFC3339);
        $governanceStatus = 'ungoverned';
        $routingRule = 'direct';

        $statement = $this->database->prepare(
            'INSERT INTO message_broker_operations (
                broker_ref, message_id, source, destination, routing_rule,
                delivery_status, governance_status, error, created_at
            ) VALUES (
                :broker_ref, :message_id, :source, :destination, :routing_rule,
                :delivery_status, :governance_status, :error, :created_at
            )'
        );
        $statement->execute([
            'broker_ref' => $brokerRef,
            'message_id' => $messageId,
            'source' => $source,
            'destination' => $destination,
            'routing_rule' => $routingRule,
            'delivery_status' => $deliveryStatus,
            'governance_status' => $governanceStatus,
            'error' => $error,
            'created_at' => $createdAt,
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'message.routed',
            new DateTimeImmutable(),
            self::class,
            ['broker_ref' => $brokerRef, 'destination' => $destination, 'delivery_status' => $deliveryStatus]
        ));

        return [
            'broker_ref' => $brokerRef,
            'message_id' => $messageId,
            'source' => $source,
            'destination' => $destination,
            'routing_rule' => $routingRule,
            'delivery_status' => $deliveryStatus,
            'governance_status' => $governanceStatus,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function operation(string $brokerRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM message_broker_operations WHERE broker_ref = :broker_ref'
        );
        $statement->execute(['broker_ref' => $brokerRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM message_broker_operations ORDER BY rowid DESC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
