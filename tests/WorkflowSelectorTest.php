<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\WorkflowSelector;

final class WorkflowSelectorTest extends TestCase
{
    private string $workflowsDirectory;

    protected function setUp(): void
    {
        $this->workflowsDirectory = sys_get_temp_dir() . '/sfws-fixture-' . bin2hex(random_bytes(8));
        mkdir($this->workflowsDirectory);
        file_put_contents($this->workflowsDirectory . '/BUG-FIX-WORKFLOW.md', '# Bug Fix');
        file_put_contents($this->workflowsDirectory . '/FEATURE-DEVELOPMENT-WORKFLOW.md', '# Feature Development');
        file_put_contents($this->workflowsDirectory . '/SECURITY-REVIEW-WORKFLOW.md', '# Security Review');
        file_put_contents($this->workflowsDirectory . '/README.md', '# Index');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->workflowsDirectory . '/*.md') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->workflowsDirectory);
    }

    private function selector(): WorkflowSelector
    {
        return new WorkflowSelector($this->workflowsDirectory);
    }

    public function testListAvailableWorkflowsExcludesReadmeAndSortsResults(): void
    {
        $result = $this->selector()->listAvailableWorkflows();

        $this->assertSame(['BUG-FIX-WORKFLOW', 'FEATURE-DEVELOPMENT-WORKFLOW', 'SECURITY-REVIEW-WORKFLOW'], $result);
    }

    public function testSelectWorkflowShortCircuitsOnAClarificationRequiredGoal(): void
    {
        $result = $this->selector()->selectWorkflow(['status' => 'clarification-required'], []);

        $this->assertSame('CLARIFICATION_REQUIRED', $result['status']);
        $this->assertNull($result['primary_workflow']);
    }

    public function testSelectWorkflowShortCircuitsOnABlockedGoal(): void
    {
        $result = $this->selector()->selectWorkflow(['status' => 'blocked'], []);

        $this->assertSame('BLOCKED', $result['status']);
    }

    public function testSelectWorkflowRejectsACandidateThatDoesNotExist(): void
    {
        $result = $this->selector()->selectWorkflow(['status' => 'planned'], [
            ['name' => 'NONEXISTENT-WORKFLOW', 'outcome_fit' => 5],
        ]);

        $this->assertSame('UNSUPPORTED', $result['status']);
        $this->assertStringContainsString('does not exist', $result['rejected_candidates'][0]['rejection_reason']);
    }

    public function testSelectWorkflowRejectsACandidateRequiringAnInactiveDomain(): void
    {
        $result = $this->selector()->selectWorkflow(
            ['status' => 'planned', 'active_domains' => []],
            [['name' => 'BUG-FIX-WORKFLOW', 'domain' => 'wordpress_plugin', 'outcome_fit' => 5]]
        );

        $this->assertSame('UNSUPPORTED', $result['status']);
        $this->assertStringContainsString('not active', $result['rejected_candidates'][0]['rejection_reason']);
    }

    public function testSelectWorkflowAcceptsACandidateWithAnActiveDomain(): void
    {
        $result = $this->selector()->selectWorkflow(
            ['status' => 'planned', 'active_domains' => ['wordpress_plugin']],
            [['name' => 'BUG-FIX-WORKFLOW', 'domain' => 'wordpress_plugin', 'outcome_fit' => 5]]
        );

        $this->assertSame('SELECTED', $result['status']);
        $this->assertSame('BUG-FIX-WORKFLOW', $result['primary_workflow']);
    }

    public function testSelectWorkflowHonorsAnExplicitRejectionReason(): void
    {
        $result = $this->selector()->selectWorkflow(['status' => 'planned'], [
            ['name' => 'BUG-FIX-WORKFLOW', 'rejection_reason' => 'Permission boundary exceeded.'],
        ]);

        $this->assertSame('Permission boundary exceeded.', $result['rejected_candidates'][0]['rejection_reason']);
    }

    public function testSelectWorkflowWithNoAcceptedCandidatesIsUnsupported(): void
    {
        $result = $this->selector()->selectWorkflow(['status' => 'planned'], []);

        $this->assertSame('UNSUPPORTED', $result['status']);
    }

    public function testSelectWorkflowPicksTheHighestScoringCandidate(): void
    {
        $result = $this->selector()->selectWorkflow(['status' => 'planned'], [
            ['name' => 'BUG-FIX-WORKFLOW', 'outcome_fit' => 2, 'simplicity' => 2],
            ['name' => 'FEATURE-DEVELOPMENT-WORKFLOW', 'outcome_fit' => 5, 'simplicity' => 5],
        ]);

        $this->assertSame('SELECTED', $result['status']);
        $this->assertSame('FEATURE-DEVELOPMENT-WORKFLOW', $result['primary_workflow']);
        $this->assertStringContainsString('FEATURE-DEVELOPMENT-WORKFLOW', $result['rationale']);
    }

    public function testHighRiskWithoutRequiredSupportingWorkflowsIsSelectedWithLimitations(): void
    {
        $result = $this->selector()->selectWorkflow(
            ['status' => 'planned', 'initial_risk' => 'high'],
            [['name' => 'BUG-FIX-WORKFLOW', 'outcome_fit' => 5]]
        );

        $this->assertSame('SELECTED_WITH_LIMITATIONS', $result['status']);
        $this->assertStringContainsString('security_review', $result['limitations'][0]);
    }

    public function testHighRiskWithRequiredSupportingWorkflowsIsFullySelected(): void
    {
        $result = $this->selector()->selectWorkflow(
            ['status' => 'planned', 'initial_risk' => 'high'],
            [['name' => 'BUG-FIX-WORKFLOW', 'outcome_fit' => 5]],
            [
                ['name' => 'SECURITY-REVIEW-WORKFLOW', 'purpose' => 'security_review'],
                ['name' => 'TESTING-WORKFLOW', 'purpose' => 'testing'],
            ]
        );

        $this->assertSame('SELECTED', $result['status']);
        $this->assertSame(['SECURITY-REVIEW-WORKFLOW', 'TESTING-WORKFLOW'], $result['supporting_workflows']);
    }

    public function testMissingCapabilitiesOnThePrimaryWorkflowAreRecordedAsALimitation(): void
    {
        $result = $this->selector()->selectWorkflow(['status' => 'planned'], [
            ['name' => 'BUG-FIX-WORKFLOW', 'outcome_fit' => 5, 'missing_capabilities' => ['staging_environment']],
        ]);

        $this->assertSame('SELECTED_WITH_LIMITATIONS', $result['status']);
        $this->assertStringContainsString('staging_environment', $result['limitations'][0]);
    }
}
