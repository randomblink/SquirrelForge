<?php

declare(strict_types=1);

namespace SquirrelForge\Optimization;

/**
 * Owns operational cost analysis, cost-benefit comparison, and
 * cost-reduction proposals, per 32_OPTIMIZATION/COST-OPTIMIZER.md.
 *
 * Neither method here invents a dollar figure: every cost input
 * (current monthly cost, scenario cost, migration cost) is
 * caller-supplied, matching the Boundary's "does not make purchasing
 * decisions, approve budgets... or alter licensing" -- this class has
 * no pricing model of its own, only arithmetic over real evidence and
 * real caller-supplied figures.
 *
 * identifyReductionFromUtilization() is a genuine composition of
 * ResourceOptimizer's real analyzeUtilizationBand(): a cost-reduction
 * finding is produced only when Resource Optimizer itself already
 * detected over-provisioning, and the estimated saving is a plain,
 * explainable formula -- the fraction of the target band's minimum
 * that current usage falls short of, applied to the caller's own
 * stated current cost. Not a fabricated pricing curve.
 *
 * forecastBudgetImpact() is a genuine composition of Capacity
 * Planner's real compareScalingScenarios(): "expected savings" is the
 * caller's current cost minus the cheapest viable scenario's
 * caller-supplied cost, and "payback" is a plain migration-cost /
 * monthly-savings division, computed only when both a migration cost
 * and a positive saving are available -- never invented when the
 * caller hasn't supplied the inputs required to compute it.
 *
 * Like its Optimization siblings, this class has no database: the
 * Boundary forbids owning "financial audit infrastructure", so
 * findings and forecasts are pure return values.
 */
final class CostOptimizer
{
    public function __construct(
        private readonly ?ResourceOptimizer $resourceOptimizer = null,
        private readonly ?CapacityPlanner $capacityPlanner = null
    ) {
    }

    /**
     * @return array{finding: ?array<string, mixed>, outcome: string, error: ?string}
     */
    public function identifyReductionFromUtilization(string $metricName, float $targetMin, float $targetMax, float $currentMonthlyCost, string $aggregateType = 'mean'): array
    {
        if ($this->resourceOptimizer === null) {
            return ['finding' => null, 'outcome' => 'not_configured', 'error' => 'Resource Optimizer is not configured.'];
        }

        $utilization = $this->resourceOptimizer->analyzeUtilizationBand($metricName, $targetMin, $targetMax, $aggregateType);

        if ($utilization['outcome'] !== 'over_provisioned') {
            return ['finding' => null, 'outcome' => $utilization['outcome'], 'error' => $utilization['error']];
        }

        $value = $utilization['finding']['evidence']['aggregate']['value'];
        $reductionFraction = min(1.0, max(0.0, ($targetMin - $value) / $targetMin));
        $estimatedSavings = $currentMonthlyCost * $reductionFraction;

        $finding = [
            'type' => 'cost_reduction_opportunity',
            'metric_name' => $metricName,
            'evidence' => [
                'utilization_finding' => $utilization['finding'],
                'reduction_fraction' => $reductionFraction,
                'current_monthly_cost' => $currentMonthlyCost,
                'estimated_monthly_savings' => $estimatedSavings,
            ],
            'measurable_target' => sprintf('Reduce provisioned capacity for "%s" to cut its monthly cost from %.2f toward %.2f.', $metricName, $currentMonthlyCost, $currentMonthlyCost - $estimatedSavings),
            'improvement_proposal' => sprintf('"%s" is running %.0f%% below its target minimum utilization -- right-sizing it could save roughly %.2f/month of its current %.2f cost.', $metricName, $reductionFraction * 100, $estimatedSavings, $currentMonthlyCost),
        ];

        return ['finding' => $finding, 'outcome' => 'cost_reduction_opportunity', 'error' => null];
    }

    /**
     * @param array<string, array{capacity: float, cost?: ?float}> $scenarios
     * @return array{finding: ?array<string, mixed>, outcome: string, error: ?string}
     */
    public function forecastBudgetImpact(string $metricName, array $scenarios, float $currentMonthlyCost, int $periodsAhead = 1, ?float $migrationCost = null): array
    {
        if ($this->capacityPlanner === null) {
            return ['finding' => null, 'outcome' => 'not_configured', 'error' => 'Capacity Planner is not configured.'];
        }

        $comparison = $this->capacityPlanner->compareScalingScenarios($metricName, $scenarios, $periodsAhead);

        if ($comparison['outcome'] !== 'compared') {
            return ['finding' => null, 'outcome' => $comparison['outcome'], 'error' => $comparison['error']];
        }

        if ($comparison['ranked_by_cost'] === []) {
            return ['finding' => null, 'outcome' => 'no_viable_scenario', 'error' => 'No scaling scenario meets the forecasted demand.'];
        }

        $cheapestScenario = $comparison['ranked_by_cost'][0];
        $cheapestCost = $scenarios[$cheapestScenario]['cost'] ?? null;

        if ($cheapestCost === null) {
            return ['finding' => null, 'outcome' => 'no_cost_data', 'error' => sprintf('Scenario "%s" has no supplied cost.', $cheapestScenario)];
        }

        $expectedMonthlySavings = $currentMonthlyCost - $cheapestCost;
        $paybackMonths = $migrationCost !== null && $expectedMonthlySavings > 0
            ? $migrationCost / $expectedMonthlySavings
            : null;

        $finding = [
            'type' => 'budget_impact_forecast',
            'metric_name' => $metricName,
            'evidence' => [
                'forecast' => $comparison['forecast'],
                'cheapest_viable_scenario' => $cheapestScenario,
                'current_monthly_cost' => $currentMonthlyCost,
                'proposed_monthly_cost' => $cheapestCost,
                'expected_monthly_savings' => $expectedMonthlySavings,
                'migration_cost' => $migrationCost,
                'payback_months' => $paybackMonths,
            ],
            'measurable_target' => sprintf('Migrate "%s" to scenario "%s" to move monthly cost from %.2f to %.2f.', $metricName, $cheapestScenario, $currentMonthlyCost, $cheapestCost),
            'improvement_proposal' => $expectedMonthlySavings > 0
                ? sprintf('Scenario "%s" meets forecasted demand for "%s" at %.2f/month, saving %.2f/month versus the current %.2f.', $cheapestScenario, $metricName, $cheapestCost, $expectedMonthlySavings, $currentMonthlyCost)
                : sprintf('Scenario "%s" meets forecasted demand for "%s" at %.2f/month, %.2f more than the current %.2f.', $cheapestScenario, $metricName, $cheapestCost, abs($expectedMonthlySavings), $currentMonthlyCost),
        ];

        return ['finding' => $finding, 'outcome' => 'compared', 'error' => null];
    }
}
