<?php

declare(strict_types=1);

namespace SquirrelForge\Resilience;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Top-level coordinator for the Resilience Layer, per
 * 35_RESILIENCE/RESILIENCE-MANAGER.md.
 *
 * The spec has this component route a detected failure to whichever of
 * Self-Healing Engine, Recovery Manager, Rollback Manager, Redundancy
 * Manager, Failover Coordinator, Disaster Recovery, or Business
 * Continuity fits. Rollback Manager is already reachable through
 * Recovery Manager's `rollback` strategy, so it isn't a separate branch
 * here, and Redundancy Manager is already reachable through Failover
 * Coordinator's own optional composition (`failover()` calls
 * `SqliteRedundancyManager::provideVerifiedResource()` itself when one is
 * injected into it) -- routing to it separately here would duplicate
 * that, not add real coverage. Failover Coordinator, Disaster Recovery,
 * and Business Continuity are wired in now: all three were built after
 * this class's own docblock first documented them as missing (Resilience
 * Manager: 2026-07-28; the other three: 2026-08-03), the same
 * "coordinator predates its own specialist" resolution this codebase's
 * other coordinators have already gone through. Per the spec's own rule
 * that it must never "execute unapproved recovery actions" and must
 * "prevent uncontrolled recovery actions", routing to each of these
 * remains entirely caller-directed -- this class never infers from
 * severity alone that, say, a disaster should be declared -- and a
 * failure for which the caller supplies none of self_heal_action,
 * recovery_strategy, failover_request, disaster_recovery, or
 * business_continuity is still escalated for human/governance review
 * rather than defaulting to some invented action.
 *
 * Governance status is the fixed constant `ungoverned` unless the caller
 * supplies a `governance_request` and a `SqliteResilienceGovernance` is
 * configured, in which case the real decision from `review()` is
 * recorded instead -- the same optional, caller-opted-in composition
 * `SqliteStorageManager`'s `governance_review` operation already
 * established, adapted to this class's single-call shape rather than a
 * separate operation name.
 *
 * Routing is caller-directed rather than inferred from severity: the
 * caller supplies which self-healing action and/or recovery strategy are
 * candidates for this failure (mirroring how those components themselves
 * only ever act on names/strategies the caller explicitly names), and
 * this class tries them in escalating order of disruption -- self-healing,
 * recovery, failover, disaster recovery, business continuity -- recording
 * which component actually resolved the failure.
 */
