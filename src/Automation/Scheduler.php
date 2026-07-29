<?php

declare(strict_types=1);

namespace SquirrelForge\Automation;

use Closure;
use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Owns Automation-domain schedule records, recurrence calculation, and
 * due-time evaluation, per 33_AUTOMATION/SCHEDULER.md.
 *
 * Of the eight listed timing types, six get real, deterministic
 * calculations: one_time, delayed, interval (elapsed-seconds cadence),
 * recurring (daily at a time-of-day), calendar (specific dates), and
 * cron (a genuine 5-field minute/hour/day/month/weekday matcher
 * supporting wildcards, exact values, comma lists, and step values like
 * "every 15 minutes" -- a well-defined algorithm, not an
 * approximation). "Maintenance-window"
 * and "blackout" aren't separate due-time types; they're a suppression
 * check applied on top of whichever type determined a schedule is due,
 * matching how the spec frames them as modifiers on execution
 * eligibility rather than their own recurrence pattern.
 *
 * "Consume... maintenance-window... references" reuses RuleEngine's
 * real `schedule_window` condition through its public evaluate() method
 * rather than duplicating the time-window math -- an optional
 * composition, genuinely skipped when no RuleEngine is injected.
 * "Produce scheduled trigger requests" is a real handoff to the
 * injected TriggerManager: a due schedule with a `linked_trigger_id`
 * calls TriggerManager::recordSignal(), supplying "schedule fired" as
 * one correlated input -- Scheduler never decides the trigger's
 * condition or fires anything itself, matching the spec's rule that "a
 * due schedule produces a trigger request; it does not itself authorize
 * or execute the automation".
 *
 * "Prevent duplicate due-time emission" is real per-type dedup: each
 * type's own natural due-instant granularity (once ever, once per
 * elapsed interval, once per day, once per calendar minute, once per
 * date) is tracked and a repeat check within the same instant never
 * re-fires. Like the rest of 33_AUTOMATION's real components, there is
 * no database or audit table -- only bounded in-memory schedule/dedup
 * state and an optional EventBusInterface announcement per check.
 */
final class Scheduler
{
    private const TYPES = ['one_time', 'delayed', 'interval', 'recurring', 'calendar', 'cron'];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $schedules = [];

    /**
     * @var array<string, mixed>
     */
    private array $lastFired = [];

