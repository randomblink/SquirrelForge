<?php

declare(strict_types=1);

namespace SquirrelForge\Contracts;

interface SecurityEventSinkInterface
{
    public function record(
        string $eventType,
        string $outcome,
        ?string $identityRef,
        string $correlationId,
        array $metadata = []
    ): string;
}
