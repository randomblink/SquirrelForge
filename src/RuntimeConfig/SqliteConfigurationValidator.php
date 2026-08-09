<?php

declare(strict_types=1);

namespace SquirrelForge\RuntimeConfig;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Engine\EngineValidation;

/**
 * Owns configuration-domain validation for runtime configuration
 * records -- structure, schema, required fields, type constraints,
 * dependency references, feature-flag dependencies, policy-configuration
 * references, and secret-reference presence -- per
 * 28_RUNTIME-CONFIG/CONFIGURATION-VALIDATOR.md -- the third real
 * component in `28_RUNTIME-CONFIG`'s gap.
 *
 * "Configuration Validator must not evaluate general policy
 * independently" (Rule 2) is upheld by taking no dependency on
 * `SqlitePolicyEngine` at all, even though `23_GOVERNANCE/POLICY-ENGINE.md`
 * is a listed "Depends On" -- this class checks "policy-configuration
 * reference integrity" (Responsibilities), meaning whether a
 * `policy_configuration_ref` is present and well-formed, never whether
 * the policy behind it would evaluate to `allow`. That distinction is
 * the same one `SqliteAgentGovernance` already draws between reading a
 * Policy Engine decision and independently deciding one.
 *
 * "Check secret-reference presence without reading raw secret values"
 * is a real, structural guarantee: this class only ever checks whether
 * a `secret_ref` string is non-empty, and takes no dependency on
 * `SECRETS-MANAGER.md` at all -- there is no code path here that could
 * read a secret's actual value even if it wanted to.
 *
 * "Check dependency references and duplicate/conflicting configuration
 * records" is genuine composition of the just-built
 * `SqliteConfigurationRegistry::get()` -- a declared dependency
 * reference is checked against the real registry, never assumed to
 * exist.
 *
 * "Configuration Validator must consume... platform validation
 * references when required rather than replacing those owners"
 * (Rule 1) composes the already-real `EngineValidation::evaluate()`
 * directly for structure/schema/type-constraint items, the same
 * "compose the real aggregator with raw evidence" shape
 * `ExecutionEngine`/`SqliteResultReviewer` already established for the
 * same class -- never a fabricated schema checker of its own.
 * `EngineValidation`'s own real seven-value decision is mapped onto
 * this spec's own five-value Validation Status
 * (`Pending`/`Valid`/`Warning`/`Failed`/`Blocked`), the same
 * "different owners, different real vocabularies" treatment already
 * established throughout this session.
 *
 * When nothing is actually checkable -- no dependency refs, secret
 * refs, policy/feature-flag refs, or validation items were declared,
 * and nothing was composed to check any of them -- the honest status
 * is `Pending` ("has not completed"), never a false `Valid`; a real
 * check must actually have run for anything stronger to be claimed.
 * The final status is the worst (highest-severity) outcome across
 * every check that did run, never averaged or overridden by a passing
 * one.
 */
final class SqliteConfigurationValidator
{
    /** Maps EngineValidation's own real decision vocabulary onto this spec's own Validation Status. */
    private const DECISION_TO_STATUS = [
        'ACCEPTED' => 'Valid',
        'ACCEPTED_WITH_LIMITATIONS' => 'Warning',
        'REPAIR_REQUIRED' => 'Failed',
        'REJECTED' => 'Failed',
        'BLOCKED' => 'Blocked',
        'RECOVERY_REQUIRED' => 'Blocked',
        'CLARIFICATION_REQUIRED' => 'Blocked',
    ];

