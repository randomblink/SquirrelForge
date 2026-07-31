<?php

declare(strict_types=1);

namespace SquirrelForge\Optimization;

use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Top-level coordinator for the Optimization Layer, per
 * 32_OPTIMIZATION/OPTIMIZATION-MANAGER.md, mirroring how
 * ObservabilityManager, LearningManager, and AutomationManager route to
 * their own layer's real specialists.
 *
 * Every dependency the spec lists is real and gets at least one routed
 * operation: Optimization Engine (identify_opportunities,
 * create_proposal), Agent Optimizer (analyze_agent), Performance
 * Optimizer (analyze_bottlenecks, compare_performance_baseline),
 * Resource Optimizer (analyze_resource_utilization,
 * analyze_resource_constraints), Workflow Optimizer
 * (analyze_workflow_parallelization, analyze_workflow_execution_pattern),
 * Capacity Planner (forecast_capacity, identify_capacity_constraint),
 * Cost Optimizer (identify_cost_reduction, forecast_budget_impact),
 * Optimization Validator (validate_proposal), and Optimization
 * Governance (review_proposal) -- this closes out 32_OPTIMIZATION with
 * every real component wired into a single coordination surface, the
 * same way ObservabilityManager closed out 27_OBSERVABILITY.
 *
 * This class never re-implements any specialist's logic, only forwards
 * payloads and passes back real results unmodified, upholding the
 * spec's Boundary ("does not independently perform specialist
 * optimization analysis... or Optimization Governance decisions").
 * Like its sibling coordinators, it owns no database of its own -- the
 * Boundary forbids owning "storage persistence... or audit-trail
 * infrastructure" -- only an optional EventBusInterface announcement
 * per operation.
 */
final class OptimizationManager
{
    private const REQUIRED_FIELDS = [
        'identify_opportunities' => [],
        'create_proposal' => ['proposal_id', 'opportunity'],
        'analyze_agent' => ['agent_id'],
        'analyze_bottlenecks' => ['trace_id'],
        'compare_performance_baseline' => ['baseline_metric', 'candidate_metric'],
        'analyze_resource_utilization' => ['metric_name', 'target_min', 'target_max'],
        'analyze_resource_constraints' => ['resources'],
        'analyze_workflow_parallelization' => ['steps'],
        'analyze_workflow_execution_pattern' => ['trace_id'],
        'forecast_capacity' => ['metric_name'],
        'identify_capacity_constraint' => ['metric_name', 'capacity_limit'],
        'identify_cost_reduction' => ['metric_name', 'target_min', 'target_max', 'current_monthly_cost'],
        'forecast_budget_impact' => ['metric_name', 'scenarios', 'current_monthly_cost'],
        'validate_proposal' => ['proposal'],
        'review_proposal' => ['proposal_id', 'proposal'],
    ];

