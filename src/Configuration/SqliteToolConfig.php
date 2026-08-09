<?php

declare(strict_types=1);

namespace SquirrelForge\Configuration;

use DateTimeImmutable;
use PDO;

/**
 * Declarative tool configuration and registration metadata -- what a
 * tool is, what it supports, and what it requires -- without deciding
 * retry policy, side-effect classification, permissions, health,
 * availability, or which tool executes a given action, per
 * 21_CONFIGURATION/TOOL-CONFIG.md -- the fifth and final real
 * component in 21_CONFIGURATION, closing out this layer's roster.
 *
 * "Registering a tool here is a configuration prerequisite for it to
 * be usable at all, but registration is not itself health, permission,
 * or availability" (Purpose) is upheld structurally: this class never
 * exposes an "is this tool usable" query, only "is it registered" --
 * combining that with `PERMISSIONS.md`'s decision and
 * `HEALTH-REPORTER.md`'s assessment stays `TOOL-SELECTOR.md`'s job, as
 * the spec's own Registration and Availability section states.
 *
 * "Reference the required permission (owned by `PERMISSIONS.md`)" is
 * genuine composition, not an opaque passthrough: when a
 * `SqlitePermissions` instance is configured, `required_permission_ref`
 * must reference a declaration that component's own `get()` can
 * actually find -- a tool cannot point at a permission that was never
 * declared. `side_effect_classification_ref`, by contrast, stays an
 * opaque caller-supplied string: `PERMISSIONS.md`'s own real
 * implementation has no separate side-effect-classification registry
 * to check against (its Permission Model is actor/capability/resource/
 * duration only), so fabricating a check against a registry that
 * doesn't exist would be dishonest, not more rigorous.
 *
 * "Must not store secrets directly" (Boundary) gets real, checked
 * enforcement, not just a naming convention: registration scans every
 * `parameters` key against the same secret-shaped-name pattern
 * `SqliteExecutionLogger` already uses for redaction -- but here the
 * violation is an outright rejection, not a redaction, since holding a
 * secret directly (even redacted) is an absolute prohibition this spec
 * draws, not a conditional one. `secret_references` (plural, since a
 * tool's parameters may need several) stay opaque strings pointing
 * into `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`, which exposes no public
 * "does this reference exist" query to cross-check against -- the same
 * "reference, don't verify contents you can't see" boundary this class
 * already applies to `side_effect_classification_ref`.
 *
 * SQLite-backed for the same reasoning `SqlitePermissions`/
 * `SqliteModelConfig` already established: registration state and its
 * history need to persist across separate calls.
 */
final class SqliteToolConfig
{
    private const SECRET_SHAPED_KEY_PATTERN = '/password|token|secret|api[_-]?key|credential|authorization/i';

    private PDO $database;

