<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\AutomationValidator;

final class AutomationValidatorTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function completeReferences(): array
    {
        return [
            'test' => true,
            'platform_validation' => true,
            'security' => true,
            'policy' => true,
            'observability_coverage' => true,
            'recovery_plan' => true,
        ];
    }

    public function testFullyCompleteDefinitionIsReady(): void
    {
        $validator = new AutomationValidator();

        $result = $validator->validate([
            'triggers' => [['id' => 't1', 'condition' => ['type' => 'boolean', 'field' => 'a', 'equals' => true]]],
            'success_criteria' => ['error rate below 1%'],
            'failure_handling' => 'roll back to previous version',
            'references' => $this->completeReferences(),
        ]);

        $this->assertSame('ready', $result['outcome']);
        $this->assertSame([], $result['issues']);
    }

    public function testDuplicateTriggerIdsAreNotReady(): void
    {
        $validator = new AutomationValidator();

        $result = $validator->validate([
            'triggers' => [
                ['id' => 't1', 'condition' => ['type' => 'boolean', 'field' => 'a', 'equals' => true]],
                ['id' => 't1', 'condition' => ['type' => 'boolean', 'field' => 'b', 'equals' => true]],
            ],
            'success_criteria' => ['x'],
            'failure_handling' => 'y',
            'references' => $this->completeReferences(),
        ]);

        $this->assertSame('not-ready', $result['outcome']);
        $this->assertStringContainsString('Duplicate identifier', $result['issues'][0]);
    }

    public function testScheduleLinkedToUnknownTriggerIsNotReady(): void
    {
        $validator = new AutomationValidator();

        $result = $validator->validate([
            'schedules' => [['id' => 's1', 'linked_trigger_id' => 't_ghost']],
            'success_criteria' => ['x'],
            'failure_handling' => 'y',
            'references' => $this->completeReferences(),
        ]);

        $this->assertSame('not-ready', $result['outcome']);
        $this->assertStringContainsString('unknown trigger', $result['issues'][0]);
    }

    public function testTriggerDependencyOnAnUnknownRuleIsNotReady(): void
    {
        $validator = new AutomationValidator();

        $result = $validator->validate([
            'triggers' => [['id' => 't1', 'condition' => ['type' => 'dependency', 'depends_on' => 'rule_ghost']]],
            'success_criteria' => ['x'],
            'failure_handling' => 'y',
            'references' => $this->completeReferences(),
        ]);

        $this->assertSame('not-ready', $result['outcome']);
        $this->assertStringContainsString('unknown rule/trigger', $result['issues'][0]);
    }

    public function testTriggerDependencyResolvedByAKnownRuleIsCoherent(): void
    {
        $validator = new AutomationValidator();

        $result = $validator->validate([
            'rules' => [['id' => 'r1', 'condition' => ['type' => 'boolean', 'field' => 'a', 'equals' => true]]],
            'triggers' => [['id' => 't1', 'condition' => ['type' => 'dependency', 'depends_on' => 'r1']]],
            'success_criteria' => ['x'],
            'failure_handling' => 'y',
            'references' => $this->completeReferences(),
        ]);

        $this->assertSame('ready', $result['outcome']);
    }

    public function testNestedCompositeDependencyIsCheckedRecursively(): void
    {
        $validator = new AutomationValidator();

        $condition = [
            'type' => 'composite',
            'operator' => 'and',
            'conditions' => [
                ['type' => 'boolean', 'field' => 'a', 'equals' => true],
                ['type' => 'dependency', 'depends_on' => 'rule_ghost'],
            ],
        ];

        $result = $validator->validate([
            'triggers' => [['id' => 't1', 'condition' => $condition]],
            'success_criteria' => ['x'],
            'failure_handling' => 'y',
            'references' => $this->completeReferences(),
        ]);

        $this->assertSame('not-ready', $result['outcome']);
    }

    public function testMissingSuccessCriteriaRequiresRevision(): void
    {
        $validator = new AutomationValidator();

        $result = $validator->validate(['failure_handling' => 'y', 'references' => $this->completeReferences()]);

        $this->assertSame('requires-revision', $result['outcome']);
    }

    public function testBlankSuccessCriteriaEntryRequiresRevision(): void
    {
        $validator = new AutomationValidator();

        $result = $validator->validate([
            'success_criteria' => ['   '],
            'failure_handling' => 'y',
            'references' => $this->completeReferences(),
        ]);

        $this->assertSame('requires-revision', $result['outcome']);
    }

    public function testMissingFailureHandlingRequiresRevision(): void
    {
        $validator = new AutomationValidator();

        $result = $validator->validate(['success_criteria' => ['x'], 'references' => $this->completeReferences()]);

        $this->assertSame('requires-revision', $result['outcome']);
    }

    public function testAnEntirelyUnaddressedReferenceCategoryRequiresRevision(): void
    {
        $validator = new AutomationValidator();
        $references = $this->completeReferences();
        unset($references['security']);

        $result = $validator->validate(['success_criteria' => ['x'], 'failure_handling' => 'y', 'references' => $references]);

        $this->assertSame('requires-revision', $result['outcome']);
        $this->assertSame(['security'], $result['missing_references']);
    }

    public function testAPendingReferenceIsDeferred(): void
    {
        $validator = new AutomationValidator();
        $references = $this->completeReferences();
        $references['security'] = 'pending';

        $result = $validator->validate(['success_criteria' => ['x'], 'failure_handling' => 'y', 'references' => $references]);

        $this->assertSame('deferred', $result['outcome']);
        $this->assertSame(['security'], $result['pending_references']);
    }

    public function testAnExplicitlyFalseReferenceIsReadyWithConditions(): void
    {
        $validator = new AutomationValidator();
        $references = $this->completeReferences();
        $references['recovery_plan'] = false;

        $result = $validator->validate(['success_criteria' => ['x'], 'failure_handling' => 'y', 'references' => $references]);

        $this->assertSame('ready-with-conditions', $result['outcome']);
        $this->assertSame(['recovery_plan'], $result['unsatisfied_references']);
    }

    public function testPriorityLadderPutsStructuralErrorsAboveEverythingElse(): void
    {
        $validator = new AutomationValidator();

        $result = $validator->validate([
            'triggers' => [
                ['id' => 't1', 'condition' => ['type' => 'boolean', 'field' => 'a', 'equals' => true]],
                ['id' => 't1', 'condition' => ['type' => 'boolean', 'field' => 'b', 'equals' => true]],
            ],
            // also missing success_criteria and references -- should still report not-ready, not requires-revision
        ]);

        $this->assertSame('not-ready', $result['outcome']);
    }
}
