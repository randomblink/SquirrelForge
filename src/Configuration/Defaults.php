<?php

declare(strict_types=1);

namespace SquirrelForge\Configuration;

/**
 * The baseline default values every other component reads its
 * starting policy from, per 21_CONFIGURATION/DEFAULTS.md -- the first
 * real component in 21_CONFIGURATION.
 *
 * "These are baseline default values, not enforcement" (the spec's own
 * text) is upheld literally: this class only ever exposes and
 * validates values, it never itself enforces least privilege, requires
 * authorization, retries anything, validates a phase, or logs an
 * event -- each of those stays the real, already-built owner the spec
 * names (`PERMISSIONS.md`, `EngineValidation`, `SqliteFailureRecovery`,
 * `SqliteExecutionLogger`, the Engine's planning components).
 * `max_retries`'s default value (3) is deliberately the same number
 * `SqliteFailureRecovery::DEFAULT_MAX_RETRIES` already uses -- not a
 * coincidence, but this spec's own "bounded retries" baseline that
 * component's default already embodies, made an explicit, checkable
 * fact here rather than two independently-guessed threes.
 *
 * "`PROJECT-SETTINGS.md` may override a default, but an override must
 * state its source and must not weaken mandatory governance or
 * security policy" is this component's one genuinely enforced rule,
 * not mere documentation: `validateOverride()` is the real gate any
 * future override authority (`PROJECT-SETTINGS.md`, not yet built)
 * must pass through. A subset of the defaults is marked mandatory
 * (`least_privilege`, `destructive_action_requires_authorization`,
 * `validate_after_material_phase`, `structured_event_logging` -- the
 * ones this spec's own text ties directly to governance/security
 * enforcement, all defaulting `true`); an override attempting to flip
 * one of those specific booleans to `false` is refused outright,
 * regardless of what source claims to authorize it. Every override
 * also requires a non-empty `source` string -- "must state its
 * source" is a real, checked precondition, not an optional courtesy.
 *
 * Owns no database and re-derives nothing: `VALUES` is a fixed,
 * in-code table, the same "pure constant table, no persistence" shape
 * `EngineValidation`'s own stage/status constants already use for this
 * codebase's other declarative baselines.
 */
final class Defaults
{
    /** @var array<string, mixed> */
    private const VALUES = [
        'deterministic_planning' => true,
        'least_privilege' => true,
        'destructive_action_requires_authorization' => true,
        'max_retries' => 3,
        'validate_after_material_phase' => true,
        'structured_event_logging' => true,
        'output_location' => 'project_local',
    ];

    /** The subset PROJECT-SETTINGS.md must not weaken, per this spec's own governance/security tie. */
    private const MANDATORY_KEYS = [
        'least_privilege', 'destructive_action_requires_authorization', 'validate_after_material_phase', 'structured_event_logging',
    ];

    /**
     * @return array{found: bool, value: mixed}
     */
    public function get(string $key): array
    {
        return array_key_exists($key, self::VALUES) ? ['found' => true, 'value' => self::VALUES[$key]] : ['found' => false, 'value' => null];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return self::VALUES;
    }

    public function isMandatory(string $key): bool
    {
        return in_array($key, self::MANDATORY_KEYS, true);
    }

    /**
     * The real gate an override authority must pass through before
     * applying a value in place of a default.
     *
     * @return array{outcome: string, key: string, value: mixed, source: ?string, error: ?string}
     */
    public function validateOverride(string $key, mixed $value, ?string $source): array
    {
        if (!array_key_exists($key, self::VALUES)) {
            return ['outcome' => 'unknown_key', 'key' => $key, 'value' => $value, 'source' => $source, 'error' => sprintf('"%s" is not a known default.', $key)];
        }

        if (!is_string($source) || $source === '') {
            return ['outcome' => 'invalid', 'key' => $key, 'value' => $value, 'source' => $source, 'error' => 'An override must state its source.'];
        }

        if ($this->isMandatory($key) && $this->weakens(self::VALUES[$key], $value)) {
            return ['outcome' => 'rejected', 'key' => $key, 'value' => $value, 'source' => $source, 'error' => sprintf('"%s" is a mandatory governance/security default; an override may not weaken it.', $key)];
        }

        return ['outcome' => 'accepted', 'key' => $key, 'value' => $value, 'source' => $source, 'error' => null];
    }

    /**
     * A mandatory default's own current value is always the "safe"
     * value (every one of them is `true`) -- weakening is the one real
     * transition this class can actually detect without inventing
     * domain knowledge about non-boolean defaults: flipping `true` to
     * `false`.
     */
    private function weakens(mixed $currentValue, mixed $overrideValue): bool
    {
        return $currentValue === true && $overrideValue === false;
    }
}
