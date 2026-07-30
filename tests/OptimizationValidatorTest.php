<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Optimization\OptimizationValidator;
use SquirrelForge\Reasoning\SqliteRiskAssessor;

final class OptimizationValidatorTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-optimization-validator-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function completeReferences(): array
    {
        return ['test_evidence' => true, 'platform_validation' => true, 'policy_result' => true, 'security_decision' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function completeProposal(): array
    {
        return ['proposal_id' => 'proposal_1', 'opportunity' => ['type' => 'recurring_issue', 'evidence' => ['count' => 5]], 'success_criteria' => ['timeout rate below 1%']];
    }

    /**
     * @return array<string, mixed>
     */
    private function completeOptions(): array
    {
        return ['baseline_reference' => 'baseline_1', 'comparison_method' => 'before_after', 'rollback_plan' => 'revert config', 'references' => $this->completeReferences()];
    }

    public function testFullyCompleteProposalIsReady(): void
    {
        $validator = new OptimizationValidator();

        $result = $validator->validate($this->completeProposal(), $this->completeOptions());

        $this->assertSame('ready', $result['outcome']);
    }

    public function testMissingOpportunityEvidenceIsNotReady(): void
    {
        $validator = new OptimizationValidator();

        $result = $validator->validate(['proposal_id' => 'p1', 'success_criteria' => ['x']], $this->completeOptions());

        $this->assertSame('not_ready', $result['outcome']);
    }

    public function testUnmitigatedCriticalRiskIsNotReady(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $riskAssessor->assess('proposal_1', 'security', 'critical issue', 'high', 'high');
        $validator = new OptimizationValidator($riskAssessor);

        $result = $validator->validate($this->completeProposal(), $this->completeOptions());

        $this->assertSame('not_ready', $result['outcome']);
    }

    public function testMitigatedCriticalRiskNoLongerBlocksReadiness(): void
    {
        $riskAssessor = new SqliteRiskAssessor($this->tempPath('risk'));
        $critical = $riskAssessor->assess('proposal_1', 'security', 'critical issue', 'high', 'high');
        $riskAssessor->markMitigated($critical['risk_id']);
        $validator = new OptimizationValidator($riskAssessor);

        $result = $validator->validate($this->completeProposal(), $this->completeOptions());

        $this->assertSame('ready', $result['outcome']);
    }

    public function testDeniedReferenceIsNotReady(): void
    {
        $validator = new OptimizationValidator();
        $options = $this->completeOptions();
        $options['references']['security_decision'] = 'denied';

        $result = $validator->validate($this->completeProposal(), $options);

        $this->assertSame('not_ready', $result['outcome']);
        $this->assertSame(['security_decision'], $result['denied_references']);
    }

    public function testMissingSuccessCriteriaRequiresRevision(): void
    {
        $validator = new OptimizationValidator();
        $proposal = $this->completeProposal();
        $proposal['success_criteria'] = [];

        $result = $validator->validate($proposal, $this->completeOptions());

        $this->assertSame('requires_revision', $result['outcome']);
    }

    public function testMissingRollbackPlanRequiresRevision(): void
    {
        $validator = new OptimizationValidator();
        $options = $this->completeOptions();
        $options['rollback_plan'] = '';

        $result = $validator->validate($this->completeProposal(), $options);

        $this->assertSame('requires_revision', $result['outcome']);
    }

    public function testAnEntirelyUnaddressedReferenceCategoryRequiresRevision(): void
    {
        $validator = new OptimizationValidator();
        $options = $this->completeOptions();
        unset($options['references']['policy_result']);

        $result = $validator->validate($this->completeProposal(), $options);

        $this->assertSame('requires_revision', $result['outcome']);
        $this->assertSame(['policy_result'], $result['missing_references']);
    }

    public function testAPendingReferenceIsDeferred(): void
    {
        $validator = new OptimizationValidator();
        $options = $this->completeOptions();
        $options['references']['test_evidence'] = 'pending';

        $result = $validator->validate($this->completeProposal(), $options);

        $this->assertSame('deferred', $result['outcome']);
    }

    public function testAnExplicitlyFalseReferenceIsReadyWithConditions(): void
    {
        $validator = new OptimizationValidator();
        $options = $this->completeOptions();
        $options['references']['platform_validation'] = false;

        $result = $validator->validate($this->completeProposal(), $options);

        $this->assertSame('ready_with_conditions', $result['outcome']);
        $this->assertSame(['platform_validation'], $result['unsatisfied_references']);
    }

    public function testDeniedTakesPriorityOverEverythingElse(): void
    {
        $validator = new OptimizationValidator();
        $proposal = $this->completeProposal();
        $proposal['success_criteria'] = [];
        $options = $this->completeOptions();
        $options['references']['security_decision'] = 'denied';

        $result = $validator->validate($proposal, $options);

        $this->assertSame('not_ready', $result['outcome']);
    }
}
