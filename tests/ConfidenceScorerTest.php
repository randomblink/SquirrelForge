<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Reasoning\ConfidenceScorer;

final class ConfidenceScorerTest extends TestCase
{
    public function testStrongSupportingEvidenceProducesVeryHighConfidence(): void
    {
        $scorer = new ConfidenceScorer();

        $result = $scorer->score([
            'decision_id' => 'decision_1',
            'validation_decision' => 'ACCEPTED',
            'rule_evaluation_outcome' => 'Passed',
            'risk_can_proceed' => true,
            'tradeoff_overall_rating' => 4.0,
            'supporting_evidence' => ['a', 'b', 'c'],
            'assumptions' => [],
            'unresolved_limitations' => [],
            'task_count' => 2,
        ]);

        $this->assertEqualsWithDelta(96.0, $result['confidence_score'], 0.01);
        $this->assertSame('Very High', $result['confidence_level']);
        $this->assertNull($result['recommendation']);
    }

    public function testWeakEvidenceProducesVeryLowConfidenceWithARecommendation(): void
    {
        $scorer = new ConfidenceScorer();

        $result = $scorer->score([
            'decision_id' => 'decision_2',
            'validation_decision' => null,
            'rule_evaluation_outcome' => 'Failed',
            'risk_can_proceed' => false,
            'tradeoff_overall_rating' => null,
            'supporting_evidence' => [],
            'assumptions' => ['a1', 'a2', 'a3'],
            'unresolved_limitations' => ['l1', 'l2'],
            'task_count' => 10,
        ]);

        $this->assertEqualsWithDelta(8.6, $result['confidence_score'], 0.01);
        $this->assertSame('Very Low', $result['confidence_level']);
        $this->assertNotNull($result['recommendation']);
    }

    public function testMoreAssumptionsLowerTheAssumptionsFactorButNothingElse(): void
    {
        $scorer = new ConfidenceScorer();

        $none = $scorer->score(['decision_id' => 'd', 'assumptions' => []]);
        $three = $scorer->score(['decision_id' => 'd', 'assumptions' => ['a', 'b', 'c']]);

        $this->assertEqualsWithDelta(1.0, $none['factors']['assumptions'], 0.001);
        $this->assertEqualsWithDelta(0.4, $three['factors']['assumptions'], 0.001);
        $this->assertLessThan($none['confidence_score'], $three['confidence_score']);
    }

    public function testUnresolvedLimitationsLowerTheLimitationsFactor(): void
    {
        $scorer = new ConfidenceScorer();

        $result = $scorer->score(['decision_id' => 'd', 'unresolved_limitations' => ['l1', 'l2']]);

        $this->assertEqualsWithDelta(0.5, $result['factors']['limitations'], 0.001);
    }

    public function testComplexityIsFullScoreAtOrBelowThreeTasks(): void
    {
        $scorer = new ConfidenceScorer();

        $result = $scorer->score(['decision_id' => 'd', 'task_count' => 3]);

        $this->assertEqualsWithDelta(1.0, $result['factors']['complexity'], 0.001);
    }

    public function testComplexityDegradesAboveThreeTasks(): void
    {
        $scorer = new ConfidenceScorer();

        $result = $scorer->score(['decision_id' => 'd', 'task_count' => 8]);

        $this->assertEqualsWithDelta(0.5, $result['factors']['complexity'], 0.001);
    }

    public function testKnowledgeIsNeutralWhenNoPriorSuccessesAreSupplied(): void
    {
        $scorer = new ConfidenceScorer();

        $result = $scorer->score(['decision_id' => 'd']);

        $this->assertEqualsWithDelta(0.5, $result['factors']['knowledge'], 0.001);
    }

    public function testKnowledgeReflectsRealPriorSuccessCounts(): void
    {
        $scorer = new ConfidenceScorer();

        $zero = $scorer->score(['decision_id' => 'd', 'prior_successes' => 0]);
        $two = $scorer->score(['decision_id' => 'd', 'prior_successes' => 2]);

        $this->assertEqualsWithDelta(0.0, $zero['factors']['knowledge'], 0.001);
        $this->assertEqualsWithDelta(1.0, $two['factors']['knowledge'], 0.001);
    }

    public function testWarningRuleComplianceScoresBelowPassed(): void
    {
        $scorer = new ConfidenceScorer();

        $passed = $scorer->score(['decision_id' => 'd', 'rule_evaluation_outcome' => 'Passed']);
        $warning = $scorer->score(['decision_id' => 'd', 'rule_evaluation_outcome' => 'Warning']);

        $this->assertGreaterThan($warning['confidence_score'], $passed['confidence_score']);
    }

    public function testAModerateConfidenceLevelCarriesNoRecommendation(): void
    {
        $scorer = new ConfidenceScorer();

        $moderate = $scorer->score(['decision_id' => 'd', 'validation_decision' => 'ACCEPTED_WITH_LIMITATIONS', 'rule_evaluation_outcome' => 'Warning', 'risk_can_proceed' => true, 'tradeoff_overall_rating' => 3.0]);

        $this->assertSame('Moderate', $moderate['confidence_level']);
        $this->assertNull($moderate['recommendation']);
    }
}
