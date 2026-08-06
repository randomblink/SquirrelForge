<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Integration\SqliteIntegrationGovernance;

final class SqliteIntegrationGovernanceTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-integration-governance-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function boolCondition(string $field, bool $equals = true): array
    {
        return ['type' => 'boolean', 'field' => $field, 'equals' => $equals];
    }

    /**
     * @return array{integration_request_id: string, requesting_component: string, external_service_ref: string, policy_context: array<string, mixed>}
     */
    private function request(array $overrides = []): array
    {
        return array_replace([
            'integration_request_id' => 'req_1',
            'requesting_component' => 'workflow_owner_1',
            'external_service_ref' => 'github_api',
            'policy_context' => ['ready' => true],
        ], $overrides);
    }

    private function exceptionRequest(array $overrides = []): array
    {
        return array_replace([
            'scope' => 'github_api integration',
            'reason' => 'temporary migration workaround',
            'expiration_or_review_date' => '2026-12-31',
        ], $overrides);
    }

    public function testRejectsByDefaultWhenNoPolicyEngineIsConfigured(): void
    {
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'));

        $result = $governance->review($this->request());

        $this->assertSame('Rejected', $result['decision']);
        $this->assertNotNull($result['error']);
    }

    public function testMissingRequiredFieldReturnsRequiresAdditionalEvidenceNotRejected(): void
    {
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'));

        $result = $governance->review(['integration_request_id' => 'req_1']);

        $this->assertSame('Requires Additional Evidence', $result['decision']);
        $this->assertNotNull($result['error']);
    }

    public function testEmptyExternalServiceRefIsTreatedAsMissing(): void
    {
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'));

        $result = $governance->review($this->request(['external_service_ref' => '']));

        $this->assertSame('Requires Additional Evidence', $result['decision']);
    }

    public function testApprovesWhenAnAllowingPolicyApplies(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow']);
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Approved', $result['decision']);
        $this->assertNull($result['error']);
        $this->assertNotNull($governance->get($result['governance_id']));
    }

    public function testApprovedWithConditionsCapturesTheRealConditionPolicyIds(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow_with_conditions']);
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Approved with Conditions', $result['decision']);
        $this->assertSame(['p1'], $result['conditions_applied']);
    }

    public function testRequiresAdditionalEvidenceFromPolicyIsDistinctFromDeferred(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'require_review']);
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Requires Additional Evidence', $result['decision']);
    }

    public function testDeferredIsReturnedWhenNoPolicyDecisionCanBeDetermined(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => ['type' => 'boolean', 'field' => 'missing_field', 'equals' => true], 'effect' => 'allow']);
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Deferred', $result['decision']);
    }

    public function testProhibitedMapsDirectlyFromPolicyEngine(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'prohibit']);
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Prohibited', $result['decision']);
    }

    public function testDeniedWithoutExceptionRequestStaysRejected(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'deny']);
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());

        $this->assertSame('Rejected', $result['decision']);
        $this->assertNull($result['exception']);
    }

    public function testDeniedWithAWellFormedExceptionRequestIsUpgradedToExceptionApproved(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'deny']);
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request(['exception_request' => $this->exceptionRequest()]));

        $this->assertSame('Exception Approved', $result['decision']);
        $this->assertSame($this->exceptionRequest(), $result['exception']);
    }

    public function testDeniedWithAnIncompleteExceptionRequestStaysRejected(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'deny']);
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request(['exception_request' => $this->exceptionRequest(['reason' => ''])]));

        $this->assertSame('Rejected', $result['decision']);
        $this->assertNull($result['exception']);
    }

    public function testProhibitedIsNeverUpgradedByAnExceptionRequestEvenWellFormed(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'prohibit']);
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request(['exception_request' => $this->exceptionRequest()]));

        $this->assertSame('Prohibited', $result['decision']);
        $this->assertNull($result['exception']);
    }

    public function testApprovedIsNeverAffectedByAnExceptionRequest(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow']);
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request(['exception_request' => $this->exceptionRequest()]));

        $this->assertSame('Approved', $result['decision']);
        $this->assertNull($result['exception']);
    }

    public function testEvidenceReferencesRecordWhichEvidenceCategoriesWereSupplied(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow']);
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request([
            'connector_evidence' => 'connector_ready',
            'risk_assessment' => 'low',
        ]));

        $this->assertContains('connector_evidence', $result['evidence_references']);
        $this->assertContains('risk_assessment', $result['evidence_references']);
        $this->assertNotContains('security_evidence', $result['evidence_references']);
    }

    public function testEveryReviewIsRecordedAndRetrievableByGovernanceId(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'deny']);
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->review($this->request());
        $record = $governance->get($result['governance_id']);

        $this->assertSame('Rejected', $record['decision']);
        $this->assertSame('req_1', $record['integration_request_id']);
        $this->assertSame('workflow_owner_1', $record['requesting_component']);
        $this->assertSame('github_api', $record['external_service_ref']);
    }

    public function testGetOnUnknownGovernanceIdReturnsNull(): void
    {
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'));

        $this->assertNull($governance->get('integration_gov_does_not_exist'));
    }

    public function testFindByDecisionFiltersToThatDecision(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'integration', 'priority' => 1, 'condition' => $this->boolCondition('ready'), 'effect' => 'allow']);
        $governance = new SqliteIntegrationGovernance($this->tempPath('gov'), $policyEngine);
        $governance->review($this->request(['integration_request_id' => 'req_1']));
        $governance->review($this->request(['integration_request_id' => 'req_2']));

        $approved = $governance->findByDecision('Approved');

        $this->assertCount(2, $approved);
    }
}
