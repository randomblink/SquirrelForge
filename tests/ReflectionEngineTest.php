<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Memory\EpisodicMemory;
use SquirrelForge\Memory\InMemoryStore;
use SquirrelForge\Reasoning\ReflectionEngine;

final class ReflectionEngineTest extends TestCase
{
    private function episodicRecord(array $overrides = []): array
    {
        return array_merge([
            'task_reference' => 'task_1',
            'goal' => ['primary_goal' => 'Add checkout discount field', 'acceptance_criteria' => ['Field renders', 'Code validates']],
            'result' => 'Discount field shipped',
            'validation_reference' => ['decision' => 'ACCEPTED'],
            'acceptance_criteria_met' => ['Field renders' => true, 'Code validates' => true],
        ], $overrides);
    }

    public function testFullSuccessProducesAnAchievedGoalWithALearningSignal(): void
    {
        $engine = new ReflectionEngine();

        $result = $engine->reflect($this->episodicRecord());

        $this->assertTrue($result['reflection']['goal_achieved']);
        $this->assertSame([], $result['reflection']['issues']);
        $this->assertNotEmpty($result['reflection']['successes']);
        $this->assertNotEmpty($result['reflection']['learning_signals']);
    }

    public function testARejectedValidationDecisionMeansTheGoalWasNotAchieved(): void
    {
        $engine = new ReflectionEngine();

        $result = $engine->reflect($this->episodicRecord(['validation_reference' => ['decision' => 'REJECTED']]));

        $this->assertFalse($result['reflection']['goal_achieved']);
        $this->assertStringContainsString('REJECTED', $result['reflection']['issues'][0]);
        $this->assertSame([], $result['reflection']['learning_signals']);
    }

    public function testAcceptedWithLimitationsStillCountsAsGoalAchievedButNotesTheLimitation(): void
    {
        $engine = new ReflectionEngine();

        $result = $engine->reflect($this->episodicRecord(['validation_reference' => ['decision' => 'ACCEPTED_WITH_LIMITATIONS']]));

        $this->assertTrue($result['reflection']['goal_achieved']);
        $this->assertContains('Validation passed only with limitations.', $result['reflection']['issues']);
    }

    public function testAnUnmetAcceptanceCriterionIsANamedIssueAndBlocksGoalAchievement(): void
    {
        $engine = new ReflectionEngine();

        $result = $engine->reflect($this->episodicRecord(['acceptance_criteria_met' => ['Field renders' => true, 'Code validates' => false]]));

        $this->assertFalse($result['reflection']['goal_achieved']);
        $this->assertContains('Acceptance criterion not met: "Code validates".', $result['reflection']['issues']);
    }

    public function testAnIssueRecurringAtTheThresholdBecomesARepeatedIssueWithAnImprovementCandidate(): void
    {
        $engine = new ReflectionEngine();
        $record = $this->episodicRecord(['validation_reference' => ['decision' => 'REJECTED']]);
        $priorIssues = [['description' => 'Validation did not pass (decision: "REJECTED").']];

        $result = $engine->reflect($record, $priorIssues, 2);

        $this->assertContains('Validation did not pass (decision: "REJECTED").', $result['reflection']['repeated_issues']);
        $this->assertNotEmpty($result['reflection']['improvement_candidates']);
    }

    public function testAnIssueBelowTheRecurrenceThresholdIsNotFlaggedAsRepeated(): void
    {
        $engine = new ReflectionEngine();
        $record = $this->episodicRecord(['validation_reference' => ['decision' => 'REJECTED']]);

        $result = $engine->reflect($record, [], 2);

        $this->assertSame([], $result['reflection']['repeated_issues']);
        $this->assertSame([], $result['reflection']['improvement_candidates']);
    }

    public function testMissingGoalAndAcceptanceCriteriaDefaultGracefully(): void
    {
        $engine = new ReflectionEngine();

        $result = $engine->reflect(['task_reference' => 'task_1', 'result' => 'done', 'validation_reference' => ['decision' => 'ACCEPTED']]);

        $this->assertTrue($result['reflection']['goal_achieved']);
        $this->assertNull($result['reflection']['goal']);
    }

    public function testReflectOnTaskWithoutEpisodicMemoryIsNotConfigured(): void
    {
        $engine = new ReflectionEngine();

        $result = $engine->reflectOnTask('task_1');

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testReflectOnTaskWithAnUnknownTaskReferenceIsNotFound(): void
    {
        $episodicMemory = new EpisodicMemory(new InMemoryStore());
        $engine = new ReflectionEngine($episodicMemory);

        $result = $engine->reflectOnTask('task_ghost');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testReflectOnTaskGenuinelyFetchesTheRecordFromEpisodicMemory(): void
    {
        $episodicMemory = new EpisodicMemory(new InMemoryStore());
        $recorded = $episodicMemory->recordCompletedTask([
            'task_reference' => 'task_1',
            'goal_reference' => 'goal_1',
            'outcome_summary' => 'Checkout discount field shipped.',
            'validation_result_reference' => 'ACCEPTED',
        ]);
        $engine = new ReflectionEngine($episodicMemory);

        $result = $engine->reflectOnTask('task_1');

        $this->assertSame('reflected', $result['outcome']);
        $this->assertTrue($result['reflection']['goal_achieved']);
        $this->assertSame('task_1', $result['reflection']['task_reference']);
        $this->assertSame('goal_1', $result['reflection']['goal']);
        $this->assertSame('Checkout discount field shipped.', $result['reflection']['result']);
        $this->assertNotNull($recorded['memory_id']);
    }

    public function testReflectOnTaskUsesCallerSuppliedAcceptanceCriteriaAgainstTheFetchedRecord(): void
    {
        $episodicMemory = new EpisodicMemory(new InMemoryStore());
        $episodicMemory->recordCompletedTask([
            'task_reference' => 'task_1',
            'goal_reference' => 'goal_1',
            'outcome_summary' => 'Checkout discount field shipped.',
            'validation_result_reference' => 'ACCEPTED',
        ]);
        $engine = new ReflectionEngine($episodicMemory);

        $result = $engine->reflectOnTask('task_1', [
            'acceptance_criteria' => ['Field renders', 'Code validates'],
            'acceptance_criteria_met' => ['Field renders' => true, 'Code validates' => false],
        ]);

        $this->assertFalse($result['reflection']['goal_achieved']);
        $this->assertContains('Acceptance criterion not met: "Code validates".', $result['reflection']['issues']);
    }
}
