<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Observability\TraceManager;
use SquirrelForge\Optimization\WorkflowOptimizer;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class WorkflowOptimizerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-workflow-optimizer-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function telemetry(array $payload, string $correlationId = 'trace_1'): array
    {
        return [
            'event_id' => 'evt_' . bin2hex(random_bytes(4)),
            'source' => 'workflow-engine',
            'signal_type' => 'trace',
            'correlation_id' => $correlationId,
            'payload' => $payload,
            'timestamp' => '2026-07-29T12:00:00Z',
        ];
    }

    public function testAnalyzeParallelizationOpportunitiesRejectsADuplicateStepId(): void
    {
        $optimizer = new WorkflowOptimizer();

        $result = $optimizer->analyzeParallelizationOpportunities([
            ['id' => 'step_a'],
            ['id' => 'step_a'],
        ]);

        $this->assertSame('invalid_graph', $result['outcome']);
    }

    public function testAnalyzeParallelizationOpportunitiesRejectsAnUnknownDependency(): void
    {
        $optimizer = new WorkflowOptimizer();

        $result = $optimizer->analyzeParallelizationOpportunities([
            ['id' => 'step_a', 'depends_on' => ['step_ghost']],
        ]);

        $this->assertSame('invalid_graph', $result['outcome']);
    }

    public function testAnalyzeParallelizationOpportunitiesRejectsACycle(): void
    {
        $optimizer = new WorkflowOptimizer();

        $result = $optimizer->analyzeParallelizationOpportunities([
            ['id' => 'step_a', 'depends_on' => ['step_b']],
            ['id' => 'step_b', 'depends_on' => ['step_a']],
        ]);

        $this->assertSame('invalid_graph', $result['outcome']);
        $this->assertStringContainsString('cycle', $result['error']);
    }

    public function testAnalyzeParallelizationOpportunitiesFindsIndependentStepsAtTheSameLevel(): void
    {
        $optimizer = new WorkflowOptimizer();

        $result = $optimizer->analyzeParallelizationOpportunities([
            ['id' => 'fetch_inventory'],
            ['id' => 'fetch_pricing'],
            ['id' => 'checkout', 'depends_on' => ['fetch_inventory', 'fetch_pricing']],
        ]);

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertCount(1, $result['findings']);
        $this->assertSame('parallelization_opportunity', $result['findings'][0]['type']);
        $this->assertEqualsCanonicalizing(['fetch_inventory', 'fetch_pricing'], $result['findings'][0]['evidence']['step_ids']);
    }

    public function testAnalyzeParallelizationOpportunitiesWithAFullySequentialGraphFindsNothing(): void
    {
        $optimizer = new WorkflowOptimizer();

        $result = $optimizer->analyzeParallelizationOpportunities([
            ['id' => 'step_a'],
            ['id' => 'step_b', 'depends_on' => ['step_a']],
            ['id' => 'step_c', 'depends_on' => ['step_b']],
        ]);

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertSame([], $result['findings']);
    }

    public function testAnalyzeExecutionPatternWithoutTraceManagerIsNotConfigured(): void
    {
        $optimizer = new WorkflowOptimizer();

        $result = $optimizer->analyzeExecutionPattern('trace_1');

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testAnalyzeExecutionPatternFlagsSequentialSpansWithoutADeclaredDependency(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $traces = new TraceManager($documents);
        $optimizer = new WorkflowOptimizer($traces);
        $traces->recordSpan($this->telemetry(['span_id' => 'span_a', 'operation' => 'fetch_a', 'started_at' => '2026-07-29T12:00:00Z', 'ended_at' => '2026-07-29T12:00:05Z']));
        $traces->recordSpan($this->telemetry(['span_id' => 'span_b', 'operation' => 'fetch_b', 'started_at' => '2026-07-29T12:00:05Z', 'ended_at' => '2026-07-29T12:00:10Z']));

        $result = $optimizer->analyzeExecutionPattern('trace_1');

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertCount(1, $result['findings']);
        $this->assertSame('sequential_without_dependency', $result['findings'][0]['type']);
        $this->assertSame('span_a', $result['findings'][0]['evidence']['preceding_span']);
        $this->assertSame('span_b', $result['findings'][0]['evidence']['following_span']);
    }

    public function testAnalyzeExecutionPatternSkipsSpansWithADeclaredParentChildRelationship(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $traces = new TraceManager($documents);
        $optimizer = new WorkflowOptimizer($traces);
        $traces->recordSpan($this->telemetry(['span_id' => 'span_a', 'operation' => 'parent', 'started_at' => '2026-07-29T12:00:00Z', 'ended_at' => '2026-07-29T12:00:05Z']));
        $traces->recordSpan($this->telemetry(['span_id' => 'span_b', 'operation' => 'child', 'parent_span_id' => 'span_a', 'started_at' => '2026-07-29T12:00:05Z', 'ended_at' => '2026-07-29T12:00:10Z']));

        $result = $optimizer->analyzeExecutionPattern('trace_1');

        $this->assertSame([], $result['findings']);
    }

    public function testAnalyzeExecutionPatternSkipsOverlappingSpans(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $traces = new TraceManager($documents);
        $optimizer = new WorkflowOptimizer($traces);
        $traces->recordSpan($this->telemetry(['span_id' => 'span_a', 'operation' => 'a', 'started_at' => '2026-07-29T12:00:00Z', 'ended_at' => '2026-07-29T12:00:10Z']));
        $traces->recordSpan($this->telemetry(['span_id' => 'span_b', 'operation' => 'b', 'started_at' => '2026-07-29T12:00:05Z', 'ended_at' => '2026-07-29T12:00:15Z']));

        $result = $optimizer->analyzeExecutionPattern('trace_1');

        $this->assertSame([], $result['findings']);
    }

    public function testAnalyzeExecutionPatternSkipsSpansMissingTimestamps(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $traces = new TraceManager($documents);
        $optimizer = new WorkflowOptimizer($traces);
        $traces->recordSpan($this->telemetry(['span_id' => 'span_a', 'operation' => 'a', 'started_at' => '2026-07-29T12:00:00Z']));
        $traces->recordSpan($this->telemetry(['span_id' => 'span_b', 'operation' => 'b', 'started_at' => '2026-07-29T12:00:05Z']));

        $result = $optimizer->analyzeExecutionPattern('trace_1');

        $this->assertSame([], $result['findings']);
    }
}
