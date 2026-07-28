<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Resilience\RetryManager;

final class RetryManagerTest extends TestCase
{
    public function testSuccessOnFirstAttemptNeverSleeps(): void
    {
        $sleeps = [];
        $manager = new RetryManager(function (float $seconds) use (&$sleeps): void {
            $sleeps[] = $seconds;
        });

        $result = $manager->execute(static fn(): string => 'ok');

        $this->assertSame('successful', $result['status']);
        $this->assertSame('ok', $result['result']);
        $this->assertSame(1, $result['attempts']);
        $this->assertSame([], $sleeps);
    }

    public function testRetriesThenSucceedsAndRecordsDelays(): void
    {
        $sleeps = [];
        $manager = new RetryManager(function (float $seconds) use (&$sleeps): void {
            $sleeps[] = $seconds;
        });

        $calls = 0;
        $result = $manager->execute(function () use (&$calls): string {
            $calls++;
            if ($calls < 3) {
                throw new RuntimeException('transient');
            }

            return 'ok';
        }, ['strategy' => 'fixed', 'base_delay_seconds' => 2.0, 'max_attempts' => 5]);

        $this->assertSame('successful', $result['status']);
        $this->assertSame(3, $result['attempts']);
        $this->assertSame([2.0, 2.0], $sleeps);
        $this->assertSame(
            ['retrying', 'retrying', 'successful'],
            array_column($result['log'], 'outcome')
        );
    }

    public function testExhaustingAttemptsReturnsEscalated(): void
    {
        $manager = new RetryManager(static function (): void {
        });

        $result = $manager->execute(static function (): never {
            throw new RuntimeException('down');
        }, ['max_attempts' => 3]);

        $this->assertSame('escalated', $result['status']);
        $this->assertSame(3, $result['attempts']);
        $this->assertSame('down', $result['error']);
        $this->assertSame(
            ['retrying', 'retrying', 'escalated'],
            array_column($result['log'], 'outcome')
        );
    }

    public function testNonRetryablePredicateStopsImmediately(): void
    {
        $sleepCount = 0;
        $manager = new RetryManager(function () use (&$sleepCount): void {
            $sleepCount++;
        });

        $result = $manager->execute(static function (): never {
            throw new RuntimeException('permanent');
        }, [
            'max_attempts' => 5,
            'retryable' => static fn(): bool => false,
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertSame(1, $result['attempts']);
        $this->assertSame(0, $sleepCount);
    }

    public function testFixedStrategyDelay(): void
    {
        $sleeps = $this->collectDelays('fixed', 3.0, attemptsBeforeSuccess: 3);

        $this->assertSame([3.0, 3.0], $sleeps);
    }

    public function testLinearStrategyDelay(): void
    {
        $sleeps = $this->collectDelays('linear', 2.0, attemptsBeforeSuccess: 3);

        $this->assertSame([2.0, 4.0], $sleeps);
    }

    public function testExponentialStrategyDelay(): void
    {
        $sleeps = $this->collectDelays('exponential', 1.0, attemptsBeforeSuccess: 4);

        $this->assertSame([1.0, 2.0, 4.0], $sleeps);
    }

    public function testExponentialJitterStrategyDelayIsWithinExpectedBounds(): void
    {
        $sleeps = $this->collectDelays('exponential_jitter', 1.0, attemptsBeforeSuccess: 3);

        // base * 2^(attempt-1) * [0.5, 1.5]
        $this->assertGreaterThanOrEqual(0.5, $sleeps[0]);
        $this->assertLessThanOrEqual(1.5, $sleeps[0]);
        $this->assertGreaterThanOrEqual(1.0, $sleeps[1]);
        $this->assertLessThanOrEqual(3.0, $sleeps[1]);
    }

    /**
     * @return array<int, float>
     */
    private function collectDelays(string $strategy, float $baseDelay, int $attemptsBeforeSuccess): array
    {
        $sleeps = [];
        $manager = new RetryManager(function (float $seconds) use (&$sleeps): void {
            $sleeps[] = $seconds;
        });

        $calls = 0;
        $manager->execute(function () use (&$calls, $attemptsBeforeSuccess): string {
            $calls++;
            if ($calls < $attemptsBeforeSuccess) {
                throw new RuntimeException('transient');
            }

            return 'ok';
        }, ['strategy' => $strategy, 'base_delay_seconds' => $baseDelay, 'max_attempts' => $attemptsBeforeSuccess]);

        return $sleeps;
    }
}
