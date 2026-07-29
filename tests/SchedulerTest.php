<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Automation\Scheduler;
use SquirrelForge\Automation\TriggerManager;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;

final class SchedulerTest extends TestCase
{
    public function testRegisterRejectsUnknownType(): void
    {
        $scheduler = new Scheduler();

        $result = $scheduler->register('s1', ['type' => 'telepathic']);

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testRegisterRejectsMissingRequiredFieldForType(): void
    {
        $scheduler = new Scheduler();

        $result = $scheduler->register('s1', ['type' => 'interval']);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('every_seconds', (string) $result['error']);
    }

    public function testCheckDueOnUnregisteredScheduleIsNotFound(): void
    {
        $scheduler = new Scheduler();

        $result = $scheduler->checkDue('ghost');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testOneTimeFiresOnceAtOrAfterItsMomentThenNeverAgain(): void
    {
        $currentTime = new DateTimeImmutable('2026-07-29T09:00:00Z');
        $scheduler = new Scheduler(clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });
        $scheduler->register('s1', ['type' => 'one_time', 'at' => '2026-07-29T10:00:00Z']);

        $tooEarly = $scheduler->checkDue('s1');
        $currentTime = new DateTimeImmutable('2026-07-29T10:00:00Z');
        $onTime = $scheduler->checkDue('s1');
        $afterFiring = $scheduler->checkDue('s1');

        $this->assertFalse($tooEarly['due']);
        $this->assertTrue($onTime['due']);
        $this->assertFalse($afterFiring['due']);
    }

    public function testDelayedFiresOnceAfterTheConfiguredDelayFromItsStartingMoment(): void
    {
        $currentTime = new DateTimeImmutable('2026-07-29T09:00:00Z');
        $scheduler = new Scheduler(clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });
        $scheduler->register('s1', ['type' => 'delayed', 'from' => '2026-07-29T09:00:00Z', 'delay_seconds' => 300]);

        $tooEarly = $scheduler->checkDue('s1');
        $currentTime = $currentTime->modify('+301 seconds');
        $due = $scheduler->checkDue('s1');

        $this->assertFalse($tooEarly['due']);
        $this->assertTrue($due['due']);
    }

    public function testIntervalFiresRepeatedlyOnceEachElapsedPeriod(): void
    {
        $currentTime = new DateTimeImmutable('2026-07-29T09:00:00Z');
        $scheduler = new Scheduler(clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });
        $scheduler->register('s1', ['type' => 'interval', 'every_seconds' => 60, 'anchor' => '2026-07-29T09:00:00Z']);

        $tooEarly = $scheduler->checkDue('s1');
        $currentTime = $currentTime->modify('+61 seconds');
        $firstFire = $scheduler->checkDue('s1');
        $immediatelyAfter = $scheduler->checkDue('s1');
        $currentTime = $currentTime->modify('+61 seconds');
        $secondFire = $scheduler->checkDue('s1');

