<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface NotificationChannelInterface
{
    public function getId(): string;

    /**
     * Attempt delivery. Never throws -- a delivery failure is a returned
     * result the caller (SquirrelForge\Communication\SqliteNotificationManager)
     * can act on (e.g. retry), not an exception.
     *
     * @param array<string, mixed> $notification
     * @return array{delivered: bool, error?: string}
     */
    public function send(array $notification): array;
}
