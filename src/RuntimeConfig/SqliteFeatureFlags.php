<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use DateTimeImmutable;
use PDO;

/**
 * Owns feature-flag configuration records, flag lifecycle status,
 * targeting references, rollout settings, dependency references, and
 * kill-switch configuration status, per
 * 28_RUNTIME-CONFIG/FEATURE-FLAGS.md -- the fifth real component in
 * `28_RUNTIME-CONFIG`'s gap.
 *
 * Unlike `SqliteEnvironments`, whose own "Lifecycle Status" was a
 * literal subset of `SqliteConfigurationRegistry`'s Registry States,
 * this spec's six Feature Flag States (`Disabled`/`Enabled`/
 * `Experimental`/`Beta`/`Deprecated`/`Retired`) are a genuinely
 * distinct vocabulary -- only `Deprecated` overlaps by name. A feature
 * flag is still a real `SqliteConfigurationRegistry` item for identity
 * (`data_type = feature_flag`) and its audit anchor, but this class
 * owns its own, separate operational-state column: registry cataloging
 * and feature operational state are two orthogonal concerns here, not
 * one reused field.
 *
 * "Evaluate configuration-level targeting rules against supplied
 * context references" (Responsibilities) is a real, deterministic
 * membership check -- every declared targeting rule's context key must
 * match one of its allowed values for the supplied context, an AND-of-
 * membership-checks evaluator in the same spirit as `TaskRouter`'s own
 * real permission/domain matching. No declared targeting rules means
 * no restriction is configured, so the flag matches every context --
 * the standard feature-flag semantic this spec's own "configured
 * available for allowed contexts" wording implies (an empty rule set
 * imposes no restriction to check).
 *
 * `Disabled`/`Retired` resolve unavailable for every context
 * regardless of targeting rules -- both states mean "not available"
 * by their own definition, so no context can override that. A kill
 * switch, when engaged, forces the same unavailable resolution
 * regardless of the configured state, since the whole point of a kill
 * switch is to override normal targeting.
 *
 * "Feature Flags may record kill-switch configuration status but must
 * not execute incident response or rollback" (Rule 2) is upheld
 * structurally: `setKillSwitch()` only ever writes a boolean record;
 * this class exposes no method that could execute a response.
 *
 * "Feature Flag changes must preserve configuration-domain history"
 * (Rule 3) composes the already-real `SqliteConfigurationAudit`
 * directly, using its own real `Feature Flag Changed` event.
 */
final class SqliteFeatureFlags
{
    /** Feature Flag States, reproduced verbatim. */
    private const STATES = ['Disabled', 'Enabled', 'Experimental', 'Beta', 'Deprecated', 'Retired'];