        $this->assertFalse($tooEarly['due']);
        $this->assertTrue($firstFire['due']);
        $this->assertFalse($immediatelyAfter['due']);
        $this->assertTrue($secondFire['due']);
    }

    public function testRecurringFiresOncePerDayAtOrAfterTheTimeOfDay(): void
    {
        $currentTime = new DateTimeImmutable('2026-07-29T08:00:00Z');
        $scheduler = new Scheduler(clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });
        $scheduler->register('s1', ['type' => 'recurring', 'time_of_day' => '09:00']);

        $beforeTime = $scheduler->checkDue('s1');
        $currentTime = new DateTimeImmutable('2026-07-29T09:30:00Z');
        $dueToday = $scheduler->checkDue('s1');
        $laterSameDay = $scheduler->checkDue('s1');
        $currentTime = new DateTimeImmutable('2026-07-30T09:30:00Z');
        $dueNextDay = $scheduler->checkDue('s1');

        $this->assertFalse($beforeTime['due']);
        $this->assertTrue($dueToday['due']);
        $this->assertFalse($laterSameDay['due']);
        $this->assertTrue($dueNextDay['due']);
    }

    public function testCalendarFiresOnlyOnListedDatesOncePerDate(): void
    {
        $currentTime = new DateTimeImmutable('2026-07-29T12:00:00Z');
        $scheduler = new Scheduler(clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });
        $scheduler->register('s1', ['type' => 'calendar', 'dates' => ['2026-07-29', '2026-12-25']]);

        $onListedDate = $scheduler->checkDue('s1');
        $again = $scheduler->checkDue('s1');
        $currentTime = new DateTimeImmutable('2026-07-30T12:00:00Z');
        $unlistedDate = $scheduler->checkDue('s1');

        $this->assertTrue($onListedDate['due']);
        $this->assertFalse($again['due']);
        $this->assertFalse($unlistedDate['due']);
    }

    public function testCronMatchesExactMinuteAndHour(): void
    {
        $currentTime = new DateTimeImmutable('2026-07-29T09:30:00Z');
        $scheduler = new Scheduler(clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });
        $scheduler->register('s1', ['type' => 'cron', 'expression' => '30 9 * * *']);

        $matching = $scheduler->checkDue('s1');
        $currentTime = new DateTimeImmutable('2026-07-29T09:31:00Z');
        $nonMatching = $scheduler->checkDue('s1');

        $this->assertTrue($matching['due']);
        $this->assertFalse($nonMatching['due']);
    }

    public function testCronSupportsStepValues(): void
    {
        $currentTime = new DateTimeImmutable('2026-07-29T09:15:00Z');
        $scheduler = new Scheduler(clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });
        $scheduler->register('s1', ['type' => 'cron', 'expression' => '*/15 * * * *']);

        $onStep = $scheduler->checkDue('s1');
        $currentTime = new DateTimeImmutable('2026-07-29T09:16:00Z');
        $offStep = $scheduler->checkDue('s1');

        $this->assertTrue($onStep['due']);
        $this->assertFalse($offStep['due']);
    }

    public function testBlackoutWindowSuppressesAnOtherwiseDueSchedule(): void
    {
        $currentTime = new DateTimeImmutable('2026-07-29T02:00:00Z');
        $scheduler = new Scheduler(ruleEngine: new RuleEngine(), clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });
        $scheduler->register('s1', [
            'type' => 'one_time',
            'at' => '2026-07-29T02:00:00Z',
            'blackout_windows' => [['start' => '00:00', 'end' => '04:00']],
        ]);

        $result = $scheduler->checkDue('s1');

        $this->assertFalse($result['due']);
        $this->assertSame('suppressed_by_blackout', $result['outcome']);
    }

    public function testBlackoutWindowIsIgnoredWithoutARuleEngineConfigured(): void
    {
        $currentTime = new DateTimeImmutable('2026-07-29T02:00:00Z');
        $scheduler = new Scheduler(clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });
        $scheduler->register('s1', [
            'type' => 'one_time',
            'at' => '2026-07-29T02:00:00Z',
            'blackout_windows' => [['start' => '00:00', 'end' => '04:00']],
        ]);

        $result = $scheduler->checkDue('s1');

        $this->assertTrue($result['due']);
    }

    public function testDueScheduleFeedsALinkedTriggerAsACorrelatedSignal(): void
    {
        $currentTime = new DateTimeImmutable('2026-07-29T09:00:00Z');
        $triggers = new TriggerManager(clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });
        $scheduler = new Scheduler($triggers, clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });
        $scheduler->register('s1', ['type' => 'one_time', 'at' => '2026-07-29T09:00:00Z', 'linked_trigger_id' => 't1']);

        $scheduler->checkDue('s1');
        $triggerResult = $triggers->evaluate(['id' => 't1', 'requires' => ['schedule.due:s1'], 'window_seconds' => 60]);

        $this->assertSame('satisfied', $triggerResult['outcome']);
    }

    public function testCheckDueDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('schedule.due', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $currentTime = new DateTimeImmutable('2026-07-29T09:00:00Z');
        $scheduler = new Scheduler(events: $events, clock: function () use (&$currentTime): DateTimeImmutable {
            return $currentTime;
        });
        $scheduler->register('s1', ['type' => 'one_time', 'at' => '2026-07-29T09:00:00Z']);
        $scheduler->checkDue('s1');

        $this->assertCount(1, $captured);
    }
}
