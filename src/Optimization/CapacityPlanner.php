<?php

declare(strict_types=1);

namespace SquirrelForge\Optimization;

use SquirrelForge\Observability\MetricsManager;

/**
 * Owns capacity forecasting, constraint analysis, scenario planning,
 * and scaling recommendations based on historical utilization
 * evidence, per 32_OPTIMIZATION/CAPACITY-PLANNER.md.
 *
 * Does not take Cost Optimizer as a dependency even though the spec
 * lists it: COST-OPTIMIZER.md itself depends on CAPACITY-PLANNER.md, a
 * genuine cycle in the spec's own Depends On headers. This class breaks
 * it by building first -- cost in compareScalingScenarios() is pure
 * caller-supplied scenario data, never computed here, which matches the
 * spec's own Boundary ("does not... approve budgets") regardless of
 * which component gets built first.
 *
 * forecastDemand() is this class's real statistical core: ordinary
 * least-squares linear regression over a metric's actual recorded
 * history (MetricsManager::values(), reordered chronologically), the
 * same rigor class as PatternDetector's own trend detection but a true
 * point forecast rather than a two-halves comparison. The confidence
 * range is a deliberately simple residual-standard-error margin
 * (forecast +/- ~1.96 * residual SE), not a full prediction interval
 * that accounts for distance from the sample mean -- an honest,
 * explainable heuristic, not a claim of rigorous statistics this class
 * doesn't implement.
 *
 * identifyConstraint() and compareScalingScenarios() are genuine
 * compositions built on top of that one real forecast: a constraint
 * finding is reported only when the forecast itself crosses a
 * caller-supplied capacity limit, and scenario comparison only ever
 * ranks caller-supplied scenario capacities/costs against that same
 * forecast -- never inventing demand, growth, or cost figures no
 * component in this codebase actually owns yet.
 *
 * Like its Optimization siblings, this class has no database: the
 * Boundary forbids owning "audit infrastructure", so forecasts and
 * findings are pure return values.
 */
final class CapacityPlanner
{
    private const CONFIDENCE_MULTIPLIER = 1.96;

    public function __construct(private readonly ?MetricsManager $metrics = null)
    {
    }

    /**
     * @return array{metric_name: string, forecast_value: ?float, confidence_range: ?array{low: float, high: float}, periods_ahead: int, sample_count: int, outcome: string, error: ?string}
     */
    public function forecastDemand(string $metricName, int $periodsAhead = 1): array
    {
        if ($this->metrics === null) {
            return ['metric_name' => $metricName, 'forecast_value' => null, 'confidence_range' => null, 'periods_ahead' => $periodsAhead, 'sample_count' => 0, 'outcome' => 'not_configured', 'error' => 'Metrics Manager is not configured.'];
        }

        $values = array_reverse($this->metrics->values($metricName));
        $sampleCount = count($values);

        if ($sampleCount < 2) {
            return ['metric_name' => $metricName, 'forecast_value' => null, 'confidence_range' => null, 'periods_ahead' => $periodsAhead, 'sample_count' => $sampleCount, 'outcome' => 'insufficient_data', 'error' => 'At least 2 recorded values are required to forecast a trend.'];
        }

        [$intercept, $slope] = $this->fitLine($values);
        $forecastIndex = $sampleCount - 1 + $periodsAhead;
        $forecastValue = $intercept + $slope * $forecastIndex;

        $confidenceRange = $sampleCount >= 3
            ? $this->confidenceRange($values, $intercept, $slope, $forecastValue)
            : null;

        return ['metric_name' => $metricName, 'forecast_value' => $forecastValue, 'confidence_range' => $confidenceRange, 'periods_ahead' => $periodsAhead, 'sample_count' => $sampleCount, 'outcome' => 'forecasted', 'error' => null];
    }

