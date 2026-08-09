<?php

declare(strict_types=1);

namespace SquirrelForge\Configuration;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Governance\SqlitePolicyEngine;

/**
 * The declarative model capability requirements, routing criteria,
 * context limits, output constraints, fallback behavior, and
 * data-handling classification that govern which AI models may be
 * used and how, per 21_CONFIGURATION/MODEL-CONFIG.md -- the fourth
 * real component in 21_CONFIGURATION.
 *
 * "Model Config is a policy document, not a routing decision engine...
 * that is `34_AIDRIVER/MODEL-ROUTER.md`'s responsibility" (Purpose) is
 * the same hard line `SqlitePermissions` already draws around itself
 * for the identical reason: this class never selects a model for a
 * request, it only declares and validates the static configuration
 * `ModelRouter` would read as input. `model_id` stays a fully opaque,
 * caller-supplied identifier rather than a value cross-checked against
 * `ModelRouter`'s own model table -- that table is a private
 * implementation detail with no public accessor, and this spec assigns
 * "which models actually exist" nowhere to this component anyway; it
 * only owns the *configuration* for whatever model ID a caller
 * declares.
 *
 * "Validate the declaration against `POLICY-ENGINE.md`" and "keep
 * declarations consistent with" it composes the same already-real
 * `SqlitePolicyEngine::evaluate()` `SqlitePermissions` already
 * established this pattern with, under the real `resource_access`
 * category (model access is a form of resource access in
 * `SqlitePolicyEngine`'s own fixed category vocabulary -- the same
 * category `SqlitePermissions` uses, since both spec's declarations
 * describe the same underlying question: what may act on what).
 *
 * "Declare context window limits per model" gets a real, checked
 * sanity rule beyond mere storage: `reserved_response_tokens` must be
 * strictly less than `max_context_window`, since a reservation at or
 * above the ceiling leaves no room for any actual input -- a
 * declaration this spec calls "auditable" (Rule) should not be
 * silently self-contradictory.
 *
 * "Fallback behavior when a primary model is unavailable" gets real,
 * checked graph logic: a declared `fallback_model_id` must reference
 * a model *already declared* in this configuration (an undeclared
 * fallback target is not a real fallback), and the full fallback
 * chain is walked for cycles before accepting a new declaration --
 * the same "detect the cycle, don't just check the immediate neighbor"
 * discipline `DependencyAnalyzer`'s own circular-dependency check
 * already applies to a structurally identical graph shape.
 *
 * SQLite-backed for "version and record changes to model
 * configuration" (Responsibilities) and "record every change... for
 * audit" (Process step 5) -- the same reasoning `SqlitePermissions`
 * already established for this shape of requirement.
 */
final class SqliteModelConfig
{
    private const SENSITIVITY_LEVELS = ['public', 'internal', 'confidential', 'restricted'];

    private PDO $database;

