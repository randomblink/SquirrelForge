<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use SquirrelForge\Contracts\NotificationChannelInterface;

/**
 * Delivers a notification "in-platform" -- held in memory for retrieval by
 * whatever surfaces in-app notifications, rather than sent to an external
 * system. Represents 36_COMMUNICATION/NOTIFICATION-MANAGER.md's "in-platform
 * notifications" delivery channel.
 */
final class InPlatformNotificationChannel implements NotificationChannelInterface
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $deliveries = [];

    public function getId(): string
    {
        return 'in_platform';
    }

    public function send(array $notification): array
    {
        if (!isset($notification['recipient']) || trim((string) $notification['recipient']) === '') {
            return ['delivered' => false, 'error' => 'A non-empty "recipient" is required.'];
        }

        $this->deliveries[] = $notification;

        return ['delivered' => true];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function deliveries(): array
    {
        return $this->deliveries;
    }
}
