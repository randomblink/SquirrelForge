<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface ProviderTelemetryInterface
{
    public function record(string $eventType, array $metadata = []): void;

    /**
     * @return array{counters: array<string, int>, circuit_state: string, last_event_at: ?string}
     */
    public function snapshot(): array;
}
