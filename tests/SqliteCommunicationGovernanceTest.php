<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Communication\SqliteCommunicationGovernance;
use SquirrelForge\Governance\SqlitePolicyEngine;

final class SqliteCommunicationGovernanceTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-communication-governance-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function boolCondition(string $field, bool $equals = true): array
    {
        return ['type' => 'boolean', 'field' => $field, 'equals' => $equals];
    }

    /**
     * @return array{communication_component: string, policy_context: array<string, mixed>, reviewer: string}
     */
    private function request(array $overrides = []): array
    {
        return array_replace([
            'communication_component' => 'message_queuing',
            'policy_context' => ['ready' => true],
            'reviewer' => 'communication_manager',
        ], $overrides);
    }

    public function testRejectsByDefaultWhenNoPolicyEngineIsConfigured(): void
    {
        $governance = new SqliteCommunicationGovernance($this->tempPath('gov'));

        $result = $governance->review($this->request());

        $this->assertSame('Reject', $result['decision']);
        $this->assertSame('not_configured', $result['governance_action']);
    }

    public function testRejectsAnUnrecognizedCommunicationComponent(): void
    {
        $governance = new SqliteCommunicationGovernance($this->tempPath('gov'));

        $result = $governance->review($this->request(['communication_component' => 'astrology']));

        $this->assertSame('Reject', $result['decision']);
        $this->assertSame('invalid_component', $result['governance_action']);
    }

    public function testApprovesWhenAnAllowingPolicyApplies(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow']);
        $governance = new SqliteCommunicationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Approve', $result['decision']);
        $this->assertSame('allowed', $result['governance_action']);
        $this->assertSame(['p1'], $result['policies_evaluated']);
        $this->assertNotNull($governance->get($result['communication_governance_operation_id']));
    }

    public function testDefersWhenAReviewRequiringPolicyApplies(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'require_review']);
        $governance = new SqliteCommunicationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Defer', $result['decision']);
        $this->assertSame('requires_additional_review', $result['governance_action']);
    }

    public function testRejectsWhenAProhibitingPolicyApplies(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'prohibit']);
        $governance = new SqliteCommunicationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Reject', $result['decision']);
        $this->assertSame('permanently_prohibited', $result['governance_action']);
    }

    public function testCriticalRiskClassificationForcesRejectionEvenWhenPolicyAllows(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow']);
        $governance = new SqliteCommunicationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request(['risk_classification' => 'critical']));

        $this->assertSame('Reject', $result['decision']);
        $this->assertSame('rejected_critical_risk', $result['governance_action']);
    }

    public function testRejectsAMalformedRequestAsInvalid(): void
    {
        $governance = new SqliteCommunicationGovernance($this->tempPath('gov'));

        $result = $governance->review(['communication_component' => 'message_queuing']);

        $this->assertSame('Reject', $result['decision']);
        $this->assertSame('invalid_request', $result['governance_action']);
        $this->assertNotNull($result['error']);
    }

    public function testEveryReviewIsRecordedIncludingRejections(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $governance = new SqliteCommunicationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());
        $record = $governance->get($result['communication_governance_operation_id']);

        $this->assertSame('rejected', $record['final_outcome']);
        $this->assertSame('message_queuing', $record['communication_component']);
        $this->assertSame('communication_manager', $record['reviewer']);
    }

    public function testUnclassifiedRiskDefaultsWhenNotSupplied(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'operational', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow']);
        $governance = new SqliteCommunicationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('unclassified', $result['risk_classification']);
    }
}
