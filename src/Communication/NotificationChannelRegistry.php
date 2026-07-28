<?php

declare(strict_types=1);

namespace SquirrelForge\Communication;

use SquirrelForge\Contracts\NotificationChannelInterface;

final class NotificationChannelRegistry
{
    /**
     * @var array<string, NotificationChannelInterface>
     */
    private array $channels = [];

    public function register(NotificationChannelInterface $channel): void
    {
        $this->channels[$channel->getId()] = $channel;
    }

    public function get(string $id): ?NotificationChannelInterface
    {
        return $this->channels[$id] ?? null;
    }

    /**
     * @return array<int, NotificationChannelInterface>
     */
    public function all(): array
    {
        return array_values($this->channels);
    }
}
