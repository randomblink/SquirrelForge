<?php

declare(strict_types=1);

namespace SquirrelForge\Integration;

use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use Throwable;

/**
 * Interprets integration-domain telemetry and status references to
 * produce integration health, availability, and degradation findings,
 * per 26_INTEGRATIONS/INTEGRATION-MONITOR.md -- the eighth real
 * component in 26_INTEGRATIONS.
 *
 * Rule 1 ("must consume telemetry and status references from owning
 * observability and Integration components") means this class collects
 * no telemetry itself -- every signal (`availability`, `latency_ms`,
 * `error_rate`, `rate_limited`, `authentication_failure`, `timeout`,
 * `degradation`) is caller-supplied evidence, the same "consume, don't
 * compute the underlying measurement" boundary `SqliteFailoverCoordinator`
 * already draws around its own `degraded` flag. What this class actually
 * contributes is real interpretation: a deterministic precedence over
 * the Monitored Integration Signals table into one of the spec's six
 * named Finding States, plus the concrete reasons that produced it --
 * work the spec's own Purpose explicitly assigns ("interpret... to
 * produce... findings"), not caller-supplied evidence to merely pass
 * through.
 *
 * Precedence (most severe first) is a real design decision this spec
 * leaves unstated, since its own signal table is unordered:
 * `Unavailable` (the service cannot serve requests at all) outranks
 * `Authentication Failing` (a total outage makes an auth failure
 * unobservable, not absent), which outranks `Rate Limited`, which
 * outranks the general `Degraded` tier. Unlike the higher tiers, which
 * are single-cause and terminate on the first match, `Degraded` collects
 * every contributing signal (elevated latency, elevated error rate, an
 * explicit timeout, an explicit degradation flag, or a `degraded`
 * availability reference) rather than stopping at the first one, since
 * those causes are not mutually exclusive and reporting all of them is
 * genuinely more useful evidence for the owning components this class
 * reports to.
 *
 * `Unknown` ("required observability or status references are missing
 * or stale") is real too: no signals supplied at all, or an `observed_at`
 * reference supplied but older than `max_staleness_seconds` (an
 * injectable "now" keeps this deterministic in tests) both resolve here
 * rather than silently defaulting to `Healthy` -- the same fail-closed
 * stance `IntegrationManager` already takes toward a missing governance
 * decision.
 *
 * Responsibility 4 ("Provide availability references to
 * CONNECTOR-MANAGER.md, SERVICE-DISCOVERY.md, and INTEGRATION-MANAGER.md")
 * and Rule 5 ("must provide... references... without replacing their
 * ownership") are upheld by mapping a finding to each target's own real,
 * already-defined status vocabulary (`SqliteConnectorManager::LIFECYCLE_STATUSES`,
 * `SqliteServiceDiscovery::AVAILABILITY_STATUSES`) and returning that
 * reference in the result for the caller to apply -- this class never
 * calls either component's own mutation methods itself, which would
 * cross from "reference" into "replace their ownership." Connector
 * Manager's own lifecycle vocabulary has no distinct "unavailable" value
 * (only `active`/`degraded` describe an operable connector; `suspended`/
 * `retired` are governance decisions, not availability findings), so an
 * `Unavailable` finding maps to `null` for that target rather than
 * fabricating a status Connector Manager was never given.
 *
 * Owns no database (Rule 2, "must not maintain observability
 * infrastructure"), matching this layer's other pure coordinators: every
 * evaluation is emitted as an `Event` for the real `27_OBSERVABILITY`
 * owner to record, never persisted here.
 */
final class IntegrationMonitor
{
    private const FINDING_STATES = ['Healthy', 'Degraded', 'Unavailable', 'Rate Limited', 'Authentication Failing', 'Unknown'];

    private const DEFAULT_LATENCY_THRESHOLD_MS = 2000.0;

    private const DEFAULT_ERROR_RATE_THRESHOLD = 0.1;

    private const DEFAULT_MAX_STALENESS_SECONDS = 300;

    /** Maps a finding to SqliteConnectorManager::LIFECYCLE_STATUSES; null where that vocabulary has no matching value. */
    private const CONNECTOR_STATUS_REFERENCE = [
        'Healthy' => 'active',
        'Degraded' => 'degraded',
        'Rate Limited' => 'degraded',
        'Authentication Failing' => 'degraded',
        'Unavailable' => null,
        'Unknown' => null,
    ];

    /** Maps a finding to SqliteServiceDiscovery::AVAILABILITY_STATUSES. */
    private const SERVICE_DISCOVERY_AVAILABILITY_REFERENCE = [
        'Healthy' => 'Available',
        'Degraded' => 'Degraded',
        'Rate Limited' => 'Degraded',
        'Authentication Failing' => 'Degraded',
        'Unavailable' => 'Unavailable',
        'Unknown' => null,
    ];

    public function __construct(private readonly ?EventBusInterface $events = null)
    {
    }

