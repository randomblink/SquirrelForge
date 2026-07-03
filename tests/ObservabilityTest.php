<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\LoggerInterface;
use SquirrelForge\Observability\ArrayLogger;

final class ObservabilityTest extends TestCase
{
    private ArrayLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new ArrayLogger();
    }

    public function testArrayLoggerImplementsLoggerInterface(): void
    {
        $this->assertInstanceOf(LoggerInterface::class, $this->logger);
    }

    /**
     * Regression test: ArrayLogger previously only implemented log()/getLogs()
     * and did not satisfy LoggerInterface, so calls like ->info() from
     * ObservabilityServiceProvider::boot() failed with a fatal error.
     */
    #[DataProvider('levelMethodProvider')]
    public function testEachLevelMethodRecordsALogEntry(string $method, string $expectedLevel): void
    {
        $this->logger->{$method}('a message', ['key' => 'value']);

        $logs = $this->logger->getLogs();

        $this->assertCount(1, $logs);
        $this->assertSame($expectedLevel, $logs[0]['level']);
        $this->assertSame('a message', $logs[0]['message']);
        $this->assertSame(['key' => 'value'], $logs[0]['context']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function levelMethodProvider(): array
    {
        return [
            'emergency' => ['emergency', 'emergency'],
            'alert' => ['alert', 'alert'],
            'critical' => ['critical', 'critical'],
            'error' => ['error', 'error'],
            'warning' => ['warning', 'warning'],
            'notice' => ['notice', 'notice'],
            'info' => ['info', 'info'],
            'debug' => ['debug', 'debug'],
        ];
    }

    public function testLogAcceptsAnArbitraryLevel(): void
    {
        $this->logger->log('custom-level', 'custom message');

        $logs = $this->logger->getLogs();

        $this->assertSame('custom-level', $logs[0]['level']);
    }

    public function testGetLogsAccumulatesInOrder(): void
    {
        $this->logger->info('first');
        $this->logger->error('second');

        $logs = $this->logger->getLogs();

        $this->assertCount(2, $logs);
        $this->assertSame('first', $logs[0]['message']);
        $this->assertSame('second', $logs[1]['message']);
    }

    public function testContextDefaultsToEmptyArray(): void
    {
        $this->logger->info('no context here');

        $this->assertSame([], $this->logger->getLogs()[0]['context']);
    }
}
