<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Core;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Core\SystemOrchestrator;
use SquirrelForge\Engine\ContextManager;
use SquirrelForge\Engine\PromptCompiler;
use SquirrelForge\Knowledge\KnowledgeManager;
use SquirrelForge\Memory\EpisodicMemory;
use SquirrelForge\Memory\InMemoryStore;
use SquirrelForge\Reasoning\AIDriver;
use SquirrelForge\Reasoning\DecisionEngine;
use SquirrelForge\Reasoning\ExplanationEngine;
use SquirrelForge\Reasoning\ReflectionEngine;
use SquirrelForge\Reasoning\StrategyPlanner;
use SquirrelForge\Reasoning\TradeoffAnalyzer;
use SquirrelForge\Tests\Support\FakeLlmClient;

final class SystemOrchestratorTest extends TestCase
{
    private function option(string $name, float $weightedTotal): array
    {
        return ['option' => $name, 'matrix_score' => ['weighted_total' => $weightedTotal, 'disqualified' => false, 'disqualification_reason' => null]];
    }

    // --- think(): composes AIDriver ---

    public function testThinkWithoutAiDriverIsNotConfigured(): void
    {
        $orchestrator = new SystemOrchestrator();

        $result = $orchestrator->think();

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testThinkRoutesToTheRealAiDriver(): void
    {
        $context = new ContextManager();
        $context->load('goal', 'user_request', 'Ship the login feature.');
        $llm = new FakeLlmClient('The login feature is ready.');
        $orchestrator = new SystemOrchestrator(new AIDriver(new PromptCompiler($context), $llm));

        $result = $orchestrator->think();

        $this->assertSame('completed', $result['outcome']);
        $this->assertSame('The login feature is ready.', $result['result']);
    }

    // --- reasonAndExplain(): real sequential readiness gating ---

    public function testReasonAndExplainWithoutDecisionEngineIsNotConfigured(): void
    {
        $orchestrator = new SystemOrchestrator();

        $result = $orchestrator->reasonAndExplain([], [], [], []);

        $this->assertSame('not_configured', $result['outcome']);
        $this->assertNull($result['decision']);
    }

    public function testReasonAndExplainStopsAtAFailedDecision(): void
    {
        $orchestrator = new SystemOrchestrator(decisionEngine: new DecisionEngine(tradeoffAnalyzer: new TradeoffAnalyzer()));

        $result = $orchestrator->reasonAndExplain([], [], [], []);

        $this->assertSame('decision_failed', $result['outcome']);
        $this->assertNull($result['decision']);
        $this->assertNull($result['strategy']);
    }

    public function testReasonAndExplainReturnsTheRealDecisionWhenStrategyPlannerIsNotConfigured(): void
    {
        $orchestrator = new SystemOrchestrator(
            decisionEngine: new DecisionEngine(tradeoffAnalyzer: new TradeoffAnalyzer())
        );

        $result = $orchestrator->reasonAndExplain(
            [$this->option('option_a', 3.0), $this->option('option_b', 4.5)],
            [],
            [],
            []
        );

        $this->assertSame('not_configured', $result['outcome']);
        $this->assertNotNull($result['decision']);
        $this->assertSame('option_b', $result['decision']['selected_option']);
        $this->assertNull($result['strategy']);
    }

    public function testReasonAndExplainStopsAtAFailedStrategy(): void
    {
        $orchestrator = new SystemOrchestrator(
            decisionEngine: new DecisionEngine(tradeoffAnalyzer: new TradeoffAnalyzer()),
            strategyPlanner: new StrategyPlanner()
        );

        // No phases declared -- StrategyPlanner's own real "invalid_strategy" rejection.
        $result = $orchestrator->reasonAndExplain(
            [$this->option('option_a', 3.0)],
            [],
            [],
            []
        );

        $this->assertSame('strategy_failed', $result['outcome']);
        $this->assertNotNull($result['decision']);
        $this->assertNull($result['strategy']);
    }

    public function testReasonAndExplainReturnsDecisionAndStrategyWhenExplanationEngineIsNotConfigured(): void
    {
        $orchestrator = new SystemOrchestrator(
            decisionEngine: new DecisionEngine(tradeoffAnalyzer: new TradeoffAnalyzer()),
            strategyPlanner: new StrategyPlanner()
        );

        $result = $orchestrator->reasonAndExplain(
            [$this->option('option_a', 3.0)],
            [],
            ['expected_output' => 'A working discount code field'],
            [['id' => 'phase_1', 'description' => 'Build the field', 'validation_note' => 'Manual QA since no automated coverage exists yet.']]
        );

        $this->assertSame('not_configured', $result['outcome']);
        $this->assertNotNull($result['decision']);
        $this->assertNotNull($result['strategy']);
        $this->assertNull($result['explanation']);
    }

    public function testReasonAndExplainFullyCoordinatesAllThreeRealStages(): void
    {
        $orchestrator = new SystemOrchestrator(
            decisionEngine: new DecisionEngine(tradeoffAnalyzer: new TradeoffAnalyzer(), knowledgeManager: new KnowledgeManager()),
            strategyPlanner: new StrategyPlanner(),
            explanationEngine: new ExplanationEngine()
        );

        $result = $orchestrator->reasonAndExplain(
            [$this->option('option_a', 3.0), $this->option('option_b', 4.5)],
            ['knowledge_query' => 'checkout discount patterns'],
            ['expected_output' => 'A working discount code field'],
            [['id' => 'phase_1', 'description' => 'Build the field', 'validation_note' => 'Manual QA since no automated coverage exists yet.']]
        );

        $this->assertSame('coordinated', $result['outcome']);
        $this->assertSame('option_b', $result['decision']['selected_option']);
        $this->assertSame($result['decision']['decision_id'], $result['strategy']['decision_reference']);
        $this->assertSame($result['decision']['decision_id'], $result['explanation']['decision_reference']);
    }

    // --- reflectOnCompletedTask(): composes ReflectionEngine ---

    public function testReflectOnCompletedTaskWithoutReflectionEngineIsNotConfigured(): void
    {
        $orchestrator = new SystemOrchestrator();

        $result = $orchestrator->reflectOnCompletedTask('task_1');

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testReflectOnCompletedTaskRoutesToTheRealReflectionEngine(): void
    {
        $episodicMemory = new EpisodicMemory(new InMemoryStore());
        $episodicMemory->recordCompletedTask([
            'task_reference' => 'task_1',
            'goal_reference' => 'goal_1',
            'outcome_summary' => 'Discount field shipped',
            'validation_result_reference' => 'ACCEPTED',
        ]);
        $orchestrator = new SystemOrchestrator(reflectionEngine: new ReflectionEngine($episodicMemory));

        $result = $orchestrator->reflectOnCompletedTask('task_1');

        $this->assertSame('reflected', $result['outcome']);
        $this->assertTrue($result['reflection']['goal_achieved']);
    }
}