    /**
     * @param array{
     *     availability?: ?string,
     *     latency_ms?: ?float,
     *     latency_threshold_ms?: float,
     *     error_rate?: ?float,
     *     error_rate_threshold?: float,
     *     rate_limited?: bool,
     *     authentication_failure?: bool,
     *     timeout?: bool,
     *     degradation?: bool,
     *     observed_at?: ?string,
     *     max_staleness_seconds?: int,
     *     now?: ?string
     * } $signals
     * @return array{
     *     finding: string,
     *     subject_ref: string,
     *     reasons: array<int, string>,
     *     connector_status_reference: ?string,
     *     service_discovery_availability_reference: ?string
     * }
     */
    public function evaluate(string $subjectRef, array $signals): array
    {
        if ($signals === []) {
            return $this->result($subjectRef, 'Unknown', ['No integration telemetry or status references were supplied.']);
        }

        $stalenessReason = $this->stalenessReason($signals);

        if ($stalenessReason !== null) {
            return $this->result($subjectRef, 'Unknown', [$stalenessReason]);
        }

        if (($signals['authentication_failure'] ?? false) === true && ($signals['availability'] ?? null) !== 'unavailable') {
            return $this->result($subjectRef, 'Authentication Failing', ['authentication_failure signal is active.']);
        }

        if (($signals['availability'] ?? null) === 'unavailable') {
            return $this->result($subjectRef, 'Unavailable', ['availability signal reports unavailable.']);
        }

        if (($signals['rate_limited'] ?? false) === true) {
            return $this->result($subjectRef, 'Rate Limited', ['rate_limited signal is active.']);
        }

        $degradedReasons = $this->degradedReasons($signals);

        if ($degradedReasons !== []) {
            return $this->result($subjectRef, 'Degraded', $degradedReasons);
        }

        return $this->result($subjectRef, 'Healthy', ['All supplied signals are within expected thresholds.']);
    }

    /**
     * @param array<string, array<string, mixed>> $subjects subject_ref => signals, per evaluate().
     * @return array<string, array{finding: string, subject_ref: string, reasons: array<int, string>, connector_status_reference: ?string, service_discovery_availability_reference: ?string}>
     */
    public function evaluateMany(array $subjects): array
    {
        $results = [];

        foreach ($subjects as $subjectRef => $signals) {
            $results[$subjectRef] = $this->evaluate((string) $subjectRef, is_array($signals) ? $signals : []);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $signals
     */
    private function stalenessReason(array $signals): ?string
    {
        $observedAt = $signals['observed_at'] ?? null;

        if ($observedAt === null) {
            return null;
        }

        if (!is_string($observedAt)) {
            return 'observed_at reference could not be parsed.';
        }

        try {
            $observed = new DateTimeImmutable($observedAt);
        } catch (Throwable) {
            return 'observed_at reference could not be parsed.';
        }

        $now = isset($signals['now']) && is_string($signals['now']) ? new DateTimeImmutable($signals['now']) : new DateTimeImmutable();
        $maxStaleness = is_int($signals['max_staleness_seconds'] ?? null) ? $signals['max_staleness_seconds'] : self::DEFAULT_MAX_STALENESS_SECONDS;

        if (abs($now->getTimestamp() - $observed->getTimestamp()) > $maxStaleness) {
            return sprintf('observed_at is stale (older than the %d-second staleness allowance).', $maxStaleness);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $signals
     * @return array<int, string>
     */
    private function degradedReasons(array $signals): array
    {
        $reasons = [];

        if (($signals['availability'] ?? null) === 'degraded') {
            $reasons[] = 'availability signal reports degraded.';
        }

        if (($signals['degradation'] ?? false) === true) {
            $reasons[] = 'degradation signal is active.';
        }

        if (($signals['timeout'] ?? false) === true) {
            $reasons[] = 'timeout signal is active.';
        }

        $latency = $signals['latency_ms'] ?? null;
        $latencyThreshold = is_numeric($signals['latency_threshold_ms'] ?? null) ? (float) $signals['latency_threshold_ms'] : self::DEFAULT_LATENCY_THRESHOLD_MS;

        if (is_numeric($latency) && (float) $latency > $latencyThreshold) {
            $reasons[] = sprintf('latency_ms %s exceeds the %s ms threshold.', $latency, $latencyThreshold);
        }

        $errorRate = $signals['error_rate'] ?? null;
        $errorRateThreshold = is_numeric($signals['error_rate_threshold'] ?? null) ? (float) $signals['error_rate_threshold'] : self::DEFAULT_ERROR_RATE_THRESHOLD;

        if (is_numeric($errorRate) && (float) $errorRate > $errorRateThreshold) {
            $reasons[] = sprintf('error_rate %s exceeds the %s threshold.', $errorRate, $errorRateThreshold);
        }

        return $reasons;
    }

    /**
     * @param array<int, string> $reasons
     * @return array{finding: string, subject_ref: string, reasons: array<int, string>, connector_status_reference: ?string, service_discovery_availability_reference: ?string}
     */
    private function result(string $subjectRef, string $finding, array $reasons): array
    {
        $finding = in_array($finding, self::FINDING_STATES, true) ? $finding : 'Unknown';

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'integration_monitor.' . strtolower(str_replace(' ', '_', $finding)),
            new DateTimeImmutable(),
            self::class,
            ['finding' => $finding, 'subject_ref' => $subjectRef, 'reasons' => $reasons]
        ));

        return [
            'finding' => $finding,
            'subject_ref' => $subjectRef,
            'reasons' => $reasons,
            'connector_status_reference' => self::CONNECTOR_STATUS_REFERENCE[$finding],
            'service_discovery_availability_reference' => self::SERVICE_DISCOVERY_AVAILABILITY_REFERENCE[$finding],
        ];
    }
}
