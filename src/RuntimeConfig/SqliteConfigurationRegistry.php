<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use DateTimeImmutable;
use PDO;

/**
 * Owns the catalog of configuration items used by SquirrelForge --
 * identifiers, names, ownership, scope, data type, default references,
 * lifecycle status, version references, and registry metadata -- per
 * 28_RUNTIME-CONFIG/CONFIGURATION-REGISTRY.md -- the second real
 * component in `28_RUNTIME-CONFIG`'s gap.
 *
 * Picked second because its own "Depends On" -- the just-built
 * `SqliteConfigurationAudit` and the already-real `37_STORAGE` --
 * resolves entirely against existing code.
 *
 * "Registry changes must create configuration-domain history
 * references" (Rule 3) is read as a real, non-optional requirement,
 * not a secondary nicety: `register()`/`transition()` reject outright
 * when no `SqliteConfigurationAudit` is composed, the same fail-closed
 * stance `SqliteAgentGovernance` already takes toward its own
 * required Policy Engine, rather than silently proceeding without the
 * history reference the Rule explicitly demands. Every state change
 * maps onto one of `SqliteConfigurationAudit`'s own real Audited
 * Configuration Events (`Registered` -> `Configuration Registered`,
 * `Active` -> `Configuration Updated`, `Deprecated` -> `Configuration
 * Deprecated`, `Archived` -> `Configuration Archived`) -- a real
 * translation between this spec's own Registry States and that one's
 * own event vocabulary, since the two are genuinely different (five
 * registry states, nine audited events), the same "different owners,
 * different real vocabularies" treatment already established
 * throughout this session.
 *
 * The Registry States table gives no explicit transition-pair table
 * (unlike `AGENT-LIFECYCLE.md`) and no "may not skip a gate" rule
 * (unlike `STATE-MANAGER.md`), so this class does not fabricate a full
 * pairwise matrix neither exists to justify. It enforces only the few
 * guards the state *meanings* themselves make unambiguous: `Archived`
 * ("retained for history only") is terminal; an item that has already
 * left `Draft` ("metadata is incomplete or not approved") can never
 * regress back into it; and `Draft` itself may only advance to
 * `Registered` next, since `Active` ("may be resolved") presupposes
 * the item already "exists in the catalog," which is exactly what
 * `Registered` means.
 */
final class SqliteConfigurationRegistry
{
    /** Registry States, reproduced verbatim. */
    private const STATES = ['Draft', 'Registered', 'Active', 'Deprecated', 'Archived'];

    /** States a configuration item may legitimately start in. */
    private const INITIAL_STATES = ['Draft', 'Registered'];