    public function __construct(string $databasePath, private readonly ?SqlitePolicyEngine $policyEngine = null)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS model_configurations (
                config_id TEXT PRIMARY KEY,
                model_id TEXT NOT NULL,
                capability_requirements_json TEXT NOT NULL,
                routing_criteria_json TEXT NOT NULL,
                max_context_window INTEGER NOT NULL,
                reserved_response_tokens INTEGER NOT NULL,
                output_constraints_json TEXT NOT NULL,
                fallback_model_id TEXT,
                data_handling_classification_json TEXT NOT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * Process steps 1-3, 5: registers a model's declared configuration.
     *
     * @param array{
     *     model_id?: ?string,
     *     capability_requirements?: array<string, mixed>,
     *     routing_criteria?: array<string, mixed>,
     *     max_context_window?: ?int,
     *     reserved_response_tokens?: ?int,
     *     output_constraints?: array<string, mixed>,
     *     fallback_model_id?: ?string,
     *     data_handling_classification?: array<int, string>
     * } $declaration
     * @return array{outcome: string, config_id: ?string, error: ?string}
     */
    public function declare(array $declaration): array
    {
        $modelId = $declaration['model_id'] ?? null;

        if (!is_string($modelId) || $modelId === '') {
            return ['outcome' => 'invalid', 'config_id' => null, 'error' => 'A model configuration requires a non-empty model_id.'];
        }

        $existingActive = $this->find($modelId);

        if ($existingActive !== null && $existingActive['status'] === 'active') {
            return ['outcome' => 'duplicate', 'config_id' => null, 'error' => sprintf('Model "%s" is already declared; revoke it before re-declaring.', $modelId)];
        }

        $maxContextWindow = $declaration['max_context_window'] ?? null;
        $reservedResponseTokens = $declaration['reserved_response_tokens'] ?? 0;

        if (!is_int($maxContextWindow) || $maxContextWindow <= 0) {
            return ['outcome' => 'invalid', 'config_id' => null, 'error' => 'max_context_window must be a positive integer.'];
        }

        if (!is_int($reservedResponseTokens) || $reservedResponseTokens < 0 || $reservedResponseTokens >= $maxContextWindow) {
            return ['outcome' => 'invalid', 'config_id' => null, 'error' => 'reserved_response_tokens must be a non-negative integer strictly less than max_context_window.'];
        }

        $sensitivity = $declaration['data_handling_classification'] ?? [];
        $unknownSensitivity = array_values(array_diff($sensitivity, self::SENSITIVITY_LEVELS));

        if ($unknownSensitivity !== []) {
            return ['outcome' => 'invalid', 'config_id' => null, 'error' => sprintf('Unknown data-handling sensitivity level(s): %s.', implode(', ', $unknownSensitivity))];
        }

        $fallbackModelId = $declaration['fallback_model_id'] ?? null;

        if ($fallbackModelId !== null) {
            if ($fallbackModelId === $modelId) {
                return ['outcome' => 'invalid', 'config_id' => null, 'error' => 'A model may not declare itself as its own fallback.'];
            }

            $fallbackTarget = $this->find($fallbackModelId);

            if ($fallbackTarget === null || $fallbackTarget['status'] !== 'active') {
                return ['outcome' => 'invalid', 'config_id' => null, 'error' => sprintf('fallback_model_id "%s" does not reference an already-declared, active model.', $fallbackModelId)];
            }

            $cycle = $this->wouldCreateCycle($modelId, $fallbackModelId);

            if ($cycle !== null) {
                return ['outcome' => 'invalid', 'config_id' => null, 'error' => sprintf('Declaring this fallback would create a cycle: %s.', implode(' -> ', $cycle))];
            }
        }

        if ($this->policyEngine !== null) {
            $decision = $this->policyEngine->evaluate(
                'model_config_' . bin2hex(random_bytes(8)),
                ['model_id' => $modelId, 'max_context_window' => $maxContextWindow],
                'resource_access'
            );

            if ($decision['decision'] !== 'allowed') {
                return ['outcome' => 'rejected', 'config_id' => null, 'error' => sprintf('Policy Engine did not allow this declaration: %s', $decision['rationale'] ?? 'no rationale given.')];
            }
        }

        $configId = 'model_config_' . bin2hex(random_bytes(12));
        $now = (new DateTimeImmutable())->format(DATE_ATOM);

        $statement = $this->database->prepare(
            'INSERT INTO model_configurations
                (config_id, model_id, capability_requirements_json, routing_criteria_json, max_context_window, reserved_response_tokens, output_constraints_json, fallback_model_id, data_handling_classification_json, status, created_at, updated_at)
             VALUES
                (:config_id, :model_id, :capability_requirements_json, :routing_criteria_json, :max_context_window, :reserved_response_tokens, :output_constraints_json, :fallback_model_id, :data_handling_classification_json, :status, :created_at, :updated_at)'
        );
        $statement->execute([
            'config_id' => $configId,
            'model_id' => $modelId,
            'capability_requirements_json' => json_encode($declaration['capability_requirements'] ?? [], JSON_THROW_ON_ERROR),
            'routing_criteria_json' => json_encode($declaration['routing_criteria'] ?? [], JSON_THROW_ON_ERROR),
            'max_context_window' => $maxContextWindow,
            'reserved_response_tokens' => $reservedResponseTokens,
            'output_constraints_json' => json_encode($declaration['output_constraints'] ?? [], JSON_THROW_ON_ERROR),
            'fallback_model_id' => $fallbackModelId,
            'data_handling_classification_json' => json_encode($sensitivity, JSON_THROW_ON_ERROR),
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['outcome' => 'declared', 'config_id' => $configId, 'error' => null];
    }

    /**
     * @return array{outcome: string, config_id: string, error: ?string}
     */
    public function revoke(string $modelId): array
    {
        $existing = $this->find($modelId);

        if ($existing === null) {
            return ['outcome' => 'not_found', 'config_id' => '', 'error' => sprintf('Model "%s" is not declared.', $modelId)];
        }

        if ($existing['status'] !== 'active') {
            return ['outcome' => 'already_revoked', 'config_id' => $existing['config_id'], 'error' => null];
        }

        $statement = $this->database->prepare(
            "UPDATE model_configurations SET status = 'revoked', updated_at = :updated_at WHERE model_id = :model_id"
        );
        $statement->execute(['updated_at' => (new DateTimeImmutable())->format(DATE_ATOM), 'model_id' => $modelId]);

        return ['outcome' => 'revoked', 'config_id' => $existing['config_id'], 'error' => null];
    }

    /**
     * "Make the declaration available for MODEL-ROUTER.md to apply at
     * runtime" -- an active declaration's hydrated configuration, or
     * null when none is declared (or it was revoked).
     *
     * @return ?array<string, mixed>
     */
    public function get(string $modelId): ?array
    {
        $row = $this->find($modelId);

        return $row === null || $row['status'] !== 'active' ? null : $this->hydrate($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $modelId): array
    {
        $statement = $this->database->prepare('SELECT * FROM model_configurations WHERE model_id = :model_id ORDER BY rowid ASC');
        $statement->execute(['model_id' => $modelId]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @return ?array<string, mixed>
     */
    private function find(string $modelId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM model_configurations WHERE model_id = :model_id ORDER BY rowid DESC LIMIT 1');
        $statement->execute(['model_id' => $modelId]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Walks the fallback chain starting from `$fallbackModelId`; if it
     * ever reaches `$modelId` again, declaring `$modelId -> $fallbackModelId`
     * would close a cycle.
     *
     * @return ?array<int, string> the cycle path, or null when none exists.
     */
    private function wouldCreateCycle(string $modelId, string $fallbackModelId): ?array
    {
        $path = [$modelId, $fallbackModelId];
        $current = $fallbackModelId;
        $visited = [$modelId => true, $fallbackModelId => true];

        while (true) {
            $config = $this->find($current);
            $next = $config['fallback_model_id'] ?? null;

            if ($next === null) {
                return null;
            }

            if ($next === $modelId) {
                $path[] = $next;

                return $path;
            }

            if (isset($visited[$next])) {
                return null;
            }

            $visited[$next] = true;
            $path[] = $next;
            $current = $next;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['capability_requirements'] = json_decode($row['capability_requirements_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['routing_criteria'] = json_decode($row['routing_criteria_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['output_constraints'] = json_decode($row['output_constraints_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['data_handling_classification'] = json_decode($row['data_handling_classification_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['capability_requirements_json'], $row['routing_criteria_json'], $row['output_constraints_json'], $row['data_handling_classification_json']);

        return $row;
    }
}