final class SqliteResilienceManager
{
    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly SqliteSelfHealingEngine $selfHealing,
        private readonly SqliteRecoveryManager $recovery,
        private readonly ?SqliteFailoverCoordinator $failover = null,
        private readonly ?SqliteDisasterRecovery $disasterRecovery = null,
        private readonly ?SqliteBusinessContinuity $businessContinuity = null,
        private readonly ?SqliteResilienceGovernance $governance = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS resilience_operations (
                resilience_ref TEXT PRIMARY KEY,
                failure_ref TEXT,
                coordinated_component TEXT NOT NULL,
                outcome TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                notes_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, mixed> $failureDetection the record produced by
     *        SqliteFailureDetector::detect()/detection() -- only
     *        `detection_ref` is read directly, the rest is the caller's
     *        basis for choosing options below.
     * @param array{
     *     self_heal_action?: ?string,
     *     self_heal_options?: array<string, mixed>,
     *     recovery_strategy?: ?string,
     *     recovery_options?: array<string, mixed>,
     *     failover_request?: array<string, mixed>,
     *     disaster_recovery?: array{classification: string, affected_services: array<string, string>},
     *     business_continuity?: array{operating_mode: string, critical_services: array<string, string>, strategy: string, evidence: array<string, mixed>},
     *     governance_request?: array<string, mixed>,
     * } $options
     * @return array{resilience_ref: string, failure_ref: ?string, coordinated_component: string, outcome: string, governance_status: string, notes: array<int, string>}
     */
    public function coordinate(array $failureDetection, array $options = []): array
    {
        $failureRef = $failureDetection['detection_ref'] ?? null;
        $notes = [];
        $governanceStatus = $this->reviewGovernance($options['governance_request'] ?? null, $notes);
        $lastAttempted = 'none';

        $selfHealAction = $options['self_heal_action'] ?? null;

        if ($selfHealAction !== null) {
            $lastAttempted = 'self_healing';
            $result = $this->selfHealing->heal($selfHealAction, [
                'failure_ref' => $failureRef,
                ...$options['self_heal_options'] ?? [],
            ]);
            $notes[] = sprintf('self_healing: %s%s', $result['state'], $result['error'] !== null ? " ({$result['error']})" : '');

            if ($result['state'] === 'successful') {
                return $this->persist($failureRef, 'self_healing', 'completed', $notes, $governanceStatus);
            }
        }

        $recoveryStrategy = $options['recovery_strategy'] ?? null;

        if ($recoveryStrategy !== null) {
            $result = $this->recovery->recover($recoveryStrategy, [
                'failure_ref' => $failureRef,
                ...$options['recovery_options'] ?? [],
            ]);
            $notes[] = sprintf('recovery: %s%s', $result['state'], $result['error'] !== null ? " ({$result['error']})" : '');

            if ($result['state'] === 'completed') {
                return $this->persist($failureRef, 'recovery', 'completed', $notes, $governanceStatus);
            }

            if ($result['state'] === 'partially_recovered') {
                return $this->persist($failureRef, 'recovery', 'partially_recovered', $notes, $governanceStatus);
            }

            return $this->persist($failureRef, 'recovery', 'escalated', $notes, $governanceStatus);
        }

        $failoverRequest = $options['failover_request'] ?? null;

        if ($failoverRequest !== null) {
            $lastAttempted = 'failover';

            if ($this->failover === null) {
                $notes[] = 'failover: Failover Coordinator is not configured.';
            } else {
                $result = $this->failover->failover($failoverRequest);
                $notes[] = sprintf('failover: %s%s', $result['state'], $result['error'] !== null ? " ({$result['error']})" : '');

                if ($result['state'] === 'Operational') {
                    return $this->persist($failureRef, 'failover', 'completed', $notes, $governanceStatus);
                }
            }
        }

        $disasterRecoveryRequest = $options['disaster_recovery'] ?? null;

        if ($disasterRecoveryRequest !== null) {
            $lastAttempted = 'disaster_recovery';

            if ($this->disasterRecovery === null) {
                $notes[] = 'disaster_recovery: Disaster Recovery is not configured.';
            } else {
                $result = $this->disasterRecovery->declare($disasterRecoveryRequest['classification'], $disasterRecoveryRequest['affected_services']);
                $notes[] = $result['error'] !== null ? sprintf('disaster_recovery: rejected (%s)', $result['error']) : sprintf('disaster_recovery: declared (%s)', $result['disaster_recovery_id']);

                if ($result['error'] === null) {
                    return $this->persist($failureRef, 'disaster_recovery', 'declared', $notes, $governanceStatus);
                }
            }
        }

        $businessContinuityRequest = $options['business_continuity'] ?? null;

        if ($businessContinuityRequest !== null) {
            $lastAttempted = 'business_continuity';

            if ($this->businessContinuity === null) {
                $notes[] = 'business_continuity: Business Continuity is not configured.';
            } else {
                $result = $this->businessContinuity->activate(
                    $businessContinuityRequest['operating_mode'],
                    $businessContinuityRequest['critical_services'],
                    $businessContinuityRequest['strategy'],
                    $businessContinuityRequest['evidence']
                );
                $notes[] = sprintf('business_continuity: %s%s', $result['outcome'], $result['error'] !== null ? " ({$result['error']})" : '');

                if ($result['error'] === null) {
                    return $this->persist($failureRef, 'business_continuity', 'activated', $notes, $governanceStatus);
                }
            }
        }

        if ($notes === []) {
            $notes[] = 'No self-healing action, recovery strategy, failover request, disaster recovery, or '
                . 'business continuity activation was supplied.';
        }

        return $this->persist($failureRef, $lastAttempted, 'escalated', $notes, $governanceStatus);
    }

    /**
     * @param array<int, string> $notes
     */
    private function reviewGovernance(?array $governanceRequest, array &$notes): string
    {
        if ($governanceRequest === null) {
            return 'ungoverned';
        }

        if ($this->governance === null) {
            $notes[] = 'governance: Resilience Governance is not configured.';

            return 'ungoverned';
        }

        $result = $this->governance->review($governanceRequest);
        $notes[] = sprintf('governance: %s', $result['decision']);

        return $result['decision'];
    }

    /**
     * @param array<int, string> $notes
     * @return array{resilience_ref: string, failure_ref: ?string, coordinated_component: string, outcome: string, governance_status: string, notes: array<int, string>}
     */
    private function persist(?string $failureRef, string $coordinatedComponent, string $outcome, array $notes, string $governanceStatus = 'ungoverned'): array
    {
        $resilienceRef = 'resilience_' . bin2hex(random_bytes(12));
        $createdAt = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO resilience_operations (
                resilience_ref, failure_ref, coordinated_component, outcome,
                governance_status, notes_json, created_at
            ) VALUES (
                :resilience_ref, :failure_ref, :coordinated_component, :outcome,
                :governance_status, :notes_json, :created_at
            )'
        );
        $statement->execute([
            'resilience_ref' => $resilienceRef,
            'failure_ref' => $failureRef,
            'coordinated_component' => $coordinatedComponent,
            'outcome' => $outcome,
            'governance_status' => $governanceStatus,
            'notes_json' => json_encode($notes, JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'resilience.finished',
            new DateTimeImmutable(),
            self::class,
            ['resilience_ref' => $resilienceRef, 'coordinated_component' => $coordinatedComponent, 'outcome' => $outcome]
        ));

        return [
            'resilience_ref' => $resilienceRef,
            'failure_ref' => $failureRef,
            'coordinated_component' => $coordinatedComponent,
            'outcome' => $outcome,
            'governance_status' => $governanceStatus,
            'notes' => $notes,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function operation(string $resilienceRef): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM resilience_operations WHERE resilience_ref = :resilience_ref'
        );
        $statement->execute(['resilience_ref' => $resilienceRef]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM resilience_operations ORDER BY rowid DESC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        return [
            'resilience_ref' => $row['resilience_ref'],
            'failure_ref' => $row['failure_ref'],
            'coordinated_component' => $row['coordinated_component'],
            'outcome' => $row['outcome'],
            'governance_status' => $row['governance_status'],
            'notes' => json_decode((string) $row['notes_json'], true, flags: JSON_THROW_ON_ERROR),
            'created_at' => $row['created_at'],
        ];
    }
}
