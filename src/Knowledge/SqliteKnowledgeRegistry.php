<?php

declare(strict_types=1);

namespace SquirrelForge\Knowledge;

use PDO;

/**
 * The authoritative catalog of knowledge asset metadata, per
 * 25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md.
 *
 * "The Knowledge Registry records metadata only... does not validate
 * knowledge, assign trust independently, model graph relationships,
 * own version records, resolve citations" is upheld structurally: every
 * write method here only ever stores a reference or classification a
 * caller supplies -- recordTrustLevel(), attachVersionReference(),
 * attachCitationReference(), and attachRelationshipReference() all
 * require the specialist-produced value as an argument rather than
 * deriving one, the same "Registry records what specialists produce"
 * boundary this spec states in prose.
 *
 * Type and Lifecycle Status are each closed enumerations taken directly
 * from the spec's own tables; register() and
 * recordLifecycleStatus() reject anything outside them.
 * Relationship references are a real accumulating list (an asset may
 * have many), matching the plural "Relationship References" field the
 * spec's own Registry Record table names, unlike the singular
 * version/citation/trust reference fields.
 */
final class SqliteKnowledgeRegistry
{
    private const TYPES = [
        'document', 'artifact', 'rule', 'decision', 'memory_reference',
        'dataset', 'integration_record', 'observation_reference',
    ];

    private const LIFECYCLE_STATUSES = ['Draft', 'Validated', 'Active', 'Deprecated', 'Archived'];

    private const TRUST_LEVELS = ['Low', 'Medium', 'High', 'Authoritative'];

    private PDO $database;

    public function __construct(string $databasePath)
    {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS knowledge_registry (
                knowledge_id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                source TEXT NOT NULL,
                owner TEXT NOT NULL,
                trust_level TEXT,
                trust_level_reference TEXT,
                lifecycle_status TEXT NOT NULL,
                version_reference TEXT,
                citation_reference TEXT,
                relationship_references_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @return array{outcome: string, knowledge_id: ?string, error: ?string}
     */
    public function register(string $name, string $type, string $source, string $owner): array
    {
        if (!in_array($type, self::TYPES, true)) {
            return ['outcome' => 'invalid_type', 'knowledge_id' => null, 'error' => sprintf('"%s" is not a recognized knowledge type.', $type)];
        }

        if ($source === '' || $owner === '') {
            return ['outcome' => 'invalid', 'knowledge_id' => null, 'error' => 'Registration requires a non-empty source and owner.'];
        }

        $knowledgeId = 'knowledge_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO knowledge_registry (
                knowledge_id, name, type, source, owner, trust_level, trust_level_reference,
                lifecycle_status, version_reference, citation_reference, relationship_references_json,
                created_at, updated_at
            ) VALUES (
                :knowledge_id, :name, :type, :source, :owner, NULL, NULL,
                :lifecycle_status, NULL, NULL, :relationship_references_json,
                :created_at, :updated_at
            )'
        );
        $statement->execute([
            'knowledge_id' => $knowledgeId,
            'name' => $name,
            'type' => $type,
            'source' => $source,
            'owner' => $owner,
            'lifecycle_status' => 'Draft',
            'relationship_references_json' => json_encode([], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['outcome' => 'registered', 'knowledge_id' => $knowledgeId, 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function recordTrustLevel(string $knowledgeId, string $trustLevel, string $trustLevelReference): array
    {
        if (!in_array($trustLevel, self::TRUST_LEVELS, true)) {
            return ['outcome' => 'invalid_trust_level', 'error' => sprintf('"%s" is not a recognized trust level.', $trustLevel)];
        }

        if ($this->fetch($knowledgeId) === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Knowledge asset "%s" is not registered.', $knowledgeId)];
        }

        $statement = $this->database->prepare(
            'UPDATE knowledge_registry SET trust_level = :trust_level, trust_level_reference = :trust_level_reference, updated_at = :updated_at WHERE knowledge_id = :knowledge_id'
        );
        $statement->execute([
            'trust_level' => $trustLevel,
            'trust_level_reference' => $trustLevelReference,
            'updated_at' => gmdate(DATE_RFC3339),
            'knowledge_id' => $knowledgeId,
        ]);

        return ['outcome' => 'recorded', 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function recordLifecycleStatus(string $knowledgeId, string $status): array
    {
        if (!in_array($status, self::LIFECYCLE_STATUSES, true)) {
            return ['outcome' => 'invalid_status', 'error' => sprintf('"%s" is not a recognized lifecycle status.', $status)];
        }

        if ($this->fetch($knowledgeId) === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Knowledge asset "%s" is not registered.', $knowledgeId)];
        }

        $statement = $this->database->prepare(
            'UPDATE knowledge_registry SET lifecycle_status = :status, updated_at = :updated_at WHERE knowledge_id = :knowledge_id'
        );
        $statement->execute(['status' => $status, 'updated_at' => gmdate(DATE_RFC3339), 'knowledge_id' => $knowledgeId]);

        return ['outcome' => 'recorded', 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function attachVersionReference(string $knowledgeId, string $versionReference): array
    {
        return $this->attachSingularReference($knowledgeId, 'version_reference', $versionReference);
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function attachCitationReference(string $knowledgeId, string $citationReference): array
    {
        return $this->attachSingularReference($knowledgeId, 'citation_reference', $citationReference);
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function attachRelationshipReference(string $knowledgeId, string $relationshipReference): array
    {
        $record = $this->fetch($knowledgeId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Knowledge asset "%s" is not registered.', $knowledgeId)];
        }

        $references = json_decode((string) $record['relationship_references_json'], true, flags: JSON_THROW_ON_ERROR);
        $references[] = $relationshipReference;

        $statement = $this->database->prepare(
            'UPDATE knowledge_registry SET relationship_references_json = :references, updated_at = :updated_at WHERE knowledge_id = :knowledge_id'
        );
        $statement->execute(['references' => json_encode($references, JSON_THROW_ON_ERROR), 'updated_at' => gmdate(DATE_RFC3339), 'knowledge_id' => $knowledgeId]);

        return ['outcome' => 'recorded', 'error' => null];
    }

    private function attachSingularReference(string $knowledgeId, string $column, string $reference): array
    {
        if ($this->fetch($knowledgeId) === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Knowledge asset "%s" is not registered.', $knowledgeId)];
        }

        $statement = $this->database->prepare(
            sprintf('UPDATE knowledge_registry SET %s = :reference, updated_at = :updated_at WHERE knowledge_id = :knowledge_id', $column)
        );
        $statement->execute(['reference' => $reference, 'updated_at' => gmdate(DATE_RFC3339), 'knowledge_id' => $knowledgeId]);

        return ['outcome' => 'recorded', 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $knowledgeId): ?array
    {
        $record = $this->fetch($knowledgeId);

        if ($record === null) {
            return null;
        }

        $record['relationship_references'] = json_decode((string) $record['relationship_references_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($record['relationship_references_json']);

        return $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByType(string $type): array
    {
        $statement = $this->database->prepare('SELECT * FROM knowledge_registry WHERE type = :type');
        $statement->execute(['type' => $type]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByStatus(string $status): array
    {
        $statement = $this->database->prepare('SELECT * FROM knowledge_registry WHERE lifecycle_status = :status');
        $statement->execute(['status' => $status]);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $knowledgeId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM knowledge_registry WHERE knowledge_id = :knowledge_id');
        $statement->execute(['knowledge_id' => $knowledgeId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }
}
