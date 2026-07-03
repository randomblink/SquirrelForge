<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface EventInterface
{
    /**
     * Returns the unique event identifier.
     */
    public function getId(): string;

    /**
     * Returns the event name.
     */
    public function getName(): string;

    /**
     * Returns the UTC timestamp when the event occurred.
     */
    public function getOccurredAt(): \DateTimeImmutable;

    /**
     * Returns the component that generated the event.
     */
    public function getSource(): string;

    /**
     * Returns the event payload.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array;

    /**
     * Returns optional metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;
}
