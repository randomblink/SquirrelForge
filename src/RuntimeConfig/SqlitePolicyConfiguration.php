<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use DateTimeImmutable;
use PDO;

/**
 * Owns configurable policy value records and policy-reference records
 * used by policy-owning components -- identifiers, categories, values,
 * owners, scopes, versions, override references, and lifecycle status
 * -- per 28_RUNTIME-CONFIG/POLICY-CONFIGURATION.md -- the sixth real
 * component in `28_RUNTIME-CONFIG`'s gap.
 *
 * "Policy Configuration must not replace the policy owner that
 * interprets or evaluates the policy" (Rule 1) is upheld the same way
 * `SqliteConfigurationValidator` already treats `23_GOVERNANCE/POLICY-ENGINE.md`:
 * no dependency on `SqlitePolicyEngine` at all, even though it is the
 * first-listed "Depends On." This class stores the *values* a policy
 * references (a threshold, an allow-list, an override); interpreting
 * or evaluating what those values mean stays entirely with the Policy
 * Engine, which reads them from elsewhere -- this spec's own "Used By"
 * lists `23_GOVERNANCE/POLICY-ENGINE.md` as a *consumer*, never a
 * dependency this class calls into.
 *
 * A policy configuration value is a real `SqliteConfigurationRegistry`
 * item for identity, the same pattern `SqliteEnvironments`/
 * `SqliteFeatureFlags` already established; no separate lifecycle
 * vocabulary is fabricated since this spec names none of its own.
 *
 * "Policy configuration values must be registered, validated,
 * versioned, and traceable before use" (Rule 2) is read precisely:
 * *registration* does not itself require a passing validation (an item
 * may sit `Registered` while still being fixed up), but *use* --
 * `SqliteConfigurationRegistry`'s own `Active` state, the one that
 * means "may be resolved by runtime configuration" -- genuinely does.
 * `activate()` composes the real `SqliteConfigurationValidator::validate()`
 * result and refuses to promote to `Active` unless that spec's own
 * status is `Valid` or `Warning`; `Pending`/`Failed`/`Blocked`, or
 * never having been validated at all, all block activation. `version_ref`
 * is a required field at registration, not optional, since "versioned...
 * before use" is stated as a real requirement, not a nicety.
 *
 * "Preserve policy configuration changes through configuration-domain
 * history" composes the already-real `SqliteConfigurationAudit`
 * directly, using its own real `Policy Configuration Changed` event --
 * recorded alongside (not instead of) whatever event
 * `SqliteConfigurationRegistry::transition()` itself already records
 * through its own composed audit, the same "two different actors
 * recording two different aspects of one event" shape established
 * throughout this session.
 */