    private const UNAVAILABLE_STATES = ['Disabled', 'Retired'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteConfigurationRegistry $registry = null,
        private readonly ?SqliteConfigurationAudit $configurationAudit = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS feature_flags (
                flag_id TEXT PRIMARY KEY,
                state TEXT NOT NULL,
                dependency_refs_json TEXT NOT NULL,
                targeting_rules_json TEXT NOT NULL,
                rollout_json TEXT NOT NULL,
                kill_switch_engaged INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{
     *     name?: ?string,
     *     actor_ref?: ?string,
     *     initial_state?: ?string,
     *     dependency_refs?: array<int, string>,
     *     targeting_rules?: array<int, array{context_key: string, allowed_values: array<int, mixed>}>,
     *     rollout?: array<string, mixed>
     * } $entry
     * @return array{outcome: string, flag_id: ?string, state: ?string, error: ?string}
     */
    public function registerFlag(array $entry): array
    {
        $name = $entry['name'] ?? null;
        $actorRef = $entry['actor_ref'] ?? null;

        if (!$this->present($name) || !$this->present($actorRef)) {
            return $this->envelope('invalid', null, null, 'Registering a feature flag requires a non-empty name and actor_ref.');
        }

        $initialState = $entry['initial_state'] ?? 'Disabled';

        if (!in_array($initialState, self::STATES, true)) {
            return $this->envelope('invalid', null, null, sprintf('"%s" is not one of this spec\'s named Feature Flag States.', $initialState));
        }

        if ($this->registry === null) {
            return $this->envelope('rejected', null, null, 'Configuration Registry is not configured; a feature flag must be a real registry item.');
        }

        $registration = $this->registry->register([
            'name' => $name,
            'owner' => $actorRef,
            'scope' => 'feature_flag',
            'data_type' => 'feature_flag',
            'actor_ref' => $actorRef,
        ]);

        if ($registration['outcome'] !== 'registered') {
            return $this->envelope('rejected', null, null, $registration['error']);
        }

        $flagId = $registration['configuration_ref'];
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $statement = $this->database->prepare(
            'INSERT INTO feature_flags (flag_id, state, dependency_refs_json, targeting_rules_json, rollout_json, kill_switch_engaged, created_at, updated_at)
             VALUES (:flag_id, :state, :dependency_refs_json, :targeting_rules_json, :rollout_json, 0, :created_at, :updated_at)'
        );
        $statement->execute([
            'flag_id' => $flagId,
            'state' => $initialState,
            'dependency_refs_json' => json_encode($entry['dependency_refs'] ?? [], JSON_THROW_ON_ERROR),
            'targeting_rules_json' => json_encode($entry['targeting_rules'] ?? [], JSON_THROW_ON_ERROR),
            'rollout_json' => json_encode($entry['rollout'] ?? [], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->configurationAudit?->record([
            'event' => 'Feature Flag Changed',
            'configuration_ref' => $flagId,
            'actor_ref' => $actorRef,
            'new_state' => ['flag_state' => $initialState],
        ]);

        return $this->envelope('registered', $flagId, $initialState, null);
    }

    /**
     * @return array{outcome: string, flag_id: ?string, state: ?string, error: ?string}
     */
    public function transition(string $flagId, string $toState, string $actorRef, ?string $reason = null): array
    {
        if (!in_array($toState, self::STATES, true)) {
            return $this->envelope('invalid', $flagId, null, sprintf('"%s" is not one of this spec\'s named Feature Flag States.', $toState));
        }

        if (!$this->present($actorRef)) {
            return $this->envelope('invalid', $flagId, null, 'A transition requires a non-empty actor_ref.');
        }

        $record = $this->flagRow($flagId);

        if ($record === null) {
            return $this->envelope('invalid', $flagId, null, sprintf('"%s" is not a registered feature flag.', $flagId));
        }

        if ($record['state'] === 'Retired') {
            return $this->envelope('rejected', $flagId, $record['state'], 'Retired is a terminal feature-flag state.');
        }

        $this->database->prepare('UPDATE feature_flags SET state = :state, updated_at = :updated_at WHERE flag_id = :flag_id')
            ->execute(['state' => $toState, 'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM), 'flag_id' => $flagId]);

        $this->configurationAudit?->record([
            'event' => 'Feature Flag Changed',
            'configuration_ref' => $flagId,
            'actor_ref' => $actorRef,
            'reason' => $reason,
            'prior_state' => ['flag_state' => $record['state']],
            'new_state' => ['flag_state' => $toState],
        ]);

        return $this->envelope('transitioned', $flagId, $toState, null);
    }

    /**
     * Rule 2: records status only -- never executes anything.
     *
     * @return array{outcome: string, flag_id: ?string, kill_switch_engaged: ?bool, error: ?string}
     */
    public function setKillSwitch(string $flagId, bool $engaged, string $actorRef, ?string $reason = null): array
    {
        $record = $this->flagRow($flagId);

        if ($record === null) {
            return ['outcome' => 'invalid', 'flag_id' => $flagId, 'kill_switch_engaged' => null, 'error' => sprintf('"%s" is not a registered feature flag.', $flagId)];
        }

        $this->database->prepare('UPDATE feature_flags SET kill_switch_engaged = :kill_switch_engaged, updated_at = :updated_at WHERE flag_id = :flag_id')
            ->execute(['kill_switch_engaged' => $engaged ? 1 : 0, 'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM), 'flag_id' => $flagId]);

        $this->configurationAudit?->record([
            'event' => 'Feature Flag Changed',
            'configuration_ref' => $flagId,
            'actor_ref' => $actorRef,
            'reason' => $reason,
            'prior_state' => ['kill_switch_engaged' => (bool) $record['kill_switch_engaged']],
            'new_state' => ['kill_switch_engaged' => $engaged],
        ]);

        return ['outcome' => 'recorded', 'flag_id' => $flagId, 'kill_switch_engaged' => $engaged, 'error' => null];
    }

    /**
     * "Resolves whether a registered feature flag is configured as
     * enabled, disabled, experimental, beta, deprecated, or retired
     * for a supplied context" (Purpose).
     *
     * @param array<string, mixed> $context
     * @return array{outcome: string, flag_id: ?string, resolved_state: ?string, matched: bool, error: ?string}
     */
    public function evaluate(string $flagId, array $context = []): array
    {
        $record = $this->flagRow($flagId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'flag_id' => $flagId, 'resolved_state' => null, 'matched' => false, 'error' => sprintf('"%s" is not a registered feature flag.', $flagId)];
        }

        if ((bool) $record['kill_switch_engaged']) {
            return ['outcome' => 'resolved', 'flag_id' => $flagId, 'resolved_state' => 'Disabled', 'matched' => false, 'error' => null];
        }

        if (in_array($record['state'], self::UNAVAILABLE_STATES, true)) {
            return ['outcome' => 'resolved', 'flag_id' => $flagId, 'resolved_state' => $record['state'], 'matched' => false, 'error' => null];
        }

        $targetingRules = json_decode((string) $record['targeting_rules_json'], true, flags: JSON_THROW_ON_ERROR);
        $matched = true;

        foreach ($targetingRules as $rule) {
            $contextValue = $context[$rule['context_key']] ?? null;

            if (!in_array($contextValue, $rule['allowed_values'], true)) {
                $matched = false;

                break;
            }
        }

        return ['outcome' => 'resolved', 'flag_id' => $flagId, 'resolved_state' => $record['state'], 'matched' => $matched, 'error' => null];
    }

    /**
     * Combines the Registry's own real name/owner with this class's
     * own operational state.
     *
     * @return ?array<string, mixed>
     */
    public function get(string $flagId): ?array
    {
        $row = $this->flagRow($flagId);

        if ($row === null) {
            return null;
        }

        $hydrated = $this->hydrate($row);
        $registryRecord = $this->registry?->get($flagId);
        $hydrated['name'] = $registryRecord['name'] ?? null;
        $hydrated['owner'] = $registryRecord['owner'] ?? null;

        return $hydrated;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $flagId): array
    {
        return $this->configurationAudit?->history($flagId) ?? [];
    }

    /**
     * @return ?array<string, mixed>
     */
    private function flagRow(string $flagId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM feature_flags WHERE flag_id = :flag_id');
        $statement->execute(['flag_id' => $flagId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['dependency_refs'] = json_decode((string) $row['dependency_refs_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['targeting_rules'] = json_decode((string) $row['targeting_rules_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['rollout'] = json_decode((string) $row['rollout_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['kill_switch_engaged'] = (bool) $row['kill_switch_engaged'];
        unset($row['dependency_refs_json'], $row['targeting_rules_json'], $row['rollout_json']);

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @return array{outcome: string, flag_id: ?string, state: ?string, error: ?string}
     */
    private function envelope(string $outcome, ?string $flagId, ?string $state, ?string $error): array
    {
        return ['outcome' => $outcome, 'flag_id' => $flagId, 'state' => $state, 'error' => $error];
    }
}
