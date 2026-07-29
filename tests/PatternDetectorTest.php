<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Learning\PatternDetector;
use SquirrelForge\Learning\SqliteExperienceStore;

final class PatternDetectorTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }
    }

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-pattern-detector-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @param array<int, DateTimeImmutable> $ticks consumed one per record() call
     */
    private function makeStore(array $ticks): SqliteExperienceStore
    {
        $index = -1;

        return new SqliteExperienceStore($this->tempPath('store'), clock: function () use ($ticks, &$index): DateTimeImmutable {
            $index++;

            return $ticks[$index] ?? end($ticks);
        });
    }

    /**
     * @return array<int, DateTimeImmutable>
     */
    private function ticks(int $count): array
    {
        $start = new DateTimeImmutable('2026-07-29T00:00:00Z');

        return array_map(static fn(int $i): DateTimeImmutable => $start->modify("+{$i} hours"), range(0, $count - 1));
    }

    public function testWithoutExperienceStoreConfiguredEveryMethodReportsNotConfigured(): void
    {
        $detector = new PatternDetector();

        $this->assertSame('not_configured', $detector->findRecurringPatterns()['outcome']);
        $this->assertSame('not_configured', $detector->findTrend('a', 'b')['outcome']);
        $this->assertSame('not_configured', $detector->findAnomalies()['outcome']);
    }

    public function testFindRecurringPatternsGroupsBySourceAndCategory(): void
    {
        $store = $this->makeStore($this->ticks(5));
        $store->record('agent:1', 'timeout');
        $store->record('agent:1', 'timeout');
        $store->record('agent:1', 'timeout');
        $store->record('agent:1', 'success');
        $store->record('agent:2', 'timeout');
        $detector = new PatternDetector($store);

        $result = $detector->findRecurringPatterns(minOccurrences: 3);

        $this->assertCount(1, $result['findings']);
        $this->assertSame('agent:1', $result['findings'][0]['source']);
        $this->assertSame('timeout', $result['findings'][0]['event_category']);
        $this->assertSame(3, $result['findings'][0]['count']);
    }

    public function testRecurrenceConfidenceGrowsWithCountButNeverExceedsOne(): void
    {
        $store = $this->makeStore($this->ticks(10));
        for ($i = 0; $i < 10; $i++) {
            $store->record('agent:1', 'timeout');
        }
        $detector = new PatternDetector($store);

        $result = $detector->findRecurringPatterns(minOccurrences: 3);

        $this->assertSame(1.0, $result['findings'][0]['confidence']);
    }

    public function testFindTrendReportsInsufficientDataBelowFourRecords(): void
    {
        $store = $this->makeStore($this->ticks(3));
        $store->record('agent:1', 'timeout', ['confidence' => 0.5]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.5]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.5]);
        $detector = new PatternDetector($store);

        $result = $detector->findTrend('agent:1', 'timeout');

        $this->assertSame('insufficient_data', $result['outcome']);
    }

    public function testFindTrendDetectsIncreasingConfidenceOverTime(): void
    {
        $store = $this->makeStore($this->ticks(4));
        $store->record('agent:1', 'timeout', ['confidence' => 0.2]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.3]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.8]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.9]);
        $detector = new PatternDetector($store);

        $result = $detector->findTrend('agent:1', 'timeout');

        $this->assertSame('trend_found', $result['outcome']);
        $this->assertSame('increasing', $result['direction']);
        $this->assertEqualsWithDelta(0.6, $result['magnitude'], 0.0001);
    }

    public function testFindTrendDetectsDecreasingConfidenceOverTime(): void
    {
        $store = $this->makeStore($this->ticks(4));
        $store->record('agent:1', 'timeout', ['confidence' => 0.9]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.9]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.2]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.2]);
        $detector = new PatternDetector($store);

        $result = $detector->findTrend('agent:1', 'timeout');

        $this->assertSame('decreasing', $result['direction']);
    }

    public function testFindTrendReportsStableWhenConfidenceBarelyChanges(): void
    {
        $store = $this->makeStore($this->ticks(4));
        $store->record('agent:1', 'timeout', ['confidence' => 0.50]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.50]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.505]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.505]);
        $detector = new PatternDetector($store);

        $result = $detector->findTrend('agent:1', 'timeout');

        $this->assertSame('stable', $result['direction']);
    }

    public function testFindAnomaliesReportsInsufficientDataBelowTwoRecords(): void
    {
        $store = $this->makeStore($this->ticks(1));
        $store->record('agent:1', 'timeout', ['confidence' => 0.5]);
        $detector = new PatternDetector($store);

        $result = $detector->findAnomalies();

        $this->assertSame('insufficient_data', $result['outcome']);
    }

    public function testFindAnomaliesFlagsAConfidenceValueFarFromTheGroupMean(): void
    {
        $store = $this->makeStore($this->ticks(6));
        $store->record('agent:1', 'timeout', ['confidence' => 0.50]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.52]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.48]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.51]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.49]);
        $outlier = $store->record('agent:1', 'timeout', ['confidence' => 0.05]);
        $detector = new PatternDetector($store);

        $result = $detector->findAnomalies(zScoreThreshold: 1.5);

        $this->assertNotEmpty($result['anomalies']);
        $this->assertSame($outlier['experience_id'], $result['anomalies'][0]['experience_id']);
    }

    public function testFindAnomaliesReportsNoneWhenAllValuesAreIdentical(): void
    {
        $store = $this->makeStore($this->ticks(3));
        $store->record('agent:1', 'timeout', ['confidence' => 0.5]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.5]);
        $store->record('agent:1', 'timeout', ['confidence' => 0.5]);
        $detector = new PatternDetector($store);

        $result = $detector->findAnomalies();

        $this->assertSame([], $result['anomalies']);
        $this->assertSame('analyzed', $result['outcome']);
    }
}