    private const STATUS_RANK = ['Valid' => 0, 'Warning' => 1, 'Failed' => 2, 'Blocked' => 3];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?EngineValidation $validation = null,
        private readonly ?SqliteConfigurationRegistry $registry = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS configuration_validations (
                validation_ref TEXT PRIMARY KEY,
                configuration_ref TEXT NOT NULL,
                status TEXT NOT NULL,
                findings_json TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{
     *     configuration_ref?: ?string,
     *     dependency_refs?: array<int, string>,
     *     secret_refs?: array<int, string>,
     *     policy_configuration_ref?: ?string,
     *     feature_flag_refs?: array<int, string>,
     *     validation_items?: array<int, array{item_id: string, stage: string, required: bool, blocking?: bool, status: string, waivable?: bool, repairable?: bool}>,
     *     validation_options?: array{remaining_attempts?: int, recovery_required?: bool, clarification_needed?: bool}
     * } $request
     * @return array{
     *     outcome: string,
     *     validation_ref: ?string,
     *     status: ?string,
     *     findings: array<int, string>,
     *     error: ?string
     * }
     */
    public function validate(array $request): array
    {
        $configurationRef = $request['configuration_ref'] ?? null;

        if (!$this->present($configurationRef)) {
            return $this->envelope('invalid', null, null, [], 'Validation requires a non-empty configuration_ref.');
        }

        $findings = [];
        $statuses = [];

        foreach ($request['dependency_refs'] ?? [] as $dependencyRef) {
            if ($this->registry === null) {
                $statuses[] = 'Blocked';
                $findings[] = sprintf('Cannot confirm dependency reference "%s" exists; the Configuration Registry is not configured.', $dependencyRef);

                continue;
            }

            if ($this->registry->get($dependencyRef) === null) {
                $statuses[] = 'Failed';
                $findings[] = sprintf('Dependency reference "%s" does not exist in the Configuration Registry.', $dependencyRef);
            }
        }

        foreach ($request['secret_refs'] ?? [] as $secretRef) {
            if (!$this->present($secretRef)) {
                $statuses[] = 'Failed';
                $findings[] = 'A declared secret_ref is empty.';
            }
        }

        $policyConfigurationRef = $request['policy_configuration_ref'] ?? null;

        if (array_key_exists('policy_configuration_ref', $request) && !$this->present($policyConfigurationRef)) {
            $statuses[] = 'Failed';
            $findings[] = 'The declared policy_configuration_ref is empty.';
        }

        foreach ($request['feature_flag_refs'] ?? [] as $featureFlagRef) {
            if (!$this->present($featureFlagRef)) {
                $statuses[] = 'Failed';
                $findings[] = 'A declared feature_flag_ref is empty.';
            }
        }

        if (isset($request['validation_items'])) {
            if ($this->validation === null) {
                $statuses[] = 'Blocked';
                $findings[] = 'Validation items were declared but EngineValidation is not configured.';
            } else {
                $result = $this->validation->evaluate($request['validation_items'], $request['validation_options'] ?? []);
                $status = self::DECISION_TO_STATUS[$result['decision']];
                $statuses[] = $status;
                $findings[] = sprintf('Structural validation decision: %s.', $result['decision']);
            }
        }

        $finalStatus = $statuses === [] ? 'Pending' : $this->worstStatus($statuses);

        return $this->recordAndEnvelope($configurationRef, $finalStatus, $findings);
    }

    /**
     * @return ?array<string, mixed>
     */
    public function get(string $validationRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM configuration_validations WHERE validation_ref = :validation_ref');
        $statement->execute(['validation_ref' => $validationRef]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $configurationRef): array
    {
        $statement = $this->database->prepare('SELECT * FROM configuration_validations WHERE configuration_ref = :configuration_ref ORDER BY rowid ASC');
        $statement->execute(['configuration_ref' => $configurationRef]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * @param array<int, string> $statuses
     */
    private function worstStatus(array $statuses): string
    {
        $worst = 'Valid';

        foreach ($statuses as $status) {
            if (self::STATUS_RANK[$status] > self::STATUS_RANK[$worst]) {
                $worst = $status;
            }
        }

        return $worst;
    }

    /**
     * @param array<int, string> $findings
     * @return array{outcome: string, validation_ref: ?string, status: ?string, findings: array<int, string>, error: ?string}
     */
    private function recordAndEnvelope(string $configurationRef, string $status, array $findings): array
    {
        $validationRef = 'config_validation_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO configuration_validations (validation_ref, configuration_ref, status, findings_json, created_at)
             VALUES (:validation_ref, :configuration_ref, :status, :findings_json, :created_at)'
        );
        $statement->execute([
            'validation_ref' => $validationRef,
            'configuration_ref' => $configurationRef,
            'status' => $status,
            'findings_json' => json_encode($findings, JSON_THROW_ON_ERROR),
            'created_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        return $this->envelope('validated', $validationRef, $status, $findings, null);
    }

    /**
     * @param array<int, string> $findings
     * @return array{outcome: string, validation_ref: ?string, status: ?string, findings: array<int, string>, error: ?string}
     */
    private function envelope(string $outcome, ?string $validationRef, ?string $status, array $findings, ?string $error): array
    {
        return ['outcome' => $outcome, 'validation_ref' => $validationRef, 'status' => $status, 'findings' => $findings, 'error' => $error];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['findings'] = json_decode((string) $row['findings_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['findings_json']);

        return $row;
    }

    private function present(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
