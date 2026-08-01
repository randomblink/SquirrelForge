<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Reasoning\StrategyPlanner;

final class StrategyPlannerTest extends TestCase
{
    private function decisionResult(array $overrides = []): array
    {
        return [
            'outcome' => 'decided',
            'decision' => array_merge([
                'decision_id' => 'decision_abc',
                'selected_option' => 'Add a discount code field to checkout',
                'unresolved_limitations' => [],
            ], $overrides),
        ];
    }

    public function testPlanStrategyRequiresADecidedDecisionRecord(): void
    {
        $planner = new StrategyPlanner();

        $result = $planner->planStrategy(['outcome' => 'no_eligible_option'], [], []);

        $this->assertSame('no_decision', $result['outcome']);
    }

    public function testPlanStrategyRequiresAtLeastOnePhase(): void
    {
        $planner = new StrategyPlanner();

        $result = $planner->planStrategy($this->decisionResult(), [], []);

        $this->assertSame('invalid_strategy', $result['outcome']);
    }

    public function testPlanStrategyRejectsADuplicatePhaseId(): void
    {
        $planner = new StrategyPlanner();

        $result = $planner->planStrategy($this->decisionResult(), [], [
            ['id' => 'phase_1', 'description' => 'Build'],
            ['id' => 'phase_1', 'description' => 'Build again'],
        ]);

        $this->assertSame('invalid_strategy', $result['outcome']);
    }

    public function testPlanStrategyRejectsAMilestoneReferencingAnUnknownPhase(): void
    {
        $planner = new StrategyPlanner();

        $result = $planner->planStrategy(
            $this->decisionResult(),
            [],
            [['id' => 'phase_1', 'description' => 'Build']],
            [['id' => 'milestone_1', 'phase_id' => 'phase_ghost', 'description' => 'Ship it']]
        );

        $this->assertSame('invalid_strategy', $result['outcome']);
    }

    public function testPlanStrategyRequiresAValidationNoteWhenTheDecisionCarriesUnresolvedLimitations(): void
    {
        $planner = new StrategyPlanner();

        $result = $planner->planStrategy(
            $this->decisionResult(['unresolved_limitations' => ['No historical context was supplied.']]),
            [],
            [['id' => 'phase_1', 'description' => 'Build']]
        );

        $this->assertSame('validation_required', $result['outcome']);
    }

    public function testPlanStrategySucceedsWhenAPhaseNotesValidationForCarriedLimitations(): void
    {
        $planner = new StrategyPlanner();

        $result = $planner->planStrategy(
            $this->decisionResult(['unresolved_limitations' => ['No historical context was supplied.']]),
            ['expected_output' => 'A working discount code field'],
            [['id' => 'phase_1', 'description' => 'Build', 'validation_note' => 'Manually verify against prior checkout regressions since no historical context was available.']]
        );

        $this->assertSame('planned', $result['outcome']);
        $this->assertCount(1, $result['strategy']['validation_notes']);
        $this->assertSame(['No historical context was supplied.'], $result['strategy']['carried_limitations']);
    }

    public function testPlanStrategySucceedsWithoutAValidationNoteWhenThereAreNoUnresolvedLimitations(): void
    {
        $planner = new StrategyPlanner();

        $result = $planner->planStrategy(
            $this->decisionResult(),
            ['expected_output' => 'A working discount code field'],
            [['id' => 'phase_1', 'description' => 'Build']]
        );

        $this->assertSame('planned', $result['outcome']);
    }

    public function testPlanStrategyDerivesTheObjectiveAndExpectedOutcomeFromRealUpstreamSources(): void
    {
        $planner = new StrategyPlanner();

        $result = $planner->planStrategy(
            $this->decisionResult(),
            ['expected_output' => 'A working discount code field'],
            [['id' => 'phase_1', 'description' => 'Build']]
        );

        $this->assertSame('Implement: Add a discount code field to checkout', $result['strategy']['objective']);
        $this->assertSame('A working discount code field', $result['strategy']['expected_outcome']);
        $this->assertSame('decision_abc', $result['strategy']['decision_reference']);
    }
}