    public function __construct(
        private readonly ?OptimizationEngine $optimizationEngine = null,
        private readonly ?AgentOptimizer $agentOptimizer = null,
        private readonly ?PerformanceOptimizer $performanceOptimizer = null,
        private readonly ?ResourceOptimizer $resourceOptimizer = null,
        private readonly ?WorkflowOptimizer $workflowOptimizer = null,
        private readonly ?CapacityPlanner $capacityPlanner = null,
        private readonly ?CostOptimizer $costOptimizer = null,
        private readonly ?OptimizationValidator $optimizationValidator = null,
        private readonly ?SqliteOptimizationGovernance $governance = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options forwarded to whichever specialist handles this operation
     * @return array{operation: string, target_component: string, outcome: string, error: ?string, result: mixed}
     */
    public function coordinate(string $operation, array $payload, array $options = []): array
    {
        if (!isset(self::REQUIRED_FIELDS[$operation])) {
            return $this->finish($operation, 'none', 'rejected', sprintf('Unknown optimization operation "%s".', $operation), null);
        }

        $missingField = $this->firstMissingField($operation, $payload);

        if ($missingField !== null) {
            return $this->finish($operation, 'none', 'rejected', sprintf('Missing required field "%s" for operation "%s".', $missingField, $operation), null);
        }

        return match ($operation) {
            'identify_opportunities' => $this->coordinateIdentifyOpportunities($payload, $options),
            'create_proposal' => $this->coordinateCreateProposal($payload, $options),
            'analyze_agent' => $this->coordinateAnalyzeAgent($payload, $options),
            'analyze_bottlenecks' => $this->coordinateAnalyzeBottlenecks($payload),
            'compare_performance_baseline' => $this->coordinateComparePerformanceBaseline($payload),
            'analyze_resource_utilization' => $this->coordinateAnalyzeResourceUtilization($payload),
            'analyze_resource_constraints' => $this->coordinateAnalyzeResourceConstraints($payload),
            'analyze_workflow_parallelization' => $this->coordinateAnalyzeWorkflowParallelization($payload),
            'analyze_workflow_execution_pattern' => $this->coordinateAnalyzeWorkflowExecutionPattern($payload),
            'forecast_capacity' => $this->coordinateForecastCapacity($payload),
            'identify_capacity_constraint' => $this->coordinateIdentifyCapacityConstraint($payload),
            'identify_cost_reduction' => $this->coordinateIdentifyCostReduction($payload),
            'forecast_budget_impact' => $this->coordinateForecastBudgetImpact($payload),
            'validate_proposal' => $this->coordinateValidateProposal($payload, $options),
            'review_proposal' => $this->coordinateReviewProposal($payload, $options),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function firstMissingField(string $operation, array $payload): ?string
    {
        foreach (self::REQUIRED_FIELDS[$operation] as $field) {
            if (!array_key_exists($field, $payload)) {
                return $field;
            }
        }

        return null;
    }

    private function coordinateIdentifyOpportunities(array $payload, array $options): array
    {
        if ($this->optimizationEngine === null) {
            return $this->finish('identify_opportunities', 'optimization_engine', 'rejected', 'Optimization Engine is not configured.', null);
        }

        $result = $this->optimizationEngine->identifyOpportunities($payload, $options);

        return $this->finish('identify_opportunities', 'optimization_engine', $result['outcome'], $result['error'], $result);
    }

    private function coordinateCreateProposal(array $payload, array $options): array
    {
        if ($this->optimizationEngine === null) {
            return $this->finish('create_proposal', 'optimization_engine', 'rejected', 'Optimization Engine is not configured.', null);
        }

        $result = $this->optimizationEngine->createProposal($payload['proposal_id'], $payload['opportunity'], $options);

        return $this->finish('create_proposal', 'optimization_engine', $result['outcome'], null, $result);
    }

    private function coordinateAnalyzeAgent(array $payload, array $options): array
    {
        if ($this->agentOptimizer === null) {
            return $this->finish('analyze_agent', 'agent_optimizer', 'rejected', 'Agent Optimizer is not configured.', null);
        }

        $result = $this->agentOptimizer->analyzeAgent($payload['agent_id'], $payload['filters'] ?? [], $options);

        return $this->finish('analyze_agent', 'agent_optimizer', $result['outcome'], $result['error'], $result);
    }

    private function coordinateAnalyzeBottlenecks(array $payload): array
    {
        if ($this->performanceOptimizer === null) {
            return $this->finish('analyze_bottlenecks', 'performance_optimizer', 'rejected', 'Performance Optimizer is not configured.', null);
        }

        $result = $this->performanceOptimizer->analyzeBottlenecks($payload['trace_id'], $payload['limit'] ?? 3);

        return $this->finish('analyze_bottlenecks', 'performance_optimizer', $result['outcome'], $result['error'], $result);
    }

    private function coordinateComparePerformanceBaseline(array $payload): array
    {
        if ($this->performanceOptimizer === null) {
            return $this->finish('compare_performance_baseline', 'performance_optimizer', 'rejected', 'Performance Optimizer is not configured.', null);
        }

        $result = $this->performanceOptimizer->compareBaseline($payload['baseline_metric'], $payload['candidate_metric'], $payload['aggregate_type'] ?? 'mean');

        return $this->finish('compare_performance_baseline', 'performance_optimizer', $result['outcome'], $result['error'], $result);
    }

    private function coordinateAnalyzeResourceUtilization(array $payload): array
    {
        if ($this->resourceOptimizer === null) {
            return $this->finish('analyze_resource_utilization', 'resource_optimizer', 'rejected', 'Resource Optimizer is not configured.', null);
        }

        $result = $this->resourceOptimizer->analyzeUtilizationBand($payload['metric_name'], $payload['target_min'], $payload['target_max'], $payload['aggregate_type'] ?? 'mean');

        return $this->finish('analyze_resource_utilization', 'resource_optimizer', $result['outcome'], $result['error'], $result);
    }

    private function coordinateAnalyzeResourceConstraints(array $payload): array
    {
        if ($this->resourceOptimizer === null) {
            return $this->finish('analyze_resource_constraints', 'resource_optimizer', 'rejected', 'Resource Optimizer is not configured.', null);
        }

        $result = $this->resourceOptimizer->analyzeConstraints($payload['resources']);

        return $this->finish('analyze_resource_constraints', 'resource_optimizer', $result['outcome'], $result['error'], $result);
    }

    private function coordinateAnalyzeWorkflowParallelization(array $payload): array
    {
        if ($this->workflowOptimizer === null) {
            return $this->finish('analyze_workflow_parallelization', 'workflow_optimizer', 'rejected', 'Workflow Optimizer is not configured.', null);
        }

        $result = $this->workflowOptimizer->analyzeParallelizationOpportunities($payload['steps']);

        return $this->finish('analyze_workflow_parallelization', 'workflow_optimizer', $result['outcome'], $result['error'], $result);
    }

    private function coordinateAnalyzeWorkflowExecutionPattern(array $payload): array
    {
        if ($this->workflowOptimizer === null) {
            return $this->finish('analyze_workflow_execution_pattern', 'workflow_optimizer', 'rejected', 'Workflow Optimizer is not configured.', null);
        }

        $result = $this->workflowOptimizer->analyzeExecutionPattern($payload['trace_id']);

        return $this->finish('analyze_workflow_execution_pattern', 'workflow_optimizer', $result['outcome'], $result['error'], $result);
    }

    private function coordinateForecastCapacity(array $payload): array
    {
        if ($this->capacityPlanner === null) {
            return $this->finish('forecast_capacity', 'capacity_planner', 'rejected', 'Capacity Planner is not configured.', null);
        }

        $result = $this->capacityPlanner->forecastDemand($payload['metric_name'], $payload['periods_ahead'] ?? 1);

        return $this->finish('forecast_capacity', 'capacity_planner', $result['outcome'], $result['error'], $result);
    }

    private function coordinateIdentifyCapacityConstraint(array $payload): array
    {
        if ($this->capacityPlanner === null) {
            return $this->finish('identify_capacity_constraint', 'capacity_planner', 'rejected', 'Capacity Planner is not configured.', null);
        }

        $result = $this->capacityPlanner->identifyConstraint($payload['metric_name'], $payload['capacity_limit'], $payload['periods_ahead'] ?? 1);

        return $this->finish('identify_capacity_constraint', 'capacity_planner', $result['outcome'], $result['error'], $result);
    }

    private function coordinateIdentifyCostReduction(array $payload): array
    {
        if ($this->costOptimizer === null) {
            return $this->finish('identify_cost_reduction', 'cost_optimizer', 'rejected', 'Cost Optimizer is not configured.', null);
        }

        $result = $this->costOptimizer->identifyReductionFromUtilization($payload['metric_name'], $payload['target_min'], $payload['target_max'], $payload['current_monthly_cost'], $payload['aggregate_type'] ?? 'mean');

        return $this->finish('identify_cost_reduction', 'cost_optimizer', $result['outcome'], $result['error'], $result);
    }

    private function coordinateForecastBudgetImpact(array $payload): array
    {
        if ($this->costOptimizer === null) {
            return $this->finish('forecast_budget_impact', 'cost_optimizer', 'rejected', 'Cost Optimizer is not configured.', null);
        }

        $result = $this->costOptimizer->forecastBudgetImpact($payload['metric_name'], $payload['scenarios'], $payload['current_monthly_cost'], $payload['periods_ahead'] ?? 1, $payload['migration_cost'] ?? null);

        return $this->finish('forecast_budget_impact', 'cost_optimizer', $result['outcome'], $result['error'], $result);
    }

    private function coordinateValidateProposal(array $payload, array $options): array
    {
        if ($this->optimizationValidator === null) {
            return $this->finish('validate_proposal', 'optimization_validator', 'rejected', 'Optimization Validator is not configured.', null);
        }

        $result = $this->optimizationValidator->validate($payload['proposal'], $options);

        return $this->finish('validate_proposal', 'optimization_validator', $result['outcome'], null, $result);
    }

    private function coordinateReviewProposal(array $payload, array $options): array
    {
        if ($this->governance === null) {
            return $this->finish('review_proposal', 'optimization_governance', 'rejected', 'Optimization Governance is not configured.', null);
        }

        $result = $this->governance->review($payload['proposal_id'], $payload['proposal'], $options);

        return $this->finish('review_proposal', 'optimization_governance', $result['outcome'], null, $result);
    }

    private function finish(string $operation, string $targetComponent, string $outcome, ?string $error, mixed $result): array
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'optimization.finished',
            new DateTimeImmutable(),
            self::class,
            ['operation' => $operation, 'target_component' => $targetComponent, 'outcome' => $outcome]
        ));

        return ['operation' => $operation, 'target_component' => $targetComponent, 'outcome' => $outcome, 'error' => $error, 'result' => $result];
    }
}
