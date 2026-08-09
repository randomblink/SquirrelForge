<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use DateTimeImmutable;
use PDO;

/**
 * Owns environment profile records, environment overlays, inheritance
 * rules, environment-specific configuration references, and override
 * precedence for runtime configuration, per
 * 28_RUNTIME-CONFIG/ENVIRONMENTS.md -- the fourth real component in
 * `28_RUNTIME-CONFIG`'s gap, and the first to compose all three
 * already-real siblings this cluster has built so far.
 *
 * The Environment Record's own "Lifecycle Status: Active, deprecated,
 * or archived" is a real, literal three-value subset of
 * `SqliteConfigurationRegistry`'s own five Registry States. Rather
 * than duplicate a second lifecycle machine, each environment profile
 * *is* a real `SqliteConfigurationRegistry` item (`data_type =
 * environment_profile`), and its "Environment ID" is exactly the
 * Registry's own `configuration_ref` -- never a second, parallel
 * identifier. This class owns only what the Registry genuinely does
 * not: overlay references, inheritance order, and override precedence.
 *
 * "Environment overlays must be deterministic and traceable" (Rule 1)
 * is a real, checked guard: a duplicate overlay reference is rejected
 * outright, since resolving the same overlay twice (in an unspecified
 * order) is exactly the kind of non-determinism the Rule forbids.
 *
 * "Environment profiles may reference secret identifiers but must not
 * contain raw secret values" (Rule 2) reuses `SqliteToolConfig`'s own
 * secret-shaped-key pattern for outright rejection, not redaction, of
 * any override rule whose key name looks like a secret --
 * distinguishing a genuine reference from an inline raw value is not
 * mechanically possible from a value alone, so the same key-name
 * heuristic this codebase already relies on elsewhere is reused here
 * rather than inventing a new one.
 *
 * "Preserve environment profile changes through configuration-domain
 * history" composes the already-real `SqliteConfigurationAudit`
 * directly, using its own real `Environment Overlay Changed` event --
 * one of the nine Audited Configuration Events that spec already
 * names, not a fabricated new one. "Environment validation is
 * configuration-domain validation only" (Rule 3) composes the
 * already-real `SqliteConfigurationValidator::validate()` for a
 * profile's declared `parent_profile` dependency, never a deployment-
 * readiness decision this class has no authority to make.
 */