final class SqlitePolicyConfiguration
{
    /** SqliteConfigurationValidator's own real statuses that permit promoting a value to Active. */
    private const ACTIVATABLE_STATUSES = ['Valid', 'Warning'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteConfigurationRegistry $registry = null,
        private readonly ?SqliteConfigurationValidator $validator = null,
        private readonly ?SqliteConfigurationAudit $configurationAudit = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS policy_configuration_values (
                policy_config_ref TEXT PRIMARY KEY,
                category TEXT NOT NULL,
                value_json TEXT NOT NULL,
                version_ref TEXT NOT NULL,
                override_ref TEXT,
                validation_ref TEXT,
                validation_status TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{
     *     name?: ?string,
     *     category?: ?string,
     *     owner?: ?string,
     *     scope?: ?string,
     *     value?: mixed,
     *     version_ref?: ?string,
     *     override_ref?: ?string,
     *     actor_ref?: ?string
     * } $entry
     * @return array{outcome: string, policy_config_ref: ?string, error: ?string}
     */
    public function registerValue(array $entry): array
    {
        $name = $entry['name'] ?? null;
        $category = $entry['category'] ?? null;
        $owner = $entry['owner'] ?? null;
        $scope = $entry['scope'] ?? null;
        $versionRef = $entry['version_ref'] ?? null;
        $actorRef = $entry['actor_ref'] ?? null;

        if (!$this->present($name) || !$this->present($category) || !$this->present($owner) || !$this->present($scope) || !$this->present($versionRef) || !$this->present($actorRef)) {
            return $this->envelope('invalid', null, 'Registering a policy configuration value requires a non-empty name, category, owner, scope, version_ref, and actor_ref.');
        }

        $overrideRef = $entry['override_ref'] ?? null;

        if ($this->present($overrideRef) && ($this->registry === null || $this->registry->get($overrideRef) === null)) {
            return $this->envelope('rejected', null, sprintf('Override reference "%s" does not exist in the Configuration Registry.', $overrideRef));
        }

        if ($this->registry === null) {
            return $this->envelope('rejected', null, 'Configuration Registry is not configured; a policy configuration value must be a real registry item.');
        }

        $registration = $this->registry->register([
            'name' => $name,
            'owner' => $owner,
            'scope' => $scope,
            'data_type' => 'policy_configuration_value',
            'version_ref' => $versionRef,
            'actor_ref' => $actorRef,
        ]);

        if ($registration['outcome'] !== 'registered') {
            return $this->envelope('rejected', null, $registration['error']);
        }

        $policyConfigRef = $registration['configuration_ref'];
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $statement = $this->database->prepare(
            'INSERT INTO policy_configuration_values (
                policy_config_ref, category, value_json, version_ref, override_ref, validation_ref, validation_status, created_at, updated_at
            ) VALUES (
                :policy_config_ref, :category, :value_json, :version_ref, :override_ref, NULL, NULL, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'policy_config_ref' => $policyConfigRef,
            'category' => $category,
            'value_json' => json_encode($entry['value'] ?? null, JSON_THROW_ON_ERROR),
            'version_ref' => $versionRef,
            'override_ref' => $overrideRef,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->configurationAudit?->record([
            'event' => 'Policy Configuration Changed',
            'configuration_ref' => $policyConfigRef,
            'actor_ref' => $actorRef,
            'new_state' => ['category' => $category, 'version_ref' => $versionRef],
        ]);

        return $this->envelope('registered', $policyConfigRef, null);
    }

    /**
     * "Validate policy configuration structure and reference integrity
     * through CONFIGURATION-VALIDATOR.md" -- genuine composition, never
     * a fabricated check of its own. `validation_items` is forwarded
     * verbatim to `SqliteConfigurationValidator::validate()` for the
     * structure/schema/type-constraint side of that check, alongside
     * the override reference this class already knows about.
     *
     * @param ?array<int, array{item_id: string, stage: string, required: bool, blocking?: bool, status: string, waivable?: bool, repairable?: bool}> $validationItems Omit entirely to validate only the override reference; pass (even an empty array) to also run EngineValidation's own structural check.
     * @return array{outcome: string, policy_config_ref: ?string, status: ?string, error: ?string}
     */
    public function validate(string $policyConfigRef, string $actorRef, ?array $validationItems = null): array
    {
        $record = $this->row($policyConfigRef);

        if ($record === null) {
            return ['outcome' => 'invalid', 'policy_config_ref' => $policyConfigRef, 'status' => null, 'error' => sprintf('"%s" is not a registered policy configuration value.', $policyConfigRef)];
        }

        if ($this->validator === null) {
            return ['outcome' => 'rejected', 'policy_config_ref' => $policyConfigRef, 'status' => null, 'error' => 'Configuration Validator is not configured; a policy configuration value must be validated, not assumed valid.'];
        }

        $dependencyRefs = $this->present($record['override_ref']) ? [$record['override_ref']] : [];
        $validatorRequest = ['configuration_ref' => $policyConfigRef, 'dependency_refs' => $dependencyRefs];

        if ($validationItems !== null) {
            $validatorRequest['validation_items'] = $validationItems;
        }

        $result = $this->validator->validate($validatorRequest);

        $this->database->prepare('UPDATE policy_configuration_values SET validation_ref = :validation_ref, validation_status = :validation_status, updated_at = :updated_at WHERE policy_config_ref = :policy_config_ref')
            ->execute([
                'validation_ref' => $result['validation_ref'],
                'validation_status' => $result['status'],
                'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
                'policy_config_ref' => $policyConfigRef,
            ]);

        $this->configurationAudit?->record([
            'event' => 'Validation Recorded',
            'configuration_ref' => $policyConfigRef,
            'actor_ref' => $actorRef,
            'new_state' => ['validation_status' => $result['status']],
        ]);

        return ['outcome' => 'validated', 'policy_config_ref' => $policyConfigRef, 'status' => $result['status'], 'error' => null];
    }

    /**
     * Rule 2: promotion to Active ("may be resolved") requires a real,
     * passing validation -- never assumed.
     *
     * @return array{outcome: string, policy_config_ref: ?string, error: ?string}
     */
    public function activate(string $policyConfigRef, string $actorRef): array
    {
        $record = $this->row($policyConfigRef);

        if ($record === null) {
            return $this->envelope('invalid', $policyConfigRef, sprintf('"%s" is not a registered policy configuration value.', $policyConfigRef));
        }

        if (!in_array($record['validation_status'], self::ACTIVATABLE_STATUSES, true)) {
            return $this->envelope('rejected', $policyConfigRef, sprintf('Policy configuration values must be registered, validated, versioned, and traceable before use; current validation status is "%s".', $record['validation_status'] ?? 'never validated'));
        }

        if ($this->registry === null) {
            return $this->envelope('rejected', $policyConfigRef, 'Configuration Registry is not configured.');
        }

        $transition = $this->registry->transition($policyConfigRef, 'Active', $actorRef);

        if ($transition['outcome'] !== 'transitioned') {
            return $this->envelope('rejected', $policyConfigRef, $transition['error']);
        }

        $this->configurationAudit?->record([
            'event' => 'Policy Configuration Changed',
            'configuration_ref' => $policyConfigRef,
            'actor_ref' => $actorRef,
            'new_state' => ['registry_state' => 'Active'],
        ]);

        return $this->envelope('activated', $policyConfigRef, null);
    }

    /**
     * @return array{outcome: string, policy_config_ref: ?string, error: ?string}
     */
    public function updateValue(string $policyConfigRef, mixed $value, string $newVersionRef, string $actorRef): array
    {
        $record = $this->row($policyConfigRef);

        if ($record === null) {
            return $this->envelope('invalid', $policyConfigRef, sprintf('"%s" is not a registered policy configuration value.', $policyConfigRef));
        }

        if (!$this->present($newVersionRef) || $newVersionRef === $record['version_ref']) {
            return $this->envelope('invalid', $policyConfigRef, 'Updating a value requires a new, non-empty version_ref, distinct from the current one.');
        }

        $priorValue = json_decode((string) $record['value_json'], true, flags: JSON_THROW_ON_ERROR);

        // A changed value invalidates any prior validation -- it must be re-validated before further use.
        $this->database->prepare(
            'UPDATE policy_configuration_values SET value_json = :value_json, version_ref = :version_ref, validation_ref = NULL, validation_status = NULL, updated_at = :updated_at WHERE policy_config_ref = :policy_config_ref'
        )->execute([
            'value_json' => json_encode($value, JSON_THROW_ON_ERROR),
            'version_ref' => $newVersionRef,
            'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'policy_config_ref' => $policyConfigRef,
        ]);

        $this->configurationAudit?->record([
            'event' => 'Policy Configuration Changed',
            'configuration_ref' => $policyConfigRef,
            'actor_ref' => $actorRef,
            'prior_state' => ['value' => $priorValue, 'version_ref' => $record['version_ref']],
            'new_state' => ['value' => $value, 'version_ref' => $newVersionRef],
        ]);

        return $this->envelope('updated', $policyConfigRef, null);
    }

    /**
     * Combines the Registry's own real name/owner/state with this
     * class's own policy-value data.
     *
     * @return ?array<string, mixed>
     */
    public function get(string $policyConfigRef): ?array
    {
        $row = $this->row($policyConfigRef);

        if ($row === null) {
            return null;
        }

        $hydrated = $this->hydrate($row);
        $registryRecord = $this->registry?->get($policyConfigRef);
        $hydrated['name'] = $registryRecord['name'] ?? null;
        $hydrated['owner'] = $registryRecord['owner'] ?? null;
        $hydrated['lifecycle_status'] = $registryRecord['state'] ?? null;

        return $hydrated;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $policyConfigRef): array
    {
        return $this->configurationAudit?->history($policyConfigRef) ?? [];
    }

    /**
     * @return ?array<string, mixed>
     */
    private function row(string $policyConfigRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM policy_configuration_values WHERE policy_config_ref = :policy_config_ref');
        $statement->execute(['policy_config_ref' => $policyConfigRef]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['value'] = json_decode((string) $row['value_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['value_json']);

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{outcome: string, policy_config_ref: ?string, error: ?string}
     */
    private function envelope(string $outcome, ?string $policyConfigRef, ?string $error): array
    {
        return ['outcome' => $outcome, 'policy_config_ref' => $policyConfigRef, 'error' => $error];
    }
}
