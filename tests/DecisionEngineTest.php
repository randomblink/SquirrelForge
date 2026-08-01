<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Knowledge\KnowledgeManager;
use SquirrelForge\Reasoning\DecisionEngine;
use SquirrelForge\Reasoning\RuleEvaluator;
use SquirrelForge\Reasoning\SqliteRiskAssessor;
use SquirrelForge\Reasoning\TradeoffAnalyzer;

final class DecisionEngineTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-decision-engine-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function option(string $name, float $weightedTotal, array $overrides = []): array
    {
        return array_merge([
            'option' => $name,
            'matrix_score' => ['weighted_total' => $weightedTotal, 'disqualified' => false, 'disqualification_reason' => null],
        ], $overrides);
    }

    public function testDecideWithNoOptionsIsRejected(): void
    {
        $engine = new DecisionEngine(tradeoffAnalyzer: new TradeoffAnalyzer());

        $result = $engine->decide([]);

        $this->assertSame('no_options', $result['outcome']);
    }

    public function testDecideWithoutTradeoffAnalyzerIsNotConfigured(): void
    {
        $engine = new DecisionEngine();

        $result = $engine->decide([$this->option('option_a', 4.0)]);

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testDecideSelectsTheHighestRatedEligibleOption(): void
    {
        $engine = new DecisionEngine(tradeoffAnalyzer: new TradeoffAnalyzer());

        $result = $engine->decide([
            $this->option('option_a', 3.0),
            $this->option('option_b', 4.5),
        ]);

        $this->assertSame('decided', $result['outcome']);
        $this->assertSame('option_b', $result['decision']['selected_option']);
        $this->assertCount(1, $result['decision']['alternatives_considered']);
        $this->assertSame('option_a', $result['decision']['alternatives_considered'][0]['option']);
    }

    public function testDecideRecordsLimitationsWhenSpecialistsAreNotConfigured(): void
    {
        $engine = new DecisionEngine(tradeoffAnalyzer: new TradeoffAnalyzer());

        $result = $engine->decide([$this->option('option_a', 4.0)]);

        $this->assertContains('No historical context was supplied through Memory Retrieval.', $result['decision']['unresolved_limitations']);
        $this->assertContains('Rule Evaluator did not independently verify compliance for one or more options.', $result['decision']['unresolved_limitations']);
        $this->assertContains('One or more options carry no confidence score from Confidence Scorer.', $result['decision']['unresolved_limitations']);
    }

    public function testDecideOmitsTheHistoricalContextLimitationWhenSupplied(): void
    {
        $engine = new DecisionEngine(tradeoffAnalyzer: new TradeoffAnalyzer());

        $result = $engine->decide([$this->option('option_a', 4.0)], ['historical_context' => ['prior_decision' => 'used option_a before']]);

        $this->assertNotContains('No historical context was supplied through Memory Retrieval.', $result['decision']['unresolved_limitations']);
    }

    public function testDecideSkipsARuleNonCompliantTopRankedOptionInFavorOfTheNextEligibleOne(): void
    {
        $engine = new DecisionEngine(new RuleEvaluator(), new TradeoffAnalyzer());

        $result = $engine->decide([
            $this->option('option_a', 4.9, [
                'rules' => [['id' => 'r1', 'source' => 'workflow', 'condition' => ['type' => 'boolean', 'field' => 'destructive', 'equals' => false]]],
                'rule_context' => ['destructive' => true],
            ]),
            $this->option('option_b', 3.0, [
                'rules' => [['id' => 'r1', 'source' => 'workflow', 'condition' => ['type' => 'boolean', 'field' => 'destructive', 'equals' => false]]],
                'rule_context' => ['destructive' => false],
            ]),
        ]);

        $this->assertSame('decided', $result['outcome']);
        $this->assertSame('option_b', $result['decision']['selected_option']);
    }

    public function testDecideWithNoEligibleCompliantOptionReportsThatOutcome(): void
    {
        $engine = new DecisionEngine(new RuleEvaluator(), new TradeoffAnalyzer());

        $result = $engine->decide([
            $this->option('option_a', 4.0, [
                'rules' => [['id' => 'r1', 'source' => 'workflow', 'condition' => ['type' => 'boolean', 'field' => 'destructive', 'equals' => false]]],
                'rule_context' => ['destructive' => true],
            ]),
        ]);

        $this->assertSame('no_eligible_option', $result['outcome']);
    }

    public function testDecideExcludesARiskBlockedOptionEvenWithTheHighestScore(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $riskAssessor->assess('option_a', 'technical', 'Untested migration', 'high', 'high');
        $engine = new DecisionEngine(tradeoffAnalyzer: new TradeoffAnalyzer($riskAssessor));

        $result = $engine->decide([
            $this->option('option_a', 5.0),
            $this->option('option_b', 2.0),
        ]);

        $this->assertSame('option_b', $result['decision']['selected_option']);
    }

    public function testDecideComposesKnowledgeManagerWhenAQueryIsSupplied(): void
    {
        $engine = new DecisionEngine(tradeoffAnalyzer: new TradeoffAnalyzer(), knowledgeManager: new KnowledgeManager());

        $result = $engine->decide([$this->option('option_a', 4.0)], ['knowledge_query' => 'checkout discount patterns']);

        $this->assertNotNull($result['decision']['approved_knowledge']);
        $this->assertSame('search', $result['decision']['approved_knowledge']['operation']);
        $this->assertNotContains('Knowledge Manager is not configured; approved knowledge was not consulted.', $result['decision']['unresolved_limitations']);
    }

    public function testDecideRecordsALimitationWhenKnowledgeQueryIsSuppliedButNoKnowledgeManagerIsConfigured(): void
    {
        $engine = new DecisionEngine(tradeoffAnalyzer: new TradeoffAnalyzer());

        $result = $engine->decide([$this->option('option_a', 4.0)], ['knowledge_query' => 'checkout discount patterns']);

        $this->assertContains('Knowledge Manager is not configured; approved knowledge was not consulted.', $result['decision']['unresolved_limitations']);
    }
}
