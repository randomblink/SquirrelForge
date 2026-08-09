<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Agent;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\SqliteAgentGovernance;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Governance\SqlitePolicyEngine;

final class SqliteAgentGovernanceTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-agent-governance-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function boolCondition(string $field, bool $equals = true): array
    {
        return ['type' => 'boolean', 'field' => $field, 'equals' => $equals];
    }

    private function policyEngineWith(string $effect, string $field = 'ready'): SqlitePolicyEngine
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', [
            'category' => 'compliance',
            'priority' => 1,
            'condition' => $this->boolCondition($field),
            'effect' => $effect,
        ]);

        return $policyEngine;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestFor(array $overrides = []): array
    {
        return array_replace([
            'escalating_stage' => 'Reviewer',
            'policy_question' => 'Is this waiver permitted under the data-protection policy?',
            'required_authority_level' => 'lead',
            'held_authority_level' => 'lead',
            'context' => ['ready' => true],
        ], $overrides);
    }

    // --- shape validation ---

    public function testMissingEscalatingStageIsInvalid(): void
    {
        $governance = new SqliteAgentGovernance($this->tempPath('gov'));

        $result = $governance->decide($this->requestFor(['escalating_stage' => null]));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUnrecognizedEscalatingStageIsInvalid(): void
    {
        $governance = new SqliteAgentGovernance($this->tempPath('gov'));

        $result = $governance->decide($this->requestFor(['escalating_stage' => 'Documentation']));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('Escalating Stages', $result['error']);
    }

    // --- fail-closed on missing policy question / authority level ---

    public function testMissingPolicyQuestionIsBlockedAndRecorded(): void
    {
        $governance = new SqliteAgentGovernance($this->tempPath('gov'), $this->policyEngineWith('allow'));

        $result = $governance->decide($this->requestFor(['policy_question' => null]));

        $this->assertSame('BLOCKED', $result['outcome']);
        $this->assertNotNull($result['governance_id']);
        $this->assertNotNull($governance->get($result['governance_id']));
    }

    public function testMissingRequiredAuthorityLevelIsBlocked(): void
    {
        $governance = new SqliteAgentGovernance($this->tempPath('gov'), $this->policyEngineWith('allow'));

        $result = $governance->decide($this->requestFor(['required_authority_level' => '']));

        $this->assertSame('BLOCKED', $result['outcome']);
    }

    public function testMissingHeldAuthorityLevelIsBlocked(): void
    {
        $governance = new SqliteAgentGovernance($this->tempPath('gov'), $this->policyEngineWith('allow'));

        $result = $governance->decide($this->requestFor(['held_authority_level' => null]));

        $this->assertSame('BLOCKED', $result['outcome']);
    }

    // --- authority-level check happens before policy evaluation ---

    public function testInsufficientAuthorityForcesEscalationRegardlessOfPolicyResult(): void
    {
        // Even a policy that would ALLOW must not be applied past the authority actually held.
        $governance = new SqliteAgentGovernance($this->tempPath('gov'), $this->policyEngineWith('allow'));

        $result = $governance->decide($this->requestFor(['required_authority_level' => 'director', 'held_authority_level' => 'lead']));

        $this->assertSame('ESCALATION_REQUIRED', $result['outcome']);
        $this->assertStringContainsString('director', $result['rationale']);
        $this->assertNull($result['policy_reference']);
    }

    // --- no Policy Engine composed: real domain authority, so BLOCKED not silently approved ---

    public function testNoPolicyEngineComposedIsBlockedNotApproved(): void
    {
        $governance = new SqliteAgentGovernance($this->tempPath('gov'));

        $result = $governance->decide($this->requestFor());

        $this->assertSame('BLOCKED', $result['outcome']);
        $this->assertStringContainsString('No Policy Engine is configured', $result['rationale']);
    }

    // --- real Policy Engine decision -> Governance Outcome mapping ---

    public function testAllowedPolicyMapsToApproved(): void
    {
        $governance = new SqliteAgentGovernance($this->tempPath('gov'), $this->policyEngineWith('allow'));

        $result = $governance->decide($this->requestFor());

        $this->assertSame('APPROVED', $result['outcome']);
        $this->assertNotEmpty($result['policy_reference']);
    }

    public function testAllowWithConditionsMapsToApprovedWithConditions(): void
    {
        $governance = new SqliteAgentGovernance($this->tempPath('gov'), $this->policyEngineWith('allow_with_conditions'));

        $result = $governance->decide($this->requestFor());

        $this->assertSame('APPROVED_WITH_CONDITIONS', $result['outcome']);
    }

    public function testDenyMapsToDenied(): void
    {
        $governance = new SqliteAgentGovernance($this->tempPath('gov'), $this->policyEngineWith('deny'));

        $result = $governance->decide($this->requestFor());

        $this->assertSame('DENIED', $result['outcome']);
    }

    public function testProhibitAlsoMapsToDeniedNotASeparateTier(): void
    {
        $governance = new SqliteAgentGovernance($this->tempPath('gov'), $this->policyEngineWith('prohibit'));

        $result = $governance->decide($this->requestFor());

        $this->assertSame('DENIED', $result['outcome']);
    }

    public function testRequireReviewMapsToEscalationRequired(): void
    {
        $governance = new SqliteAgentGovernance($this->tempPath('gov'), $this->policyEngineWith('require_review'));

        $result = $governance->decide($this->requestFor());

        $this->assertSame('ESCALATION_REQUIRED', $result['outcome']);
    }

    public function testNoApplicablePolicyMapsToBlockedViaDeferredOrDenied(): void
    {
        // No registered policy applies to this context at all -> Policy Engine's own "deny by default".
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $governance = new SqliteAgentGovernance($this->tempPath('gov'), $policyEngine);

        $result = $governance->decide($this->requestFor());

        $this->assertSame('DENIED', $result['outcome']);
    }

    // --- traceability ---

    public function testGetUnknownGovernanceIdReturnsNull(): void
    {
        $governance = new SqliteAgentGovernance($this->tempPath('gov'));

        $this->assertNull($governance->get('ghost'));
    }

    public function testHistoryTracksDecisionsByEscalatingStage(): void
    {
        $governance = new SqliteAgentGovernance($this->tempPath('gov'), $this->policyEngineWith('allow'));

        $governance->decide($this->requestFor(['escalating_stage' => 'Security']));
        $governance->decide($this->requestFor(['escalating_stage' => 'Reviewer']));
        $governance->decide($this->requestFor(['escalating_stage' => 'Security']));

        $history = $governance->history('Security');

        $this->assertCount(2, $history);
        $this->assertSame('Security', $history[0]['escalating_stage']);
    }

    public function testEveryDecisionAttemptIsRecordedIncludingBlockedOnes(): void
    {
        $governance = new SqliteAgentGovernance($this->tempPath('gov'), $this->policyEngineWith('allow'));

        $governance->decide($this->requestFor(['policy_question' => null]));
        $governance->decide($this->requestFor());

        $history = $governance->history('Reviewer');

        $this->assertCount(2, $history);
        $this->assertSame('BLOCKED', $history[0]['decision']);
        $this->assertSame('APPROVED', $history[1]['decision']);
    }
}
