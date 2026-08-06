<?php

declare(strict_types=1);

namespace SquirrelForge\Integration;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Owns the registry of approved connector definitions used by the
 * Integrations layer, per 26_INTEGRATIONS/CONNECTOR-MANAGER.md -- the
 * second real component in 26_INTEGRATIONS, after IntegrationAuthentication.
 *
 * This spec's own header lists `26_INTEGRATIONS/INTEGRATION-GOVERNANCE.md`
 * as a dependency, but that component has no code yet -- the same
 * situation IntegrationAuthentication documented for its own Policy
 * Engine references before Policy Engine existed. `governance_ref` is
 * therefore always caller-supplied evidence, never a live call: the
 * Connector Record table itself already names it a "reference," and
 * Rule 1 requires connector records to "use references... for...
 * governance approvals," not a governance decision this class computes.
 * The same is true of `configuration_ref` and `credential_ref` --
 * `21_CONFIGURATION` and `28_RUNTIME-CONFIG` own those, this class only
 * stores the reference string it's given.
 *
 * "Connector readiness checks... limited to registry and
 * connector-definition readiness" (the spec's own Connector Readiness
 * Checks section) is upheld literally: checkReadiness() only confirms
 * required *references* are non-empty strings and that capability
 * metadata is internally consistent (no duplicate or blank operation
 * names) -- it never dereferences those strings against Security,
 * RuntimeConfig, or Governance to verify they're actually valid, which
 * the spec's own Boundary explicitly excludes ("does not... make
 * authentication or authorization decisions").
 *
 * Registered/Readiness Pending/Ready are kept as three genuinely
 * distinct, independently reachable states rather than collapsing
 * readiness evaluation into register() itself: a freshly registered
 * connector sits at `registered` (component recorded, "not yet checked"
 * per the spec's own Readiness Pending definition) until an explicit
 * checkReadiness() call resolves it to `ready` or `readiness_pending`.
 * Folding that check into register() would make "not yet checked" an
 * unreachable state the spec names but this class could never produce.
 *
 * activate() requires the stored status to already be `ready` --
 * matching Rule 4 ("activation and deactivation are registry lifecycle
 * decisions only," a separate act from readiness evaluation) -- and a
 * non-empty `governance_ref`, the caller-supplied approval evidence
 * "Active: Connector is approved for routing consideration" describes.
 * This class never decides that approval, it only requires the
 * reference to exist, the same "consume, don't compute" boundary
 * SqliteFailoverCoordinator draws around its own `authorized` field.
 *
 * `degraded`/`recordAvailability()` never computes availability itself
 * (general health monitoring is explicitly out of Boundary) -- it only
 * records the caller-supplied reference and toggles active/degraded
 * based on the caller-supplied `degraded` flag, mirroring how
 * SqliteFailureDetector treats a caller-reported signal.
 */
final class SqliteConnectorManager
{
    private const LIFECYCLE_STATUSES = [
        'registered', 'readiness_pending', 'ready', 'active', 'degraded', 'suspended', 'retired',
    ];

    private const REQUIRED_READINESS_REFERENCES = [
        'endpoint_ref', 'protocol_ref', 'configuration_ref', 'credential_ref', 'governance_ref',
    ];

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
            'CREATE TABLE IF NOT EXISTS connector_registry (
                connector_id TEXT PRIMARY KEY,
                connector_name TEXT NOT NULL,
                version TEXT NOT NULL,
                owner_ref TEXT NOT NULL,
                provider_ref TEXT,
                endpoint_ref TEXT,
                protocol_ref TEXT,
                configuration_ref TEXT,
                credential_ref TEXT,
                governance_ref TEXT,
                capability_metadata_json TEXT NOT NULL,
                availability_ref TEXT,
                lifecycle_status TEXT NOT NULL,
                deactivation_reason TEXT,
                retirement_reason TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{connector_name: string, version: string, owner_ref: string, provider_ref?: ?string, endpoint_ref?: ?string, protocol_ref?: ?string, configuration_ref?: ?string, credential_ref?: ?string, governance_ref?: ?string, capability_metadata?: array<int, string>} $definition
     * @return array{outcome: string, connector_id: ?string, lifecycle_status: ?string, error: ?string}
     */
    public function register(array $definition): array
    {
        $name = $definition['connector_name'] ?? '';
        $version = $definition['version'] ?? '';
        $ownerRef = $definition['owner_ref'] ?? '';

        if ($name === '' || $version === '' || $ownerRef === '') {
            return [
                'outcome' => 'invalid',
                'connector_id' => null,
                'lifecycle_status' => null,
                'error' => 'Registration requires a non-empty connector_name, version, and owner_ref.',
            ];
        }

        $capabilityMetadata = $definition['capability_metadata'] ?? [];
        $consistencyError = $this->capabilityConsistencyError($capabilityMetadata);

        if ($consistencyError !== null) {
            return ['outcome' => 'invalid_capability_metadata', 'connector_id' => null, 'lifecycle_status' => null, 'error' => $consistencyError];
        }

        $connectorId = 'connector_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO connector_registry (
                connector_id, connector_name, version, owner_ref, provider_ref, endpoint_ref,
                protocol_ref, configuration_ref, credential_ref, governance_ref,
                capability_metadata_json, availability_ref, lifecycle_status,
                deactivation_reason, retirement_reason, created_at, updated_at
            ) VALUES (
                :connector_id, :connector_name, :version, :owner_ref, :provider_ref, :endpoint_ref,
                :protocol_ref, :configuration_ref, :credential_ref, :governance_ref,
                :capability_metadata_json, NULL, :lifecycle_status,
                NULL, NULL, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'connector_id' => $connectorId,
            'connector_name' => $name,
            'version' => $version,
            'owner_ref' => $ownerRef,
            'provider_ref' => $this->nullableString($definition['provider_ref'] ?? null),
            'endpoint_ref' => $this->nullableString($definition['endpoint_ref'] ?? null),
            'protocol_ref' => $this->nullableString($definition['protocol_ref'] ?? null),
            'configuration_ref' => $this->nullableString($definition['configuration_ref'] ?? null),
            'credential_ref' => $this->nullableString($definition['credential_ref'] ?? null),
            'governance_ref' => $this->nullableString($definition['governance_ref'] ?? null),
            'capability_metadata_json' => json_encode($capabilityMetadata, JSON_THROW_ON_ERROR),
            'lifecycle_status' => 'registered',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->emit($connectorId, 'registered');

        return ['outcome' => 'registered', 'connector_id' => $connectorId, 'lifecycle_status' => 'registered', 'error' => null];
    }

    /**
     * Patches connector reference fields and capability metadata --
     * always a plain field update, never a lifecycle transition on its
     * own (per Rule 4, activation/deactivation are separate decisions).
     *
     * @param array{provider_ref?: ?string, endpoint_ref?: ?string, protocol_ref?: ?string, configuration_ref?: ?string, credential_ref?: ?string, governance_ref?: ?string, capability_metadata?: array<int, string>} $references
     * @return array{outcome: string, error: ?string}
     */
    public function updateReferences(string $connectorId, array $references): array
    {
        $record = $this->fetch($connectorId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Connector "%s" is not registered.', $connectorId)];
        }

        if (array_key_exists('capability_metadata', $references)) {
            $consistencyError = $this->capabilityConsistencyError($references['capability_metadata']);

            if ($consistencyError !== null) {
                return ['outcome' => 'invalid_capability_metadata', 'error' => $consistencyError];
            }
        }

        $columns = ['provider_ref', 'endpoint_ref', 'protocol_ref', 'configuration_ref', 'credential_ref', 'governance_ref'];
        $assignments = [];
        $params = ['connector_id' => $connectorId, 'updated_at' => gmdate(DATE_RFC3339)];

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
            sprintf('UPDATE connector_registry SET %s WHERE connector_id = :connector_id', implode(', ', $assignments))
        );
        $statement->execute($params);

        return ['outcome' => 'updated', 'error' => null];
    }

    /**
     * Confirms required references exist and declared capabilities are
     * internally consistent -- registry/schema readiness only, per the
     * spec's own Connector Readiness Checks section. Never touches
     * activation, availability, or any state outside
     * registered/readiness_pending/ready.
     *
     * @return array{outcome: string, lifecycle_status: ?string, missing_references: array<int, string>, error: ?string}
     */
    public function checkReadiness(string $connectorId): array
    {
        $record = $this->fetch($connectorId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'lifecycle_status' => null, 'missing_references' => [], 'error' => sprintf('Connector "%s" is not registered.', $connectorId)];
        }

        if (!in_array($record['lifecycle_status'], ['registered', 'readiness_pending', 'ready'], true)) {
            return [
                'outcome' => 'not_applicable',
                'lifecycle_status' => $record['lifecycle_status'],
                'missing_references' => [],
                'error' => sprintf('Connector "%s" is past readiness evaluation (status: %s).', $connectorId, $record['lifecycle_status']),
            ];
        }

        $missing = array_values(array_filter(
            self::REQUIRED_READINESS_REFERENCES,
            static fn(string $field): bool => ($record[$field] ?? null) === null || $record[$field] === ''
        ));

        $capabilityMetadata = json_decode((string) $record['capability_metadata_json'], true, flags: JSON_THROW_ON_ERROR);
        $consistencyError = $this->capabilityConsistencyError($capabilityMetadata);
        $status = ($missing === [] && $consistencyError === null) ? 'ready' : 'readiness_pending';

        $this->setStatus($connectorId, $status);
        $this->emit($connectorId, $status);

        return ['outcome' => 'checked', 'lifecycle_status' => $status, 'missing_references' => $missing, 'error' => $consistencyError];
    }

    /**
     * Registry lifecycle decision only (Rule 4): requires the connector
     * to already be `ready` and to carry a non-empty `governance_ref` as
     * its caller-supplied approval evidence. Never itself decides
     * whether that approval is valid.
     *
     * The `governance_ref` check is not dead weight even though
     * `governance_ref` is also one of checkReadiness()'s required
     * references: updateReferences() can clear it after a connector has
     * already reached `ready` without forcing a re-check, so this guards
     * against activating on a now-stale ready status.
     *
     * @return array{outcome: string, lifecycle_status: ?string, error: ?string}
     */
    public function activate(string $connectorId): array
    {
        $record = $this->fetch($connectorId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'lifecycle_status' => null, 'error' => sprintf('Connector "%s" is not registered.', $connectorId)];
        }

        if (!in_array($record['lifecycle_status'], ['ready', 'suspended'], true)) {
            return [
                'outcome' => 'not_ready',
                'lifecycle_status' => $record['lifecycle_status'],
                'error' => sprintf('Connector "%s" must be ready (or reactivated from suspended) before activation, not "%s".', $connectorId, $record['lifecycle_status']),
            ];
        }

        if (($record['governance_ref'] ?? '') === '' || $record['governance_ref'] === null) {
            return ['outcome' => 'missing_governance_reference', 'lifecycle_status' => $record['lifecycle_status'], 'error' => 'Activation requires a governance approval reference.'];
        }

        $this->setStatus($connectorId, 'active');
        $this->emit($connectorId, 'active');

        return ['outcome' => 'activated', 'lifecycle_status' => 'active', 'error' => null];
    }

    /**
     * Administratively removes a connector from routing consideration
     * without retiring it -- Rule 4's "registry lifecycle decision only,"
     * not recovery execution or a workflow state change.
     *
     * @return array{outcome: string, lifecycle_status: ?string, error: ?string}
     */
    public function deactivate(string $connectorId, string $reason): array
    {
        $record = $this->fetch($connectorId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'lifecycle_status' => null, 'error' => sprintf('Connector "%s" is not registered.', $connectorId)];
        }

        if (!in_array($record['lifecycle_status'], ['active', 'degraded'], true)) {
            return [
                'outcome' => 'not_active',
                'lifecycle_status' => $record['lifecycle_status'],
                'error' => sprintf('Connector "%s" is not active or degraded (status: %s).', $connectorId, $record['lifecycle_status']),
            ];
        }

        $this->setStatus($connectorId, 'suspended', ['deactivation_reason' => $reason]);
        $this->emit($connectorId, 'suspended');

        return ['outcome' => 'deactivated', 'lifecycle_status' => 'suspended', 'error' => null];
    }

    /**
     * Terminal lifecycle state: the connector is no longer available for
     * new integration use. Reachable from any non-retired status.
     *
     * @return array{outcome: string, lifecycle_status: ?string, error: ?string}
     */
    public function retire(string $connectorId, string $reason): array
    {
        $record = $this->fetch($connectorId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'lifecycle_status' => null, 'error' => sprintf('Connector "%s" is not registered.', $connectorId)];
        }

        if ($record['lifecycle_status'] === 'retired') {
            return ['outcome' => 'already_retired', 'lifecycle_status' => 'retired', 'error' => null];
        }

        $this->setStatus($connectorId, 'retired', ['retirement_reason' => $reason]);
        $this->emit($connectorId, 'retired');

        return ['outcome' => 'retired', 'lifecycle_status' => 'retired', 'error' => null];
    }

    /**
     * Records a caller-supplied availability reference and toggles
     * active/degraded per the caller-supplied `$degraded` flag -- this
     * class never computes availability itself (general health
     * monitoring is out of Boundary), it only records what integration
     * monitoring or observability owners already determined.
     *
     * @return array{outcome: string, lifecycle_status: ?string, error: ?string}
     */
    public function recordAvailability(string $connectorId, string $availabilityRef, bool $degraded): array
    {
        $record = $this->fetch($connectorId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'lifecycle_status' => null, 'error' => sprintf('Connector "%s" is not registered.', $connectorId)];
        }

        $currentStatus = $record['lifecycle_status'];
        $nextStatus = $currentStatus;

        if ($degraded && $currentStatus === 'active') {
            $nextStatus = 'degraded';
        } elseif (!$degraded && $currentStatus === 'degraded') {
            $nextStatus = 'active';
        }

        $this->setStatus($connectorId, $nextStatus, ['availability_ref' => $availabilityRef]);

        if ($nextStatus !== $currentStatus) {
            $this->emit($connectorId, $nextStatus);
        }

        return ['outcome' => 'recorded', 'lifecycle_status' => $nextStatus, 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $connectorId): ?array
    {
        $record = $this->fetch($connectorId);

        return $record === null ? null : $this->hydrate($record);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByStatus(string $lifecycleStatus): array
    {
        if (!in_array($lifecycleStatus, self::LIFECYCLE_STATUSES, true)) {
            return [];
        }

        $statement = $this->database->prepare(
            'SELECT * FROM connector_registry WHERE lifecycle_status = :lifecycle_status ORDER BY rowid'
        );
        $statement->execute(['lifecycle_status' => $lifecycleStatus]);

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
    /**
     * @param array<string, string|null> $extraColumns
     */
    private function setStatus(string $connectorId, string $status, array $extraColumns = []): void
    {
        $assignments = ['lifecycle_status = :lifecycle_status', 'updated_at = :updated_at'];
        $params = ['connector_id' => $connectorId, 'lifecycle_status' => $status, 'updated_at' => gmdate(DATE_RFC3339)];

        foreach ($extraColumns as $column => $value) {
            $assignments[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $statement = $this->database->prepare(
            sprintf('UPDATE connector_registry SET %s WHERE connector_id = :connector_id', implode(', ', $assignments))
        );
        $statement->execute($params);
    }

    private function emit(string $connectorId, string $status): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'connector.' . $status,
            new DateTimeImmutable(),
            self::class,
            ['connector_id' => $connectorId, 'lifecycle_status' => $status]
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
    private function fetch(string $connectorId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM connector_registry WHERE connector_id = :connector_id');
        $statement->execute(['connector_id' => $connectorId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }
}
