<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Coordinates notification delivery, retries, and audit tracking, per
 * 36_COMMUNICATION/NOTIFICATION-MANAGER.md.
 *
 * This is a deliberately scoped subset of that spec: delivery is synchronous
 * (no queue), priority is a fixed six-value list, and status covers Created,
 * Delivering, Retrying, Delivered, and Failed only (not Queued, Acknowledged,
 * Read, Expired, or Archived). It does not implement recipient preferences,
 * quiet hours, escalation, or governance policy enforcement -- none of the
 * supporting stores/layers those need exist yet. On delivery or final
 * failure it dispatches a `notification.delivered` / `notification.failed`
 * event through an optional injected EventBusInterface, the one integration
 * point from the spec that already has a real counterpart (src/Events).
 */
final class SqliteNotificationManager
{
    private const PRIORITIES = ['emergency', 'critical', 'high', 'normal', 'low', 'informational'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly NotificationChannelRegistry $channels,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS notifications (
                notification_ref TEXT PRIMARY KEY,
                recipient TEXT NOT NULL,
                channel TEXT NOT NULL,
                priority TEXT NOT NULL,
                status TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                last_error TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{notification_ref: ?string, status: string, attempts: int, error: ?string}
     */
    public function send(
        string $recipient,
        string $channel,
        string $priority,
        array $payload,
        int $maxAttempts = 3
    ): array {
        if (!in_array($priority, self::PRIORITIES, true)) {
            return ['notification_ref' => null, 'status' => 'rejected', 'attempts' => 0,
                'error' => sprintf('Unknown priority "%s".', $priority)];
        }

        $resolvedChannel = $this->channels->get($channel);

        if ($resolvedChannel === null) {
            return ['notification_ref' => null, 'status' => 'rejected', 'attempts' => 0,
                'error' => sprintf('Unknown channel "%s".', $channel)];
        }

        $notificationRef = 'notification_' . bin2hex(random_bytes(12));
        $createdAt = gmdate(DATE_RFC3339);

        $this->insert($notificationRef, $recipient, $channel, $priority, $createdAt);
        $this->update($notificationRef, 'delivering', 0, null);

        $attempts = 0;
        $lastError = null;
        $delivered = false;

        while ($attempts < $maxAttempts) {
            $attempts++;
            $result = $resolvedChannel->send(['recipient' => $recipient, 'priority' => $priority, ...$payload]);

            if (($result['delivered'] ?? false) === true) {
                $delivered = true;
                break;
            }

            $lastError = (string) ($result['error'] ?? 'Delivery failed.');

            if ($attempts < $maxAttempts) {
                $this->update($notificationRef, 'retrying', $attempts, $lastError);
            }
        }

        $finalStatus = $delivered ? 'delivered' : 'failed';
        $this->update($notificationRef, $finalStatus, $attempts, $lastError);
        $this->dispatchOutcome($notificationRef, $recipient, $channel, $finalStatus);

        return [
            'notification_ref' => $notificationRef,
            'status' => $finalStatus,
            'attempts' => $attempts,
            'error' => $delivered ? null : $lastError,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function status(string $notificationRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM notifications WHERE notification_ref = :notification_ref'
        );
        $statement->execute(['notification_ref' => $notificationRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM notifications ORDER BY created_at DESC, notification_ref DESC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    private function insert(
        string $notificationRef,
        string $recipient,
        string $channel,
        string $priority,
        string $createdAt
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO notifications (
                notification_ref, recipient, channel, priority, status, attempts, created_at, updated_at
            ) VALUES (
                :notification_ref, :recipient, :channel, :priority, :status, 0, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'notification_ref' => $notificationRef,
            'recipient' => $recipient,
            'channel' => $channel,
            'priority' => $priority,
            'status' => 'created',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function update(string $notificationRef, string $status, int $attempts, ?string $lastError): void
    {
        $statement = $this->database->prepare(
            'UPDATE notifications
             SET status = :status, attempts = :attempts, last_error = :last_error, updated_at = :updated_at
             WHERE notification_ref = :notification_ref'
        );
        $statement->execute([
            'status' => $status,
            'attempts' => $attempts,
            'last_error' => $lastError,
            'updated_at' => gmdate(DATE_RFC3339),
            'notification_ref' => $notificationRef,
        ]);
    }

    private function dispatchOutcome(string $notificationRef, string $recipient, string $channel, string $status): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            $status === 'delivered' ? 'notification.delivered' : 'notification.failed',
            new DateTimeImmutable(),
            self::class,
            ['notification_ref' => $notificationRef, 'recipient' => $recipient, 'channel' => $channel]
        ));
    }
}
