<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use SquirrelForge\Contracts\ProviderTelemetryInterface;

final class NullProviderTelemetry implements ProviderTelemetryInterface
{
    public function record(string $eventType, array $metadata = []): void
    {
    }

    public function snapshot(): array
    {
        return ['counters' => [], 'circuit_state' => 'UNKNOWN', 'last_event_at' => null];
    }
}
