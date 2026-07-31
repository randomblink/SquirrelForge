<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\OutputRules;

final class OutputRulesTest extends TestCase
{
    private function blocker(): array
    {
        return [
            'what' => 'Cannot push to main',
            'why' => 'Branch protection requires a passing CI run',
            'owning_phase' => 'release',
            'required_resolution' => 'Wait for CI to complete',
            'non_continuable_actions' => ['merge', 'deploy'],
        ];
    }

    public function testBuildReportRejectsAnInvalidOutcome(): void
    {
        $rules = new OutputRules();

        $result = $rules->buildReport(['outcome' => 'made_up_outcome']);

        $this->assertSame('invalid_request', $result['outcome']);
    }

    public function testBuildReportRejectsAnUnrecognizedValidationStatus(): void
    {
        $rules = new OutputRules();

        $result = $rules->buildReport(['outcome' => 'success', 'validation_items' => ['tests' => 'kinda_passed']]);

        $this->assertSame('invalid_validation_status', $result['outcome']);
    }

    public function testSuccessWithNoValidationItemsIsComplete(): void
    {
        $rules = new OutputRules();

        $result = $rules->buildReport(['outcome' => 'success']);

        $this->assertSame('COMPLETE', $result['report']['type']);
    }

    public function testSuccessWithAllPassingValidationIsComplete(): void
    {
        $rules = new OutputRules();

        $result = $rules->buildReport(['outcome' => 'success', 'validation_items' => ['tests' => 'passed', 'lint' => 'waived', 'e2e' => 'not_applicable']]);

        $this->assertSame('COMPLETE', $result['report']['type']);
    }

    public function testSuccessWithAFailedValidationItemIsCompleteWithLimitations(): void
    {
        $rules = new OutputRules();

        $result = $rules->buildReport(['outcome' => 'success', 'validation_items' => ['tests' => 'passed', 'security_scan' => 'unavailable']]);

        $this->assertSame('COMPLETE_WITH_LIMITATIONS', $result['report']['type']);
    }

    public function testBlockedWithoutABlockerRecordIsRejected(): void
    {
        $rules = new OutputRules();

        $result = $rules->buildReport(['outcome' => 'blocked']);

        $this->assertSame('invalid_blocker', $result['outcome']);
    }

    public function testBlockedWithAnIncompleteBlockerRecordIsRejected(): void
    {
        $rules = new OutputRules();
        $blocker = $this->blocker();
        unset($blocker['required_resolution']);

        $result = $rules->buildReport(['outcome' => 'blocked', 'blocker' => $blocker]);

        $this->assertSame('invalid_blocker', $result['outcome']);
        $this->assertStringContainsString('required_resolution', $result['error']);
    }

    public function testBlockedWithACompleteBlockerRecordSucceeds(): void
    {
        $rules = new OutputRules();

        $result = $rules->buildReport(['outcome' => 'blocked', 'blocker' => $this->blocker()]);

        $this->assertSame('built', $result['outcome']);
        $this->assertSame('BLOCKED', $result['report']['type']);
        $this->assertSame($this->blocker(), $result['report']['blocker']);
    }

    public function testFailedOutcomeProducesAFailedType(): void
    {
        $rules = new OutputRules();

        $result = $rules->buildReport(['outcome' => 'failed']);

        $this->assertSame('FAILED', $result['report']['type']);
    }

    public function testRecoveryRequiredOutcomeProducesThatType(): void
    {
        $rules = new OutputRules();

        $result = $rules->buildReport(['outcome' => 'recovery_required']);

        $this->assertSame('RECOVERY_REQUIRED', $result['report']['type']);
    }

    public function testAReadOnlyRequestTypeOverridesTheDefaultCompleteClassification(): void
    {
        $rules = new OutputRules();

        $result = $rules->buildReport(['outcome' => 'success', 'request_type' => 'read_only']);

        $this->assertSame('READ_ONLY_RESULT', $result['report']['type']);
    }

    public function testPlanOnlyTakesPrecedenceOverEverythingElse(): void
    {
        $rules = new OutputRules();

        $result = $rules->buildReport(['outcome' => 'success', 'request_type' => 'read_only', 'plan_only' => true]);

        $this->assertSame('PLAN_ONLY', $result['report']['type']);
    }

    public function testValidationItemsAreGroupedByTheirRealDeclaredStatus(): void
    {
        $rules = new OutputRules();

        $result = $rules->buildReport(['outcome' => 'success', 'validation_items' => [
            'unit_tests' => 'passed',
            'security_scan' => 'failed',
            'load_test' => 'unavailable',
        ]]);

        $this->assertSame(['unit_tests'], $result['report']['validation']['passed']);
        $this->assertSame(['security_scan'], $result['report']['validation']['failed']);
        $this->assertSame(['load_test'], $result['report']['validation']['unavailable']);
    }

    public function testExplicitlyEmptyChangedArtifactsIsPreservedDistinctlyFromUnset(): void
    {
        $rules = new OutputRules();

        $withNone = $rules->buildReport(['outcome' => 'success', 'changed_artifacts' => []]);
        $unstated = $rules->buildReport(['outcome' => 'success']);

        $this->assertSame([], $withNone['report']['changed_artifacts']);
        $this->assertNull($unstated['report']['changed_artifacts']);
    }

    public function testProhibitedFieldsAreRedactedFromTheEntireReport(): void
    {
        $rules = new OutputRules();

        $result = $rules->buildReport(
            ['outcome' => 'success', 'commits' => ['abc123'], 'next_action' => 'Deploy to staging'],
            ['prohibited_fields' => ['commits']]
        );

        $this->assertArrayNotHasKey('commits', $result['report']);
        $this->assertSame('Deploy to staging', $result['report']['next_action']);
    }
}
