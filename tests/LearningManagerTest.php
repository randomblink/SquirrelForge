<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Learning\LearningManager;
use SquirrelForge\Learning\LearningMonitor;
use SquirrelForge\Learning\PatternDetector;
use SquirrelForge\Learning\SqliteAdaptationManager;
use SquirrelForge\Learning\SqliteEvaluationEngine;
use SquirrelForge\Learning\SqliteExperienceStore;
use SquirrelForge\Learning\SqliteFeedbackCollector;
use SquirrelForge\Learning\SqliteLearningGovernance;

final class LearningManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-learning-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{0: SqliteExperienceStore, 1: SqliteAdaptationManager, 2: LearningManager}
     */
    private function fullyWiredManager(?EventBus $events = null): array
    {
        $feedback = new SqliteFeedbackCollector($this->tempPath('feedback'));
        $experiences = new SqliteExperienceStore($this->tempPath('experiences'));
        $evaluation = new SqliteEvaluationEngine($this->tempPath('evaluations'), $experiences);
        $patterns = new PatternDetector($experiences);
        $governance = new SqliteLearningGovernance($this->tempPath('governance'));
        $adaptation = new SqliteAdaptationManager($this->tempPath('adaptation'), $governance);
        $monitor = new LearningMonitor($experiences, $adaptation, $governance, $patterns);

        $manager = new LearningManager($feedback, $experiences, $evaluation, $patterns, $governance, $adaptation, $monitor, $events);

        return [$experiences, $adaptation, $manager];
    }

    public function testSubmitFeedbackRoutesToFeedbackCollector(): void
    {
        [, , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('submit_feedback', ['source_type' => 'user', 'content' => ['rating' => 5]]);

        $this->assertSame('collected', $result['outcome']);
        $this->assertSame('feedback_collector', $result['target_component']);
    }

    public function testRecordExperienceRoutesToExperienceStore(): void
    {
        [, , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('record_experience', ['source' => 'agent:1', 'event_category' => 'timeout']);

        $this->assertSame('recorded', $result['outcome']);
        $this->assertSame('experience_store', $result['target_component']);
    }

    public function testEvaluateExperienceRoutesToEvaluationEngine(): void
    {
        [$experiences, , $manager] = $this->fullyWiredManager();
        $recorded = $experiences->record('agent:1', 'timeout', ['evidence_references' => ['e1']]);

        $result = $manager->coordinate('evaluate_experience', ['experience_id' => $recorded['experience_id']]);

        $this->assertSame('qualified', $result['outcome']);
        $this->assertSame('evaluation_engine', $result['target_component']);
    }

    public function testDetectRecurringPatternsRoutesToPatternDetector(): void
    {
        [$experiences, , $manager] = $this->fullyWiredManager();
        $experiences->record('agent:1', 'timeout');
        $experiences->record('agent:1', 'timeout');
        $experiences->record('agent:1', 'timeout');

        $result = $manager->coordinate('detect_recurring_patterns', ['source' => 'agent:1']);

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertNotEmpty($result['result']['findings']);
    }

    public function testDetectTrendRoutesToPatternDetector(): void
    {
        [$experiences, , $manager] = $this->fullyWiredManager();
        $experiences->record('agent:1', 'timeout', ['confidence' => 0.5]);

        $result = $manager->coordinate('detect_trend', ['source' => 'agent:1', 'event_category' => 'timeout']);

        $this->assertSame('insufficient_data', $result['outcome']);
        $this->assertSame('pattern_detector', $result['target_component']);
    }

    public function testDetectAnomaliesRoutesToPatternDetector(): void
    {
        [, , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('detect_anomalies', []);

        $this->assertSame('insufficient_data', $result['outcome']);
        $this->assertSame('pattern_detector', $result['target_component']);
    }

    public function testReviewProposalRoutesToLearningGovernance(): void
    {
        [, , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('review_proposal', ['proposal_id' => 'proposal_1']);

        $this->assertSame('approved', $result['outcome']);
        $this->assertSame('learning_governance', $result['target_component']);
    }

    public function testCreateAdaptationPlanRoutesToAdaptationManager(): void
    {
        [, , $manager] = $this->fullyWiredManager();
        $manager->coordinate('review_proposal', ['proposal_id' => 'proposal_1']);

        $result = $manager->coordinate('create_adaptation_plan', ['proposal_id' => 'proposal_1', 'plan' => ['rollback_plan' => 'revert']]);

        $this->assertSame('planned', $result['outcome']);
        $this->assertSame('adaptation_manager', $result['target_component']);
    }

    public function testExecuteAdaptationRoutesToAdaptationManager(): void
    {
        [, $adaptation, $manager] = $this->fullyWiredManager();
        $manager->coordinate('review_proposal', ['proposal_id' => 'proposal_1']);
        $created = $adaptation->createPlan('proposal_1', ['rollback_plan' => 'revert']);

        $result = $manager->coordinate('execute_adaptation', ['plan_id' => $created['plan_id'], 'context' => []]);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('adaptation_manager', $result['target_component']);
        $this->assertStringContainsString('Workflow Engine', (string) $result['error']);
    }

    public function testValidateAdaptationRoutesToAdaptationManager(): void
    {
        [, , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('validate_adaptation', ['plan_id' => 'plan_ghost', 'result' => 'passed']);

        $this->assertSame('adaptation_manager', $result['target_component']);
    }

    public function testUnknownOperationIsRejected(): void
    {
        [, , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('teleport', []);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('none', $result['target_component']);
    }

    public function testMissingRequiredFieldIsRejectedBeforeRoutingIsAttempted(): void
    {
        [, , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('record_experience', ['source' => 'agent:1']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('event_category', (string) $result['error']);
    }

    public function testMissingDependencyIsRejectedNotFatal(): void
    {
        $manager = new LearningManager();

        $result = $manager->coordinate('record_experience', ['source' => 'agent:1', 'event_category' => 'timeout']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('not configured', (string) $result['error']);
    }

    public function testCoordinationDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('learning.finished', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        [, , $manager] = $this->fullyWiredManager($events);
        $manager->coordinate('review_proposal', ['proposal_id' => 'proposal_1']);

        $this->assertCount(1, $captured);
        $this->assertSame('review_proposal', $captured[0]->getPayload()['operation']);
    }

    public function testMonitorHealthIsRejectedWithoutAConfiguredLearningMonitor(): void
    {
        $manager = new LearningManager();

        $result = $manager->coordinate('monitor_health', []);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('learning_monitor', $result['target_component']);
    }

    public function testMonitorHealthRoutesToTheRealLearningMonitor(): void
    {
        [$experiences, $adaptation, $manager] = $this->fullyWiredManager();
        $experiences->record('agent:1', 'timeout');

        $result = $manager->coordinate('monitor_health', []);

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertSame('learning_monitor', $result['target_component']);
        $this->assertArrayHasKey('experience_status_counts', $result['result']);
    }

    public function testMonitorStalledAdaptationsRoutesToTheRealLearningMonitor(): void
    {
        [, , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('monitor_stalled_adaptations', []);

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertSame([], $result['result']['stalled']);
    }

    public function testMonitorRepeatedFailuresRoutesToTheRealLearningMonitor(): void
    {
        [, , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('monitor_repeated_failures', []);

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertSame([], $result['result']['repeated_failures']);
    }

    public function testMonitorUnauthorizedAdaptationsRoutesToTheRealLearningMonitor(): void
    {
        [, , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('monitor_unauthorized_adaptations', []);

        $this->assertSame('analyzed', $result['outcome']);
        $this->assertSame([], $result['result']['unauthorized']);
    }
}
