<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\HealthCheckInterface;
use SquirrelForge\Core\HealthManager;

final class StubHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly string $status,
        private readonly string $component = 'stub'
    ) {
    }

    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }

    public function health(): array
    {
        return [
            'status' => $this->status,
            'component' => $this->component,
            'timestamp' => '2026-01-01T00:00:00+00:00',
            'details' => [],
        ];
    }
}

final class HealthManagerTest extends TestCase
{
    public function testCheckReturnsNullForUnknownName(): void
    {
        $manager = new HealthManager();

        $this->assertNull($manager->check('missing'));
    }

    public function testCheckReturnsHealthArrayForRegisteredCheck(): void
    {
        $manager = new HealthManager();
        $manager->register('db', new StubHealthCheck('healthy'));

        $this->assertSame('healthy', $manager->check('db')['status']);
    }

    public function testAllReturnsRegisteredChecksKeyedByName(): void
    {
        $manager = new HealthManager();
        $check = new StubHealthCheck('healthy');
        $manager->register('db', $check);

        $this->assertSame(['db' => $check], $manager->all());
    }

    public function testReportIsHealthyWhenAllChecksAreHealthy(): void
    {
        $manager = new HealthManager();
        $manager->register('db', new StubHealthCheck('healthy'));
        $manager->register('cache', new StubHealthCheck('healthy'));

        $report = $manager->report();

        $this->assertSame('healthy', $report['status']);
        $this->assertArrayHasKey('timestamp', $report);
        $this->assertCount(2, $report['checks']);
    }

    public function testReportIsDegradedWhenAnyCheckIsDegradedButNoneUnhealthy(): void
    {
        $manager = new HealthManager();
        $manager->register('db', new StubHealthCheck('healthy'));
        $manager->register('cache', new StubHealthCheck('degraded'));

        $this->assertSame('degraded', $manager->report()['status']);
    }

    public function testReportIsUnhealthyWhenAnyCheckIsUnhealthyRegardlessOfOthers(): void
    {
        $manager = new HealthManager();
        $manager->register('db', new StubHealthCheck('unhealthy'));
        $manager->register('cache', new StubHealthCheck('degraded'));

        $this->assertSame('unhealthy', $manager->report()['status']);
    }

    public function testReportWithNoChecksIsHealthy(): void
    {
        $manager = new HealthManager();

        $this->assertSame('healthy', $manager->report()['status']);
        $this->assertSame([], $manager->report()['checks']);
    }
}
