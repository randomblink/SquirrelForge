<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Knowledge\KnowledgeManager;
use SquirrelForge\Reasoning\DecisionEngine;
use SquirrelForge\Reasoning\ExplanationEngine;
use SquirrelForge\Reasoning\StrategyPlanner;
use SquirrelForge\Reasoning\TradeoffAnalyzer;

final class ExplanationEngineTest extends TestCase
{
    private function option(string $name, float $weightedTotal): array
    {
        return ['option' => $name, 'matrix_score' => ['weighted_total' => $weightedTotal, 'disqualified' => false, 'disqualification_reason' => null]];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} decisionResult, strategyResult */
    private function realDecisionAndStrategy(): array
    {
        $decisionEngine = new DecisionEngine(tradeoffAnalyzer: new TradeoffAnalyzer(), knowledgeManager: new KnowledgeManager());
        $decisionResult = $decisionEngine->decide(
            [$this->option('option_a', 3.0), $this->option('option_b', 4.5)],
            ['knowledge_query' => 'checkout discount patterns']
        );

        $strategyPlanner = new StrategyPlanner();
        $strategyResult = $strategyPlanner->planStrategy(
            $decisionResult,
            ['expected_output' => 'A working discount code field'],
            [['id' => 'phase_1', 'description' => 'Build the field', 'validation_note' => 'Manual QA since no automated coverage exists yet.']]
        );

        return [$decisionResult, $strategyResult];
    }

    public function testExplainRequiresADecidedDecisionRecord(): void
    {
        $engine = new ExplanationEngine();

        $result = $engine->explain(['outcome' => 'no_eligible_option'], ['outcome' => 'planned', 'strategy' => []]);

        $this->assertSame('no_decision', $result['outcome']);
    }

    public function testExplainRequiresAPlannedStrategyRecord(): void
    {
        [$decisionResult] = $this->realDecisionAndStrategy();
        $engine = new ExplanationEngine();

        $result = $engine->explain($decisionResult, ['outcome' => 'invalid_strategy']);

        $this->assertSame('no_strategy', $result['outcome']);
    }

    public function testExplainRejectsAMismatchedStrategyReference(): void
    {
        [$decisionResult, $strategyResult] = $this->realDecisionAndStrategy();
        $strategyResult['strategy']['decision_reference'] = 'decision_someone_else';
        $engine = new ExplanationEngine();

        $result = $engine->explain($decisionResult, $strategyResult);

        $this->assertSame('mismatched_records', $result['outcome']);
    }

    public function testExplainBuildsARealExplanationFromDecisionAndStrategyRecords(): void
    {
        [$decisionResult, $strategyResult] = $this->realDecisionAndStrategy();
        $engine = new ExplanationEngine();

        $result = $engine->explain($decisionResult, $strategyResult, ['primary_goal' => 'Add a checkout discount feature']);

        $this->assertSame('explained', $result['outcome']);
        $explanation = $result['explanation'];
        $this->assertSame($decisionResult['decision']['decision_id'], $explanation['decision_reference']);
        $this->assertSame($strategyResult['strategy']['strategy_id'], $explanation['strategy_reference']);
        $this->assertSame('Add a checkout discount feature', $explanation['goal']);
        $this->assertSame('A working discount code field', $explanation['expected_outcome']);
        $this->assertSame($decisionResult['decision']['rationale'], $explanation['reasoning']);
    }

    public function testExplainDescribesWhyRejectedAlternativesWereNotSelected(): void
    {
        [$decisionResult, $strategyResult] = $this->realDecisionAndStrategy();
        $engine = new ExplanationEngine();

        $result = $engine->explain($decisionResult, $strategyResult);

        $this->assertNotEmpty($result['explanation']['tradeoffs']['rejected_alternatives']);
        $this->assertStringContainsString('option_a', $result['explanation']['tradeoffs']['rejected_alternatives'][0]);
        $this->assertStringContainsString('not selected', $result['explanation']['tradeoffs']['rejected_alternatives'][0]);
    }

    public function testExplainSummarizesAMissingRiskAssessmentReferenceHonestly(): void
    {
        [$decisionResult, $strategyResult] = $this->realDecisionAndStrategy();
        $engine = new ExplanationEngine();

        $result = $engine->explain($decisionResult, $strategyResult);

        $this->assertSame('No risk assessment reference was recorded.', $result['explanation']['risk_summary']);
    }

    public function testExplainSummarizesConsultedKnowledgeThatReturnedNoUsableResult(): void
    {
        [$decisionResult, $strategyResult] = $this->realDecisionAndStrategy();
        $engine = new ExplanationEngine();

        $result = $engine->explain($decisionResult, $strategyResult);

        $this->assertStringContainsString('no usable result', $result['explanation']['knowledge_used']);
    }

    public function testExplainSummarizesNoKnowledgeConsultedWhenNoneWasRequested(): void
    {
        $decisionEngine = new DecisionEngine(tradeoffAnalyzer: new TradeoffAnalyzer());
        $decisionResult = $decisionEngine->decide([$this->option('option_a', 4.0)]);
        $strategyResult = (new StrategyPlanner())->planStrategy(
            $decisionResult,
            [],
            [['id' => 'phase_1', 'description' => 'Build', 'validation_note' => 'No specialists were configured; verify manually before relying on this decision.']]
        );
        $engine = new ExplanationEngine();

        $result = $engine->explain($decisionResult, $strategyResult);

        $this->assertSame('No approved reusable knowledge was consulted for this decision.', $result['explanation']['knowledge_used']);
    }
}
