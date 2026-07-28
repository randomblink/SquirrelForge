<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use SquirrelForge\Contracts\HttpTransportInterface;
use SquirrelForge\Contracts\NotificationChannelInterface;
use Throwable;

/**
 * Delivers a notification by POSTing it as JSON to a configured webhook URL.
 * Represents 36_COMMUNICATION/NOTIFICATION-MANAGER.md's "Webhooks" delivery
 * channel.
 */
final class WebhookNotificationChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly HttpTransportInterface $transport,
        private readonly string $url,
        private readonly float $timeoutSeconds = 10.0
    ) {
    }

    public function getId(): string
    {
        return 'webhook';
    }

    public function send(array $notification): array
    {
        try {
            $response = $this->transport->request(
                'POST',
                $this->url,
                ['content-type' => 'application/json'],
                json_encode($notification, JSON_THROW_ON_ERROR),
                $this->timeoutSeconds
            );
        } catch (Throwable $e) {
            return ['delivered' => false, 'error' => $e->getMessage()];
        }

        if ($response->status < 200 || $response->status >= 300) {
            return ['delivered' => false, 'error' => sprintf('Webhook returned HTTP %d.', $response->status)];
        }

        return ['delivered' => true];
    }
}
