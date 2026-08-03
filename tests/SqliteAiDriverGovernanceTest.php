<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\AiDriver\SqliteAiDriverGovernance;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Governance\SqlitePolicyEngine;

final class SqliteAiDriverGovernanceTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-ai-driver-governance-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function boolCondition(string $field, bool $equals = true): array
    {
        return ['type' => 'boolean', 'field' => $field, 'equals' => $equals];
    }

    /**
     * @return array{ai_request_id: string, ai_component: string, policy_context: array<string, mixed>}
     */
    private function request(array $overrides = []): array
    {
        return array_replace([
            'ai_request_id' => 'req_1',
            'ai_component' => 'ai_driver',
            'policy_context' => ['ready' => true],
        ], $overrides);
    }

    public function testDeniesByDefaultWhenNoPolicyEngineIsConfigured(): void
    {
        $governance = new SqliteAiDriverGovernance($this->tempPath('gov'));

        $result = $governance->review($this->request());

        $this->assertSame('Deny', $result['decision']);
        $this->assertSame('not_configured', $result['governance_action']);
    }

    public function testApprovesWhenAnAllowingPolicyApplies(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow']);
        $governance = new SqliteAiDriverGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Approve', $result['decision']);
        $this->assertSame('allowed', $result['governance_action']);
        $this->assertSame(['p1'], $result['policies_evaluated']);
        $this->assertNotNull($governance->get($result['ai_governance_operation_id']));
    }

    public function testDeniesWhenNoPolicyApplies(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $governance = new SqliteAiDriverGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Deny', $result['decision']);
        $this->assertSame('denied', $result['governance_action']);
    }

    public function testDeniesWhenAProhibitingPolicyApplies(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'prohibit']);
        $governance = new SqliteAiDriverGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Deny', $result['decision']);
        $this->assertSame('permanently_prohibited', $result['governance_action']);
    }

    public function testApprovesWithConditionsMapsToApprove(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow_with_conditions']);
        $governance = new SqliteAiDriverGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Approve', $result['decision']);
        $this->assertSame('allowed_with_conditions', $result['governance_action']);
    }

    public function testCriticalRiskClassificationForcesDenialEvenWhenPolicyAllows(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow']);
        $governance = new SqliteAiDriverGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request(['risk_classification' => 'critical']));

        $this->assertSame('Deny', $result['decision']);
        $this->assertSame('denied_critical_risk', $result['governance_action']);
    }

    public function testRejectsAMalformedRequestAsInvalid(): void
    {
        $governance = new SqliteAiDriverGovernance($this->tempPath('gov'));

        $result = $governance->review(['ai_request_id' => 'req_1']);

        $this->assertSame('Deny', $result['decision']);
        $this->assertSame('invalid_request', $result['governance_action']);
        $this->assertNotNull($result['error']);
    }

    public function testEveryReviewIsRecordedIncludingDenials(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $governance = new SqliteAiDriverGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());
        $record = $governance->get($result['ai_governance_operation_id']);

        $this->assertSame('denied', $record['final_outcome']);
        $this->assertSame('req_1', $record['ai_request_id']);
        $this->assertSame('ai_driver', $record['ai_component']);
    }

    public function testUnclassifiedRiskDefaultsWhenNotSupplied(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow']);
        $governance = new SqliteAiDriverGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('unclassified', $result['risk_classification']);
    }
}