    public function __construct(string $databasePath, private readonly ?SqlitePermissions $permissions = null)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS tool_configurations (
                config_id TEXT PRIMARY KEY,
                tool_id TEXT NOT NULL,
                provider_ref TEXT NOT NULL,
                supported_actions_json TEXT NOT NULL,
                input_schema_ref TEXT,
                output_schema_ref TEXT,
                timeout_seconds REAL NOT NULL,
                required_permission_ref TEXT,
                side_effect_classification_ref TEXT,
                environment_ref TEXT,
                parameters_json TEXT NOT NULL,
                secret_references_json TEXT NOT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{
     *     tool_id?: ?string,
     *     provider_ref?: ?string,
     *     supported_actions?: array<int, string>,
     *     input_schema_ref?: ?string,
     *     output_schema_ref?: ?string,
     *     timeout_seconds?: ?float,
     *     required_permission_ref?: ?string,
     *     side_effect_classification_ref?: ?string,
     *     environment_ref?: ?string,
     *     parameters?: array<string, mixed>,
     *     secret_references?: array<int, string>
     * } $declaration
     * @return array{outcome: string, config_id: ?string, error: ?string}
     */
    public function register(array $declaration): array
    {
        $toolId = $declaration['tool_id'] ?? null;
        $providerRef = $declaration['provider_ref'] ?? null;
        $supportedActions = $declaration['supported_actions'] ?? [];
        $timeoutSeconds = $declaration['timeout_seconds'] ?? null;

        if (!$this->present($toolId) || !$this->present($providerRef)) {
            return ['outcome' => 'invalid', 'config_id' => null, 'error' => 'A tool configuration requires a non-empty tool_id and provider_ref.'];
        }

        if ($supportedActions === []) {
            return ['outcome' => 'invalid', 'config_id' => null, 'error' => 'A tool configuration requires at least one supported action.'];
        }

        if (!is_numeric($timeoutSeconds) || (float) $timeoutSeconds <= 0) {
            return ['outcome' => 'invalid', 'config_id' => null, 'error' => 'timeout_seconds must be a positive number.'];
        }

        $existingActive = $this->find($toolId);

        if ($existingActive !== null && $existingActive['status'] === 'active') {
            return ['outcome' => 'duplicate', 'config_id' => null, 'error' => sprintf('Tool "%s" is already registered; deregister it before re-registering.', $toolId)];
        }

        $leakedKeys = $this->secretShapedKeys($declaration['parameters'] ?? []);

        if ($leakedKeys !== []) {
            return ['outcome' => 'rejected', 'config_id' => null, 'error' => sprintf('parameters contains secret-shaped key(s) that must not be stored directly: %s. Reference them via secret_references instead.', implode(', ', $leakedKeys))];
        }

        $requiredPermissionRef = $declaration['required_permission_ref'] ?? null;

        if ($this->present($requiredPermissionRef) && $this->permissions !== null && $this->permissions->get($requiredPermissionRef) === null) {
            return ['outcome' => 'invalid', 'config_id' => null, 'error' => sprintf('required_permission_ref "%s" does not reference a declared permission.', $requiredPermissionRef)];
        }

        $configId = 'tool_config_' . bin2hex(random_bytes(12));
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $statement = $this->database->prepare(
            'INSERT INTO tool_configurations
                (config_id, tool_id, provider_ref, supported_actions_json, input_schema_ref, output_schema_ref, timeout_seconds, required_permission_ref, side_effect_classification_ref, environment_ref, parameters_json, secret_references_json, status, created_at, updated_at)
             VALUES
                (:config_id, :tool_id, :provider_ref, :supported_actions_json, :input_schema_ref, :output_schema_ref, :timeout_seconds, :required_permission_ref, :side_effect_classification_ref, :environment_ref, :parameters_json, :secret_references_json, :status, :created_at, :updated_at)'
        );
        $statement->execute([
            'config_id' => $configId,
            'tool_id' => $toolId,
            'provider_ref' => $providerRef,
            'supported_actions_json' => json_encode($supportedActions, JSON_THROW_ON_ERROR),
            'input_schema_ref' => $declaration['input_schema_ref'] ?? null,
            'output_schema_ref' => $declaration['output_schema_ref'] ?? null,
            'timeout_seconds' => (float) $timeoutSeconds,
            'required_permission_ref' => $requiredPermissionRef,
            'side_effect_classification_ref' => $declaration['side_effect_classification_ref'] ?? null,
            'environment_ref' => $declaration['environment_ref'] ?? null,
            'parameters_json' => json_encode($declaration['parameters'] ?? [], JSON_THROW_ON_ERROR),
            'secret_references_json' => json_encode($declaration['secret_references'] ?? [], JSON_THROW_ON_ERROR),
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['outcome' => 'registered', 'config_id' => $configId, 'error' => null];
    }

    /**
     * @return array{outcome: string, config_id: string, error: ?string}
     */
    public function deregister(string $toolId): array
    {
        $existing = $this->find($toolId);

        if ($existing === null) {
            return ['outcome' => 'not_found', 'config_id' => '', 'error' => sprintf('Tool "%s" is not registered.', $toolId)];
        }

        if ($existing['status'] !== 'active') {
            return ['outcome' => 'already_deregistered', 'config_id' => $existing['config_id'], 'error' => null];
        }

        $statement = $this->database->prepare("UPDATE tool_configurations SET status = 'deregistered', updated_at = :updated_at WHERE tool_id = :tool_id");
        $statement->execute(['updated_at' => (new DateTimeImmutable())->format(DATE_ATOM), 'tool_id' => $toolId]);

        return ['outcome' => 'deregistered', 'config_id' => $existing['config_id'], 'error' => null];
    }

    /**
     * The active registration, or null when no active registration
     * exists -- this is only a configuration lookup, never a usability
     * or availability answer (Purpose/Registration and Availability).
     *
     * @return ?array<string, mixed>
     */
    public function get(string $toolId): ?array
    {
        $row = $this->find($toolId);

        return $row === null || $row['status'] !== 'active' ? null : $this->hydrate($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $toolId): array
    {
        $statement = $this->database->prepare('SELECT * FROM tool_configurations WHERE tool_id = :tool_id ORDER BY rowid ASC');
        $statement->execute(['tool_id' => $toolId]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<int, string>
     */
    private function secretShapedKeys(array $parameters): array
    {
        return array_values(array_filter(array_keys($parameters), static fn(string $key): bool => preg_match(self::SECRET_SHAPED_KEY_PATTERN, $key) === 1));
    }

    /**
     * @return ?array<string, mixed>
     */
    private function find(string $toolId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM tool_configurations WHERE tool_id = :tool_id ORDER BY rowid DESC LIMIT 1');
        $statement->execute(['tool_id' => $toolId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['supported_actions'] = json_decode($row['supported_actions_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['parameters'] = json_decode($row['parameters_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['secret_references'] = json_decode($row['secret_references_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['supported_actions_json'], $row['parameters_json'], $row['secret_references_json']);

        return $row;
    }
}
