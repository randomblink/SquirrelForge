<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\GoalPlanner;
use SquirrelForge\Engine\ProjectLoader;

final class GoalPlannerTest extends TestCase
{
    /** @var array<int, string> */
    private array $tempDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirectories as $directory) {
            @unlink($directory . '/composer.json');
            @rmdir($directory);
        }
    }

    private function makeProjectDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/sfgp-fixture-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $this->tempDirectories[] = $directory;

        return $directory;
    }

    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'primary_goal' => 'Add a checkout discount feature',
            'expected_output' => 'A working discount code field at checkout',
            'request_text' => 'Add a discount code field to the checkout flow',
        ], $overrides);
    }

    public function testClassifyRequestTypeDetectsDestructiveKeywords(): void
    {
        $planner = new GoalPlanner();

        $this->assertSame('destructive', $planner->classifyRequestType('Please delete the old logs table'));
    }

    public function testClassifyRequestTypeDetectsReadOnlyKeywords(): void
    {
        $planner = new GoalPlanner();

        $this->assertSame('read_only', $planner->classifyRequestType('Show me the current config'));
    }

    public function testClassifyRequestTypeDefaultsToImplementation(): void
    {
        $planner = new GoalPlanner();

        $this->assertSame('implementation', $planner->classifyRequestType('Add a new feature to the checkout flow'));
    }

    public function testEstimateInitialRiskMapsDestructiveToCritical(): void
    {
        $planner = new GoalPlanner();

        $this->assertSame('critical', $planner->estimateInitialRisk('destructive'));
    }

    public function testEstimateInitialRiskDefaultsToModerateForAnUnknownType(): void
    {
        $planner = new GoalPlanner();

        $this->assertSame('moderate', $planner->estimateInitialRisk('made_up_type'));
    }

    public function testEvaluateClarificationNeedWithNoMissingInformationIsFalse(): void
    {
        $planner = new GoalPlanner();

        $result = $planner->evaluateClarificationNeed([]);

        $this->assertFalse($result['requires_clarification']);
    }

    public function testEvaluateClarificationNeedTriggersOnANamedImpactCategory(): void
    {
        $planner = new GoalPlanner();

        $result = $planner->evaluateClarificationNeed([
            ['item' => 'Which environment?', 'impact_category' => 'production_impact'],
        ]);

        $this->assertTrue($result['requires_clarification']);
        $this->assertCount(1, $result['triggering_items']);
    }

    public function testEvaluateClarificationNeedIgnoresAnUnrecognizedImpactCategory(): void
    {
        $planner = new GoalPlanner();

        $result = $planner->evaluateClarificationNeed([
            ['item' => 'Minor styling preference?', 'impact_category' => 'trivial'],
        ]);

        $this->assertFalse($result['requires_clarification']);
    }

    public function testEvaluateScopeExpansionsAcceptsAnAllowedReason(): void
    {
        $planner = new GoalPlanner();

        $result = $planner->evaluateScopeExpansions([
            ['description' => 'Also fix the related validation bug', 'reason' => 'safety'],
        ]);

        $this->assertTrue($result['valid']);
    }

    public function testEvaluateScopeExpansionsRejectsAnUnjustifiedReason(): void
    {
        $planner = new GoalPlanner();

        $result = $planner->evaluateScopeExpansions([
            ['description' => 'Rewrote unrelated module', 'reason' => 'because I felt like it'],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertCount(1, $result['unjustified_expansions']);
    }

    public function testPlanGoalRejectsAMissingRequiredField(): void
    {
        $planner = new GoalPlanner();
        $input = $this->validInput();
        unset($input['expected_output']);

        $result = $planner->planGoal($input);

        $this->assertSame('invalid_request', $result['outcome']);
    }

    public function testPlanGoalWithNoIssuesIsPlanned(): void
    {
        $planner = new GoalPlanner();

        $result = $planner->planGoal($this->validInput());

        $this->assertSame('planned', $result['outcome']);
        $this->assertSame('planned', $result['goal']['status']);
        $this->assertSame('implementation', $result['goal']['request_type']);
        $this->assertSame('moderate', $result['goal']['initial_risk']);
    }

    public function testPlanGoalWithAClarificationTriggerIsClarificationRequired(): void
    {
        $planner = new GoalPlanner();

        $result = $planner->planGoal($this->validInput([
            'missing_information' => [['item' => 'Which project?', 'impact_category' => 'target_project']],
        ]));

        $this->assertSame('clarification-required', $result['outcome']);
    }

    public function testPlanGoalWithAnUnjustifiedScopeExpansionIsBlocked(): void
    {
        $planner = new GoalPlanner();

        $result = $planner->planGoal($this->validInput([
            'scope_expansions' => [['description' => 'Refactor everything', 'reason' => null]],
        ]));

        $this->assertSame('blocked', $result['outcome']);
    }

    public function testPlanGoalClarificationTakesPrecedenceOverAnUnjustifiedScopeExpansion(): void
    {
        $planner = new GoalPlanner();

        $result = $planner->planGoal($this->validInput([
            'missing_information' => [['item' => 'Which project?', 'impact_category' => 'target_project']],
            'scope_expansions' => [['description' => 'Refactor everything', 'reason' => null]],
        ]));

        $this->assertSame('clarification-required', $result['outcome']);
    }

    public function testPlanGoalWithoutProjectLoaderLeavesActiveDomainsEmpty(): void
    {
        $planner = new GoalPlanner();

        $result = $planner->planGoal($this->validInput(['project_root' => sys_get_temp_dir()]));

        $this->assertSame([], $result['goal']['active_domains']);
    }

    public function testPlanGoalComposesProjectLoaderForRealActiveDomains(): void
    {
        $directory = $this->makeProjectDirectory();
        file_put_contents($directory . '/composer.json', '{}');
        $planner = new GoalPlanner(new ProjectLoader());

        $result = $planner->planGoal($this->validInput(['project_root' => $directory]));

        $this->assertContains('php', $result['goal']['active_domains']);
    }
}
