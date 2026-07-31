<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Observability\MetricsManager;
use SquirrelForge\Optimization\CapacityPlanner;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class CapacityPlannerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-capacity-planner-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function recordMetric(MetricsManager $metrics, string $metricName, float $value): void
    {
        $metrics->recordMetric([
            'event_id' => 'evt_' . bin2hex(random_bytes(4)), 'source' => 'workflow-engine', 'signal_type' => 'metric',
            'correlation_id' => 'corr_1', 'payload' => ['metric_name' => $metricName, 'value' => $value],
            'timestamp' => '2026-07-29T12:00:00Z',
        ]);
    }

    /**
     * Recorded oldest-first, matching MetricsManager::values()'s
     * newest-first storage order once forecastDemand() reverses it.
     */
    private function seedLinearSeries(MetricsManager $metrics, string $metricName): void
    {
        foreach ([10, 20, 30] as $value) {
            $this->recordMetric($metrics, $metricName, $value);
        }
    }

    public function testForecastDemandWithoutMetricsManagerIsNotConfigured(): void
    {
        $planner = new CapacityPlanner();

        $result = $planner->forecastDemand('queue_depth');

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testForecastDemandWithFewerThanTwoValuesIsInsufficientData(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $this->recordMetric($metrics, 'queue_depth', 10);
        $planner = new CapacityPlanner($metrics);

        $result = $planner->forecastDemand('queue_depth');

        $this->assertSame('insufficient_data', $result['outcome']);
    }

    public function testForecastDemandFitsAPerfectLinearTrend(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $this->seedLinearSeries($metrics, 'queue_depth');
        $planner = new CapacityPlanner($metrics);

        $result = $planner->forecastDemand('queue_depth', 1);

        $this->assertSame('forecasted', $result['outcome']);
        $this->assertEqualsWithDelta(40.0, $result['forecast_value'], 0.001);
        $this->assertEqualsWithDelta(40.0, $result['confidence_range']['low'], 0.001);
        $this->assertEqualsWithDelta(40.0, $result['confidence_range']['high'], 0.001);
    }

    public function testForecastDemandWithExactlyTwoPointsHasNoConfidenceRange(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $this->recordMetric($metrics, 'queue_depth', 10);
        $this->recordMetric($metrics, 'queue_depth', 20);
        $planner = new CapacityPlanner($metrics);

        $result = $planner->forecastDemand('queue_depth', 1);

        $this->assertSame('forecasted', $result['outcome']);
        $this->assertEqualsWithDelta(30.0, $result['forecast_value'], 0.001);
        $this->assertNull($result['confidence_range']);
    }

    public function testIdentifyConstraintPassesThroughInsufficientData(): void
    {
        $planner = new CapacityPlanner();

        $result = $planner->identifyConstraint('queue_depth', 100.0);

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testIdentifyConstraintWithinCapacityProducesNoFinding(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $this->seedLinearSeries($metrics, 'queue_depth');
        $planner = new CapacityPlanner($metrics);

        $result = $planner->identifyConstraint('queue_depth', 45.0, 1);

        $this->assertSame('within_capacity', $result['outcome']);
        $this->assertNull($result['finding']);
    }

    public function testIdentifyConstraintAtOrAboveTheLimitProducesAFinding(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $this->seedLinearSeries($metrics, 'queue_depth');
        $planner = new CapacityPlanner($metrics);

        $result = $planner->identifyConstraint('queue_depth', 35.0, 1);

        $this->assertSame('projected_constraint', $result['outcome']);
        $this->assertSame('projected_constraint', $result['finding']['type']);
        $this->assertEqualsWithDelta(35.0, $result['finding']['evidence']['capacity_limit'], 0.001);
    }

    public function testCompareScalingScenariosPassesThroughInsufficientData(): void
    {
        $planner = new CapacityPlanner();

        $result = $planner->compareScalingScenarios('queue_depth', []);

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testCompareScalingScenariosRanksViableScenariosByCost(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $this->seedLinearSeries($metrics, 'queue_depth');
        $planner = new CapacityPlanner($metrics);

        $result = $planner->compareScalingScenarios('queue_depth', [
            'small' => ['capacity' => 30.0, 'cost' => 10.0],
            'medium' => ['capacity' => 50.0, 'cost' => 100.0],
            'large' => ['capacity' => 100.0, 'cost' => 50.0],
        ], 1);

        $this->assertSame('compared', $result['outcome']);
        $this->assertEqualsCanonicalizing(['medium', 'large'], $result['viable']);
        $this->assertSame(['large', 'medium'], $result['ranked_by_cost']);
    }
}