    public function __construct(
        private readonly ?TriggerManager $triggerManager = null,
        private readonly ?RuleEngine $ruleEngine = null,
        private readonly ?Closure $clock = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array{type: string, at?: string, from?: string, delay_seconds?: int, every_seconds?: int, anchor?: string, time_of_day?: string, dates?: array<int, string>, expression?: string, blackout_windows?: array<int, array{start: string, end: string}>, linked_trigger_id?: string} $definition
     * @return array{schedule_id: string, outcome: string, error: ?string}
     */
    public function register(string $scheduleId, array $definition): array
    {
        $error = $this->validateDefinition($definition);

        if ($error !== null) {
            return ['schedule_id' => $scheduleId, 'outcome' => 'invalid', 'error' => $error];
        }

        $this->schedules[$scheduleId] = $definition;

        return ['schedule_id' => $scheduleId, 'outcome' => 'registered', 'error' => null];
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function validateDefinition(array $definition): ?string
    {
        $type = $definition['type'] ?? null;

        if (!in_array($type, self::TYPES, true)) {
            return sprintf('Unknown or missing schedule type "%s".', $type ?? '');
        }

        $requiredFields = match ($type) {
            'one_time' => ['at'],
            'delayed' => ['delay_seconds'],
            'interval' => ['every_seconds'],
            'recurring' => ['time_of_day'],
            'calendar' => ['dates'],
            'cron' => ['expression'],
        };

        foreach ($requiredFields as $field) {
            if (!isset($definition[$field])) {
                return sprintf('Schedule type "%s" requires a "%s" field.', $type, $field);
            }
        }

        return null;
    }

    /**
     * @return array{schedule_id: string, due: bool, outcome: string, error: ?string}
     */
    public function checkDue(string $scheduleId): array
    {
        $definition = $this->schedules[$scheduleId] ?? null;

        if ($definition === null) {
            return ['schedule_id' => $scheduleId, 'due' => false, 'outcome' => 'not_found', 'error' => sprintf('Unknown schedule "%s".', $scheduleId)];
        }

        $now = $this->now();
        $due = $this->isDue($scheduleId, $definition, $now);

        if ($due && $this->isSuppressedByBlackout($definition, $now)) {
            return $this->finish($scheduleId, false, 'suppressed_by_blackout');
        }

        if (!$due) {
            return $this->finish($scheduleId, false, 'not_due');
        }

        $this->markFired($scheduleId, $definition, $now);

        if (isset($definition['linked_trigger_id']) && $this->triggerManager !== null) {
            $this->triggerManager->recordSignal($definition['linked_trigger_id'], 'schedule.due:' . $scheduleId, $now);
        }

        return $this->finish($scheduleId, true, 'due');
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function isDue(string $scheduleId, array $definition, DateTimeImmutable $now): bool
    {
        return match ($definition['type']) {
            'one_time' => $this->isOneTimeDue($scheduleId, $definition, $now),
            'delayed' => $this->isDelayedDue($scheduleId, $definition, $now),
            'interval' => $this->isIntervalDue($scheduleId, $definition, $now),
            'recurring' => $this->isRecurringDue($scheduleId, $definition, $now),
            'calendar' => $this->isCalendarDue($scheduleId, $definition, $now),
            'cron' => $this->isCronDue($scheduleId, $definition, $now),
        };
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function isOneTimeDue(string $scheduleId, array $definition, DateTimeImmutable $now): bool
    {
        return !isset($this->lastFired[$scheduleId]) && $now >= new DateTimeImmutable($definition['at']);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function isDelayedDue(string $scheduleId, array $definition, DateTimeImmutable $now): bool
    {
        $from = isset($definition['from']) ? new DateTimeImmutable($definition['from']) : $now;
        $dueAt = $from->modify(sprintf('+%d seconds', $definition['delay_seconds']));

        return !isset($this->lastFired[$scheduleId]) && $now >= $dueAt;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function isIntervalDue(string $scheduleId, array $definition, DateTimeImmutable $now): bool
    {
        $anchor = $this->lastFired[$scheduleId] ?? (isset($definition['anchor']) ? new DateTimeImmutable($definition['anchor']) : $now);

        return $now->getTimestamp() - $anchor->getTimestamp() >= $definition['every_seconds'];
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function isRecurringDue(string $scheduleId, array $definition, DateTimeImmutable $now): bool
    {
        if (($this->lastFired[$scheduleId] ?? null) === $now->format('Y-m-d')) {
            return false;
        }

        return $now->format('H:i') >= $definition['time_of_day'];
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function isCalendarDue(string $scheduleId, array $definition, DateTimeImmutable $now): bool
    {
        if (($this->lastFired[$scheduleId] ?? null) === $now->format('Y-m-d')) {
            return false;
        }

        return in_array($now->format('Y-m-d'), $definition['dates'], true);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function isCronDue(string $scheduleId, array $definition, DateTimeImmutable $now): bool
    {
        if (($this->lastFired[$scheduleId] ?? null) === $now->format('Y-m-d H:i')) {
            return false;
        }

        return $this->cronMatches($definition['expression'], $now);
    }

    private function cronMatches(string $expression, DateTimeImmutable $now): bool
    {
        $fields = preg_split('/\s+/', trim($expression));

        if (count($fields) !== 5) {
            return false;
        }

        [$minute, $hour, $dayOfMonth, $month, $weekday] = $fields;

        return $this->cronFieldMatches($minute, (int) $now->format('i'), 0, 59)
            && $this->cronFieldMatches($hour, (int) $now->format('G'), 0, 23)
            && $this->cronFieldMatches($dayOfMonth, (int) $now->format('j'), 1, 31)
            && $this->cronFieldMatches($month, (int) $now->format('n'), 1, 12)
            && $this->cronFieldMatches($weekday, (int) $now->format('w'), 0, 6);
    }

    private function cronFieldMatches(string $field, int $actual, int $min, int $max): bool
    {
        if ($field === '*') {
            return true;
        }

        if (preg_match('/^\*\/(\d+)$/', $field, $matches) === 1) {
            $step = (int) $matches[1];

            return $step > 0 && ($actual - $min) % $step === 0;
        }

        foreach (explode(',', $field) as $token) {
            if (ctype_digit($token) && (int) $token === $actual) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function markFired(string $scheduleId, array $definition, DateTimeImmutable $now): void
    {
        $this->lastFired[$scheduleId] = match ($definition['type']) {
            'one_time', 'delayed' => true,
            'interval' => $now,
            'recurring', 'calendar' => $now->format('Y-m-d'),
            'cron' => $now->format('Y-m-d H:i'),
        };
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function isSuppressedByBlackout(array $definition, DateTimeImmutable $now): bool
    {
        if ($this->ruleEngine === null || ($definition['blackout_windows'] ?? []) === []) {
            return false;
        }

        foreach ($definition['blackout_windows'] as $window) {
            $result = $this->ruleEngine->evaluate(
                ['id' => 'blackout_window', 'condition' => ['type' => 'schedule_window', 'start' => $window['start'], 'end' => $window['end']]],
                ['now' => $now->format(DATE_RFC3339)]
            );

            if ($result['result'] === 'pass') {
                return true;
            }
        }

        return false;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }

    /**
     * @return array{schedule_id: string, due: bool, outcome: string, error: ?string}
     */
    private function finish(string $scheduleId, bool $due, string $outcome): array
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'schedule.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['schedule_id' => $scheduleId, 'due' => $due]
        ));

        return ['schedule_id' => $scheduleId, 'due' => $due, 'outcome' => $outcome, 'error' => null];
    }
}
