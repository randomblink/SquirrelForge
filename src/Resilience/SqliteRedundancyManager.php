<?php

declare(strict_types=1);

namespace SquirrelForge\Resilience;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Tracks redundant resources and their synchronization/readiness state,
 * and provides verified redundant resources to whichever caller (the
 * Failover Coordinator or Recovery Manager, per the spec) needs one,
 * per 35_RESILIENCE/REDUNDANCY-MANAGER.md. This spec predates the
 * "Depends On" header convention; of its Integration Responsibilities
 * (Resilience Manager, Recovery Manager, Failover Coordinator,
 * Self-Healing Engine, Capacity Planner, Infrastructure services,
 * Observability Layer, Resilience Governance), none is composed here:
 * the spec's own core mechanical contract -- maintain redundancy
 * readiness and hand out verified resources -- is self-contained state
 * tracking, and Failover Coordinator/Infrastructure services/Resilience
 * Governance have no real code yet to fabricate a call into.
 *
 * "The Redundancy Manager does not independently initiate failover or
 * recovery. It maintains redundancy readiness and provides the
 * Failover Coordinator and Recovery Manager with verified redundant
 * resources when required" is upheld literally: this class only ever
 * tracks state and answers `provideVerifiedResource()` queries; it
 * never triggers a failover itself, matching the Safety Rule "must
 * never trigger failover directly."
 *
 * The Safety Rule "must never promote unsynchronized resources" is a
 * real, enforced gate in provideVerifiedResource(): only a resource
 * whose tracked state is `Ready` and whose `synchronized` flag is true
 * can ever be returned -- a resource that is merely `Active` or
 * `Standby` but not yet confirmed synchronized is never handed out.
 * "Must never ignore replication failures" is enforced the same way in
 * recordHealth(): marking a resource unhealthy always clears its
 * synchronized flag too, since an unavailable resource's last-known
 * synchronization state can no longer be trusted.
 *
 * `Retired` is treated as a genuine terminal state: every mutating
 * method rejects further changes to an already-retired resource,
 * matching the spec's own state list treating retirement as an
 * endpoint, not a state redundancy monitoring cycles back from.
 *
 * Owns two tables: `redundant_resources` holds one current-state row
 * per resource (current-state model), and `redundancy_operations` is
 * the append-only audit log the Audit Requirements name -- every
 * mutating call (register, synchronization update, health update,
 * retirement) is recorded there unconditionally, including rejected
 * operations, per "Maintain audit continuity." Read-only queries
 * (get(), list(), provideVerifiedResource()) are not audited, matching
 * this codebase's existing precedent (e.g. SqliteRiskAssessor::get()).
 */
final class SqliteRedundancyManager
{
    private const TYPES = [
        'service', 'compute', 'storage', 'database_replication', 'network',
        'agent', 'workflow', 'integration', 'geographic', 'backup_infrastructure',
    ];

