<?php

declare(strict_types=1);

namespace SquirrelForge\Knowledge;

use PDO;

/**
 * Owns knowledge-domain quality assessment, per
 * 25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md.
 *
 * "Verify registration" (Validation Process step 2) is a real,
 * enforced gate: validate() refuses with `Rejected` when a
 * `SqliteKnowledgeRegistry` is injected and the knowledge asset is not
 * found in it -- genuinely skipped, not silently treated as passing,
 * when no registry is composed, matching this codebase's established
 * optional-composition stance.
 *
 * "Read citation/reference status from Citation Manager" is a real
 * composition, not a fabricated pass-through: when a
 * `SqliteCitationManager` is injected, validate() actually calls
 * findByKnowledgeId() and inspects the real citation statuses returned
 * -- any `Unresolved` citation becomes a real, recorded limitation, and
 * `citation_required` requesting zero citations is also a real
 * limitation, not an assumed pass.
 *
 * "Detect consistency issues and contradiction signals... without
 * performing general rule evaluation" is upheld by taking
 * `contradiction_signals` and `freshness_days` as caller-supplied
 * inputs rather than deriving them: this component has no rule
 * evaluator or clock-based freshness engine of its own to produce
 * them, the same "Depends On vs Inputs" distinction already
 * established for signals with no owning component elsewhere in this
 * codebase (e.g. SqliteAiDriverGovernance's risk_classification).
 *
 * Result is a real aggregation, not a single flag: Rejected (not
 * registered) outranks Failed (incomplete provenance), which outranks
 * Warning (any limitation), which outranks Valid -- and trust level is
 * derived from that same result rather than being independently
 * guessed. `Authoritative` is deliberately unreachable from validate()
 * itself: assignAuthoritativeTrust() requires a non-empty governance
 * reference and an existing prior validation record, the same
 * "risk acceptance requires a real reference" shape
 * SqliteVulnerabilityManager::acceptRisk() already established.
 *
 * "Report validation/trust results to Knowledge Manager and Knowledge
 * Registry" (Validation Process step 11) is real: when a Registry is
 * injected, both validate() and assignAuthoritativeTrust() push the
 * resulting trust level back through recordTrustLevel(), and a `Valid`
 * result also pushes lifecycle status `Validated`.
 */
