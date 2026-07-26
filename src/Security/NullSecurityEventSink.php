<?php

declare(strict_types=1);

namespace SquirrelForge\Security;

use SquirrelForge\Contracts\SecurityEventSinkInterface;

final class NullSecurityEventSink implements SecurityEventSinkInterface
{
    public function record(
        string $eventType,
        string $outcome,
        ?string $identityRef,
        string $correlationId,
        array $metadata = []
    ): string {
        return 'security_event_unrecorded';
    }
}
