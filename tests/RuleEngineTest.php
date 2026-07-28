<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;

final class RuleEngineTest extends TestCase
{
    public function testBooleanConditionPassesAndFails(): void
    {
        $engine = new RuleEngine();

        $pass = $engine->evaluate(['id' => 'r1', 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true]], ['ready' => true]);
        $fail = $engine->evaluate(['id' => 'r2', 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true]], ['ready' => false]);

        $this->assertSame('pass', $pass['result']);
        $this->assertSame('fail', $fail['result']);
    }

    public function testBooleanConditionWithMissingFieldIsInputMissing(): void
    {
        $engine = new RuleEngine();

        $result = $engine->evaluate(['id' => 'r1', 'condition' => ['type' => 'boolean', 'field' => 'ready']], []);

        $this->assertSame('input_missing', $result['result']);
    }

    /**
     * @return array<string, array{0: string, 1: float, 2: bool}>
     */
    public static function thresholdProvider(): array
    {
        return [
            '>' => ['>', 80.0, false],
            '>= exact' => ['>=', 75.0, true],
            '<' => ['<', 80.0, true],
            '<= exact' => ['<=', 75.0, true],
            '==' => ['==', 75.0, true],
            '!=' => ['!=', 75.0, false],
        ];
    }

    #[DataProvider('thresholdProvider')]
    public function testThresholdOperators(string $operator, float $value, bool $expectedPass): void
    {
        $engine = new RuleEngine();

        $result = $engine->evaluate(['id' => 'r1', 'condition' => ['type' => 'threshold', 'field' => 'cpu', 'operator' => $operator, 'value' => $value]], ['cpu' => 75.0]);

        $this->assertSame($expectedPass ? 'pass' : 'fail', $result['result']);
    }

    public function testThresholdWithNonNumericFieldFails(): void
    {
        $engine = new RuleEngine();

        $result = $engine->evaluate(['id' => 'r1', 'condition' => ['type' => 'threshold', 'field' => 'cpu', 'operator' => '>=', 'value' => 50]], ['cpu' => 'high']);

        $this->assertSame('fail', $result['result']);
        $this->assertNotNull($result['error']);
    }

    public function testThresholdWithMissingFieldIsInputMissing(): void
    {
        $engine = new RuleEngine();

        $result = $engine->evaluate(['id' => 'r1', 'condition' => ['type' => 'threshold', 'field' => 'cpu', 'operator' => '>=', 'value' => 50]], []);

        $this->assertSame('input_missing', $result['result']);
    }

    public function testScheduleWindowWithinRegularHours(): void
    {
        $engine = new RuleEngine();

        $result = $engine->evaluate(
            ['id' => 'r1', 'condition' => ['type' => 'schedule_window', 'start' => '09:00', 'end' => '17:00']],
            ['now' => '2026-07-28T12:00:00+00:00']
        );

        $this->assertSame('pass', $result['result']);
    }

    public function testScheduleWindowOutsideRegularHours(): void
    {
        $engine = new RuleEngine();

        $result = $engine->evaluate(
            ['id' => 'r1', 'condition' => ['type' => 'schedule_window', 'start' => '09:00', 'end' => '17:00']],
            ['now' => '2026-07-28T20:00:00+00:00']
        );

        $this->assertSame('fail', $result['result']);
    }

    public function testScheduleWindowWrapsPastMidnight(): void
    {
        $engine = new RuleEngine();

        $insideWrap = $engine->evaluate(
            ['id' => 'r1', 'condition' => ['type' => 'schedule_window', 'start' => '22:00', 'end' => '06:00']],
            ['now' => '2026-07-28T23:30:00+00:00']
        );
        $outsideWrap = $engine->evaluate(
            ['id' => 'r1', 'condition' => ['type' => 'schedule_window', 'start' => '22:00', 'end' => '06:00']],
            ['now' => '2026-07-28T12:00:00+00:00']
        );

        $this->assertSame('pass', $insideWrap['result']);
        $this->assertSame('fail', $outsideWrap['result']);
    }

    public function testScheduleWindowUsesInjectedClockWhenNoExplicitNowIsGiven(): void
    {
        $engine = new RuleEngine(fn() => new \DateTimeImmutable('2026-07-28T12:00:00+00:00'));

        $result = $engine->evaluate(['id' => 'r1', 'condition' => ['type' => 'schedule_window', 'start' => '09:00', 'end' => '17:00']], []);

        $this->assertSame('pass', $result['result']);
    }

    public function testDependencyPassesWhenUpstreamMatchesExpectation(): void
    {
        $engine = new RuleEngine();

        $result = $engine->evaluate(
            ['id' => 'r2', 'condition' => ['type' => 'dependency', 'depends_on' => 'r1', 'expect' => 'pass']],
            ['rule_results' => ['r1' => 'pass']]
        );

        $this->assertSame('pass', $result['result']);
    }

    public function testDependencyIsDeferredWhenUpstreamHasNotRunYet(): void
    {
        $engine = new RuleEngine();

        $result = $engine->evaluate(['id' => 'r2', 'condition' => ['type' => 'dependency', 'depends_on' => 'r1']], []);

        $this->assertSame('deferred', $result['result']);
    }

    public function testCompositeAndIsPassOnlyWhenAllSubconditionsPass(): void
    {
        $engine = new RuleEngine();
        $condition = [
            'type' => 'composite',
            'operator' => 'and',
            'conditions' => [
                ['type' => 'boolean', 'field' => 'a', 'equals' => true],
                ['type' => 'boolean', 'field' => 'b', 'equals' => true],
            ],
        ];

        $allPass = $engine->evaluate(['id' => 'r1', 'condition' => $condition], ['a' => true, 'b' => true]);
        $oneFails = $engine->evaluate(['id' => 'r1', 'condition' => $condition], ['a' => true, 'b' => false]);

        $this->assertSame('pass', $allPass['result']);
        $this->assertSame('fail', $oneFails['result']);
    }

    public function testCompositeAndIsConditionalWhenAnInputIsMissingAndNothingHasFailed(): void
    {
        $engine = new RuleEngine();
        $condition = [
            'type' => 'composite',
            'operator' => 'and',
            'conditions' => [
                ['type' => 'boolean', 'field' => 'a', 'equals' => true],
                ['type' => 'boolean', 'field' => 'b', 'equals' => true],
            ],
        ];

        $result = $engine->evaluate(['id' => 'r1', 'condition' => $condition], ['a' => true]);

        $this->assertSame('conditional', $result['result']);
    }

    public function testCompositeOrShortCircuitsToPassDespiteAMissingInput(): void
    {
        $engine = new RuleEngine();
        $condition = [
            'type' => 'composite',
            'operator' => 'or',
            'conditions' => [
                ['type' => 'boolean', 'field' => 'a', 'equals' => true],
                ['type' => 'boolean', 'field' => 'b', 'equals' => true],
            ],
        ];

        $result = $engine->evaluate(['id' => 'r1', 'condition' => $condition], ['a' => true]);

        $this->assertSame('pass', $result['result']);
    }

    public function testCompositeNotInvertsPassAndFail(): void
    {
        $engine = new RuleEngine();
        $condition = ['type' => 'composite', 'operator' => 'not', 'conditions' => [['type' => 'boolean', 'field' => 'a', 'equals' => true]]];

        $inverted = $engine->evaluate(['id' => 'r1', 'condition' => $condition], ['a' => true]);

        $this->assertSame('fail', $inverted['result']);
    }

    public function testUnknownConditionTypeFailsWithAnError(): void
    {
        $engine = new RuleEngine();

        $result = $engine->evaluate(['id' => 'r1', 'condition' => ['type' => 'telepathy']], []);

        $this->assertSame('fail', $result['result']);
        $this->assertNotNull($result['error']);
    }

    public function testEvaluateAllMakesEarlierResultsAvailableToLaterDependencyRules(): void
    {
        $engine = new RuleEngine();
        $rules = [
            ['id' => 'r1', 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true]],
            ['id' => 'r2', 'condition' => ['type' => 'dependency', 'depends_on' => 'r1']],
        ];

        $result = $engine->evaluateAll($rules, ['ready' => true]);

        $this->assertSame('pass', $result['results']['r1']['result']);
        $this->assertSame('pass', $result['results']['r2']['result']);
    }

    public function testEvaluateAllDetectsDuplicateRuleIds(): void
    {
        $engine = new RuleEngine();
        $rules = [
            ['id' => 'r1', 'condition' => ['type' => 'boolean', 'field' => 'a', 'equals' => true]],
            ['id' => 'r1', 'condition' => ['type' => 'boolean', 'field' => 'b', 'equals' => true]],
        ];

        $result = $engine->evaluateAll($rules, ['a' => true, 'b' => true]);

        $this->assertCount(1, $result['conflicts']);
        $this->assertSame('duplicate_id', $result['conflicts'][0]['type']);
    }

    public function testEvaluateAllDetectsContradictoryBooleanClaimsOnTheSameField(): void
    {
        $engine = new RuleEngine();
        $rules = [
            ['id' => 'r1', 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true]],
            ['id' => 'r2', 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => false]],
        ];

        $result = $engine->evaluateAll($rules, ['ready' => true]);

        $this->assertCount(1, $result['conflicts']);
        $this->assertSame('contradictory_boolean', $result['conflicts'][0]['type']);
    }

    public function testEvaluateAllReportsNoConflictsForNonContradictoryRules(): void
    {
        $engine = new RuleEngine();
        $rules = [
            ['id' => 'r1', 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true]],
            ['id' => 'r2', 'condition' => ['type' => 'threshold', 'field' => 'cpu', 'operator' => '>=', 'value' => 50]],
        ];

        $result = $engine->evaluateAll($rules, ['ready' => true, 'cpu' => 75]);

        $this->assertSame([], $result['conflicts']);
    }
}