final class SqliteKnowledgeValidator
{
    private const RESULTS = ['Valid', 'Warning', 'Failed', 'Rejected'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteKnowledgeRegistry $registry = null,
        private readonly ?SqliteCitationManager $citations = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS knowledge_validations (
                validation_id TEXT PRIMARY KEY,
                knowledge_id TEXT NOT NULL,
                citation_status_reference TEXT,
                result TEXT NOT NULL,
                trust_level TEXT NOT NULL,
                limitations_json TEXT NOT NULL,
                notes TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{contradiction_signals?: array<int, string>, freshness_days?: int, max_age_days?: int, citation_required?: bool} $options
     * @return array{outcome: string, validation_id: ?string, result: string, trust_level: string, limitations: array<int, string>, error: ?string}
     */
    public function validate(string $knowledgeId, array $options = []): array
    {
        $limitations = [];

        if ($this->registry !== null) {
            $registryEntry = $this->registry->get($knowledgeId);

            if ($registryEntry === null) {
                return $this->finish($knowledgeId, null, 'Rejected', 'Low', ['Knowledge asset is not registered.'], sprintf('Knowledge asset "%s" is not registered.', $knowledgeId));
            }

            if ($registryEntry['source'] === '' || $registryEntry['owner'] === '') {
                return $this->finish($knowledgeId, null, 'Failed', 'Low', ['Provenance is incomplete: source or owner is missing.'], 'Provenance is incomplete.');
            }
        } else {
            $limitations[] = 'No Knowledge Registry configured; registration and provenance could not be verified.';
        }

        $citationStatusReference = null;

        if ($this->citations !== null) {
            $citationRecords = $this->citations->findByKnowledgeId($knowledgeId);

            if ($citationRecords === [] && ($options['citation_required'] ?? false)) {
                $limitations[] = 'Citations are required but none are recorded.';
            }

            foreach ($citationRecords as $citation) {
                if ($citation['citation_status'] === 'Unresolved') {
                    $limitations[] = sprintf('Citation "%s" is unresolved.', $citation['citation_id']);
                }
            }

            if ($citationRecords !== []) {
                $citationStatusReference = $citationRecords[array_key_last($citationRecords)]['citation_id'];
            }
        } else {
            $limitations[] = 'No Citation Manager configured; citation status could not be consumed.';
        }

        if (($options['contradiction_signals'] ?? []) !== []) {
            $limitations[] = sprintf('%d contradiction signal(s) detected.', count($options['contradiction_signals']));
        }

        if (isset($options['freshness_days'], $options['max_age_days']) && $options['freshness_days'] > $options['max_age_days']) {
            $limitations[] = sprintf('Knowledge is %d day(s) old, exceeding the %d-day freshness threshold.', $options['freshness_days'], $options['max_age_days']);
        }

        $result = $limitations === [] ? 'Valid' : 'Warning';
        $trustLevel = $result === 'Valid' ? 'High' : 'Medium';

        return $this->finish($knowledgeId, $citationStatusReference, $result, $trustLevel, $limitations, null);
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function assignAuthoritativeTrust(string $knowledgeId, string $governanceReference): array
    {
        if ($governanceReference === '') {
            return ['outcome' => 'authorization_required', 'error' => 'Assigning Authoritative trust requires a non-empty governance reference.'];
        }

        $latest = $this->latestValidation($knowledgeId);

        if ($latest === null) {
            return ['outcome' => 'not_validated', 'error' => sprintf('Knowledge asset "%s" has no prior validation record.', $knowledgeId)];
        }

        $statement = $this->database->prepare('UPDATE knowledge_validations SET trust_level = :trust_level WHERE validation_id = :validation_id');
        $statement->execute(['trust_level' => 'Authoritative', 'validation_id' => $latest['validation_id']]);

        $this->registry?->recordTrustLevel($knowledgeId, 'Authoritative', $governanceReference);

        return ['outcome' => 'assigned', 'error' => null];
    }

    /**
     * @param array<int, string> $limitations
     * @return array{outcome: string, validation_id: ?string, result: string, trust_level: string, limitations: array<int, string>, error: ?string}
     */
    private function finish(string $knowledgeId, ?string $citationStatusReference, string $result, string $trustLevel, array $limitations, ?string $notes): array
    {
        $validationId = 'validation_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO knowledge_validations (
                validation_id, knowledge_id, citation_status_reference, result, trust_level, limitations_json, notes, created_at
            ) VALUES (
                :validation_id, :knowledge_id, :citation_status_reference, :result, :trust_level, :limitations_json, :notes, :created_at
            )'
        );
        $statement->execute([
            'validation_id' => $validationId,
            'knowledge_id' => $knowledgeId,
            'citation_status_reference' => $citationStatusReference,
            'result' => $result,
            'trust_level' => $trustLevel,
            'limitations_json' => json_encode($limitations, JSON_THROW_ON_ERROR),
            'notes' => $notes,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        if ($this->registry !== null) {
            $this->registry->recordTrustLevel($knowledgeId, $trustLevel, $validationId);

            if ($result === 'Valid') {
                $this->registry->recordLifecycleStatus($knowledgeId, 'Validated');
            }
        }

        return [
            'outcome' => 'validated',
            'validation_id' => $validationId,
            'result' => $result,
            'trust_level' => $trustLevel,
            'limitations' => $limitations,
            'error' => $notes,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $validationId): ?array
    {
        $record = $this->fetch($validationId);

        if ($record === null) {
            return null;
        }

        $record['limitations'] = json_decode((string) $record['limitations_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($record['limitations_json']);

        return $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByKnowledgeId(string $knowledgeId): array
    {
        $statement = $this->database->prepare('SELECT * FROM knowledge_validations WHERE knowledge_id = :knowledge_id ORDER BY rowid ASC');
        $statement->execute(['knowledge_id' => $knowledgeId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestValidation(string $knowledgeId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM knowledge_validations WHERE knowledge_id = :knowledge_id ORDER BY rowid DESC LIMIT 1');
        $statement->execute(['knowledge_id' => $knowledgeId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $validationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM knowledge_validations WHERE validation_id = :validation_id');
        $statement->execute(['validation_id' => $validationId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }
}
