<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\AiDriver;

use PHPUnit\Framework\TestCase;
use SquirrelForge\AiDriver\SqliteResultReviewer;
use SquirrelForge\Engine\EngineValidation;
use SquirrelForge\Execution\SqliteResultCollector;

final class SqliteResultReviewerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-result-reviewer-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function resultCollector(): SqliteResultCollector
    {
        return new SqliteResultCollector($this->tempPath('collector'));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestFor(array $overrides = []): array
    {
        return array_replace([
            'goal_id' => 'goal_1',
            'action_id' => 'action_1',
            'workflow_step_ref' => 'step_1',
            'expected_outcome' => 'the file is created',
            'matches_expected_outcome' => true,
            'validation_items' => [],
        ], $overrides);
    }

    // --- shape validation ---

    public function testMissingGoalIdIsInvalid(): void
    {
        $reviewer = new SqliteResultReviewer($this->tempPath('db'));

        $result = $reviewer->review($this->requestFor(['goal_id' => '']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMissingExpectedOutcomeIsInvalid(): void
    {
        $reviewer = new SqliteResultReviewer($this->tempPath('db'));

        $result = $reviewer->review($this->requestFor(['expected_outcome' => null]));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMissingMatchesExpectedOutcomeIsInvalidNeverAssumedTrue(): void
    {
        $reviewer = new SqliteResultReviewer($this->tempPath('db'));
        $request = $this->requestFor();
        unset($request['matches_expected_outcome']);

        $result = $reviewer->review($request);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('must never fabricate', $result['error']);
    }

    public function testNonBooleanMatchesExpectedOutcomeIsInvalid(): void
    {
        $reviewer = new SqliteResultReviewer($this->tempPath('db'));

        $result = $reviewer->review($this->requestFor(['matches_expected_outcome' => 'yes']));

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- unconfigured dependencies are a real, recorded Blocked outcome ---

    public function testUnconfiguredResultCollectorIsBlockedAndRecorded(): void
    {
        $reviewer = new SqliteResultReviewer($this->tempPath('db'), null, new EngineValidation());

        $result = $reviewer->review($this->requestFor());

        $this->assertSame('reviewed', $result['outcome']);
        $this->assertSame('Blocked', $result['goal_status']);
        $this->assertSame('Escalate the issue', $result['recommended_next_step']);
        $this->assertNotNull($reviewer->get($result['result_review_id']));
    }

    public function testUnconfiguredValidationIsBlocked(): void
    {
        $reviewer = new SqliteResultReviewer($this->tempPath('db'), $this->resultCollector());

        $result = $reviewer->review($this->requestFor());

        $this->assertSame('Blocked', $result['goal_status']);
    }

    // --- EngineValidation's real 7-decision vocabulary -> Goal Status ---

    public function testAcceptedWithNoMissingOutputsAndMatchingOutcomeIsCompleted(): void
    {
        $collector = $this->resultCollector();
        $reviewer = new SqliteResultReviewer($this->tempPath('db'), $collector, new EngineValidation());

        $result = $reviewer->review($this->requestFor());

        $this->assertSame('Completed', $result['goal_status']);
        $this->assertSame('Mark the goal complete', $result['recommended_next_step']);
    }

    public function testAcceptedWithMissingOutputsIsPartiallyCompleted(): void
    {
        $collector = $this->resultCollector();
        $collector->registerExpected('step_1', ['output_a', 'output_b']);
        $collector->collect(['execution_ref' => 'exec_1', 'workflow_step_ref' => 'step_1', 'expected_output_ref' => 'output_a']);
        // output_b never collected -> a real missing_reference.

        $reviewer = new SqliteResultReviewer($this->tempPath('db'), $collector, new EngineValidation());

        $result = $reviewer->review($this->requestFor());

        $this->assertSame('Partially completed', $result['goal_status']);
        $this->assertSame('Retry the current action', $result['recommended_next_step']);
    }

    public function testPartiallyCompletedRecommendsAlternativeToolWhenDeclaredAvailable(): void
    {
        $collector = $this->resultCollector();
        $collector->registerExpected('step_1', ['output_a']);

        $reviewer = new SqliteResultReviewer($this->tempPath('db'), $collector, new EngineValidation());

        $result = $reviewer->review($this->requestFor(['alternative_tool_available' => true]));

        $this->assertSame('Partially completed', $result['goal_status']);
        $this->assertSame('Retry with a different tool', $result['recommended_next_step']);
    }

    public function testAcceptedWithNoMissingOutputsButMismatchedOutcomeRequiresReplanning(): void
    {
        $collector = $this->resultCollector();
        $reviewer = new SqliteResultReviewer($this->tempPath('db'), $collector, new EngineValidation());

        $result = $reviewer->review($this->requestFor(['matches_expected_outcome' => false]));

        $this->assertSame('Requires replanning', $result['goal_status']);
        $this->assertSame('Revise the plan', $result['recommended_next_step']);
    }

    public function testMismatchedOutcomeRequestsClarificationWhenDeclared(): void
    {
        $collector = $this->resultCollector();
        $reviewer = new SqliteResultReviewer($this->tempPath('db'), $collector, new EngineValidation());

        $result = $reviewer->review($this->requestFor(['matches_expected_outcome' => false, 'clarification_needed' => true]));

        $this->assertSame('Requires replanning', $result['goal_status']);
        $this->assertSame('Request clarification', $result['recommended_next_step']);
    }

    public function testRejectedValidationIsFailedAndEscalated(): void
    {
        $collector = $this->resultCollector();
        $reviewer = new SqliteResultReviewer($this->tempPath('db'), $collector, new EngineValidation());

        $result = $reviewer->review($this->requestFor([
            'validation_items' => [['item_id' => 'i1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'FAILED', 'waivable' => false]],
        ]));

        $this->assertSame('Failed', $result['goal_status']);
        $this->assertSame('Escalate the issue', $result['recommended_next_step']);
    }

    public function testBlockedValidationIsBlockedAndEscalated(): void
    {
        $collector = $this->resultCollector();
        $reviewer = new SqliteResultReviewer($this->tempPath('db'), $collector, new EngineValidation());

        $result = $reviewer->review($this->requestFor([
            'validation_items' => [['item_id' => 'i1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'UNAVAILABLE', 'waivable' => false]],
        ]));

        $this->assertSame('Blocked', $result['goal_status']);
        $this->assertSame('Escalate the issue', $result['recommended_next_step']);
    }

    public function testRepairRequiredValidationIsRequiresRetry(): void
    {
        $collector = $this->resultCollector();
        $reviewer = new SqliteResultReviewer($this->tempPath('db'), $collector, new EngineValidation());

        $result = $reviewer->review($this->requestFor([
            'validation_items' => [['item_id' => 'i1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'FAILED', 'waivable' => true, 'repairable' => true]],
            'validation_options' => ['remaining_attempts' => 2],
        ]));

        $this->assertSame('Requires retry', $result['goal_status']);
        $this->assertSame('Retry the current action', $result['recommended_next_step']);
    }

    public function testRecoveryRequiredValidationIsBlocked(): void
    {
        $collector = $this->resultCollector();
        $reviewer = new SqliteResultReviewer($this->tempPath('db'), $collector, new EngineValidation());

        $result = $reviewer->review($this->requestFor([
            'validation_options' => ['recovery_required' => true],
        ]));

        $this->assertSame('Blocked', $result['goal_status']);
        $this->assertSame('Escalate the issue', $result['recommended_next_step']);
    }

    public function testClarificationRequiredValidationRequiresReplanningAndRequestsClarification(): void
    {
        $collector = $this->resultCollector();
        $reviewer = new SqliteResultReviewer($this->tempPath('db'), $collector, new EngineValidation());

        $result = $reviewer->review($this->requestFor([
            'validation_options' => ['clarification_needed' => true],
        ]));

        $this->assertSame('Requires replanning', $result['goal_status']);
        $this->assertSame('Request clarification', $result['recommended_next_step']);
    }

    public function testAcceptedWithLimitationsAndMismatchRequiresReplanning(): void
    {
        $collector = $this->resultCollector();
        $reviewer = new SqliteResultReviewer($this->tempPath('db'), $collector, new EngineValidation());

        $result = $reviewer->review($this->requestFor([
            'matches_expected_outcome' => false,
            'validation_items' => [['item_id' => 'i1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'WAIVED', 'waivable' => true]],
        ]));

        $this->assertSame('Requires replanning', $result['goal_status']);
    }

    // --- recorded evidence ---

    public function testRecordedReviewCarriesTheAssembledResultSetAndValidationDecision(): void
    {
        $collector = $this->resultCollector();
        $collector->collect(['execution_ref' => 'exec_1', 'workflow_step_ref' => 'step_1', 'subject_ref' => 'file.php']);
        $reviewer = new SqliteResultReviewer($this->tempPath('db'), $collector, new EngineValidation());

        $result = $reviewer->review($this->requestFor());
        $record = $reviewer->get($result['result_review_id']);

        $this->assertSame('ACCEPTED', $record['actual_outcome']['validation_decision']);
        $this->assertCount(1, $record['actual_outcome']['result_set']['included_result_references']);
    }

    // --- get() / history() ---

    public function testGetUnknownReviewReturnsNull(): void
    {
        $reviewer = new SqliteResultReviewer($this->tempPath('db'));

        $this->assertNull($reviewer->get('ghost'));
    }

    public function testHistoryPreservesEveryReviewForAGoal(): void
    {
        $collector = $this->resultCollector();
        $reviewer = new SqliteResultReviewer($this->tempPath('db'), $collector, new EngineValidation());

        $reviewer->review($this->requestFor(['action_id' => 'action_1']));
        $reviewer->review($this->requestFor(['action_id' => 'action_2']));
        $reviewer->review($this->requestFor(['goal_id' => 'goal_other']));

        $history = $reviewer->history('goal_1');

        $this->assertCount(2, $history);
        $this->assertSame('action_1', $history[0]['action_id']);
        $this->assertSame('action_2', $history[1]['action_id']);
    }
}
