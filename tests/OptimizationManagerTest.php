<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Learning\PatternDetector;
use SquirrelForge\Learning\SqliteExperienceStore;
use SquirrelForge\Observability\DiagnosticsEngine;
use SquirrelForge\Observability\MetricsManager;
use SquirrelForge\Observability\TraceManager;
use SquirrelForge\Optimization\AgentOptimizer;
use SquirrelForge\Optimization\CapacityPlanner;
use SquirrelForge\Optimization\CostOptimizer;
use SquirrelForge\Optimization\OptimizationEngine;
use SquirrelForge\Optimization\OptimizationManager;
use SquirrelForge\Optimization\OptimizationValidator;
use SquirrelForge\Optimization\PerformanceOptimizer;
use SquirrelForge\Optimization\ResourceOptimizer;
use SquirrelForge\Optimization\SqliteOptimizationGovernance;
use SquirrelForge\Optimization\WorkflowOptimizer;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class OptimizationManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-optimization-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
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
     * @return array{0: MetricsManager, 1: TraceManager, 2: SqliteExperienceStore, 3: OptimizationManager}
     */
    private function fullyWiredManager(?EventBus $events = null): array
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $metrics = new MetricsManager($documents);
        $traces = new TraceManager($documents);
        $diagnostics = new DiagnosticsEngine(metrics: $metrics, traces: $traces);
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $patternDetector = new PatternDetector($experiences);

        $optimizationEngine = new OptimizationEngine($patternDetector);
        $agentOptimizer = new AgentOptimizer($patternDetector);
        $performanceOptimizer = new PerformanceOptimizer($diagnostics, $metrics);
        $resourceOptimizer = new ResourceOptimizer($metrics, $diagnostics);
        $workflowOptimizer = new WorkflowOptimizer($traces);
        $capacityPlanner = new CapacityPlanner($metrics);
        $costOptimizer = new CostOptimizer($resourceOptimizer, $capacityPlanner);
        $validator = new OptimizationValidator();
        $governance = new SqliteOptimizationGovernance($this->tempPath('governance'), $validator);

        $manager = new OptimizationManager(
            $optimizationEngine,
            $agentOptimizer,
            $performanceOptimizer,
            $resourceOptimizer,
            $workflowOptimizer,
            $capacityPlanner,
            $costOptimizer,
            $validator,
            $governance,
            $events
        );

        return [$metrics, $traces, $experiences, $manager];
    }

    public function testUnknownOperationIsRejected(): void
    {
        $manager = new OptimizationManager();

        $result = $manager->coordinate('teleport', []);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('none', $result['target_component']);
    }

    public function testMissingRequiredFieldIsRejectedBeforeRouting(): void
    {
        $manager = new OptimizationManager();

        $result = $manager->coordinate('create_proposal', ['proposal_id' => 'proposal_1']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('opportunity', (string) $result['error']);
    }

    public function testIdentifyOpportunitiesRoutesToOptimizationEngine(): void
    {
        [, , , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('identify_opportunities', []);

        $this->assertSame('optimization_engine', $result['target_component']);
        $this->assertSame('analyzed', $result['outcome']);
    }

    public function testCreateProposalRoutesToOptimizationEngine(): void
    {
        [, , , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('create_proposal', [
            'proposal_id' => 'proposal_1',
            'opportunity' => ['type' => 'recurring_issue', 'evidence' => ['count' => 5, 'confidence' => 0.8]],
        ]);

        $this->assertSame('optimization_engine', $result['target_component']);
        $this->assertSame('proposed', $result['outcome']);
    }

    public function testAnalyzeAgentRoutesToAgentOptimizer(): void
    {
        [, , , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('analyze_agent', ['agent_id' => 'agent_1']);

        $this->assertSame('agent_optimizer', $result['target_component']);
        $this->assertSame('analyzed', $result['outcome']);
    }

    public function testAnalyzeBottlenecksRoutesToPerformanceOptimizer(): void
    {
        [, $traces, , $manager] = $this->fullyWiredManager();
        $traces->recordSpan([
            'event_id' => 'evt_1', 'source' => 'workflow-engine', 'signal_type' => 'trace', 'correlation_id' => 'trace_1',
            'payload' => ['span_id' => 'span_a', 'operation' => 'checkout', 'started_at' => '2026-07-29T12:00:00Z', 'ended_at' => '2026-07-29T12:00:05Z'],
            'timestamp' => '2026-07-29T12:00:00Z',
        ]);

        $result = $manager->coordinate('analyze_bottlenecks', ['trace_id' => 'trace_1']);

        $this->assertSame('performance_optimizer', $result['target_component']);
        $this->assertSame('analyzed', $result['outcome']);
    }

    public function testComparePerformanceBaselineRoutesToPerformanceOptimizer(): void
    {
        [$metrics, , , $manager] = $this->fullyWiredManager();
        $this->recordMetric($metrics, 'latency_baseline', 100);
        $this->recordMetric($metrics, 'latency_candidate', 80);

        $result = $manager->coordinate('compare_performance_baseline', ['baseline_metric' => 'latency_baseline', 'candidate_metric' => 'latency_candidate']);

        $this->assertSame('performance_optimizer', $result['target_component']);
        $this->assertSame('compared', $result['outcome']);
    }

    public function testAnalyzeResourceUtilizationRoutesToResourceOptimizer(): void
    {
        [$metrics, , , $manager] = $this->fullyWiredManager();
        $this->recordMetric($metrics, 'cpu_utilization_pct', 10);

        $result = $manager->coordinate('analyze_resource_utilization', ['metric_name' => 'cpu_utilization_pct', 'target_min' => 40.0, 'target_max' => 80.0]);

        $this->assertSame('resource_optimizer', $result['target_component']);
        $this->assertSame('over_provisioned', $result['outcome']);
    }

    public function testAnalyzeResourceConstraintsRoutesToResourceOptimizer(): void
    {
        [$metrics, , , $manager] = $this->fullyWiredManager();
        $this->recordMetric($metrics, 'db_connections', 95);

        $result = $manager->coordinate('analyze_resource_constraints', ['resources' => [
            'database' => ['metric' => 'db_connections', 'operator' => '>', 'threshold' => 80.0],
        ]]);

        $this->assertSame('resource_optimizer', $result['target_component']);
        $this->assertSame('analyzed', $result['outcome']);
        $this->assertCount(1, $result['result']['findings']);
    }

    public function testAnalyzeWorkflowParallelizationRoutesToWorkflowOptimizer(): void
    {
        [, , , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('analyze_workflow_parallelization', ['steps' => [
            ['id' => 'fetch_a'],
            ['id' => 'fetch_b'],
        ]]);

        $this->assertSame('workflow_optimizer', $result['target_component']);
        $this->assertSame('analyzed', $result['outcome']);
        $this->assertCount(1, $result['result']['findings']);
    }

    public function testAnalyzeWorkflowExecutionPatternRoutesToWorkflowOptimizer(): void
    {
        [, $traces, , $manager] = $this->fullyWiredManager();
        $traces->recordSpan([
            'event_id' => 'evt_1', 'source' => 'workflow-engine', 'signal_type' => 'trace', 'correlation_id' => 'trace_1',
            'payload' => ['span_id' => 'span_a', 'operation' => 'a', 'started_at' => '2026-07-29T12:00:00Z', 'ended_at' => '2026-07-29T12:00:05Z'],
            'timestamp' => '2026-07-29T12:00:00Z',
        ]);

        $result = $manager->coordinate('analyze_workflow_execution_pattern', ['trace_id' => 'trace_1']);

        $this->assertSame('workflow_optimizer', $result['target_component']);
        $this->assertSame('analyzed', $result['outcome']);
    }

    public function testForecastCapacityRoutesToCapacityPlanner(): void
    {
        [$metrics, , , $manager] = $this->fullyWiredManager();
        foreach ([10, 20, 30] as $value) {
            $this->recordMetric($metrics, 'queue_depth', $value);
        }

        $result = $manager->coordinate('forecast_capacity', ['metric_name' => 'queue_depth']);

        $this->assertSame('capacity_planner', $result['target_component']);
        $this->assertSame('forecasted', $result['outcome']);
        $this->assertEqualsWithDelta(40.0, $result['result']['forecast_value'], 0.001);
    }

    public function testIdentifyCapacityConstraintRoutesToCapacityPlanner(): void
    {
        [$metrics, , , $manager] = $this->fullyWiredManager();
        foreach ([10, 20, 30] as $value) {
            $this->recordMetric($metrics, 'queue_depth', $value);
        }

        $result = $manager->coordinate('identify_capacity_constraint', ['metric_name' => 'queue_depth', 'capacity_limit' => 35.0]);

        $this->assertSame('capacity_planner', $result['target_component']);
        $this->assertSame('projected_constraint', $result['outcome']);
    }

    public function testIdentifyCostReductionRoutesToCostOptimizer(): void
    {
        [$metrics, , , $manager] = $this->fullyWiredManager();
        $this->recordMetric($metrics, 'cpu_utilization_pct', 10);

        $result = $manager->coordinate('identify_cost_reduction', [
            'metric_name' => 'cpu_utilization_pct', 'target_min' => 40.0, 'target_max' => 80.0, 'current_monthly_cost' => 1000.0,
        ]);

        $this->assertSame('cost_optimizer', $result['target_component']);
        $this->assertSame('cost_reduction_opportunity', $result['outcome']);
    }

    public function testForecastBudgetImpactRoutesToCostOptimizer(): void
    {
        [$metrics, , , $manager] = $this->fullyWiredManager();
        foreach ([10, 20, 30] as $value) {
            $this->recordMetric($metrics, 'queue_depth', $value);
        }

        $result = $manager->coordinate('forecast_budget_impact', [
            'metric_name' => 'queue_depth', 'current_monthly_cost' => 200.0,
            'scenarios' => ['large' => ['capacity' => 100.0, 'cost' => 50.0]],
        ]);

        $this->assertSame('cost_optimizer', $result['target_component']);
        $this->assertSame('compared', $result['outcome']);
    }

    public function testValidateProposalRoutesToOptimizationValidator(): void
    {
        [, , , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('validate_proposal', [
            'proposal' => ['proposal_id' => 'proposal_1', 'opportunity' => ['evidence' => ['count' => 5]], 'success_criteria' => ['reduce errors']],
        ]);

        $this->assertSame('optimization_validator', $result['target_component']);
        $this->assertContains($result['outcome'], ['ready', 'ready_with_conditions', 'requires_revision', 'not_ready', 'deferred']);
    }

    public function testReviewProposalRoutesToOptimizationGovernance(): void
    {
        [, , , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('review_proposal', [
            'proposal_id' => 'proposal_1',
            'proposal' => ['opportunity' => ['evidence' => ['count' => 5]], 'success_criteria' => ['reduce errors']],
        ]);

        $this->assertSame('optimization_governance', $result['target_component']);
        $this->assertNotNull($result['outcome']);
    }

    public function testCoordinateDispatchesAnEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('optimization.finished', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        [, , , $manager] = $this->fullyWiredManager($events);
        $manager->coordinate('identify_opportunities', []);

        $this->assertCount(1, $captured);
    }
}
