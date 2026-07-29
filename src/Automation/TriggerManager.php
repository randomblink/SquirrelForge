<?php

declare(strict_types=1);

namespace SquirrelForge\Automation;

use Closure;
use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Owns Automation-domain trigger definitions, candidate correlation,
 * deduplication, and recursion protection, per
 * 33_AUTOMATION/TRIGGER-MANAGER.md.
 *
 * Condition evaluation is genuinely delegated to the real RuleEngine
 * rather than reinventing condition logic: a trigger's `condition` is a
 * RuleEngine condition, evaluated via RuleEngine::evaluate(). RuleEngine's
 * three "can't determine yet" results (conditional/deferred/input_missing)
 * all map to this class's own `deferred` outcome -- they mean the same
 * thing from a trigger's perspective. A trigger with a condition but no
 * RuleEngine configured is `invalid`, not silently treated as satisfied
 * or unsatisfied: unlike an optional enhancement dependency, the
 * condition *is* the trigger's whole point, so skipping it would be
 * dishonest rather than a documented reduction in scope.
 *
 * "Correlate composite trigger candidates across defined windows" is a
 * real sliding window over recorded signals via recordSignal(): a
 * trigger's `requires` list of signal names is satisfied when every name
 * has a recorded occurrence within the last `window_seconds`. This only
 * distinguishes `satisfied` (all present) from `deferred` (still
 * missing some, could still arrive) -- there is deliberately no
 * "expired -> unsatisfied" state, since a sliding window has no natural
 * expiry moment without the caller supplying an explicit window-start
 * reference, which the spec doesn't describe.
 *
 * "Prevent duplicate activation... and trigger storms" is a real
 * cooldown: a trigger that already fired within `cooldown_seconds` is
 * `duplicate` on a repeat evaluation -- a trigger firing too rapidly
 * repeatedly is exactly the storm scenario, so this single mechanism
 * covers both concerns rather than needing a separate one. "Recursive
 * trigger loops, circular chains" is real: the caller passes the
 * growing chain of trigger IDs that led to this evaluation, and a
 * repeat of this trigger's own ID in that chain is `blocked-by-reference`.
 *
 * Like RuleEngine and EventListener, this has no database or audit
 * table -- the spec forbids owning "audit/storage infrastructure" --
 * only bounded in-memory signal/cooldown state and an optional
 * EventBusInterface announcement per evaluation.
 */
final class TriggerManager
{
    /**
     * @var array<string, array<int, array{name: string, at: DateTimeImmutable}>>
     */
    private array $signalHistory = [];

    /**
     * @var array<string, DateTimeImmutable>
     */
    private array $lastFiredAt = [];

    public function __construct(
        private readonly ?RuleEngine $ruleEngine = null,
        private readonly ?Closure $clock = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    public function recordSignal(string $triggerId, string $signalName, ?DateTimeImmutable $at = null): void
    {
        $this->signalHistory[$triggerId][] = ['name' => $signalName, 'at' => $at ?? $this->now()];
    }

    /**
     * @param array{id: string, condition?: array<string, mixed>, requires?: array<int, string>, window_seconds?: int, cooldown_seconds?: int} $trigger
     * @param array<string, mixed> $context forwarded to RuleEngine when the trigger has a condition
     * @param array<int, string> $causeChain the trigger IDs that led to this evaluation, for recursion protection
     * @return array{trigger_id: ?string, outcome: string, evidence: array<string, mixed>, error: ?string}
     */
    public function evaluate(array $trigger, array $context = [], array $causeChain = []): array
    {
        $triggerId = $trigger['id'] ?? null;

        if (!is_string($triggerId) || $triggerId === '') {
            return $this->finish(null, 'invalid', [], 'Trigger definition requires a non-empty "id".');
        }

        if (!isset($trigger['condition']) && !isset($trigger['requires'])) {
            return $this->finish($triggerId, 'invalid', [], 'Trigger definition requires a "condition" and/or a "requires" list.');
        }

        if (in_array($triggerId, $causeChain, true)) {
            return $this->finish($triggerId, 'blocked-by-reference', ['cause_chain' => $causeChain], sprintf('Trigger "%s" already appears in its own cause chain.', $triggerId));
        }

        $cooldownSeconds = $trigger['cooldown_seconds'] ?? 0;

        if ($cooldownSeconds > 0 && isset($this->lastFiredAt[$triggerId])) {
            $secondsSinceLastFired = $this->now()->getTimestamp() - $this->lastFiredAt[$triggerId]->getTimestamp();

            if ($secondsSinceLastFired < $cooldownSeconds) {
                return $this->finish($triggerId, 'duplicate', ['seconds_since_last_fired' => $secondsSinceLastFired, 'cooldown_seconds' => $cooldownSeconds], null);
            }
        }

        if (isset($trigger['requires'])) {
            $correlation = $this->evaluateCorrelation($triggerId, $trigger['requires'], $trigger['window_seconds'] ?? 60);

            if ($correlation !== 'satisfied') {
                return $this->finish($triggerId, $correlation, ['requires' => $trigger['requires']], null);
            }
        }

        if (isset($trigger['condition'])) {
            if ($this->ruleEngine === null) {
                return $this->finish($triggerId, 'invalid', [], 'Rule Engine is not configured; cannot evaluate this trigger\'s condition.');
            }

            $ruleResult = $this->ruleEngine->evaluate(['id' => $triggerId, 'condition' => $trigger['condition']], $context);
            $outcome = match ($ruleResult['result']) {
                'pass' => 'satisfied',
                'fail' => 'unsatisfied',
                default => 'deferred',
            };

            if ($outcome !== 'satisfied') {
                return $this->finish($triggerId, $outcome, ['rule_result' => $ruleResult], null);
            }
        }

        $this->lastFiredAt[$triggerId] = $this->now();

        return $this->finish($triggerId, 'satisfied', [], null);
    }

    /**
     * @param array<int, string> $requiredSignals
     */
    private function evaluateCorrelation(string $triggerId, array $requiredSignals, int $windowSeconds): string
    {
        $cutoff = $this->now()->getTimestamp() - $windowSeconds;
        $recent = array_values(array_filter(
            $this->signalHistory[$triggerId] ?? [],
            static fn(array $signal): bool => $signal['at']->getTimestamp() >= $cutoff
        ));
        $this->signalHistory[$triggerId] = $recent;

        $presentNames = array_unique(array_column($recent, 'name'));
        $missing = array_diff($requiredSignals, $presentNames);

        return $missing === [] ? 'satisfied' : 'deferred';
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }

    /**
     * @param array<string, mixed> $evidence
     * @return array{trigger_id: ?string, outcome: string, evidence: array<string, mixed>, error: ?string}
     */
    private function finish(?string $triggerId, string $outcome, array $evidence, ?string $error): array
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'trigger.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['trigger_id' => $triggerId, 'outcome' => $outcome]
        ));

        return ['trigger_id' => $triggerId, 'outcome' => $outcome, 'evidence' => $evidence, 'error' => $error];
    }
}
