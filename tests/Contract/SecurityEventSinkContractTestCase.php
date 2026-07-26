<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Contract;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\SecurityEventSinkInterface;

abstract class SecurityEventSinkContractTestCase extends TestCase
{
    abstract protected function securityEventSink(): SecurityEventSinkInterface;

    abstract protected function recordedEvents(string $identityRef): array;

    public function testProviderReturnsEvidenceReferenceAndPreservesCorrelation(): void
    {
        $sink = $this->securityEventSink();
        $identityRef = 'identity_contract_' . bin2hex(random_bytes(4));
        $eventRef = $sink->record(
            'CONTRACT_EVENT',
            'SUCCESS',
            $identityRef,
            'correlation_contract',
            ['safe_ref' => 'reference_contract']
        );
        $events = $this->recordedEvents($identityRef);

        self::assertNotSame('', $eventRef);
        self::assertCount(1, $events);
        self::assertSame('CONTRACT_EVENT', $events[0]['event_type']);
        self::assertSame('correlation_contract', $events[0]['correlation_id']);
    }
}
