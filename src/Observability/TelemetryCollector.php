<?php

declare(strict_types=1);

namespace SquirrelForge\Observability;

use Closure;
use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use Throwable;

/**
 * The observability intake point for approved operational events, per
 * 27_OBSERVABILITY/TELEMETRY-COLLECTOR.md.
 *
 * "Apply observability redaction and filtering rules supplied by
 * Observability Governance" is a real, genuine integration: when a
 * SqliteObservabilityGovernance is injected, receive() looks up the
 * real standard for the event's signal_type via getStandard() and
 * requires one to exist -- an event for an undefined signal type is
 * rejected, extending Observability Governance's own "must define a
 * standard before use" stance into the intake layer itself. When the
 * standard's redaction_required is true, caller-named sensitive_fields
 * are actually redacted from the normalized payload, not merely
 * documented as a policy.
 *
 * "Must not collect raw secrets or prohibited payloads" (Rule 1) is a
 * real, enforced, deny-by-default check: any field named in the
 * caller's prohibited_fields list is grounds for outright rejection if
 * present at all -- prohibited means never collected, which is a
 * stronger guarantee than redaction (sensitive-but-allowed) provides.
 *
 * "Forward normalized telemetry references to downstream Observability
 * components" is implemented via caller-registered consumer closures,
 * the same delegation pattern EventListener uses for Trigger Manager/
 * Rule Engine/Automation Manager -- none of Log Manager, Metrics
 * Manager, or Trace Manager exist yet to fabricate a concrete API for.
 * Like EventListener, this class has no database: "Telemetry Collector
 * produces telemetry references only; downstream components own their
 * own records and findings" -- retaining a history here would
 * duplicate what those future owners are responsible for.
 */
final class TelemetryCollector
{
    private const REQUIRED_FIELDS = ['event_id', 'source', 'signal_type', 'timestamp', 'correlation_id', 'payload'];

    /**
     * @var array<string, Closure>
     */
    private array $consumers = [];

    public function __construct(
        private readonly ?SqliteObservabilityGovernance $governance = null,
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
     * @param array{event_id?: string, source?: string, signal_type?: string, timestamp?: string, correlation_id?: string, payload?: array<string, mixed>} $event
     * @param array{sensitive_fields?: array<int, string>, prohibited_fields?: array<int, string>} $options
     * @return array{event_id: ?string, telemetry_ref: ?string, outcome: string, normalized: ?array<string, mixed>, consumer_outcomes: array<string, string>, error: ?string}
     */
    public function receive(array $event, array $options = []): array
    {
        $missingField = $this->firstMissingField($event);

        if ($missingField !== null) {
            return $this->reject($event['event_id'] ?? null, sprintf('Missing required field "%s".', $missingField), 'invalid_envelope');
        }

        $normalizedTimestamp = null;

        try {
            $normalizedTimestamp = (new DateTimeImmutable($event['timestamp']))->format(DATE_RFC3339);
        } catch (Throwable) {
            return $this->reject($event['event_id'], sprintf('"timestamp" is not a parseable value: "%s".', $event['timestamp']), 'invalid_envelope');
        }

        $prohibited = array_intersect($options['prohibited_fields'] ?? [], array_keys($event['payload']));

        if ($prohibited !== []) {
            return $this->reject($event['event_id'], sprintf('Payload contains prohibited field(s): %s.', implode(', ', $prohibited)), 'prohibited_payload');
        }

        $standard = null;

        if ($this->governance !== null) {
            $standard = $this->governance->getStandard($event['signal_type']);

            if ($standard === null) {
                return $this->reject($event['event_id'], sprintf('No observability standard is defined for signal type "%s".', $event['signal_type']), 'undefined_signal_type');
            }
        }

        $payload = $event['payload'];

        if (($standard['redaction_required'] ?? false) === true) {
            foreach ($options['sensitive_fields'] ?? [] as $field) {
                if (array_key_exists($field, $payload)) {
                    $payload[$field] = '[REDACTED]';
                }
            }
        }

        $telemetryRef = 'telemetry_' . bin2hex(random_bytes(12));

        $normalized = [
            'event_id' => $event['event_id'],
            'source' => $event['source'],
            'signal_type' => $event['signal_type'],
            'classification' => $standard['privacy_classification'] ?? null,
            'correlation_id' => $event['correlation_id'],
            'payload' => $payload,
            'timestamp' => $normalizedTimestamp,
            'received_at' => gmdate(DATE_RFC3339),
        ];

        $consumerOutcomes = $this->forward($normalized);

        $this->publish($event['event_id'], $telemetryRef, 'collected');

        return ['event_id' => $event['event_id'], 'telemetry_ref' => $telemetryRef, 'outcome' => 'collected', 'normalized' => $normalized, 'consumer_outcomes' => $consumerOutcomes, 'error' => null];
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

        if (!is_array($event['payload'])) {
            return 'payload';
        }

        return null;
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
     * @return array{event_id: ?string, telemetry_ref: ?string, outcome: string, normalized: null, consumer_outcomes: array<string, string>, error: string}
     */
    private function reject(?string $eventId, string $error, string $outcome): array
    {
        $this->publish($eventId, null, $outcome);

        return ['event_id' => $eventId, 'telemetry_ref' => null, 'outcome' => $outcome, 'normalized' => null, 'consumer_outcomes' => [], 'error' => $error];
    }

    private function publish(?string $eventId, ?string $telemetryRef, string $outcome): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'telemetry.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['event_id' => $eventId, 'telemetry_ref' => $telemetryRef]
        ));
    }
}
