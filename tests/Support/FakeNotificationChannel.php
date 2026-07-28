<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Support;

use SquirrelForge\Contracts\NotificationChannelInterface;

/**
 * A test double for NotificationChannelInterface. Configure it with a queue
 * of canned results consumed one per send() call (the last entry repeats
 * once exhausted); inspect `$calls` afterward to assert what was attempted.
 */
final class FakeNotificationChannel implements NotificationChannelInterface
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $calls = [];

    /**
     * @param array<int, array{delivered: bool, error?: string}> $results
     */
    public function __construct(
        private readonly string $id,
        private readonly array $results
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function send(array $notification): array
    {
        $this->calls[] = $notification;

        $index = min(count($this->calls) - 1, count($this->results) - 1);

        return $this->results[$index];
    }
}
