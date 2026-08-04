<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Storage\SqliteDataGovernance;

final class SqliteDataGovernanceTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-data-governance-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function boolCondition(string $field, bool $equals = true): array
    {
        return ['type' => 'boolean', 'field' => $field, 'equals' => $equals];
    }

    /**
     * @return array{request_id: string, data_classification: string, policy_context: array<string, mixed>}
     */
    private function request(array $overrides = []): array
    {
        return array_replace([
            'request_id' => 'req_1',
            'data_classification' => 'confidential',
            'policy_context' => ['ready' => true],
        ], $overrides);
    }

    public function testRejectsByDefaultWhenNoPolicyEngineIsConfigured(): void
    {
        $governance = new SqliteDataGovernance($this->tempPath('gov'));

        $result = $governance->review($this->request());

        $this->assertSame('Rejected', $result['decision']);
        $this->assertNotNull($result['error']);
    }

    public function testRejectsAnUnrecognizedDataClassification(): void
    {
        $governance = new SqliteDataGovernance($this->tempPath('gov'));

        $result = $governance->review($this->request(['data_classification' => 'astrology']));

        $this->assertSame('Rejected', $result['decision']);
    }

    public function testRejectsAMalformedRequest(): void
    {
        $governance = new SqliteDataGovernance($this->tempPath('gov'));

        $result = $governance->review(['request_id' => 'req_1']);

        $this->assertSame('Rejected', $result['decision']);
        $this->assertNotNull($result['error']);
    }

    public function testApprovesWhenAnAllowingPolicyApplies(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'data_protection', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow']);
        $governance = new SqliteDataGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Approved', $result['decision']);
        $this->assertNull($result['error']);
        $this->assertNotNull($governance->get($result['governance_id']));
    }

    public function testApprovedWithConditionsCapturesTheRealConditionPolicyIds(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'data_protection', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow_with_conditions']);
        $governance = new SqliteDataGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Approved with Conditions', $result['decision']);
        $this->assertSame(['p1'], $result['conditions_applied']);
    }

    public function testRequiresAdditionalEvidenceIsDistinctFromDeferred(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'data_protection', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'require_review']);
        $governance = new SqliteDataGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Requires Additional Evidence', $result['decision']);
    }

    public function testDeferredIsReturnedWhenNoPolicyDecisionCanBeDetermined(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'data_protection', 'priority' => 1, 'condition' => ['type' => 'boolean', 'field' => 'missing_field', 'equals' => true], 'effect' => 'allow']);
        $governance = new SqliteDataGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Deferred', $result['decision']);
    }

    public function testPermanentlyProhibitedMapsDirectlyFromPolicyEngine(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'data_protection', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'prohibit']);
        $governance = new SqliteDataGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Permanently Prohibited', $result['decision']);
    }

    public function testComplianceStatusDefaultsToNotEvaluatedWhenNotSupplied(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'data_protection', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow']);
        $governance = new SqliteDataGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('not_evaluated', $result['compliance_status']);
    }

    public function testEveryReviewIsRecordedWithAuthorizationStatusDerivedFromDecision(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'data_protection', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'deny']);
        $governance = new SqliteDataGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());
        $record = $governance->get($result['governance_id']);

        $this->assertSame('Rejected', $record['decision_type']);
        $this->assertSame('not_authorized', $record['authorization_status']);
        $this->assertSame('req_1', $record['request_id']);
        $this->assertSame('confidential', $record['data_classification']);
    }
}
