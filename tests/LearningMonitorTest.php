<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SquirrelForge\Learning\LearningMonitor;
use SquirrelForge\Learning\PatternDetector;
use SquirrelForge\Learning\SqliteAdaptationManager;
use SquirrelForge\Learning\SqliteExperienceStore;
use SquirrelForge\Learning\SqliteLearningGovernance;
use SquirrelForge\Workflow\CallbackStep;
use SquirrelForge\Workflow\StepWorkflow;
use SquirrelForge\Workflow\WorkflowEngine;

final class LearningMonitorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-learning-monitor-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    public function testHealthSummaryWithoutDependenciesIsNotConfigured(): void
    {
        $monitor = new LearningMonitor();

        $result = $monitor->healthSummary();

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testHealthSummaryAggregatesRealStatusCounts(): void
    {
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $experiences->record('agent:1', 'timeout');
        $experiences->record('agent:1', 'timeout');
        $adaptation = new SqliteAdaptationManager($this->tempPath('adaptation'));
        $adaptation->createPlan('proposal_1', ['rollback_plan' => 'x']);
        $monitor = new LearningMonitor($experiences, $adaptation);

        $result = $monitor->healthSummary();

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertSame(2, $result['experience_status_counts']['recorded']);
        $this->assertSame(1, $result['adaptation_status_counts']['planned']);
    }

    public function testDetectStalledAdaptationsFlagsPlansOlderThanTheThreshold(): void
    {
        $currentTime = new DateTimeImmutable('2026-07-29T09:00:00Z');
        $adaptation = new SqliteAdaptationManager($this->tempPath('adaptation'), clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });
        $created = $adaptation->createPlan('proposal_1', ['rollback_plan' => 'x']);
        $monitor = new LearningMonitor(adaptationManager: $adaptation, clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });

        $tooEarly = $monitor->detectStalledAdaptations(3600);
        $currentTime = $currentTime->modify('+3601 seconds');
        $stalled = $monitor->detectStalledAdaptations(3600);

        $this->assertSame([], $tooEarly['stalled']);
        $this->assertCount(1, $stalled['stalled']);
        $this->assertSame($created['plan_id'], $stalled['stalled'][0]['plan_id']);
    }

    private function failingEngine(): WorkflowEngine
    {
        $engine = new WorkflowEngine();
        $engine->register(new StepWorkflow('adapt', 'd', static fn(array $c): bool => true, [
            new CallbackStep('step_1', 'Step One', static function (): never {
                throw new RuntimeException('boom');
            }),
        ]));

        return $engine;
    }

    public function testDetectRepeatedAdaptationFailuresGroupsByProposal(): void
    {
        $adaptation = new SqliteAdaptationManager($this->tempPath('adaptation'), workflowEngine: $this->failingEngine());
        $planA1 = $adaptation->createPlan('proposal_1', ['rollback_plan' => 'x']);
        $adaptation->execute($planA1['plan_id'], []);
        $planA2 = $adaptation->createPlan('proposal_1', ['rollback_plan' => 'x']);
        $adaptation->execute($planA2['plan_id'], []);
        $planA3 = $adaptation->createPlan('proposal_1', ['rollback_plan' => 'x']);
        $adaptation->execute($planA3['plan_id'], []);
        $planB1 = $adaptation->createPlan('proposal_2', ['rollback_plan' => 'x']);
        $adaptation->execute($planB1['plan_id'], []);
        $monitor = new LearningMonitor(adaptationManager: $adaptation);

        $result = $monitor->detectRepeatedAdaptationFailures(3);

        $this->assertCount(1, $result['repeated_failures']);
        $this->assertSame('proposal_1', $result['repeated_failures'][0]['proposal_id']);
        $this->assertSame(3, $result['repeated_failures'][0]['failure_count']);
    }

    public function testDetectTrendDelegatesToPatternDetector(): void
    {
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $patternDetector = new PatternDetector($experiences);
        $monitor = new LearningMonitor(patternDetector: $patternDetector);

        $result = $monitor->detectTrend('agent:1', 'timeout');

        $this->assertSame('insufficient_data', $result['outcome']);
    }

    public function testDetectTrendWithoutPatternDetectorConfiguredIsNotConfigured(): void
    {
        $monitor = new LearningMonitor();

        $result = $monitor->detectTrend('agent:1', 'timeout');

        $this->assertSame('not_configured', $result['outcome']);
    }

    private function successfulEngine(): WorkflowEngine
    {
        $engine = new WorkflowEngine();
        $engine->register(new StepWorkflow('adapt', 'd', static fn(array $c): bool => true, [
            new CallbackStep('step_1', 'Step One', static fn(array $c): array => $c),
        ]));

        return $engine;
    }

    public function testDetectUnauthorizedAdaptationsFlagsExecutionWithoutMatchingApproval(): void
    {
        // No governance->review() call at all for proposal_1: it was never actually approved.
        $governance = new SqliteLearningGovernance($this->tempPath('governance'));
        $adaptation = new SqliteAdaptationManager($this->tempPath('adaptation'), workflowEngine: $this->successfulEngine());
        $created = $adaptation->createPlan('proposal_1', ['rollback_plan' => 'x']);
        $adaptation->execute($created['plan_id'], []);
        $monitor = new LearningMonitor(adaptationManager: $adaptation, governance: $governance);

        $result = $monitor->detectUnauthorizedAdaptations();

        $this->assertCount(1, $result['unauthorized']);
        $this->assertSame('proposal_1', $result['unauthorized'][0]['proposal_id']);
        $this->assertNull($result['unauthorized'][0]['governance_outcome']);
    }

    public function testDetectUnauthorizedAdaptationsDoesNotFlagAGovernedProposal(): void
    {
        $governance = new SqliteLearningGovernance($this->tempPath('governance'));
        $governance->review('proposal_1');
        $adaptation = new SqliteAdaptationManager($this->tempPath('adaptation'), $governance, workflowEngine: $this->successfulEngine());
        $created = $adaptation->createPlan('proposal_1', ['rollback_plan' => 'x']);
        $adaptation->execute($created['plan_id'], []);
        $monitor = new LearningMonitor(adaptationManager: $adaptation, governance: $governance);

        $result = $monitor->detectUnauthorizedAdaptations();

        $this->assertSame([], $result['unauthorized']);
    }
}
