<?php

declare(strict_types=1);

namespace SquirrelForge\Integration;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Owns integration-domain discovery records for external services,
 * APIs, connectors, MCP servers, plugins, repositories, database
 * services, file-storage services, automation platforms, and provider
 * endpoints, per 26_INTEGRATIONS/SERVICE-DISCOVERY.md -- the fifth real
 * component in 26_INTEGRATIONS.
 *
 * Governance stays caller-supplied evidence here, unlike
 * `IntegrationManager`'s live call to `SqliteIntegrationGovernance`: this
 * spec's own Responsibilities say "Record... governance... reference
 * requirements" and Rule 2 says "must not approve services for use;
 * governance decisions belong to INTEGRATION-GOVERNANCE.md" -- the same
 * "use references... for governance approvals" verb choice
 * `SqliteConnectorManager`'s own spec makes, not Integration Manager's
 * "must consume... FROM X" live-call language. `governance_ref` is
 * therefore a plain caller-supplied string here, the same treatment
 * Connector Manager already gives it.
 *
 * The four references "Reference Pending" (per the spec's own Discovery
 * States table) names as required -- endpoint, owner, governance, and
 * credential -- map onto `endpoint_ref`, `provider_ref` (the Discovery
 * Record table's own "External provider or owner reference"),
 * `governance_ref`, and `credential_requirement_ref`. Protocol metadata
 * and version reference are real, recorded fields but are never checked
 * for completeness: the spec's own Reference Pending definition never
 * names them, unlike Connector Manager's five-reference readiness list.
 *
 * Discovered/Reference Pending/Verified are kept as three independently
 * reachable states rather than auto-checking on discover(), the same
 * reasoning `SqliteConnectorManager` already applies to its own
 * registered/readiness_pending/ready trio: "not yet checked" (Reference
 * Pending's own state, before an explicit checkReferences() call) would
 * otherwise be unreachable.
 *
 * markAvailable() requires the record to already be `Verified` and to
 * carry a non-empty `governance_ref` as caller-supplied approval
 * evidence -- the same dual check `SqliteConnectorManager::activate()`
 * makes, including the same reason it isn't redundant with
 * checkReferences(): updateReferences() can clear `governance_ref`
 * afterward without forcing a re-check.
 *
 * recordAvailability()'s status is caller-supplied evidence, never
 * computed here: Rule 4 is explicit that "monitoring infrastructure
 * belongs to 27_OBSERVABILITY and INTEGRATION-MONITOR.md." Unlike
 * Connector Manager's binary degraded flag, this spec's own three
 * availability-tier states (Available/Degraded/Unavailable) are all
 * caller-supplied outcomes this class only ever records and transitions
 * into, never decides between -- with one exception: restoring into
 * `Available` from `Degraded`/`Unavailable` re-checks `governance_ref`
 * for the same reason `markAvailable()` does. Without that check this
 * method would be a second, ungated path into `Available` that bypasses
 * the approval-evidence invariant `markAvailable()` exists to enforce.
 *
 * Deprecated has no Connector Manager equivalent: it is reachable from
 * any non-Retired status (a service can be scheduled for removal
 * whether or not it was ever marked Available), and is a genuinely
 * distinct state from Retired -- "scheduled for removal" is still a
 * live discovery record, "no longer available for new integration use"
 * is not.
 *
 * Owns its own database (`Sqlite` prefix), the same "a private
 * discovery-record database is not the same as owning 37_STORAGE's
 * shared infrastructure" stance `SqliteConnectorManager` already takes
 * toward the identical Boundary language.
 */
final class SqliteServiceDiscovery
{
    private const DISCOVERY_STATUSES = [
        'Discovered', 'Reference Pending', 'Verified', 'Available', 'Degraded', 'Unavailable', 'Deprecated', 'Retired',
    ];

    private const REQUIRED_REFERENCES = ['endpoint_ref', 'provider_ref', 'governance_ref', 'credential_requirement_ref'];

    private const AVAILABILITY_STATUSES = ['Available', 'Degraded', 'Unavailable'];

    private PDO $database;

    public function __construct(string $databasePath, private readonly ?EventBusInterface $events = null)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS service_discovery_records (
                service_id TEXT PRIMARY KEY,
                service_name TEXT NOT NULL,
                provider_ref TEXT,
                endpoint_ref TEXT,
                protocol_metadata TEXT,
                capability_metadata_json TEXT NOT NULL,
                version_ref TEXT,
                credential_requirement_ref TEXT,
                governance_ref TEXT,
                availability_ref TEXT,
                discovery_status TEXT NOT NULL,
                deprecation_reason TEXT,
                retirement_reason TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{service_name: string, provider_ref?: ?string, endpoint_ref?: ?string, protocol_metadata?: ?string, version_ref?: ?string, credential_requirement_ref?: ?string, governance_ref?: ?string, capability_metadata?: array<int, string>} $definition
     * @return array{outcome: string, service_id: ?string, discovery_status: ?string, error: ?string}
     */
    public function discover(array $definition): array
    {
        $name = $definition['service_name'] ?? '';

        if ($name === '') {
            return ['outcome' => 'invalid', 'service_id' => null, 'discovery_status' => null, 'error' => 'Discovery requires a non-empty service_name.'];
        }

        $capabilityMetadata = $definition['capability_metadata'] ?? [];
        $consistencyError = $this->capabilityConsistencyError($capabilityMetadata);

        if ($consistencyError !== null) {
            return ['outcome' => 'invalid_capability_metadata', 'service_id' => null, 'discovery_status' => null, 'error' => $consistencyError];
        }

        $serviceId = 'service_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO service_discovery_records (
                service_id, service_name, provider_ref, endpoint_ref, protocol_metadata,
                capability_metadata_json, version_ref, credential_requirement_ref, governance_ref,
                availability_ref, discovery_status, deprecation_reason, retirement_reason, created_at, updated_at
            ) VALUES (
                :service_id, :service_name, :provider_ref, :endpoint_ref, :protocol_metadata,
                :capability_metadata_json, :version_ref, :credential_requirement_ref, :governance_ref,
                NULL, :discovery_status, NULL, NULL, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'service_id' => $serviceId,
            'service_name' => $name,
            'provider_ref' => $this->nullableString($definition['provider_ref'] ?? null),
            'endpoint_ref' => $this->nullableString($definition['endpoint_ref'] ?? null),
            'protocol_metadata' => $this->nullableString($definition['protocol_metadata'] ?? null),
            'capability_metadata_json' => json_encode($capabilityMetadata, JSON_THROW_ON_ERROR),
            'version_ref' => $this->nullableString($definition['version_ref'] ?? null),
            'credential_requirement_ref' => $this->nullableString($definition['credential_requirement_ref'] ?? null),
            'governance_ref' => $this->nullableString($definition['governance_ref'] ?? null),
            'discovery_status' => 'Discovered',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->emit($serviceId, 'Discovered');

        return ['outcome' => 'discovered', 'service_id' => $serviceId, 'discovery_status' => 'Discovered', 'error' => null];
    }

    /**
     * Patches discovery reference fields and capability metadata --
     * always a plain field update, never a state transition on its own.
     *
     * @param array{provider_ref?: ?string, endpoint_ref?: ?string, protocol_metadata?: ?string, version_ref?: ?string, credential_requirement_ref?: ?string, governance_ref?: ?string, capability_metadata?: array<int, string>} $references
     * @return array{outcome: string, error: ?string}
     */
    public function updateReferences(string $serviceId, array $references): array
    {
        $record = $this->fetch($serviceId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Service "%s" is not discovered.', $serviceId)];
        }

        if (array_key_exists('capability_metadata', $references)) {
            $consistencyError = $this->capabilityConsistencyError($references['capability_metadata']);

            if ($consistencyError !== null) {
                return ['outcome' => 'invalid_capability_metadata', 'error' => $consistencyError];
            }
        }

        $columns = ['provider_ref', 'endpoint_ref', 'protocol_metadata', 'version_ref', 'credential_requirement_ref', 'governance_ref'];
        $assignments = [];
        $params = ['service_id' => $serviceId, 'updated_at' => gmdate(DATE_RFC3339)];

        foreach ($columns as $column) {
            if (array_key_exists($column, $references)) {
                $assignments[] = "{$column} = :{$column}";
                $params[$column] = $this->nullableString($references[$column]);
            }
        }

        if (array_key_exists('capability_metadata', $references)) {
            $assignments[] = 'capability_metadata_json = :capability_metadata_json';
            $params['capability_metadata_json'] = json_encode($references['capability_metadata'], JSON_THROW_ON_ERROR);
        }

        if ($assignments === []) {
            return ['outcome' => 'noop', 'error' => null];
        }

        $assignments[] = 'updated_at = :updated_at';
        $statement = $this->database->prepare(
            sprintf('UPDATE service_discovery_records SET %s WHERE service_id = :service_id', implode(', ', $assignments))
        );
        $statement->execute($params);

        return ['outcome' => 'updated', 'error' => null];
    }

    /**
     * Confirms the four references "Reference Pending" names are
     * present and declared capabilities are internally consistent.
     * Never touches availability, deprecation, or retirement.
     *
     * @return array{outcome: string, discovery_status: ?string, missing_references: array<int, string>, error: ?string}
     */
    public function checkReferences(string $serviceId): array
    {
        $record = $this->fetch($serviceId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'discovery_status' => null, 'missing_references' => [], 'error' => sprintf('Service "%s" is not discovered.', $serviceId)];
        }

        if (!in_array($record['discovery_status'], ['Discovered', 'Reference Pending', 'Verified'], true)) {
            return [
                'outcome' => 'not_applicable',
                'discovery_status' => $record['discovery_status'],
                'missing_references' => [],
                'error' => sprintf('Service "%s" is past reference verification (status: %s).', $serviceId, $record['discovery_status']),
            ];
        }

        $missing = array_values(array_filter(
            self::REQUIRED_REFERENCES,
            static fn(string $field): bool => ($record[$field] ?? null) === null || $record[$field] === ''
        ));

        $capabilityMetadata = json_decode((string) $record['capability_metadata_json'], true, flags: JSON_THROW_ON_ERROR);
        $consistencyError = $this->capabilityConsistencyError($capabilityMetadata);
        $status = ($missing === [] && $consistencyError === null) ? 'Verified' : 'Reference Pending';

        $this->setStatus($serviceId, $status);
        $this->emit($serviceId, $status);

        return ['outcome' => 'checked', 'discovery_status' => $status, 'missing_references' => $missing, 'error' => $consistencyError];
    }

    /**
     * Requires the record to already be `Verified` and to carry a
     * non-empty `governance_ref` as caller-supplied approval evidence --
     * re-checked here even though checkReferences() already requires it
     * for `Verified`, since updateReferences() can clear it afterward
     * without forcing a re-check.
     *
     * @return array{outcome: string, discovery_status: ?string, error: ?string}
     */
    public function markAvailable(string $serviceId): array
    {
        $record = $this->fetch($serviceId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'discovery_status' => null, 'error' => sprintf('Service "%s" is not discovered.', $serviceId)];
        }

        if (!in_array($record['discovery_status'], ['Verified', 'Unavailable'], true)) {
            return [
                'outcome' => 'not_verified',
                'discovery_status' => $record['discovery_status'],
                'error' => sprintf('Service "%s" must be verified (or restored from unavailable) before it can be marked available, not "%s".', $serviceId, $record['discovery_status']),
            ];
        }

        if (($record['governance_ref'] ?? '') === '' || $record['governance_ref'] === null) {
            return ['outcome' => 'missing_governance_reference', 'discovery_status' => $record['discovery_status'], 'error' => 'Marking a service available requires a governance approval reference.'];
        }

        $this->setStatus($serviceId, 'Available');
        $this->emit($serviceId, 'Available');

        return ['outcome' => 'available', 'discovery_status' => 'Available', 'error' => null];
    }

    /**
     * Records a caller-supplied availability status and reference --
     * this class never computes availability itself (Rule 4: general
     * health monitoring belongs to 27_OBSERVABILITY and
     * Integration Monitor). Only transitions the discovery-record status
     * when the record is already in the Available/Degraded/Unavailable
     * tier; otherwise the reference is recorded without a transition.
     *
     * Restoring into `Available` from `Degraded`/`Unavailable` requires a
     * non-empty `governance_ref`, the same caller-supplied approval
     * evidence `markAvailable()` requires -- without this, an operator
     * could bypass that invariant by clearing `governance_ref` via
     * updateReferences() while `Degraded`/`Unavailable` and then calling
     * this method instead of markAvailable() to reach `Available` anyway.
     * The reference is still recorded even when this guard blocks the
     * transition, since it's real evidence regardless of the resulting
     * status.
     *
     * @return array{outcome: string, discovery_status: ?string, error: ?string}
     */
    public function recordAvailability(string $serviceId, string $availabilityRef, string $availabilityStatus): array
    {
        if (!in_array($availabilityStatus, self::AVAILABILITY_STATUSES, true)) {
            return ['outcome' => 'invalid_availability_status', 'discovery_status' => null, 'error' => sprintf('"%s" is not a recognized availability status.', $availabilityStatus)];
        }

        $record = $this->fetch($serviceId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'discovery_status' => null, 'error' => sprintf('Service "%s" is not discovered.', $serviceId)];
        }

        $currentStatus = $record['discovery_status'];
        $inAvailabilityTier = in_array($currentStatus, self::AVAILABILITY_STATUSES, true);
        $restoringToAvailable = $inAvailabilityTier && $availabilityStatus === 'Available' && $currentStatus !== 'Available';

        if ($restoringToAvailable && (($record['governance_ref'] ?? '') === '' || $record['governance_ref'] === null)) {
            $this->setStatus($serviceId, $currentStatus, ['availability_ref' => $availabilityRef]);

            return [
                'outcome' => 'missing_governance_reference',
                'discovery_status' => $currentStatus,
                'error' => 'Restoring a service to available requires a governance approval reference.',
            ];
        }

        $nextStatus = $inAvailabilityTier ? $availabilityStatus : $currentStatus;

        $this->setStatus($serviceId, $nextStatus, ['availability_ref' => $availabilityRef]);

        if ($nextStatus !== $currentStatus) {
            $this->emit($serviceId, $nextStatus);
        }

        return ['outcome' => 'recorded', 'discovery_status' => $nextStatus, 'error' => null];
    }

    /**
     * Reachable from any non-Retired status: a service can be scheduled
     * for removal whether or not it was ever marked Available.
     *
     * @return array{outcome: string, discovery_status: ?string, error: ?string}
     */
    public function deprecate(string $serviceId, string $reason): array
    {
        $record = $this->fetch($serviceId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'discovery_status' => null, 'error' => sprintf('Service "%s" is not discovered.', $serviceId)];
        }

        if ($record['discovery_status'] === 'Retired') {
            return ['outcome' => 'already_retired', 'discovery_status' => 'Retired', 'error' => 'A retired service cannot be deprecated.'];
        }

        $this->setStatus($serviceId, 'Deprecated', ['deprecation_reason' => $reason]);
        $this->emit($serviceId, 'Deprecated');

        return ['outcome' => 'deprecated', 'discovery_status' => 'Deprecated', 'error' => null];
    }

    /**
     * Terminal state: the service is no longer available for new
     * integration use. Reachable from any non-retired status.
     *
     * @return array{outcome: string, discovery_status: ?string, error: ?string}
     */
    public function retire(string $serviceId, string $reason): array
    {
        $record = $this->fetch($serviceId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'discovery_status' => null, 'error' => sprintf('Service "%s" is not discovered.', $serviceId)];
        }

        if ($record['discovery_status'] === 'Retired') {
            return ['outcome' => 'already_retired', 'discovery_status' => 'Retired', 'error' => null];
        }

        $this->setStatus($serviceId, 'Retired', ['retirement_reason' => $reason]);
        $this->emit($serviceId, 'Retired');

        return ['outcome' => 'retired', 'discovery_status' => 'Retired', 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $serviceId): ?array
    {
        $record = $this->fetch($serviceId);

        return $record === null ? null : $this->hydrate($record);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByStatus(string $discoveryStatus): array
    {
        if (!in_array($discoveryStatus, self::DISCOVERY_STATUSES, true)) {
            return [];
        }

        $statement = $this->database->prepare(
            'SELECT * FROM service_discovery_records WHERE discovery_status = :discovery_status ORDER BY rowid'
        );
        $statement->execute(['discovery_status' => $discoveryStatus]);

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    /**
     * @param mixed $capabilityMetadata
     */
    private function capabilityConsistencyError($capabilityMetadata): ?string
    {
        if (!is_array($capabilityMetadata)) {
            return 'Capability metadata must be a list of operation names.';
        }

        $seen = [];

        foreach ($capabilityMetadata as $operation) {
            if (!is_string($operation) || trim($operation) === '') {
                return 'Capability metadata must not contain blank operation names.';
            }

            if (isset($seen[$operation])) {
                return sprintf('Capability metadata declares "%s" more than once.', $operation);
            }

            $seen[$operation] = true;
        }

        return null;
    }

    private function nullableString(?string $value): ?string
    {
        return ($value === null || $value === '') ? null : $value;
    }

    /**
     * @param array<string, string|null> $extraColumns
     */
    private function setStatus(string $serviceId, string $status, array $extraColumns = []): void
    {
        $assignments = ['discovery_status = :discovery_status', 'updated_at = :updated_at'];
        $params = ['service_id' => $serviceId, 'discovery_status' => $status, 'updated_at' => gmdate(DATE_RFC3339)];

        foreach ($extraColumns as $column => $value) {
            $assignments[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $statement = $this->database->prepare(
            sprintf('UPDATE service_discovery_records SET %s WHERE service_id = :service_id', implode(', ', $assignments))
        );
        $statement->execute($params);
    }

    private function emit(string $serviceId, string $status): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'service_discovery.' . strtolower(str_replace(' ', '_', $status)),
            new DateTimeImmutable(),
            self::class,
            ['service_id' => $serviceId, 'discovery_status' => $status]
        ));
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function hydrate(array $record): array
    {
        $record['capability_metadata'] = json_decode((string) $record['capability_metadata_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($record['capability_metadata_json']);

        return $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $serviceId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM service_discovery_records WHERE service_id = :service_id');
        $statement->execute(['service_id' => $serviceId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }
}