final class SqliteEnvironments
{
    /** Reused verbatim from SqliteToolConfig's own secret-shaped-key heuristic, for outright rejection. */
    private const SECRET_SHAPED_KEY_PATTERN = '/password|token|secret|api[_-]?key|credential|authorization/i';

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
            'CREATE TABLE IF NOT EXISTS environments (
                environment_id TEXT PRIMARY KEY,
                parent_profile TEXT,
                overlay_refs_json TEXT NOT NULL,
                override_rules_json TEXT NOT NULL,
                validation_ref TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{
     *     name?: ?string,
     *     actor_ref?: ?string,
     *     parent_profile?: ?string,
     *     overlay_refs?: array<int, string>,
     *     override_rules?: array<string, mixed>
     * } $entry
     * @return array{outcome: string, environment_id: ?string, error: ?string}
     */
    public function registerProfile(array $entry): array
    {
        $name = $entry['name'] ?? null;
        $actorRef = $entry['actor_ref'] ?? null;

        if (!$this->present($name) || !$this->present($actorRef)) {
            return $this->envelope('invalid', null, 'Registering an environment profile requires a non-empty name and actor_ref.');
        }

        $overlayRefs = $entry['overlay_refs'] ?? [];
        $duplicateOverlay = $this->firstDuplicate($overlayRefs);

        if ($duplicateOverlay !== null) {
            return $this->envelope('rejected', null, sprintf('Overlay reference "%s" is declared more than once; overlays must be deterministic and traceable.', $duplicateOverlay));
        }

        $overrideRules = $entry['override_rules'] ?? [];
        $secretShapedKey = $this->firstSecretShapedKey($overrideRules);

        if ($secretShapedKey !== null) {
            return $this->envelope('rejected', null, sprintf('Override rule key "%s" looks like a raw secret; environment profiles may only reference secret identifiers.', $secretShapedKey));
        }

        $parentProfile = $entry['parent_profile'] ?? null;

        if ($this->present($parentProfile) && $this->get($parentProfile) === null) {
            return $this->envelope('rejected', null, sprintf('Parent profile "%s" is not a registered environment.', $parentProfile));
        }

        if ($this->registry === null) {
            return $this->envelope('rejected', null, 'Configuration Registry is not configured; an environment profile must be a real registry item.');
        }

        $registration = $this->registry->register([
            'name' => $name,
            'owner' => $actorRef,
            'scope' => 'environment',
            'data_type' => 'environment_profile',
            'actor_ref' => $actorRef,
            'metadata' => ['parent_profile' => $parentProfile],
        ]);

        if ($registration['outcome'] !== 'registered') {
            return $this->envelope('rejected', null, $registration['error']);
        }

        $environmentId = $registration['configuration_ref'];
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $statement = $this->database->prepare(
            'INSERT INTO environments (environment_id, parent_profile, overlay_refs_json, override_rules_json, validation_ref, created_at, updated_at)
             VALUES (:environment_id, :parent_profile, :overlay_refs_json, :override_rules_json, NULL, :created_at, :updated_at)'
        );
        $statement->execute([
            'environment_id' => $environmentId,
            'parent_profile' => $parentProfile,
            'overlay_refs_json' => json_encode($overlayRefs, JSON_THROW_ON_ERROR),
            'override_rules_json' => json_encode($overrideRules, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->configurationAudit?->record([
            'event' => 'Environment Overlay Changed',
            'configuration_ref' => $environmentId,
            'actor_ref' => $actorRef,
            'new_state' => ['overlay_refs' => $overlayRefs, 'override_rules' => $overrideRules, 'parent_profile' => $parentProfile],
        ]);

        if ($this->validator !== null && $this->present($parentProfile)) {
            $validation = $this->validator->validate(['configuration_ref' => $environmentId, 'dependency_refs' => [$parentProfile]]);
            $this->database->prepare('UPDATE environments SET validation_ref = :validation_ref WHERE environment_id = :environment_id')
                ->execute(['validation_ref' => $validation['validation_ref'], 'environment_id' => $environmentId]);
        }

        return $this->envelope('registered', $environmentId, null);
    }

    /**
     * @param array<int, string> $overlayRefs
     * @return array{outcome: string, environment_id: ?string, error: ?string}
     */
    public function updateOverlay(string $environmentId, array $overlayRefs, string $actorRef): array
    {
        if (!$this->present($actorRef)) {
            return $this->envelope('invalid', $environmentId, 'Updating an overlay requires a non-empty actor_ref.');
        }

        $existing = $this->get($environmentId);

        if ($existing === null) {
            return $this->envelope('invalid', $environmentId, sprintf('"%s" is not a registered environment.', $environmentId));
        }

        $duplicateOverlay = $this->firstDuplicate($overlayRefs);

        if ($duplicateOverlay !== null) {
            return $this->envelope('rejected', $environmentId, sprintf('Overlay reference "%s" is declared more than once; overlays must be deterministic and traceable.', $duplicateOverlay));
        }

        $priorOverlayRefs = $existing['overlay_refs'];

        $this->database->prepare('UPDATE environments SET overlay_refs_json = :overlay_refs_json, updated_at = :updated_at WHERE environment_id = :environment_id')
            ->execute(['overlay_refs_json' => json_encode($overlayRefs, JSON_THROW_ON_ERROR), 'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM), 'environment_id' => $environmentId]);

        $this->configurationAudit?->record([
            'event' => 'Environment Overlay Changed',
            'configuration_ref' => $environmentId,
            'actor_ref' => $actorRef,
            'prior_state' => ['overlay_refs' => $priorOverlayRefs],
            'new_state' => ['overlay_refs' => $overlayRefs],
        ]);

        return $this->envelope('updated', $environmentId, null);
    }

    /**
     * Combines the Registry's own real lifecycle state with this
     * class's own overlay/override/inheritance data -- one Environment
     * Record, assembled from its two real owners.
     *
     * @return ?array<string, mixed>
     */
    public function get(string $environmentId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM environments WHERE environment_id = :environment_id');
        $statement->execute(['environment_id' => $environmentId]);
        $row = $statement->fetch();

        if ($row === false) {
            return null;
        }

        $hydrated = $this->hydrate($row);
        $registryRecord = $this->registry?->get($environmentId);

        $hydrated['name'] = $registryRecord['name'] ?? null;
        $hydrated['lifecycle_status'] = $registryRecord['state'] ?? null;

        return $hydrated;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $environmentId): array
    {
        return $this->configurationAudit?->history($environmentId) ?? [];
    }

    /**
     * @param array<int, string> $refs
     */
    private function firstDuplicate(array $refs): ?string
    {
        $seen = [];

        foreach ($refs as $ref) {
            if (isset($seen[$ref])) {
                return $ref;
            }

            $seen[$ref] = true;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $overrideRules
     */
    private function firstSecretShapedKey(array $overrideRules): ?string
    {
        foreach (array_keys($overrideRules) as $key) {
            if (preg_match(self::SECRET_SHAPED_KEY_PATTERN, (string) $key) === 1) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['overlay_refs'] = json_decode((string) $row['overlay_refs_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['override_rules'] = json_decode((string) $row['override_rules_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['overlay_refs_json'], $row['override_rules_json']);

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{outcome: string, environment_id: ?string, error: ?string}
     */
    private function envelope(string $outcome, ?string $environmentId, ?string $error): array
    {
        return ['outcome' => $outcome, 'environment_id' => $environmentId, 'error' => $error];
    }
}