    /**
     * @return array{finding: ?array<string, mixed>, outcome: string, error: ?string}
     */
    public function identifyConstraint(string $metricName, float $capacityLimit, int $periodsAhead = 1): array
    {
        $forecast = $this->forecastDemand($metricName, $periodsAhead);

        if ($forecast['forecast_value'] === null) {
            return ['finding' => null, 'outcome' => $forecast['outcome'], 'error' => $forecast['error']];
        }

        if ($forecast['forecast_value'] < $capacityLimit) {
            return ['finding' => null, 'outcome' => 'within_capacity', 'error' => null];
        }

        $finding = [
            'type' => 'projected_constraint',
            'metric_name' => $metricName,
            'evidence' => ['forecast' => $forecast, 'capacity_limit' => $capacityLimit],
            'measurable_target' => sprintf('Keep "%s" under its capacity limit of %.2f within %d period(s).', $metricName, $capacityLimit, $periodsAhead),
            'improvement_proposal' => sprintf('"%s" is forecast to reach %.2f within %d period(s), at or above its capacity limit of %.2f.', $metricName, $forecast['forecast_value'], $periodsAhead, $capacityLimit),
        ];

        return ['finding' => $finding, 'outcome' => 'projected_constraint', 'error' => null];
    }

    /**
     * Ranks caller-supplied scaling scenarios against a metric's real
     * forecast. Scenario capacity and cost are always caller-supplied --
     * this class forecasts demand, never cost or infrastructure pricing.
     *
     * @param array<string, array{capacity: float, cost?: ?float}> $scenarios
     * @return array{viable: array<int, string>, ranked_by_cost: array<int, string>, forecast: array<string, mixed>, outcome: string, error: ?string}
     */
    public function compareScalingScenarios(string $metricName, array $scenarios, int $periodsAhead = 1): array
    {
        $forecast = $this->forecastDemand($metricName, $periodsAhead);

        if ($forecast['forecast_value'] === null) {
            return ['viable' => [], 'ranked_by_cost' => [], 'forecast' => $forecast, 'outcome' => $forecast['outcome'], 'error' => $forecast['error']];
        }

        $viable = [];

        foreach ($scenarios as $name => $scenario) {
            if ($scenario['capacity'] >= $forecast['forecast_value']) {
                $viable[] = $name;
            }
        }

        $rankedByCost = $viable;
        usort($rankedByCost, static fn(string $a, string $b): int => ($scenarios[$a]['cost'] ?? PHP_FLOAT_MAX) <=> ($scenarios[$b]['cost'] ?? PHP_FLOAT_MAX));

        return ['viable' => $viable, 'ranked_by_cost' => $rankedByCost, 'forecast' => $forecast, 'outcome' => 'compared', 'error' => null];
    }

    /**
     * Ordinary least squares over (index, value) pairs.
     *
     * @param array<int, float> $values
     * @return array{0: float, 1: float} intercept, slope
     */
    private function fitLine(array $values): array
    {
        $n = count($values);
        $sumX = 0;
        $sumY = 0;
        $sumXy = 0;
        $sumXx = 0;

        foreach ($values as $index => $value) {
            $sumX += $index;
            $sumY += $value;
            $sumXy += $index * $value;
            $sumXx += $index * $index;
        }

        $denominator = $n * $sumXx - $sumX ** 2;
        $slope = $denominator !== 0 ? ($n * $sumXy - $sumX * $sumY) / $denominator : 0.0;
        $intercept = ($sumY - $slope * $sumX) / $n;

        return [$intercept, $slope];
    }

    /**
     * @param array<int, float> $values
     * @return array{low: float, high: float}
     */
    private function confidenceRange(array $values, float $intercept, float $slope, float $forecastValue): array
    {
        $n = count($values);
        $sumSquaredResiduals = 0.0;

        foreach ($values as $index => $value) {
            $residual = $value - ($intercept + $slope * $index);
            $sumSquaredResiduals += $residual ** 2;
        }

        $residualStandardError = sqrt($sumSquaredResiduals / ($n - 2));
        $margin = self::CONFIDENCE_MULTIPLIER * $residualStandardError;

        return ['low' => $forecastValue - $margin, 'high' => $forecastValue + $margin];
    }
}
