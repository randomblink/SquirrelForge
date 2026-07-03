<?php

declare(strict_types=1);

namespace SquirrelForge\Events;

use DateTimeImmutable;
use SquirrelForge\Contracts\EventInterface;

final class Event implements EventInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly DateTimeImmutable $occurredAt,
        private readonly string $source,
        private readonly array $payload = [],
        private readonly array $metadata = []
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
