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
 * Continuity fits. Only the first two have real implementations (Rollback
 * Manager is already reachable through Recovery Manager's `rollback`
 * strategy, so it isn't a separate branch here); Redundancy, Failover,
 * Disaster Recovery, and Business Continuity have no code to route to.
 * Per the spec's own rule that it must never "execute unapproved recovery
 * actions" and must "prevent uncontrolled recovery actions", the absence
 * of those components is not something this class works around by
 * inventing a substitute -- a failure that would need one of them, or
 * that the caller gives no self-healing action or recovery strategy for
 * at all, is escalated for human/governance review instead. Governance
 * status is recorded as the fixed constant `ungoverned` since
 * 23_GOVERNANCE has no code to enforce a real policy against.
 *
 * Routing is caller-directed rather than inferred from severity: the
 * caller supplies which self-healing action and/or recovery strategy are
 * candidates for this failure (mirroring how those components themselves
 * only ever act on names/strategies the caller explicitly names), and
 * this class tries them in order -- self-healing first, then recovery --
 * recording which component actually resolved the failure.
 */
final class SqliteResilienceManager
{
    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly SqliteSelfHealingEngine $selfHealing,
        private readonly SqliteRecoveryManager $recovery,
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
     * } $options
     * @return array{resilience_ref: string, failure_ref: ?string, coordinated_component: string, outcome: string, governance_status: string, notes: array<int, string>}
     */
    public function coordinate(array $failureDetection, array $options = []): array
    {
        $failureRef = $failureDetection['detection_ref'] ?? null;
        $notes = [];

        $selfHealAction = $options['self_heal_action'] ?? null;

        if ($selfHealAction !== null) {
            $result = $this->selfHealing->heal($selfHealAction, [
                'failure_ref' => $failureRef,
                ...$options['self_heal_options'] ?? [],
            ]);
            $notes[] = sprintf('self_healing: %s%s', $result['state'], $result['error'] !== null ? " ({$result['error']})" : '');

            if ($result['state'] === 'successful') {
                return $this->persist($failureRef, 'self_healing', 'completed', $notes);
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
                return $this->persist($failureRef, 'recovery', 'completed', $notes);
            }

            if ($result['state'] === 'partially_recovered') {
                return $this->persist($failureRef, 'recovery', 'partially_recovered', $notes);
            }

            return $this->persist($failureRef, 'recovery', 'escalated', $notes);
        }

        if ($selfHealAction !== null) {
            return $this->persist($failureRef, 'self_healing', 'escalated', $notes);
        }

        $notes[] = 'No self-healing action or recovery strategy was supplied; Redundancy Manager, '
            . 'Failover Coordinator, Disaster Recovery, and Business Continuity have no implementation to route to.';

        return $this->persist($failureRef, 'none', 'escalated', $notes);
    }

    /**
     * @param array<int, string> $notes
     * @return array{resilience_ref: string, failure_ref: ?string, coordinated_component: string, outcome: string, governance_status: string, notes: array<int, string>}
     */
    private function persist(?string $failureRef, string $coordinatedComponent, string $outcome, array $notes): array
    {
        $resilienceRef = 'resilience_' . bin2hex(random_bytes(12));
        $createdAt = gmdate(DATE_RFC3339);
        $governanceStatus = 'ungoverned';

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