    private const STATES = ['Active', 'Standby', 'Synchronizing', 'Ready', 'Degraded', 'Unavailable', 'Recovering', 'Retired'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?EventBusInterface $events = null,
        private readonly ?Closure $clock = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS redundant_resources (
                resource_id TEXT PRIMARY KEY,
                redundancy_type TEXT NOT NULL,
                state TEXT NOT NULL,
                synchronized INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS redundancy_operations (
                redundancy_operation_id TEXT PRIMARY KEY,
                resource_id TEXT NOT NULL,
                redundancy_type TEXT NOT NULL,
                synchronization_status TEXT NOT NULL,
                readiness_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @return array{outcome: string, resource_id: string, error: ?string}
     */
    public function register(string $resourceId, string $redundancyType, string $initialState = 'Standby'): array
    {
        if (!in_array($redundancyType, self::TYPES, true)) {
            return $this->reject($resourceId, $redundancyType, 'invalid_type', sprintf('"%s" is not a supported redundancy type.', $redundancyType));
        }

        if (!in_array($initialState, self::STATES, true)) {
            return $this->reject($resourceId, $redundancyType, 'invalid_state', sprintf('"%s" is not a valid redundant resource state.', $initialState));
        }

        if ($this->fetch($resourceId) !== null) {
            return $this->recordAndReturn($resourceId, $redundancyType, 'already_registered', null, false);
        }

        $now = $this->now();
        $statement = $this->database->prepare(
            'INSERT INTO redundant_resources (resource_id, redundancy_type, state, synchronized, created_at, updated_at)
             VALUES (:resource_id, :redundancy_type, :state, 0, :created_at, :updated_at)'
        );
        $statement->execute(['resource_id' => $resourceId, 'redundancy_type' => $redundancyType, 'state' => $initialState, 'created_at' => $now, 'updated_at' => $now]);

        return $this->recordAndReturn($resourceId, $redundancyType, 'registered', null, false);
    }

    /**
     * @return array{outcome: string, resource_id: string, state: ?string, error: ?string}
     */
    public function updateSynchronization(string $resourceId, bool $synchronized, ?string $details = null): array
    {
        $resource = $this->fetch($resourceId);

        if ($resource === null) {
            return [...$this->reject($resourceId, 'unknown', 'not_registered', sprintf('Resource "%s" is not registered.', $resourceId)), 'state' => null];
        }

        if ($resource['state'] === 'Retired') {
            return [...$this->reject($resourceId, $resource['redundancy_type'], 'blocked_retired', 'Retired resources can no longer be updated.'), 'state' => 'Retired'];
        }

        $newState = $synchronized ? 'Ready' : 'Synchronizing';
        $this->updateState($resourceId, $newState, $synchronized);

        $outcome = $synchronized ? 'synchronized' : 'desynchronized';

        return [...$this->recordAndReturn($resourceId, $resource['redundancy_type'], $outcome, $details, $synchronized), 'state' => $newState];
    }

    /**
     * @return array{outcome: string, resource_id: string, state: ?string, error: ?string}
     */
    public function recordHealth(string $resourceId, bool $healthy): array
    {
        $resource = $this->fetch($resourceId);

        if ($resource === null) {
            return [...$this->reject($resourceId, 'unknown', 'not_registered', sprintf('Resource "%s" is not registered.', $resourceId)), 'state' => null];
        }

        if ($resource['state'] === 'Retired') {
            return [...$this->reject($resourceId, $resource['redundancy_type'], 'blocked_retired', 'Retired resources can no longer be updated.'), 'state' => 'Retired'];
        }

        if (!$healthy) {
            $this->updateState($resourceId, 'Unavailable', false);

            return [...$this->recordAndReturn($resourceId, $resource['redundancy_type'], 'marked_unavailable', null, false), 'state' => 'Unavailable'];
        }

        $newState = $resource['state'] === 'Unavailable' ? 'Recovering' : $resource['state'];
        $this->updateState($resourceId, $newState, (bool) $resource['synchronized']);

        return [...$this->recordAndReturn($resourceId, $resource['redundancy_type'], 'healthy', null, (bool) $resource['synchronized']), 'state' => $newState];
    }

    /**
     * @return array{outcome: string, resource_id: string, error: ?string}
     */
    public function retire(string $resourceId): array
    {
        $resource = $this->fetch($resourceId);

        if ($resource === null) {
            return $this->reject($resourceId, 'unknown', 'not_registered', sprintf('Resource "%s" is not registered.', $resourceId));
        }

        if ($resource['state'] === 'Retired') {
            return $this->recordAndReturn($resourceId, $resource['redundancy_type'], 'already_retired', null, false);
        }

        $this->updateState($resourceId, 'Retired', false);

        return $this->recordAndReturn($resourceId, $resource['redundancy_type'], 'retired', null, false);
    }

    /**
     * @return array{found: bool, resource_id: ?string, error: ?string}
     */
    public function provideVerifiedResource(string $redundancyType): array
    {
        if (!in_array($redundancyType, self::TYPES, true)) {
            return ['found' => false, 'resource_id' => null, 'error' => sprintf('"%s" is not a supported redundancy type.', $redundancyType)];
        }

        $statement = $this->database->prepare(
            "SELECT resource_id FROM redundant_resources
             WHERE redundancy_type = :redundancy_type AND state = 'Ready' AND synchronized = 1
             ORDER BY updated_at DESC LIMIT 1"
        );
        $statement->execute(['redundancy_type' => $redundancyType]);
        $row = $statement->fetch();

        if ($row === false) {
            return ['found' => false, 'resource_id' => null, 'error' => sprintf('No verified, synchronized, ready resource is available for type "%s".', $redundancyType)];
        }

        return ['found' => true, 'resource_id' => $row['resource_id'], 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $resourceId): ?array
    {
        return $this->fetch($resourceId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(?string $redundancyType = null): array
    {
        if ($redundancyType !== null) {
            $statement = $this->database->prepare('SELECT * FROM redundant_resources WHERE redundancy_type = :redundancy_type');
            $statement->execute(['redundancy_type' => $redundancyType]);
        } else {
            $statement = $this->database->prepare('SELECT * FROM redundant_resources');
            $statement->execute();
        }

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $resourceId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM redundant_resources WHERE resource_id = :resource_id');
        $statement->execute(['resource_id' => $resourceId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function updateState(string $resourceId, string $state, bool $synchronized): void
    {
        $statement = $this->database->prepare(
            'UPDATE redundant_resources SET state = :state, synchronized = :synchronized, updated_at = :updated_at WHERE resource_id = :resource_id'
        );
        $statement->execute(['state' => $state, 'synchronized' => $synchronized ? 1 : 0, 'updated_at' => $this->now(), 'resource_id' => $resourceId]);
    }

    private function now(): string
    {
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();

        return $now->format(DATE_RFC3339);
    }

    /**
     * @return array{outcome: string, resource_id: string, error: ?string}
     */
    private function reject(string $resourceId, string $redundancyType, string $outcome, string $error): array
    {
        return $this->recordAndReturn($resourceId, $redundancyType, $outcome, $error, false, isRejection: true);
    }

    /**
     * @return array{outcome: string, resource_id: string, error: ?string}
     */
    private function recordAndReturn(
        string $resourceId,
        string $redundancyType,
        string $outcome,
        ?string $error,
        bool $synchronized,
        bool $isRejection = false
    ): array {
        $operationId = 'redundancy_op_' . bin2hex(random_bytes(12));
        $resource = $this->fetch($resourceId);
        $readinessStatus = ($resource !== null && $resource['state'] === 'Ready') ? 'ready' : 'not_ready';

        $statement = $this->database->prepare(
            'INSERT INTO redundancy_operations (
                redundancy_operation_id, resource_id, redundancy_type, synchronization_status,
                readiness_status, governance_status, final_outcome, error, created_at
            ) VALUES (
                :id, :resource_id, :redundancy_type, :synchronization_status,
                :readiness_status, :governance_status, :final_outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'id' => $operationId,
            'resource_id' => $resourceId,
            'redundancy_type' => $redundancyType,
            'synchronization_status' => $synchronized ? 'synchronized' : 'not_synchronized',
            'readiness_status' => $readinessStatus,
            'governance_status' => 'ungoverned',
            'final_outcome' => $outcome,
            'error' => $error,
            'created_at' => $this->now(),
        ]);

        if (!$isRejection) {
            $this->events?->dispatch(new Event(
                uniqid('evt_', true),
                'redundancy.' . $outcome,
                new DateTimeImmutable(),
                self::class,
                ['resource_id' => $resourceId, 'redundancy_type' => $redundancyType, 'outcome' => $outcome]
            ));
        }

        return ['outcome' => $outcome, 'resource_id' => $resourceId, 'error' => $error];
    }
}