    /** Maps this spec's own Registry States onto SqliteConfigurationAudit's real Audited Configuration Events. */
    private const STATE_TO_AUDIT_EVENT = [
        'Registered' => 'Configuration Registered',
        'Active' => 'Configuration Updated',
        'Deprecated' => 'Configuration Deprecated',
        'Archived' => 'Configuration Archived',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteConfigurationAudit $configurationAudit = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS configuration_registry (
                configuration_ref TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                owner TEXT NOT NULL,
                scope TEXT NOT NULL,
                data_type TEXT NOT NULL,
                default_ref TEXT,
                version_ref TEXT,
                metadata_json TEXT NOT NULL,
                state TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{
     *     name?: ?string,
     *     owner?: ?string,
     *     scope?: ?string,
     *     data_type?: ?string,
     *     default_ref?: ?string,
     *     version_ref?: ?string,
     *     metadata?: array<string, mixed>,
     *     initial_state?: ?string,
     *     actor_ref?: ?string,
     *     reason?: ?string
     * } $entry
     * @return array{outcome: string, configuration_ref: ?string, state: ?string, error: ?string}
     */
    public function register(array $entry): array
    {
        $name = $entry['name'] ?? null;
        $owner = $entry['owner'] ?? null;
        $scope = $entry['scope'] ?? null;
        $dataType = $entry['data_type'] ?? null;
        $actorRef = $entry['actor_ref'] ?? null;

        if (!$this->present($name) || !$this->present($owner) || !$this->present($scope) || !$this->present($dataType) || !$this->present($actorRef)) {
            return $this->envelope('invalid', null, null, 'Registration requires a non-empty name, owner, scope, data_type, and actor_ref.');
        }

        $initialState = $entry['initial_state'] ?? 'Registered';

        if (!in_array($initialState, self::INITIAL_STATES, true)) {
            return $this->envelope('invalid', null, null, sprintf('"%s" is not a state a configuration item may start in.', $initialState));
        }

        if ($this->configurationAudit === null) {
            return $this->envelope('rejected', null, null, 'Configuration Audit is not configured; registry changes must create configuration-domain history references.');
        }

        $configurationRef = 'config_' . bin2hex(random_bytes(12));
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $statement = $this->database->prepare(
            'INSERT INTO configuration_registry (
                configuration_ref, name, owner, scope, data_type, default_ref, version_ref, metadata_json, state, created_at, updated_at
            ) VALUES (
                :configuration_ref, :name, :owner, :scope, :data_type, :default_ref, :version_ref, :metadata_json, :state, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'configuration_ref' => $configurationRef,
            'name' => $name,
            'owner' => $owner,
            'scope' => $scope,
            'data_type' => $dataType,
            'default_ref' => $entry['default_ref'] ?? null,
            'version_ref' => $entry['version_ref'] ?? null,
            'metadata_json' => json_encode($entry['metadata'] ?? [], JSON_THROW_ON_ERROR),
            'state' => $initialState,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->configurationAudit->record([
            'event' => self::STATE_TO_AUDIT_EVENT[$initialState] ?? 'Configuration Updated',
            'configuration_ref' => $configurationRef,
            'actor_ref' => $actorRef,
            'reason' => $entry['reason'] ?? null,
            'new_state' => ['registry_state' => $initialState, 'name' => $name],
        ]);

        return $this->envelope('registered', $configurationRef, $initialState, null);
    }

    /**
     * @return array{outcome: string, configuration_ref: ?string, state: ?string, error: ?string}
     */
    public function transition(string $configurationRef, string $toState, string $actorRef, ?string $reason = null): array
    {
        if (!in_array($toState, self::STATES, true)) {
            return $this->envelope('invalid', $configurationRef, null, sprintf('"%s" is not one of this spec\'s named Registry States.', $toState));
        }

        if (!$this->present($actorRef)) {
            return $this->envelope('invalid', $configurationRef, null, 'A transition requires a non-empty actor_ref.');
        }

        $record = $this->get($configurationRef);

        if ($record === null) {
            return $this->envelope('invalid', $configurationRef, null, sprintf('"%s" is not a registered configuration item.', $configurationRef));
        }

        $currentState = $record['state'];

        if ($currentState === 'Archived') {
            return $this->envelope('rejected', $configurationRef, $currentState, 'Archived is a terminal registry state.');
        }

        if ($toState === 'Draft' && $currentState !== 'Draft') {
            return $this->envelope('rejected', $configurationRef, $currentState, 'A configuration item that has left Draft may never regress back into it.');
        }

        if ($currentState === 'Draft' && $toState !== 'Registered') {
            return $this->envelope('rejected', $configurationRef, $currentState, 'Draft may only advance to Registered next.');
        }

        if ($this->configurationAudit === null) {
            return $this->envelope('rejected', $configurationRef, $currentState, 'Configuration Audit is not configured; registry changes must create configuration-domain history references.');
        }

        $statement = $this->database->prepare('UPDATE configuration_registry SET state = :state, updated_at = :updated_at WHERE configuration_ref = :configuration_ref');
        $statement->execute(['state' => $toState, 'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM), 'configuration_ref' => $configurationRef]);

        $this->configurationAudit->record([
            'event' => self::STATE_TO_AUDIT_EVENT[$toState] ?? 'Configuration Updated',
            'configuration_ref' => $configurationRef,
            'actor_ref' => $actorRef,
            'reason' => $reason,
            'prior_state' => ['registry_state' => $currentState],
            'new_state' => ['registry_state' => $toState],
        ]);

        return $this->envelope('transitioned', $configurationRef, $toState, null);
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $configurationRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM configuration_registry WHERE configuration_ref = :configuration_ref');
        $statement->execute(['configuration_ref' => $configurationRef]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * "Expose registry metadata to Runtime Configuration components."
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(?string $state = null): array
    {
        if ($state !== null) {
            $statement = $this->database->prepare('SELECT * FROM configuration_registry WHERE state = :state ORDER BY rowid ASC');
            $statement->execute(['state' => $state]);
        } else {
            $statement = $this->database->query('SELECT * FROM configuration_registry ORDER BY rowid ASC');
        }

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['metadata'] = json_decode((string) $row['metadata_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['metadata_json']);

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{outcome: string, configuration_ref: ?string, state: ?string, error: ?string}
     */
    private function envelope(string $outcome, ?string $configurationRef, ?string $state, ?string $error): array
    {
        return ['outcome' => $outcome, 'configuration_ref' => $configurationRef, 'state' => $state, 'error' => $error];
    }
}
