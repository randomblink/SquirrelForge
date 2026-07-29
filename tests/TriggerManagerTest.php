<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Automation\TriggerManager;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;

final class TriggerManagerTest extends TestCase
{
    public function testTriggerWithoutIdIsInvalid(): void
    {
        $manager = new TriggerManager();

        $result = $manager->evaluate(['condition' => ['type' => 'boolean', 'field' => 'a', 'equals' => true]]);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testTriggerWithNeitherConditionNorRequiresIsInvalid(): void
    {
        $manager = new TriggerManager();

        $result = $manager->evaluate(['id' => 't1']);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testTriggerIdAlreadyInCauseChainIsBlockedByReference(): void
    {
        $manager = new TriggerManager(new RuleEngine());

        $result = $manager->evaluate(
            ['id' => 't1', 'condition' => ['type' => 'boolean', 'field' => 'a', 'equals' => true]],
            ['a' => true],
            ['t0', 't1']
        );

        $this->assertSame('blocked-by-reference', $result['outcome']);
    }

    public function testConditionMapsRuleEngineResultsToTriggerOutcomes(): void
    {
        $manager = new TriggerManager(new RuleEngine());
        $trigger = ['id' => 't1', 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true]];

        $satisfied = $manager->evaluate($trigger, ['ready' => true]);
        $unsatisfied = (new TriggerManager(new RuleEngine()))->evaluate($trigger, ['ready' => false]);
        $deferred = (new TriggerManager(new RuleEngine()))->evaluate($trigger, []);

        $this->assertSame('satisfied', $satisfied['outcome']);
        $this->assertSame('unsatisfied', $unsatisfied['outcome']);
        $this->assertSame('deferred', $deferred['outcome']);
    }

    public function testConditionTriggerWithoutARuleEngineConfiguredIsInvalid(): void
    {
        $manager = new TriggerManager();

        $result = $manager->evaluate(['id' => 't1', 'condition' => ['type' => 'boolean', 'field' => 'a', 'equals' => true]], ['a' => true]);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('Rule Engine is not configured', (string) $result['error']);
    }

    public function testCorrelationIsDeferredUntilAllRequiredSignalsArrive(): void
    {
        $manager = new TriggerManager();
        $trigger = ['id' => 't1', 'requires' => ['event.a', 'event.b'], 'window_seconds' => 60];

        $beforeAnySignal = $manager->evaluate($trigger);
        $manager->recordSignal('t1', 'event.a');
        $afterOneSignal = $manager->evaluate($trigger);
        $manager->recordSignal('t1', 'event.b');
        $afterBothSignals = $manager->evaluate($trigger);

        $this->assertSame('deferred', $beforeAnySignal['outcome']);
        $this->assertSame('deferred', $afterOneSignal['outcome']);
        $this->assertSame('satisfied', $afterBothSignals['outcome']);
    }

    public function testCorrelationExcludesSignalsOutsideTheWindow(): void
    {
        $now = new DateTimeImmutable('2026-07-29T12:00:00Z');
        $manager = new TriggerManager(clock: fn() => $now);
        $trigger = ['id' => 't1', 'requires' => ['event.a', 'event.b'], 'window_seconds' => 30];

        $manager->recordSignal('t1', 'event.a', $now->modify('-45 seconds'));
        $manager->recordSignal('t1', 'event.b', $now);

        $result = $manager->evaluate($trigger);

        $this->assertSame('deferred', $result['outcome']);
    }

    public function testCorrelationThenConditionBothMustBeSatisfied(): void
    {
        $manager = new TriggerManager(new RuleEngine());
        $trigger = [
            'id' => 't1',
            'requires' => ['event.a'],
            'window_seconds' => 60,
            'condition' => ['type' => 'boolean', 'field' => 'approved', 'equals' => true],
        ];

        $manager->recordSignal('t1', 'event.a');
        $correlationOnlyResult = $manager->evaluate($trigger, ['approved' => false]);

        $this->assertSame('unsatisfied', $correlationOnlyResult['outcome']);
    }

    public function testSatisfiedTriggerIsDuplicateWithinCooldown(): void
    {
        $now = new DateTimeImmutable('2026-07-29T12:00:00Z');
        $manager = new TriggerManager(new RuleEngine(), clock: fn() => $now);
        $trigger = ['id' => 't1', 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true], 'cooldown_seconds' => 300];

        $first = $manager->evaluate($trigger, ['ready' => true]);
        $second = $manager->evaluate($trigger, ['ready' => true]);

        $this->assertSame('satisfied', $first['outcome']);
        $this->assertSame('duplicate', $second['outcome']);
    }

    public function testTriggerFiresAgainAfterCooldownElapses(): void
    {
        $currentTime = new DateTimeImmutable('2026-07-29T12:00:00Z');
        $manager = new TriggerManager(new RuleEngine(), clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });
        $trigger = ['id' => 't1', 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true], 'cooldown_seconds' => 60];

        $first = $manager->evaluate($trigger, ['ready' => true]);
        $currentTime = $currentTime->modify('+61 seconds');
        $second = $manager->evaluate($trigger, ['ready' => true]);

        $this->assertSame('satisfied', $first['outcome']);
        $this->assertSame('satisfied', $second['outcome']);
    }

    public function testEvaluationDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('trigger.satisfied', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $manager = new TriggerManager(new RuleEngine(), events: $events);
        $manager->evaluate(['id' => 't1', 'condition' => ['type' => 'boolean', 'field' => 'ready', 'equals' => true]], ['ready' => true]);

        $this->assertCount(1, $captured);
    }
}
