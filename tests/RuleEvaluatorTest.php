<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Reasoning\RuleEvaluator;

final class RuleEvaluatorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-rule-evaluator-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    public function testAllPassingRulesProduceAPassedOutcome(): void
    {
        $evaluator = new RuleEvaluator();

        $result = $evaluator->evaluate([
            ['id' => 'r1', 'source' => 'general_agent', 'condition' => ['type' => 'boolean', 'field' => 'destructive', 'equals' => false]],
        ], ['destructive' => false]);

        $this->assertSame('Passed', $result['outcome']);
        $this->assertSame('Passed', $result['records'][0]['result']);
        $this->assertSame('None', $result['records'][0]['required_action']);
        $this->assertFalse($result['records'][0]['conflict_detected']);
    }

    public function testAFailingRuleProducesAFailedOutcome(): void
    {
        $evaluator = new RuleEvaluator();

        $result = $evaluator->evaluate([
            ['id' => 'r1', 'source' => 'workflow', 'condition' => ['type' => 'boolean', 'field' => 'destructive', 'equals' => false]],
        ], ['destructive' => true]);

        $this->assertSame('Failed', $result['outcome']);
        $this->assertSame('Revise', $result['records'][0]['required_action']);
    }

    public function testMissingContextProducesAWarningOutcome(): void
    {
        $evaluator = new RuleEvaluator();

        $result = $evaluator->evaluate([
            ['id' => 'r1', 'source' => 'domain', 'condition' => ['type' => 'boolean', 'field' => 'never_supplied', 'equals' => true]],
        ], []);

        $this->assertSame('Warning', $result['outcome']);
        $this->assertSame('Warning', $result['records'][0]['result']);
    }

    public function testAnUnknownSourceTierIsRejectedWithEscalation(): void
    {
        $evaluator = new RuleEvaluator();

        $result = $evaluator->evaluate([
            ['id' => 'r1', 'source' => 'made_up_tier', 'condition' => ['type' => 'boolean', 'field' => 'x', 'equals' => true]],
        ], ['x' => true]);

        $this->assertSame('Escalate', $result['outcome']);
        $this->assertStringContainsString('made_up_tier', $result['error']);
    }

    public function testDuplicateRuleIdentifiersEscalate(): void
    {
        $evaluator = new RuleEvaluator();

        $result = $evaluator->evaluate([
            ['id' => 'r1', 'source' => 'general_agent', 'condition' => ['type' => 'boolean', 'field' => 'x', 'equals' => true]],
            ['id' => 'r1', 'source' => 'general_agent', 'condition' => ['type' => 'boolean', 'field' => 'x', 'equals' => true]],
        ], ['x' => true]);

        $this->assertSame('Escalate', $result['outcome']);
        $this->assertStringContainsString('Duplicate', $result['escalations'][0]['reason']);
    }

    public function testContradictoryRulesAtTheSameTierAndMandatoryStatusEscalate(): void
    {
        $evaluator = new RuleEvaluator();

        $result = $evaluator->evaluate([
            ['id' => 'r1', 'source' => 'domain', 'condition' => ['type' => 'boolean', 'field' => 'z', 'equals' => true]],
            ['id' => 'r2', 'source' => 'domain', 'condition' => ['type' => 'boolean', 'field' => 'z', 'equals' => false]],
        ], ['z' => true]);

        $this->assertSame('Escalate', $result['outcome']);
        $this->assertCount(2, $result['escalations']);
        $r1 = current(array_filter($result['records'], static fn(array $r): bool => $r['rule_id'] === 'r1'));
        $this->assertSame('Escalate', $r1['required_action']);
    }

    public function testAHigherPrecedenceTierWinsAContradiction(): void
    {
        $evaluator = new RuleEvaluator();

        $result = $evaluator->evaluate([
            ['id' => 'general', 'source' => 'general_agent', 'condition' => ['type' => 'boolean', 'field' => 'x', 'equals' => true]],
            ['id' => 'workflow_specific', 'source' => 'workflow', 'condition' => ['type' => 'boolean', 'field' => 'x', 'equals' => false]],
        ], ['x' => true]);

        // general_agent's own real check passes (x==true), but workflow
        // outranks it and workflow's own real check fails (x!=false).
        $this->assertSame('Failed', $result['outcome']);

        $general = current(array_filter($result['records'], static fn(array $r): bool => $r['rule_id'] === 'general'));
        $workflow = current(array_filter($result['records'], static fn(array $r): bool => $r['rule_id'] === 'workflow_specific'));

        $this->assertSame('Passed', $general['result']);
        $this->assertSame('Revise', $general['required_action']);
        $this->assertSame('Failed', $workflow['result']);
        $this->assertTrue($general['conflict_detected']);
        $this->assertTrue($workflow['conflict_detected']);
    }

    public function testAMandatoryRuleOverridesARecommendationAtTheSameTier(): void
    {
        $evaluator = new RuleEvaluator();

        $result = $evaluator->evaluate([
            ['id' => 'recommendation', 'source' => 'domain', 'mandatory' => false, 'condition' => ['type' => 'boolean', 'field' => 'y', 'equals' => true]],
            ['id' => 'mandatory_rule', 'source' => 'domain', 'mandatory' => true, 'condition' => ['type' => 'boolean', 'field' => 'y', 'equals' => false]],
        ], ['y' => true]);

        $this->assertSame('Failed', $result['outcome']);

        $recommendation = current(array_filter($result['records'], static fn(array $r): bool => $r['rule_id'] === 'recommendation'));
        $this->assertSame('Revise', $recommendation['required_action']);
    }

    public function testPolicyEngineDenialContributesAFailedRule(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('deny_destructive', [
            'category' => 'operational', 'priority' => 1,
            'condition' => ['type' => 'boolean', 'field' => 'destructive', 'equals' => true],
            'effect' => 'deny',
        ]);
        $evaluator = new RuleEvaluator(policyEngine: $policyEngine);

        $result = $evaluator->evaluate([], [], ['policy_context' => ['destructive' => true]]);

        $this->assertSame('Failed', $result['outcome']);
        $this->assertStringContainsString('denied', $result['records'][0]['reason']);
    }

    public function testPolicyEngineAllowContributesAPassedRule(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('allow_safe', [
            'category' => 'operational', 'priority' => 1,
            'condition' => ['type' => 'boolean', 'field' => 'destructive', 'equals' => false],
            'effect' => 'allow',
        ]);
        $evaluator = new RuleEvaluator(policyEngine: $policyEngine);

        $result = $evaluator->evaluate([], [], ['policy_context' => ['destructive' => false]]);

        $this->assertSame('Passed', $result['outcome']);
    }

    public function testWithoutPolicyContextThePolicyEngineIsNeverConsulted(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $evaluator = new RuleEvaluator(policyEngine: $policyEngine);

        $result = $evaluator->evaluate([
            ['id' => 'r1', 'source' => 'general_agent', 'condition' => ['type' => 'boolean', 'field' => 'x', 'equals' => true]],
        ], ['x' => true]);

        $this->assertSame('Passed', $result['outcome']);
        $this->assertCount(1, $result['records']);
    }
}
