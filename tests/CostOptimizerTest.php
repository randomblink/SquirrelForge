<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Observability\MetricsManager;
use SquirrelForge\Optimization\CapacityPlanner;
use SquirrelForge\Optimization\CostOptimizer;
use SquirrelForge\Optimization\ResourceOptimizer;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class CostOptimizerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-cost-optimizer-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
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

    public function testIdentifyReductionWithoutResourceOptimizerIsNotConfigured(): void
    {
        $optimizer = new CostOptimizer();

        $result = $optimizer->identifyReductionFromUtilization('cpu_utilization_pct', 40.0, 80.0, 1000.0);

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testIdentifyReductionPassesThroughWhenNotOverProvisioned(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $this->recordMetric($metrics, 'cpu_utilization_pct', 60.0);
        $resourceOptimizer = new ResourceOptimizer($metrics);
        $optimizer = new CostOptimizer($resourceOptimizer);

        $result = $optimizer->identifyReductionFromUtilization('cpu_utilization_pct', 40.0, 80.0, 1000.0);

        $this->assertSame('within_target', $result['outcome']);
        $this->assertNull($result['finding']);
    }

    public function testIdentifyReductionComputesARealSavingsEstimate(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $this->recordMetric($metrics, 'cpu_utilization_pct', 10.0);
        $resourceOptimizer = new ResourceOptimizer($metrics);
        $optimizer = new CostOptimizer($resourceOptimizer);

        $result = $optimizer->identifyReductionFromUtilization('cpu_utilization_pct', 40.0, 80.0, 1000.0);

        $this->assertSame('cost_reduction_opportunity', $result['outcome']);
        $this->assertEqualsWithDelta(0.75, $result['finding']['evidence']['reduction_fraction'], 0.001);
        $this->assertEqualsWithDelta(750.0, $result['finding']['evidence']['estimated_monthly_savings'], 0.001);
    }

    public function testForecastBudgetImpactWithoutCapacityPlannerIsNotConfigured(): void
    {
        $optimizer = new CostOptimizer();

        $result = $optimizer->forecastBudgetImpact('queue_depth', [], 200.0);

        $this->assertSame('not_configured', $result['outcome']);
    }

    /** @return array{0: MetricsManager, 1: CapacityPlanner} */
    private function seededCapacityPlanner(): array
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        foreach ([10, 20, 30] as $value) {
            $this->recordMetric($metrics, 'queue_depth', $value);
        }

        return [$metrics, new CapacityPlanner($metrics)];
    }

    public function testForecastBudgetImpactWithNoViableScenarioReportsThatOutcome(): void
    {
        [, $capacityPlanner] = $this->seededCapacityPlanner();
        $optimizer = new CostOptimizer(capacityPlanner: $capacityPlanner);

        $result = $optimizer->forecastBudgetImpact('queue_depth', ['small' => ['capacity' => 20.0, 'cost' => 5.0]], 200.0);

        $this->assertSame('no_viable_scenario', $result['outcome']);
    }

    public function testForecastBudgetImpactWithMissingCostDataReportsThatOutcome(): void
    {
        [, $capacityPlanner] = $this->seededCapacityPlanner();
        $optimizer = new CostOptimizer(capacityPlanner: $capacityPlanner);

        $result = $optimizer->forecastBudgetImpact('queue_depth', ['medium' => ['capacity' => 50.0]], 200.0);

        $this->assertSame('no_cost_data', $result['outcome']);
    }

    public function testForecastBudgetImpactComputesRealSavingsAndPayback(): void
    {
        [, $capacityPlanner] = $this->seededCapacityPlanner();
        $optimizer = new CostOptimizer(capacityPlanner: $capacityPlanner);

        $result = $optimizer->forecastBudgetImpact('queue_depth', [
            'small' => ['capacity' => 30.0, 'cost' => 10.0],
            'medium' => ['capacity' => 50.0, 'cost' => 100.0],
            'large' => ['capacity' => 100.0, 'cost' => 50.0],
        ], 200.0, 1, 300.0);

        $this->assertSame('compared', $result['outcome']);
        $this->assertSame('large', $result['finding']['evidence']['cheapest_viable_scenario']);
        $this->assertEqualsWithDelta(150.0, $result['finding']['evidence']['expected_monthly_savings'], 0.001);
        $this->assertEqualsWithDelta(2.0, $result['finding']['evidence']['payback_months'], 0.001);
    }

    public function testForecastBudgetImpactWithACostIncreaseHasNoPayback(): void
    {
        [, $capacityPlanner] = $this->seededCapacityPlanner();
        $optimizer = new CostOptimizer(capacityPlanner: $capacityPlanner);

        $result = $optimizer->forecastBudgetImpact('queue_depth', [
            'large' => ['capacity' => 100.0, 'cost' => 50.0],
        ], 30.0, 1, 300.0);

        $this->assertSame('compared', $result['outcome']);
        $this->assertEqualsWithDelta(-20.0, $result['finding']['evidence']['expected_monthly_savings'], 0.001);
        $this->assertNull($result['finding']['evidence']['payback_months']);
        $this->assertStringContainsString('more than', $result['finding']['improvement_proposal']);
    }
}
