<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
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
}
