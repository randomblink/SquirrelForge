<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Learning\SqliteAdaptationManager;
use SquirrelForge\Learning\SqliteExperienceStore;
use SquirrelForge\Learning\SqliteFeedbackCollector;
use SquirrelForge\Learning\SqliteLearningGovernance;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class SqliteExperienceStoreTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-experience-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    public function testValidExperienceIsRecorded(): void
    {
        $store = new SqliteExperienceStore($this->tempPath('main'));

        $result = $store->record('agent:reasoner-1', 'workflow_completion', ['confidence' => 0.8]);

        $this->assertTrue($result['found']);
        $this->assertNotNull($result['experience_id']);
        $this->assertSame('recorded', $store->get($result['experience_id'])['status']);
    }

    public function testMissingSourceOrCategoryIsRejected(): void
    {
        $store = new SqliteExperienceStore($this->tempPath('main'));

        $result = $store->record('', 'workflow_completion');

        $this->assertFalse($result['found']);
    }

    public function testOutOfRangeConfidenceIsRejected(): void
    {
        $store = new SqliteExperienceStore($this->tempPath('main'));

        $result = $store->record('agent:1', 'category', ['confidence' => 1.5]);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('Confidence', (string) $result['error']);
    }

    public function testFeedbackReferenceIsVerifiedAndMarkedForwarded(): void
    {
        $feedback = new SqliteFeedbackCollector($this->tempPath('feedback'));
        $submitted = $feedback->submit('user', ['rating' => 5]);
        $store = new SqliteExperienceStore($this->tempPath('main'), feedbackCollector: $feedback);

        $result = $store->record('agent:1', 'category', ['feedback_reference' => $submitted['feedback_id']]);

        $this->assertTrue($result['found']);
        $this->assertStringContainsString('marked forwarded', $result['notes'][0]);
        $this->assertSame('forwarded', $feedback->get($submitted['feedback_id'])['status']);
        $this->assertSame('experience_store', $feedback->get($submitted['feedback_id'])['forwarded_to']);
    }

    public function testFeedbackReferenceThatDoesNotResolveIsRejected(): void
    {
        $feedback = new SqliteFeedbackCollector($this->tempPath('feedback'));
        $store = new SqliteExperienceStore($this->tempPath('main'), feedbackCollector: $feedback);

        $result = $store->record('agent:1', 'category', ['feedback_reference' => 'feedback_ghost']);

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('does not resolve', (string) $result['error']);
    }

    public function testAlreadyForwardedFeedbackReferenceStillAllowsRecordingWithANote(): void
    {
        $feedback = new SqliteFeedbackCollector($this->tempPath('feedback'));
        $submitted = $feedback->submit('user', ['rating' => 5]);
        $feedback->markForwarded($submitted['feedback_id'], 'evaluation_engine');
        $store = new SqliteExperienceStore($this->tempPath('main'), feedbackCollector: $feedback);

        $result = $store->record('agent:1', 'category', ['feedback_reference' => $submitted['feedback_id']]);

        $this->assertTrue($result['found']);
        $this->assertStringContainsString('was not marked forwarded', $result['notes'][0]);
    }

    public function testFeedbackReferenceIsSkippedWithoutAFeedbackCollectorConfigured(): void
    {
        $store = new SqliteExperienceStore($this->tempPath('main'));

        $result = $store->record('agent:1', 'category', ['feedback_reference' => 'anything']);

        $this->assertTrue($result['found']);
        $this->assertSame([], $result['notes']);
    }

    public function testStorageReferenceIsVerifiedWhenDocumentStorageIsConfigured(): void
    {
        $documents = new SqliteDocumentStorage($this->tempPath('documents'));
        $stored = $documents->store('report', 'Experience Evidence', ['detail' => 'x']);
        $store = new SqliteExperienceStore($this->tempPath('main'), $documents);

        $resolves = $store->record('agent:1', 'category', ['storage_reference' => $stored['document_ref']]);
        $doesNotResolve = $store->record('agent:1', 'category', ['storage_reference' => 'document_ghost']);

        $this->assertTrue($resolves['found']);
        $this->assertFalse($doesNotResolve['found']);
    }

    public function testAttachEvaluationReferenceTransitionsStatus(): void
    {
        $store = new SqliteExperienceStore($this->tempPath('main'));
        $recorded = $store->record('agent:1', 'category');

        $result = $store->attachReference($recorded['experience_id'], 'evaluation', 'evaluation_ref_1');

        $this->assertTrue($result['found']);
        $fetched = $store->get($recorded['experience_id']);
        $this->assertSame('evaluation_ref_1', $fetched['evaluation_reference']);
        $this->assertSame('evaluated', $fetched['status']);
    }

    public function testAttachingANonEvaluationReferenceDoesNotChangeStatus(): void
    {
        $store = new SqliteExperienceStore($this->tempPath('main'));
        $recorded = $store->record('agent:1', 'category');

        $store->attachReference($recorded['experience_id'], 'pattern', 'pattern_ref_1');

        $fetched = $store->get($recorded['experience_id']);
        $this->assertSame('pattern_ref_1', $fetched['pattern_reference']);
        $this->assertSame('recorded', $fetched['status']);
    }

    public function testAttachReferenceRejectsUnknownType(): void
    {
        $store = new SqliteExperienceStore($this->tempPath('main'));
        $recorded = $store->record('agent:1', 'category');

        $result = $store->attachReference($recorded['experience_id'], 'telepathy', 'ref');

        $this->assertFalse($result['found']);
    }

    public function testAttachGovernanceReferenceRejectsAnUnresolvableProposalWhenGovernanceIsConfigured(): void
    {
        $governance = new SqliteLearningGovernance($this->tempPath('governance'));
        $store = new SqliteExperienceStore($this->tempPath('main'), governance: $governance);
        $recorded = $store->record('agent:1', 'timeout');

        $result = $store->attachReference($recorded['experience_id'], 'governance', 'proposal_never_reviewed');

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('Learning Governance', $result['error']);
    }

    public function testAttachGovernanceReferenceAcceptsARealProposalWhenGovernanceIsConfigured(): void
    {
        $governance = new SqliteLearningGovernance($this->tempPath('governance'));
        $governance->review('proposal_1');
        $store = new SqliteExperienceStore($this->tempPath('main'), governance: $governance);
        $recorded = $store->record('agent:1', 'timeout');

        $result = $store->attachReference($recorded['experience_id'], 'governance', 'proposal_1');

        $this->assertTrue($result['found']);
    }

    public function testAttachAdaptationReferenceRejectsAnUnresolvablePlanWhenAdaptationManagerIsConfigured(): void
    {
        $adaptationManager = new SqliteAdaptationManager($this->tempPath('adaptation'));
        $store = new SqliteExperienceStore($this->tempPath('main'), adaptationManager: $adaptationManager);
        $recorded = $store->record('agent:1', 'timeout');

        $result = $store->attachReference($recorded['experience_id'], 'adaptation', 'plan_unknown');

        $this->assertFalse($result['found']);
        $this->assertStringContainsString('Adaptation Manager', $result['error']);
    }

    public function testAttachAdaptationReferenceAcceptsARealPlanWhenAdaptationManagerIsConfigured(): void
    {
        $adaptationManager = new SqliteAdaptationManager($this->tempPath('adaptation'));
        $plan = $adaptationManager->createPlan('proposal_1', ['rollback_plan' => 'revert_config']);
        $store = new SqliteExperienceStore($this->tempPath('main'), adaptationManager: $adaptationManager);
        $recorded = $store->record('agent:1', 'timeout');

        $result = $store->attachReference($recorded['experience_id'], 'adaptation', $plan['plan_id']);

        $this->assertTrue($result['found']);
    }

    public function testAttachReferenceRejectsUnknownExperience(): void
    {
        $store = new SqliteExperienceStore($this->tempPath('main'));

        $result = $store->attachReference('experience_ghost', 'pattern', 'ref');

        $this->assertFalse($result['found']);
    }

    public function testArchiveTransitionsStatusAndRejectsRepeatArchiving(): void
    {
        $store = new SqliteExperienceStore($this->tempPath('main'));
        $recorded = $store->record('agent:1', 'category');

        $first = $store->archive($recorded['experience_id']);
        $second = $store->archive($recorded['experience_id']);

        $this->assertTrue($first['found']);
        $this->assertFalse($second['found']);
        $this->assertSame('archived', $store->get($recorded['experience_id'])['status']);
    }

    public function testGetReturnsNullForUnknownExperience(): void
    {
        $store = new SqliteExperienceStore($this->tempPath('main'));

        $this->assertNull($store->get('experience_ghost'));
    }

    public function testListFiltersBySourceCategoryAndStatus(): void
    {
        $store = new SqliteExperienceStore($this->tempPath('main'));
        $store->record('agent:1', 'workflow_completion');
        $archived = $store->record('agent:2', 'workflow_completion');
        $store->archive($archived['experience_id']);

        $this->assertCount(2, $store->list(['event_category' => 'workflow_completion']));
        $this->assertCount(1, $store->list(['source' => 'agent:1']));
        $this->assertCount(1, $store->list(['status' => 'archived']));
    }

    public function testRecordedExperienceDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('experience.recorded', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $store = new SqliteExperienceStore($this->tempPath('main'), events: $events);
        $store->record('agent:1', 'category');

        $this->assertCount(1, $captured);
    }
}
