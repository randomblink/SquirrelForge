<?php

declare(strict_types=1);

namespace SquirrelForge\Automation;

use Closure;
use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use Throwable;

/**
 * Receives platform event references, performs Automation-domain
 * structural checks and normalization, and forwards the normalized
 * result to registered consumers, per 33_AUTOMATION/EVENT-LISTENER.md.
 *
 * This does not own the platform event bus -- it's a downstream
 * consumer of one, not a replacement for src/Events/EventBus. It has no
 * database or audit table (the spec forbids owning "logging and audit
 * infrastructure"): the only state kept is a small, bounded set of
 * recently-seen event IDs, which exists purely because duplicate
 * detection is this component's own explicit responsibility, not
 * observability for others.
 *
 * "Classify events for Automation-domain routing" is real and, notably,
 * matches the dot-namespaced event-name convention every other
 * component in this codebase already dispatches under (e.g.
 * `notification.delivered`, `failure.detected`, `resilience.finished`):
 * the category is the segment before the first `.`. "Forward normalized
 * references to Trigger Manager, Rule Engine, or Automation Manager" is
 * implemented via caller-registered consumer closures rather than a
 * fabricated API for those components -- none of them define one, and
 * RuleEngine in particular has no "receive an event" method, only
 * evaluate()/evaluateAll() over context a caller assembles.
 *
 * "Must not rewrite source facts" is honored literally: normalize()
 * never touches the payload, only the envelope's own occurred_at
 * formatting and the addition of a received_at/category alongside it.
 * Approved-source and supported-category checks are both optional
 * allowlists -- genuinely skipped, not silently permissive by
 * fabrication, when the caller hasn't configured one.
 */
final class EventListener
{
    private const MAX_SEEN_EVENT_IDS = 1000;
    private const REQUIRED_FIELDS = ['event_id', 'source', 'name', 'payload'];

    /**
     * @var array<int, string>
     */
    private array $seenEventIds = [];

    /**
     * @var array<string, Closure>
     */
    private array $consumers = [];

    /**
     * @param array<int, string> $approvedSources empty means unrestricted
     * @param array<int, string> $supportedCategories empty means unrestricted
     */
    public function __construct(
        private readonly array $approvedSources = [],
        private readonly array $supportedCategories = [],
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param Closure(array<string, mixed>): mixed $handler
     */
    public function registerConsumer(string $name, Closure $handler): void
    {
        $this->consumers[$name] = $handler;
    }

    /**
     * @param array{event_id?: string, source?: string, name?: string, payload?: array<string, mixed>, occurred_at?: string} $event
     * @return array{event_id: ?string, outcome: string, category: ?string, normalized: ?array<string, mixed>, consumer_outcomes: array<string, string>, error: ?string}
     */
    public function receive(array $event): array
    {
        $missingField = $this->firstMissingField($event);

        if ($missingField !== null) {
            return $this->reject($event['event_id'] ?? null, sprintf('Missing required field "%s".', $missingField), 'invalid_envelope');
        }

        $normalizedOccurredAt = null;

        if (isset($event['occurred_at'])) {
            try {
                $normalizedOccurredAt = (new DateTimeImmutable($event['occurred_at']))->format(DATE_RFC3339);
            } catch (Throwable) {
                return $this->reject($event['event_id'], sprintf('"occurred_at" is not a parseable timestamp: "%s".', $event['occurred_at']), 'invalid_envelope');
            }
        }

        if ($this->approvedSources !== [] && !in_array($event['source'], $this->approvedSources, true)) {
            return $this->reject($event['event_id'], sprintf('Source "%s" is not an approved event source.', $event['source']), 'unapproved_source');
        }

        if (in_array($event['event_id'], $this->seenEventIds, true)) {
            return $this->reject($event['event_id'], sprintf('Event "%s" has already been processed.', $event['event_id']), 'duplicate');
        }

        $category = $this->classify($event['name']);

        if ($this->supportedCategories !== [] && !in_array($category, $this->supportedCategories, true)) {
            return $this->reject($event['event_id'], sprintf('Category "%s" is not supported for automation routing.', $category), 'unsupported_category');
        }

        $this->rememberEventId($event['event_id']);

        $normalized = [
            'event_id' => $event['event_id'],
            'source' => $event['source'],
            'name' => $event['name'],
            'category' => $category,
            'payload' => $event['payload'],
            'occurred_at' => $normalizedOccurredAt,
            'received_at' => gmdate(DATE_RFC3339),
        ];

        $consumerOutcomes = $this->forward($normalized);

        $this->publish($event['event_id'], 'processed');

        return ['event_id' => $event['event_id'], 'outcome' => 'processed', 'category' => $category, 'normalized' => $normalized, 'consumer_outcomes' => $consumerOutcomes, 'error' => null];
    }

    /**
     * @param array<string, mixed> $event
     */
    private function firstMissingField(array $event): ?string
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($event[$field]) || (is_string($event[$field]) && $event[$field] === '')) {
                return $field;
            }
        }

        return null;
    }

    private function classify(string $eventName): string
    {
        $separatorPosition = strpos($eventName, '.');

        return $separatorPosition === false ? 'uncategorized' : substr($eventName, 0, $separatorPosition);
    }

    private function rememberEventId(string $eventId): void
    {
        $this->seenEventIds[] = $eventId;

        if (count($this->seenEventIds) > self::MAX_SEEN_EVENT_IDS) {
            array_shift($this->seenEventIds);
        }
    }

    /**
     * @param array<string, mixed> $normalized
     * @return array<string, string>
     */
    private function forward(array $normalized): array
    {
        $outcomes = [];

        foreach ($this->consumers as $name => $handler) {
            try {
                $handler($normalized);
                $outcomes[$name] = 'delivered';
            } catch (Throwable $e) {
                $outcomes[$name] = 'failed: ' . $e->getMessage();
            }
        }

        return $outcomes;
    }

    /**
     * @return array{event_id: ?string, outcome: string, category: ?string, normalized: ?array<string, mixed>, consumer_outcomes: array<string, string>, error: string}
     */
    private function reject(?string $eventId, string $error, string $outcome): array
    {
        $this->publish($eventId, $outcome);

        return ['event_id' => $eventId, 'outcome' => $outcome, 'category' => null, 'normalized' => null, 'consumer_outcomes' => [], 'error' => $error];
    }

    private function publish(?string $eventId, string $outcome): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'automation_event.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['event_id' => $eventId, 'outcome' => $outcome]
        ));
    }
}
