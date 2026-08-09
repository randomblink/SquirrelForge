<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use DateTimeImmutable;
use PDO;

/**
 * Owns active runtime configuration resolution for running components
 * -- combining registered configuration records, environment overlays,
 * feature-flag configuration, policy-configuration references, and
 * secret references into validated active configuration bundles -- per
 * 28_RUNTIME-CONFIG/RUNTIME-CONFIGURATION.md -- the seventh and final
 * real component in `28_RUNTIME-CONFIG`'s gap, composing every sibling
 * this cluster built.
 *
 * "Runtime Configuration must use registered and configuration-domain-
 * validated records" (Rule 2) is upheld literally: the base
 * configuration item must already be `Active` in the real
 * `SqliteConfigurationRegistry` -- the one state that spec's own table
 * defines as "may be resolved by runtime configuration," the exact
 * phrase this spec's own Purpose reuses -- and the whole assembled set
 * of references is run through one real, holistic
 * `SqliteConfigurationValidator::validate()` call before a bundle may
 * resolve `Active`. A resolution never trusts a reference it has not
 * verified through a real composed sibling: an environment, feature
 * flag, or policy-configuration reference that does not genuinely
 * exist resolves the bundle `Invalid`, never silently proceeds.
 *
 * "Include secret references without exposing raw secret values"
 * (Responsibilities) does not compose the pre-existing
 * `SqliteSecretsManager` class: that class is a narrower, distinct
 * "AI-provider credential readiness" component (API-key registration/
 * rotation/verification only) that predates this cluster and exposes
 * no metadata-only read accessor a bundle could reference safely --
 * composing it would mean fabricating a capability it doesn't have.
 * Instead, `secret_refs` are treated as presence-only evidence, the
 * same shape `SqliteConfigurationValidator` itself already established
 * for secret references, and fed into that same holistic validation
 * call rather than duplicated here.
 *
 * "Runtime refresh does not authorize deployment or change workflow
 * state" (Rule 3) is upheld structurally: `refresh()` only re-resolves
 * and re-persists a bundle's own state; it has no dependency on, or
 * call into, any deployment or workflow-state owner.
 */
