<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Governance\SqlitePolicyEngine;
use SquirrelForge\Security\SqliteComplianceManager;
use SquirrelForge\Security\SqliteSecurityGovernance;

final class SqliteComplianceManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-compliance-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function manager(?SqliteSecurityGovernance $governance = null): SqliteComplianceManager
    {
        return new SqliteComplianceManager($this->tempPath('compliance'), $governance);
    }

    public function testRegisterRequirementRejectsAnUnrecognizedCategory(): void
    {
        $manager = $this->manager();

        $result = $manager->registerRequirement('req_1', 'astrology', 'A requirement.');

        $this->assertSame('invalid_category', $result['outcome']);
    }

    public function testRegisterRequirementIsIdempotent(): void
    {
        $manager = $this->manager();

        $first = $manager->registerRequirement('req_1', 'security', 'A requirement.');
        $second = $manager->registerRequirement('req_1', 'security', 'A requirement.');

        $this->assertSame('registered', $first['outcome']);
        $this->assertSame('already_registered', $second['outcome']);
    }

    public function testMapControlRequiresARegisteredRequirement(): void
    {
        $manager = $this->manager();

        $result = $manager->mapControl('req_unknown', 'MFA enforced', 'authentication_manager');

        $this->assertSame('not_registered', $result['outcome']);
    }

    public function testMapControlAppendsToTheRequirementsControlList(): void
    {
        $manager = $this->manager();
        $manager->registerRequirement('req_1', 'security', 'A requirement.');
        $manager->mapControl('req_1', 'MFA enforced', 'authentication_manager');

        $requirements = $manager->findByCategory('security');

        $controls = json_decode($requirements[0]['controls_json'], true);
        $this->assertSame([['control' => 'MFA enforced', 'evidence_owner' => 'authentication_manager']], $controls);
    }

    public function testAssessRequiresARegisteredRequirement(): void
    {
        $manager = $this->manager();

        $result = $manager->assess('req_unknown', 'Compliant');

        $this->assertSame('not_registered', $result['outcome']);
    }

    public function testAssessRejectsAnUnrecognizedStatus(): void
    {
        $manager = $this->manager();
        $manager->registerRequirement('req_1', 'security', 'A requirement.');

        $result = $manager->assess('req_1', 'astrology');

        $this->assertSame('invalid_status', $result['outcome']);
    }

    public function testAssessRecordsARealCompliantFinding(): void
    {
        $manager = $this->manager();
        $manager->registerRequirement('req_1', 'security', 'A requirement.');

        $result = $manager->assess('req_1', 'Compliant', ['evidence' => ['audit_log_1'], 'assessed_by' => 'reviewer_1']);

        $this->assertSame('assessed', $result['outcome']);
        $record = $manager->get($result['compliance_id']);
        $this->assertSame('Compliant', $record['status']);
        $this->assertSame(['audit_log_1'], $record['evidence']);
        $this->assertSame('reviewer_1', $record['assessed_by']);
        $this->assertSame('Not Required', $record['remediation_status']);
    }

    public function testAssessRejectsAnUnrecognizedRemediationStatus(): void
    {
        $manager = $this->manager();
        $manager->registerRequirement('req_1', 'security', 'A requirement.');

        $result = $manager->assess('req_1', 'Non-Compliant', ['remediation_status' => 'astrology']);

        $this->assertSame('invalid_remediation_status', $result['outcome']);
    }

    public function testExemptAssessmentRequiresAnExceptionReference(): void
    {
        $manager = $this->manager();
        $manager->registerRequirement('req_1', 'security', 'A requirement.');

        $result = $manager->assess('req_1', 'Exempt');

        $this->assertSame('unsupported_exemption', $result['outcome']);
    }

    public function testExemptAssessmentRejectsAnUnknownGovernanceReferenceWhenGovernanceIsConfigured(): void
    {
        $governance = new SqliteSecurityGovernance($this->tempPath('gov'));
        $manager = $this->manager($governance);
        $manager->registerRequirement('req_1', 'security', 'A requirement.');

        $result = $manager->assess('req_1', 'Exempt', ['exception_reference' => 'governance_unknown']);

        $this->assertSame('unsupported_exemption', $result['outcome']);
    }

    public function testExemptAssessmentRejectsANonApprovedGovernanceDecision(): void
    {
        $governance = new SqliteSecurityGovernance($this->tempPath('gov'));
        $decision = $governance->review(['request_id' => 'req_1', 'policy_context' => []]);
        $manager = $this->manager($governance);
        $manager->registerRequirement('req_1', 'security', 'A requirement.');

        $result = $manager->assess('req_1', 'Exempt', ['exception_reference' => $decision['governance_id']]);

        $this->assertSame('unsupported_exemption', $result['outcome']);
    }

    public function testExemptAssessmentSucceedsWithARealApprovedGovernanceDecision(): void
    {
        $policyEngine = new SqlitePolicyEngine($this->tempPath('policy'), new RuleEngine());
        $policyEngine->registerPolicy('p1', ['category' => 'compliance', 'priority' => 1, 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true], 'effect' => 'allow']);
        $governance = new SqliteSecurityGovernance($this->tempPath('gov'), $policyEngine);
        $decision = $governance->review(['request_id' => 'req_1', 'policy_context' => ['ready' => true]]);
        $manager = $this->manager($governance);
        $manager->registerRequirement('req_1', 'security', 'A requirement.');

        $result = $manager->assess('req_1', 'Exempt', ['exception_reference' => $decision['governance_id']]);

        $this->assertSame('assessed', $result['outcome']);
    }

    public function testExemptAssessmentWithoutGovernanceConfiguredOnlyChecksPresence(): void
    {
        $manager = $this->manager();
        $manager->registerRequirement('req_1', 'security', 'A requirement.');

        $result = $manager->assess('req_1', 'Exempt', ['exception_reference' => 'external_reference_1']);

        $this->assertSame('assessed', $result['outcome']);
    }

    public function testHistoryReturnsAllAssessmentsInOrder(): void
    {
        $manager = $this->manager();
        $manager->registerRequirement('req_1', 'security', 'A requirement.');
        $manager->assess('req_1', 'Under Review');
        $manager->assess('req_1', 'Compliant');

        $history = $manager->history('req_1');

        $this->assertCount(2, $history);
        $this->assertSame('Under Review', $history[0]['status']);
        $this->assertSame('Compliant', $history[1]['status']);
    }

    public function testListRequirementsReturnsEveryRegisteredRequirement(): void
    {
        $manager = $this->manager();
        $manager->registerRequirement('req_1', 'security', 'Requirement one.');
        $manager->registerRequirement('req_2', 'privacy', 'Requirement two.');

        $this->assertCount(2, $manager->listRequirements());
    }

    public function testLatestStatusReturnsTheMostRecentAssessment(): void
    {
        $manager = $this->manager();
        $manager->registerRequirement('req_1', 'security', 'A requirement.');
        $manager->assess('req_1', 'Under Review');
        $manager->assess('req_1', 'Compliant');

        $this->assertSame('Compliant', $manager->latestStatus('req_1'));
    }

    public function testLatestStatusIsNullWhenNeverAssessed(): void
    {
        $manager = $this->manager();
        $manager->registerRequirement('req_1', 'security', 'A requirement.');

        $this->assertNull($manager->latestStatus('req_1'));
    }
}
