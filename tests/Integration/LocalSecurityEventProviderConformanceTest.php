<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use SquirrelForge\Contracts\SecurityEventSinkInterface;
use SquirrelForge\Security\SqliteSecurityEventSink;
use SquirrelForge\Tests\Contract\SecurityEventSinkContractTestCase;

final class LocalSecurityEventProviderConformanceTest extends SecurityEventSinkContractTestCase
{
    private string $databasePath;
    private SqliteSecurityEventSink $sink;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-event-contract-'
            . bin2hex(random_bytes(8)) . '.sqlite';
        $this->sink = new SqliteSecurityEventSink($this->databasePath);
    }

    protected function tearDown(): void
    {
        unset($this->sink);

        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    protected function securityEventSink(): SecurityEventSinkInterface
    {
        return $this->sink;
    }

    protected function recordedEvents(string $identityRef): array
    {
        return $this->sink->events($identityRef);
    }
}