final class SqliteRuntimeConfiguration
{
    /**
     * SqliteConfigurationValidator's own real statuses that permit a
     * bundle to resolve Active. `Pending` is included deliberately: an
     * existence-only reference check (no `validation_items` declared)
     * can only ever produce `Pending` when every reference is genuinely
     * fine -- an existing dependency contributes nothing to that
     * validator's status list, only a missing one does -- so `Pending`
     * here means "nothing broken was found," not "nothing was
     * checked." This is a deliberately looser bar than
     * `SqlitePolicyConfiguration::activate()`'s one-time promotion
     * gate, which requires an explicit passing check before a *new*
     * record may ever become Active; this class only assembles a
     * bundle from components that are already real, already Active,
     * and already validated at their own point of activation.
     */
    private const ACTIVATABLE_VALIDATION_STATUSES = ['Valid', 'Warning', 'Pending'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteConfigurationRegistry $registry = null,
        private readonly ?SqliteEnvironments $environments = null,
        private readonly ?SqliteFeatureFlags $featureFlags = null,
        private readonly ?SqlitePolicyConfiguration $policyConfiguration = null,
        private readonly ?SqliteConfigurationValidator $validator = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS runtime_configuration_bundles (
                bundle_ref TEXT PRIMARY KEY,
                configuration_ref TEXT NOT NULL,
                environment_id TEXT,
                feature_flag_ids_json TEXT NOT NULL,
                policy_configuration_refs_json TEXT NOT NULL,
                secret_refs_json TEXT NOT NULL,
                context_json TEXT NOT NULL,
                state TEXT NOT NULL,
                validation_ref TEXT,
                error TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Resolves a validated active configuration bundle.
     *
     * @param array{
     *     configuration_ref?: ?string,
     *     environment_id?: ?string,
     *     feature_flag_ids?: array<int, string>,
     *     policy_configuration_refs?: array<int, string>,
     *     secret_refs?: array<int, string>,
     *     context?: array<string, mixed>,
     *     actor_ref?: ?string
     * } $request
     * @return array{outcome: string, bundle_ref: ?string, state: ?string, error: ?string}
     */
    public function resolve(array $request): array
    {
        $configurationRef = $request['configuration_ref'] ?? null;
        $actorRef = $request['actor_ref'] ?? null;

        if (!$this->present($configurationRef) || !$this->present($actorRef)) {
            return $this->envelope('invalid', null, null, 'Resolving a bundle requires a non-empty configuration_ref and actor_ref.');
        }

        if ($this->registry === null) {
            return $this->record($request, 'Invalid', null, 'Configuration Registry is not configured.');
        }

        $configRecord = $this->registry->get($configurationRef);

        if ($configRecord === null) {
            return $this->record($request, 'Invalid', null, sprintf('"%s" is not a registered configuration item.', $configurationRef));
        }

        if ($configRecord['state'] !== 'Active') {
            return $this->record($request, 'Invalid', null, sprintf('Configuration item "%s" is not Active in the registry; it may not be resolved by runtime configuration.', $configurationRef));
        }

        $environmentId = $request['environment_id'] ?? null;

        if ($this->present($environmentId)) {
            if ($this->environments === null || $this->environments->get($environmentId) === null) {
                return $this->record($request, 'Invalid', null, sprintf('Environment "%s" does not exist.', $environmentId));
            }
        }

        foreach ($request['feature_flag_ids'] ?? [] as $flagId) {
            if ($this->featureFlags === null) {
                return $this->record($request, 'Invalid', null, 'Feature Flags is not configured.');
            }

            $resolution = $this->featureFlags->evaluate($flagId, $request['context'] ?? []);

            if ($resolution['outcome'] !== 'resolved') {
                return $this->record($request, 'Invalid', null, sprintf('Feature flag "%s" does not exist.', $flagId));
            }
        }

        foreach ($request['policy_configuration_refs'] ?? [] as $policyRef) {
            if ($this->policyConfiguration === null || $this->policyConfiguration->get($policyRef) === null) {
                return $this->record($request, 'Invalid', null, sprintf('Policy configuration reference "%s" does not exist.', $policyRef));
            }
        }

        if ($this->validator === null) {
            return $this->record($request, 'Invalid', null, 'Configuration Validator is not configured; a bundle must be configuration-domain-validated, not assumed valid.');
        }

        $dependencyRefs = array_values(array_filter(array_merge(
            [$environmentId],
            $request['feature_flag_ids'] ?? [],
            $request['policy_configuration_refs'] ?? []
        ), fn(mixed $ref): bool => $this->present($ref)));

        $validation = $this->validator->validate([
            'configuration_ref' => $configurationRef,
            'dependency_refs' => $dependencyRefs,
            'secret_refs' => $request['secret_refs'] ?? [],
        ]);

        $state = in_array($validation['status'], self::ACTIVATABLE_VALIDATION_STATUSES, true) ? 'Active' : 'Invalid';
        $error = $state === 'Invalid' ? sprintf('Configuration-domain validation status was "%s".', $validation['status']) : null;

        return $this->record($request, $state, $validation['validation_ref'], $error);
    }

    /**
     * "Refresh active configuration bundles after approved
     * configuration-domain changes" -- re-resolves using the bundle's
     * own originally-declared references. Rule 3: never authorizes
     * deployment or workflow state, only re-resolves and re-persists.
     *
     * @return array{outcome: string, bundle_ref: ?string, state: ?string, error: ?string}
     */
    public function refresh(string $bundleRef, string $actorRef): array
    {
        $bundle = $this->get($bundleRef);

        if ($bundle === null) {
            return $this->envelope('invalid', $bundleRef, null, sprintf('"%s" is not a known configuration bundle.', $bundleRef));
        }

        if ($bundle['state'] === 'Expired') {
            return $this->envelope('rejected', $bundleRef, $bundle['state'], 'An Expired bundle may not be refreshed; resolve a new one instead.');
        }

        $this->database->prepare('UPDATE runtime_configuration_bundles SET state = :state, updated_at = :updated_at WHERE bundle_ref = :bundle_ref')
            ->execute(['state' => 'Refreshing', 'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM), 'bundle_ref' => $bundleRef]);

        $resolved = $this->resolve([
            'configuration_ref' => $bundle['configuration_ref'],
            'environment_id' => $bundle['environment_id'],
            'feature_flag_ids' => $bundle['feature_flag_ids'],
            'policy_configuration_refs' => $bundle['policy_configuration_refs'],
            'secret_refs' => $bundle['secret_refs'],
            'context' => $bundle['context'],
            'actor_ref' => $actorRef,
        ]);

        return $this->envelope('refreshed', $resolved['bundle_ref'], $resolved['state'], $resolved['error']);
    }

    /**
     * "Configuration bundle is no longer valid for use" -- a real,
     * terminal state.
     *
     * @return array{outcome: string, bundle_ref: ?string, state: ?string, error: ?string}
     */
    public function expire(string $bundleRef, string $actorRef, ?string $reason = null): array
    {
        $bundle = $this->get($bundleRef);

        if ($bundle === null) {
            return $this->envelope('invalid', $bundleRef, null, sprintf('"%s" is not a known configuration bundle.', $bundleRef));
        }

        $this->database->prepare('UPDATE runtime_configuration_bundles SET state = :state, error = :error, updated_at = :updated_at WHERE bundle_ref = :bundle_ref')
            ->execute(['state' => 'Expired', 'error' => $reason, 'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM), 'bundle_ref' => $bundleRef]);

        return $this->envelope('expired', $bundleRef, 'Expired', null);
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $bundleRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM runtime_configuration_bundles WHERE bundle_ref = :bundle_ref');
        $statement->execute(['bundle_ref' => $bundleRef]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * "Return runtime configuration status and evidence references to
     * callers" -- every bundle ever resolved for a configuration item,
     * in order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $configurationRef): array
    {
        $statement = $this->database->prepare('SELECT * FROM runtime_configuration_bundles WHERE configuration_ref = :configuration_ref ORDER BY rowid ASC');
        $statement->execute(['configuration_ref' => $configurationRef]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $request
     * @return array{outcome: string, bundle_ref: ?string, state: ?string, error: ?string}
     */
    private function record(array $request, string $state, ?string $validationRef, ?string $error): array
    {
        $bundleRef = 'runtime_bundle_' . bin2hex(random_bytes(12));
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $statement = $this->database->prepare(
            'INSERT INTO runtime_configuration_bundles (
                bundle_ref, configuration_ref, environment_id, feature_flag_ids_json, policy_configuration_refs_json,
                secret_refs_json, context_json, state, validation_ref, error, created_at, updated_at
            ) VALUES (
                :bundle_ref, :configuration_ref, :environment_id, :feature_flag_ids_json, :policy_configuration_refs_json,
                :secret_refs_json, :context_json, :state, :validation_ref, :error, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'bundle_ref' => $bundleRef,
            'configuration_ref' => $request['configuration_ref'],
            'environment_id' => $request['environment_id'] ?? null,
            'feature_flag_ids_json' => json_encode($request['feature_flag_ids'] ?? [], JSON_THROW_ON_ERROR),
            'policy_configuration_refs_json' => json_encode($request['policy_configuration_refs'] ?? [], JSON_THROW_ON_ERROR),
            'secret_refs_json' => json_encode($request['secret_refs'] ?? [], JSON_THROW_ON_ERROR),
            'context_json' => json_encode($request['context'] ?? [], JSON_THROW_ON_ERROR),
            'state' => $state,
            'validation_ref' => $validationRef,
            'error' => $error,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->envelope('resolved', $bundleRef, $state, $error);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['feature_flag_ids'] = json_decode((string) $row['feature_flag_ids_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['policy_configuration_refs'] = json_decode((string) $row['policy_configuration_refs_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['secret_refs'] = json_decode((string) $row['secret_refs_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['context'] = json_decode((string) $row['context_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['feature_flag_ids_json'], $row['policy_configuration_refs_json'], $row['secret_refs_json'], $row['context_json']);

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{outcome: string, bundle_ref: ?string, state: ?string, error: ?string}
     */
    private function envelope(string $outcome, ?string $bundleRef, ?string $state, ?string $error): array
    {
        return ['outcome' => $outcome, 'bundle_ref' => $bundleRef, 'state' => $state, 'error' => $error];
    }
}
