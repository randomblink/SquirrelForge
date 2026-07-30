<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\EngineValidation;

final class EngineValidationTest extends TestCase
{
    private function item(string $id, string $stage, string $status, array $overrides = []): array
    {
        return array_merge(['item_id' => $id, 'stage' => $stage, 'required' => true, 'status' => $status], $overrides);
    }

    public function testAllRequiredItemsPassedIsAccepted(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate([
            $this->item('i1', 'INPUT', 'PASSED'),
            $this->item('i2', 'ACCEPTANCE', 'PASSED'),
        ]);

        $this->assertSame('ACCEPTED', $result['decision']);
    }

    public function testNotRequiredItemsDoNotPreventAcceptance(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate([
            $this->item('i1', 'INPUT', 'PASSED'),
            $this->item('i2', 'QUALITY', 'NOT_REQUIRED', ['required' => false]),
        ]);

        $this->assertSame('ACCEPTED', $result['decision']);
    }

    public function testNonWaivableFailureIsRejected(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate([$this->item('i1', 'RULE_POLICY', 'FAILED', ['waivable' => false])]);

        $this->assertSame('REJECTED', $result['decision']);
    }

    public function testRepairableFailureWithRemainingAttemptsRequiresRepair(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate(
            [$this->item('i1', 'RUNTIME', 'FAILED', ['waivable' => true, 'repairable' => true])],
            ['remaining_attempts' => 2]
        );

        $this->assertSame('REPAIR_REQUIRED', $result['decision']);
    }

    public function testRepairableFailureWithNoRemainingAttemptsIsRejected(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate(
            [$this->item('i1', 'RUNTIME', 'FAILED', ['waivable' => true, 'repairable' => true])],
            ['remaining_attempts' => 0]
        );

        $this->assertSame('REJECTED', $result['decision']);
    }

    public function testNonWaivableUnavailableIsBlocked(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate([$this->item('i1', 'RUNTIME', 'UNAVAILABLE', ['waivable' => false])]);

        $this->assertSame('BLOCKED', $result['decision']);
    }

    public function testWaivableUnavailableIsAcceptedWithLimitations(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate([
            $this->item('i1', 'INPUT', 'PASSED'),
            $this->item('i2', 'QUALITY', 'UNAVAILABLE', ['waivable' => true]),
        ]);

        $this->assertSame('ACCEPTED_WITH_LIMITATIONS', $result['decision']);
    }

    public function testWaivedItemIsAcceptedWithLimitations(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate([$this->item('i1', 'QUALITY', 'WAIVED', ['waivable' => true])]);

        $this->assertSame('ACCEPTED_WITH_LIMITATIONS', $result['decision']);
    }

    public function testRecoveryRequiredFlagIsConsumedNotInferred(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate([$this->item('i1', 'INPUT', 'PASSED')], ['recovery_required' => true]);

        $this->assertSame('RECOVERY_REQUIRED', $result['decision']);
    }

    public function testClarificationNeededFlagIsConsumedNotInferred(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate([$this->item('i1', 'INPUT', 'PASSED')], ['clarification_needed' => true]);

        $this->assertSame('CLARIFICATION_REQUIRED', $result['decision']);
    }

    public function testANonWaivableFailureOutranksAnUnavailableItemInThePriorityLadder(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate([
            $this->item('i1', 'RULE_POLICY', 'FAILED', ['waivable' => false]),
            $this->item('i2', 'QUALITY', 'UNAVAILABLE', ['waivable' => true]),
        ]);

        $this->assertSame('REJECTED', $result['decision']);
    }

    public function testABlockingFailureInAnEarlyStageCancelsLaterStagesEntirely(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate([
            $this->item('i1', 'INPUT', 'FAILED', ['blocking' => true, 'waivable' => true]),
            $this->item('i2', 'RUNTIME', 'PASSED'),
        ]);

        $this->assertSame('CANCELLED', $result['items'][1]['status']);
        $this->assertFalse($result['stages']['RUNTIME']['evaluated']);
    }

    public function testANonBlockingFailureDoesNotCancelLaterStages(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate([
            $this->item('i1', 'INPUT', 'FAILED', ['blocking' => false, 'waivable' => true, 'repairable' => true]),
            $this->item('i2', 'RUNTIME', 'PASSED'),
        ]);

        $this->assertSame('PASSED', $result['items'][1]['status']);
        $this->assertTrue($result['stages']['RUNTIME']['evaluated']);
    }

    public function testSummaryCountsMatchItemStatuses(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate([
            $this->item('i1', 'INPUT', 'PASSED'),
            $this->item('i2', 'STRUCTURE', 'PASSED'),
            $this->item('i3', 'QUALITY', 'NOT_REQUIRED', ['required' => false]),
        ]);

        $this->assertSame(3, $result['summary']['total']);
        $this->assertSame(2, $result['summary']['passed']);
        $this->assertSame(1, $result['summary']['not_required']);
    }

    public function testMalformedStatusDefaultsToFailedConservatively(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate([$this->item('i1', 'INPUT', 'NONSENSE_STATUS')]);

        $this->assertSame('FAILED', $result['items'][0]['status']);
        $this->assertSame('REJECTED', $result['decision']);
    }

    public function testMalformedStageDefaultsToInput(): void
    {
        $engine = new EngineValidation();

        $result = $engine->evaluate([$this->item('i1', 'NOT_A_REAL_STAGE', 'PASSED')]);

        $this->assertSame('INPUT', $result['items'][0]['stage']);
    }
}
