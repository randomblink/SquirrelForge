<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Reasoning\DecisionMatrix;
use SquirrelForge\Reasoning\RuleEvaluator;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

final class DecisionMatrixTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-decision-matrix-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function fullScores(): array
    {
        return ['requirement_fit' => 4, 'maintainability' => 3, 'security' => 5, 'performance' => 4, 'reversibility' => 2, 'evidence' => 3];
    }

    public function testMissingCallerScoreDisqualifiesTheOption(): void
    {
        $matrix = new DecisionMatrix();
        $scores = $this->fullScores();
        unset($scores['security']);

        $result = $matrix->scoreOption('option_a', $scores);

        $this->assertTrue($result['disqualified']);
        $this->assertStringContainsString('security', $result['disqualification_reason']);
    }

    public function testOutOfRangeScoreDisqualifiesTheOption(): void
    {
        $matrix = new DecisionMatrix();

        $result = $matrix->scoreOption('option_a', [...$this->fullScores(), 'performance' => 6]);

        $this->assertTrue($result['disqualified']);
    }

    public function testWithoutRuleEvaluatorOrRiskAssessorScoresOnlyCallerCriteria(): void
    {
        $matrix = new DecisionMatrix();

        $result = $matrix->scoreOption('option_a', $this->fullScores());

        $this->assertFalse($result['disqualified']);
        $this->assertNull($result['scores']['rule_compliance']);
        $this->assertNull($result['scores']['delivery_risk']);
        $this->assertEqualsWithDelta(3.5, $result['weighted_total'], 0.001);
    }

    public function testPassingRulesContributeARealRuleComplianceScore(): void
    {
        $matrix = new DecisionMatrix(new RuleEvaluator());

        $result = $matrix->scoreOption('option_a', $this->fullScores(), [
            'rules' => [['id' => 'r1', 'source' => 'general_agent', 'condition' => ['type' => 'boolean', 'field' => 'x', 'equals' => true]]],
            'rule_context' => ['x' => true],
        ]);

        $this->assertFalse($result['disqualified']);
        $this->assertSame(5, $result['scores']['rule_compliance']);
        $this->assertEqualsWithDelta((21 + 5) / 7, $result['weighted_total'], 0.001);
    }

    public function testAFailingMandatoryRuleDisqualifiesTheOptionRegardlessOfTotal(): void
    {
        $matrix = new DecisionMatrix(new RuleEvaluator());

        $result = $matrix->scoreOption('option_a', $this->fullScores(), [
            'rules' => [['id' => 'r1', 'source' => 'workflow', 'condition' => ['type' => 'boolean', 'field' => 'destructive', 'equals' => false]]],
            'rule_context' => ['destructive' => true],
        ]);

        $this->assertTrue($result['disqualified']);
        $this->assertNull($result['weighted_total']);
        $this->assertStringContainsString('r1', $result['disqualification_reason']);
    }

    public function testAFailingNonMandatoryRuleDoesNotDisqualify(): void
    {
        $matrix = new DecisionMatrix(new RuleEvaluator());

        $result = $matrix->scoreOption('option_a', $this->fullScores(), [
            'rules' => [['id' => 'r1', 'source' => 'workflow', 'mandatory' => false, 'condition' => ['type' => 'boolean', 'field' => 'destructive', 'equals' => false]]],
            'rule_context' => ['destructive' => true],
        ]);

        $this->assertFalse($result['disqualified']);
        $this->assertNotNull($result['weighted_total']);
    }

    public function testRiskAssessorWithNoBlockingRiskContributesAHighDeliveryRiskScore(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $matrix = new DecisionMatrix(riskAssessor: $riskAssessor);

        $result = $matrix->scoreOption('option_a', $this->fullScores());

        $this->assertSame(5, $result['scores']['delivery_risk']);
    }

    public function testRiskAssessorWithABlockingCriticalRiskContributesALowDeliveryRiskScore(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $riskAssessor->assess('option_a', 'technical', 'Untested migration path', 'high', 'high');
        $matrix = new DecisionMatrix(riskAssessor: $riskAssessor);

        $result = $matrix->scoreOption('option_a', $this->fullScores());

        $this->assertSame(1, $result['scores']['delivery_risk']);
    }

    public function testCustomWeightsChangeTheWeightedTotal(): void
    {
        $matrix = new DecisionMatrix();

        $result = $matrix->scoreOption('option_a', $this->fullScores(), [
            'weights' => ['security' => 10.0, 'requirement_fit' => 1.0, 'maintainability' => 1.0, 'performance' => 1.0, 'reversibility' => 1.0, 'evidence' => 1.0],
        ]);

        // Heavily weighting the highest score (security=5) should pull the total up.
        $this->assertGreaterThan(3.5, $result['weighted_total']);
    }

    public function testCompareOptionsRanksQualifyingOptionsByTotal(): void
    {
        $matrix = new DecisionMatrix();
        $low = $matrix->scoreOption('option_low', ['requirement_fit' => 2, 'maintainability' => 2, 'security' => 2, 'performance' => 2, 'reversibility' => 2, 'evidence' => 2]);
        $high = $matrix->scoreOption('option_high', $this->fullScores());

        $result = $matrix->compareOptions([$low, $high]);

        $this->assertSame('option_high', $result['ranked'][0]['option']);
        $this->assertSame('option_low', $result['ranked'][1]['option']);
        $this->assertStringContainsString('option_high', $result['rationale']);
    }

    public function testCompareOptionsExcludesDisqualifiedOptionsFromRanking(): void
    {
        $matrix = new DecisionMatrix(new RuleEvaluator());
        $disqualified = $matrix->scoreOption('option_bad', $this->fullScores(), [
            'rules' => [['id' => 'r1', 'source' => 'workflow', 'condition' => ['type' => 'boolean', 'field' => 'destructive', 'equals' => false]]],
            'rule_context' => ['destructive' => true],
        ]);
        $qualifying = $matrix->scoreOption('option_good', $this->fullScores());

        $result = $matrix->compareOptions([$disqualified, $qualifying]);

        $this->assertCount(1, $result['ranked']);
        $this->assertSame('option_good', $result['ranked'][0]['option']);
        $this->assertCount(1, $result['rejected']);
        $this->assertSame('option_bad', $result['rejected'][0]['option']);
    }

    public function testCompareOptionsWithAllDisqualifiedReportsNoQualifyingOptions(): void
    {
        $matrix = new DecisionMatrix();
        $disqualified = $matrix->scoreOption('option_a', []);

        $result = $matrix->compareOptions([$disqualified]);

        $this->assertSame([], $result['ranked']);
        $this->assertStringContainsString('disqualified', $result['rationale']);
    }

    public function testCompareOptionsWithNoOptionsReturnsNullRationale(): void
    {
        $matrix = new DecisionMatrix();

        $result = $matrix->compareOptions([]);

        $this->assertSame([], $result['ranked']);
        $this->assertSame([], $result['rejected']);
        $this->assertNull($result['rationale']);
    }
}
