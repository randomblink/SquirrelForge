<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Learning\PatternDetector;
use SquirrelForge\Learning\SqliteExperienceStore;
use SquirrelForge\Optimization\AgentOptimizer;

final class AgentOptimizerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-agent-optimizer-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    public function testAnalyzeAgentWithoutPatternDetectorIsNotConfigured(): void
    {
        $optimizer = new AgentOptimizer();

        $result = $optimizer->analyzeAgent('agent:1');

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testRecurringIssueIsClassifiedAndGivenAMeasurableTarget(): void
    {
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $experiences->record('agent:1', 'tool_call_timeout');
        $experiences->record('agent:1', 'tool_call_timeout');
        $experiences->record('agent:1', 'tool_call_timeout');
        $experiences->record('agent:1', 'tool_call_timeout');
        $optimizer = new AgentOptimizer(new PatternDetector($experiences));

        $result = $optimizer->analyzeAgent('agent:1');

        $this->assertSame('analyzed', $result['outcome']);
        $finding = $result['findings'][0];
        $this->assertSame('recurring_issue', $finding['type']);
        $this->assertSame('tool_use', $finding['dimension']);
        $this->assertStringContainsString('below 2 occurrences', $finding['measurable_target']);
    }

    public function testAnomalyFindingIsClassifiedWithinTheGivenCategory(): void
    {
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $experiences->record('agent:1', 'memory_recall', ['confidence' => 0.50]);
        $experiences->record('agent:1', 'memory_recall', ['confidence' => 0.52]);
        $experiences->record('agent:1', 'memory_recall', ['confidence' => 0.48]);
        $experiences->record('agent:1', 'memory_recall', ['confidence' => 0.51]);
        $experiences->record('agent:1', 'memory_recall', ['confidence' => 0.49]);
        $experiences->record('agent:1', 'memory_recall', ['confidence' => 0.05]);
        $optimizer = new AgentOptimizer(new PatternDetector($experiences));

        $result = $optimizer->analyzeAgent('agent:1', ['event_category' => 'memory_recall']);

        $anomalyFindings = array_values(array_filter($result['findings'], static fn(array $f): bool => $f['type'] === 'anomaly'));
        $this->assertNotEmpty($anomalyFindings);
        $this->assertSame('memory', $anomalyFindings[0]['dimension']);
    }

    public function testDecliningTrendProducesAFinding(): void
    {
        $currentTime = new \DateTimeImmutable('2026-07-29T00:00:00Z');
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'), clock: function () use (&$currentTime): \DateTimeImmutable {
            $result = $currentTime;
            $currentTime = $currentTime->modify('+1 hour');

            return $result;
        });
        $experiences->record('agent:1', 'planning_step', ['confidence' => 0.9]);
        $experiences->record('agent:1', 'planning_step', ['confidence' => 0.9]);
        $experiences->record('agent:1', 'planning_step', ['confidence' => 0.2]);
        $experiences->record('agent:1', 'planning_step', ['confidence' => 0.2]);
        $optimizer = new AgentOptimizer(new PatternDetector($experiences));

        $result = $optimizer->analyzeAgent('agent:1', ['event_category' => 'planning_step']);

        $trendFindings = array_values(array_filter($result['findings'], static fn(array $f): bool => $f['type'] === 'declining_trend'));
        $this->assertNotEmpty($trendFindings);
        $this->assertSame('planning', $trendFindings[0]['dimension']);
    }

    public function testNoTrendFindingWithoutAnEventCategoryFilter(): void
    {
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $optimizer = new AgentOptimizer(new PatternDetector($experiences));

        $result = $optimizer->analyzeAgent('agent:1');

        $trendFindings = array_filter($result['findings'], static fn(array $f): bool => $f['type'] === 'declining_trend');
        $this->assertSame([], $trendFindings);
    }

    public function testUnmatchedCategoryClassifiesAsExecution(): void
    {
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $experiences->record('agent:1', 'network_call_failed');
        $experiences->record('agent:1', 'network_call_failed');
        $experiences->record('agent:1', 'network_call_failed');
        $optimizer = new AgentOptimizer(new PatternDetector($experiences));

        $result = $optimizer->analyzeAgent('agent:1');

        $this->assertSame('execution', $result['findings'][0]['dimension']);
    }

    public function testAnalysisIsScopedToTheRequestedAgentOnly(): void
    {
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $experiences->record('agent:1', 'timeout');
        $experiences->record('agent:1', 'timeout');
        $experiences->record('agent:1', 'timeout');
        $experiences->record('agent:2', 'timeout');
        $experiences->record('agent:2', 'timeout');
        $experiences->record('agent:2', 'timeout');
        $optimizer = new AgentOptimizer(new PatternDetector($experiences));

        $result = $optimizer->analyzeAgent('agent:1');

        $this->assertCount(1, $result['findings']);
        $this->assertSame('agent:1', $result['findings'][0]['agent_id']);
    }
}
