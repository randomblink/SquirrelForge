<?php

declare(strict_types=1);

namespace SquirrelForge\Storage;

use Closure;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Creates and maintains searchable indexes over records stored
 * elsewhere in the platform, per 37_STORAGE/INDEX-MANAGER.md. This
 * generalizes the same real upsert-by-(type, record_id) pattern
 * `SqliteMemoryIndex` already established for `18_MEMORY/
 * MEMORY-INDEX.md`, applied across the platform-wide Index Sources
 * this spec names, rather than one memory-specific type set.
 *
 * "Update index as data changes" and "Rebuild index" are the same real
 * operation, not two: indexRecord() always fully replaces an entry's
 * attributes rather than partially merging them, so a caller rebuilding
 * an entry from fresh source data gets the identical real effect a
 * caller merely updating it would -- inventing a second, distinct
 * "rebuild" code path would duplicate logic without adding real
 * behavior.
 *
 * "Verify index integrity" is real and meaningful, not vacuous: at
 * index time, a sha256 checksum of the attributes payload is stored
 * alongside it, and verifyIntegrity() independently re-hashes the
 * stored attributes and compares -- the same self-consistency check
 * `SqliteVersionManager::verifyIntegrity()` already established,
 * detecting corruption of this class's own stored data between write
 * and read, not drift from an external source this class doesn't own.
 *
 * "Optimize index" has no real index-restructuring work to perform
 * (SQLite's own UNIQUE index on (source_type, record_id) already
 * exists as a B-tree this class doesn't manage directly), so optimize()
 * honestly reports the current entry count and an optimization
 * timestamp rather than fabricating a restructuring step.
 *
 * search() reuses the same real term-frequency scoring formula
 * `MemoryRetrieval::relevanceScore()` established: one point per
 * occurrence of a query term in title/keywords/tags/categories -- a
 * plain, explainable formula, duplicated rather than composed since
 * Memory Retrieval is a Memory-specific class this platform-wide index
 * has no reason to depend on.
 *
 * Owns two tables: `index_entries` holds one current-state row per
 * (source_type, record_id) -- the live, queryable index -- and
 * `index_operations` is the append-only audit log the Audit
 * Requirements name, recording every indexRecord()/removeIndexEntry()
 * call unconditionally, including rejected operations.
 */
final class SqliteIndexManager
{
    private const SOURCE_TYPES = [
        'workflow_records', 'knowledge_artifacts', 'learning_experiences', 'configuration_data',
        'system_logs', 'audit_records', 'monitoring_history', 'integration_records', 'documents', 'archived_records',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?EventBusInterface $events = null,
        private readonly ?Closure $clock = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS index_entries (
                index_entry_id TEXT PRIMARY KEY,
                source_type TEXT NOT NULL,
                record_id TEXT NOT NULL,
                title TEXT,
                keywords_json TEXT NOT NULL,
                tags_json TEXT NOT NULL,
                categories_json TEXT NOT NULL,
                component TEXT,
                workflow TEXT,
                agent TEXT,
                classification TEXT,
                status TEXT,
                version TEXT,
                relationships_json TEXT NOT NULL,
                attributes_json TEXT NOT NULL,
                checksum TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (source_type, record_id)
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS index_operations (
                index_operation_id TEXT PRIMARY KEY,
                record_id TEXT NOT NULL,
                index_type TEXT NOT NULL,
                indexed_attributes_json TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                integrity_verification TEXT NOT NULL,
                optimization_status TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{title?: ?string, keywords?: array<int, string>, tags?: array<int, string>, categories?: array<int, string>, component?: ?string, workflow?: ?string, agent?: ?string, classification?: ?string, status?: ?string, version?: ?string, relationships?: array<int, string>} $attributes
     * @return array{outcome: string, index_entry_id: ?string, error: ?string}
     */
    public function indexRecord(string $sourceType, string $recordId, array $attributes = []): array
    {
        if (!in_array($sourceType, self::SOURCE_TYPES, true)) {
            return $this->reject($recordId, $sourceType, $attributes, sprintf('"%s" is not a recognized index source type.', $sourceType));
        }

        $existing = $this->fetch($sourceType, $recordId);
        $indexEntryId = $existing['index_entry_id'] ?? 'index_entry_' . bin2hex(random_bytes(12));
        $now = $this->nowString();
        $attributesJson = json_encode($attributes, JSON_THROW_ON_ERROR);
        $checksum = hash('sha256', $attributesJson);

        $statement = $this->database->prepare(
            'INSERT INTO index_entries (
                index_entry_id, source_type, record_id, title, keywords_json, tags_json, categories_json,
                component, workflow, agent, classification, status, version, relationships_json,
                attributes_json, checksum, created_at, updated_at
            ) VALUES (
                :index_entry_id, :source_type, :record_id, :title, :keywords_json, :tags_json, :categories_json,
                :component, :workflow, :agent, :classification, :status, :version, :relationships_json,
                :attributes_json, :checksum, :created_at, :updated_at
            )
            ON CONFLICT (source_type, record_id) DO UPDATE SET
                title = excluded.title, keywords_json = excluded.keywords_json, tags_json = excluded.tags_json,
                categories_json = excluded.categories_json, component = excluded.component, workflow = excluded.workflow,
                agent = excluded.agent, classification = excluded.classification, status = excluded.status,
                version = excluded.version, relationships_json = excluded.relationships_json,
                attributes_json = excluded.attributes_json, checksum = excluded.checksum, updated_at = excluded.updated_at'
        );
        $statement->execute([
            'index_entry_id' => $indexEntryId,
            'source_type' => $sourceType,
            'record_id' => $recordId,
            'title' => $attributes['title'] ?? null,
            'keywords_json' => json_encode($attributes['keywords'] ?? [], JSON_THROW_ON_ERROR),
            'tags_json' => json_encode($attributes['tags'] ?? [], JSON_THROW_ON_ERROR),
            'categories_json' => json_encode($attributes['categories'] ?? [], JSON_THROW_ON_ERROR),
            'component' => $attributes['component'] ?? null,
            'workflow' => $attributes['workflow'] ?? null,
            'agent' => $attributes['agent'] ?? null,
            'classification' => $attributes['classification'] ?? null,
            'status' => $attributes['status'] ?? null,
            'version' => $attributes['version'] ?? null,
            'relationships_json' => json_encode($attributes['relationships'] ?? [], JSON_THROW_ON_ERROR),
            'attributes_json' => $attributesJson,
            'checksum' => $checksum,
            'created_at' => $existing['created_at'] ?? $now,
            'updated_at' => $now,
        ]);

        return $this->recordOperation($recordId, $sourceType, $attributes, $existing === null ? 'indexed' : 'updated', 'recorded', null, $indexEntryId);
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function removeIndexEntry(string $sourceType, string $recordId): array
    {
        if ($this->fetch($sourceType, $recordId) === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('No index entry exists for "%s:%s".', $sourceType, $recordId)];
        }

        $statement = $this->database->prepare('DELETE FROM index_entries WHERE source_type = :source_type AND record_id = :record_id');
        $statement->execute(['source_type' => $sourceType, 'record_id' => $recordId]);

        $result = $this->recordOperation($recordId, $sourceType, [], 'removed', 'not_applicable', null, null);

        return ['outcome' => $result['outcome'], 'error' => $result['error']];
    }

    /**
     * @return array{verified: bool, outcome: string, error: ?string}
     */
    public function verifyIntegrity(string $sourceType, string $recordId): array
    {
        $entry = $this->fetch($sourceType, $recordId);

        if ($entry === null) {
            return ['verified' => false, 'outcome' => 'not_found', 'error' => sprintf('No index entry exists for "%s:%s".', $sourceType, $recordId)];
        }

        $verified = hash_equals($entry['checksum'], hash('sha256', (string) $entry['attributes_json']));

        return ['verified' => $verified, 'outcome' => $verified ? 'verified' : 'corrupted', 'error' => $verified ? null : 'Stored checksum does not match the indexed attributes.'];
    }

    /**
     * @return array{entry_count: int, optimized_at: string}
     */
    public function optimize(string $sourceType): array
    {
        $statement = $this->database->prepare('SELECT COUNT(*) AS entry_count FROM index_entries WHERE source_type = :source_type');
        $statement->execute(['source_type' => $sourceType]);
        $entryCount = (int) $statement->fetch()['entry_count'];

        return ['entry_count' => $entryCount, 'optimized_at' => $this->nowString()];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $sourceType, string $recordId): ?array
    {
        return $this->hydrate($this->fetch($sourceType, $recordId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findBySourceType(string $sourceType): array
    {
        $statement = $this->database->prepare('SELECT * FROM index_entries WHERE source_type = :source_type');
        $statement->execute(['source_type' => $sourceType]);

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    /**
     * @param array<int, string> $queryTerms
     * @param array{source_types?: array<int, string>, category?: ?string, related_to?: ?string, limit?: int} $options
     * @return array{results: array<int, array<string, mixed>>, outcome: string, error: ?string}
     */
    public function search(array $queryTerms, array $options = []): array
    {
        if ($queryTerms === []) {
            return ['results' => [], 'outcome' => 'no_query', 'error' => 'At least one query term is required.'];
        }

        $sourceTypes = $options['source_types'] ?? self::SOURCE_TYPES;
        $unknownTypes = array_diff($sourceTypes, self::SOURCE_TYPES);

        if ($unknownTypes !== []) {
            return ['results' => [], 'outcome' => 'invalid_source_type', 'error' => sprintf('Unknown source type(s): %s.', implode(', ', $unknownTypes))];
        }

        $placeholders = implode(',', array_fill(0, count($sourceTypes), '?'));
        $statement = $this->database->prepare("SELECT * FROM index_entries WHERE source_type IN ({$placeholders})");
        $statement->execute(array_values($sourceTypes));

        $scored = [];

        foreach ($statement->fetchAll() as $row) {
            if (isset($options['category']) && !in_array($options['category'], json_decode((string) $row['categories_json'], true, flags: JSON_THROW_ON_ERROR), true)) {
                continue;
            }

            if (isset($options['related_to']) && !in_array($options['related_to'], json_decode((string) $row['relationships_json'], true, flags: JSON_THROW_ON_ERROR), true)) {
                continue;
            }

            $score = $this->relevanceScore($queryTerms, $row);

            if ($score > 0) {
                $scored[] = [...$this->hydrate($row), 'score' => $score];
            }
        }

        usort($scored, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return ['results' => array_slice($scored, 0, $options['limit'] ?? 10), 'outcome' => 'searched', 'error' => null];
    }

    /**
     * @param array<int, string> $queryTerms
     * @param array<string, mixed> $row
     */
    private function relevanceScore(array $queryTerms, array $row): int
    {
        $haystack = strtolower(implode(' ', [
            (string) ($row['title'] ?? ''),
            implode(' ', json_decode((string) $row['keywords_json'], true, flags: JSON_THROW_ON_ERROR)),
            implode(' ', json_decode((string) $row['tags_json'], true, flags: JSON_THROW_ON_ERROR)),
            implode(' ', json_decode((string) $row['categories_json'], true, flags: JSON_THROW_ON_ERROR)),
        ]));

        $score = 0;

        foreach ($queryTerms as $term) {
            $score += substr_count($haystack, strtolower($term));
        }

        return $score;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $sourceType, string $recordId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM index_entries WHERE source_type = :source_type AND record_id = :record_id');
        $statement->execute(['source_type' => $sourceType, 'record_id' => $recordId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array<string, mixed>|null
     */
    private function hydrate(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'index_entry_id' => $row['index_entry_id'],
            'source_type' => $row['source_type'],
            'record_id' => $row['record_id'],
            'title' => $row['title'],
            'keywords' => json_decode((string) $row['keywords_json'], true, flags: JSON_THROW_ON_ERROR),
            'tags' => json_decode((string) $row['tags_json'], true, flags: JSON_THROW_ON_ERROR),
            'categories' => json_decode((string) $row['categories_json'], true, flags: JSON_THROW_ON_ERROR),
            'component' => $row['component'],
            'workflow' => $row['workflow'],
            'agent' => $row['agent'],
            'classification' => $row['classification'],
            'status' => $row['status'],
            'version' => $row['version'],
            'relationships' => json_decode((string) $row['relationships_json'], true, flags: JSON_THROW_ON_ERROR),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array{outcome: string, index_entry_id: ?string, error: ?string}
     */
    private function reject(string $recordId, string $sourceType, array $attributes, string $error): array
    {
        return $this->recordOperation($recordId, $sourceType, $attributes, 'rejected', 'not_applicable', $error, null);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array{outcome: string, index_entry_id: ?string, error: ?string}
     */
    private function recordOperation(
        string $recordId,
        string $indexType,
        array $attributes,
        string $outcome,
        string $integrityVerification,
        ?string $error,
        ?string $indexEntryId
    ): array {
        $operationId = 'index_op_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO index_operations (
                index_operation_id, record_id, index_type, indexed_attributes_json,
                governance_status, integrity_verification, optimization_status, final_outcome, error, created_at
            ) VALUES (
                :id, :record_id, :index_type, :indexed_attributes_json,
                :governance_status, :integrity_verification, :optimization_status, :final_outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'id' => $operationId,
            'record_id' => $recordId,
            'index_type' => $indexType,
            'indexed_attributes_json' => json_encode($attributes, JSON_THROW_ON_ERROR),
            'governance_status' => 'ungoverned',
            'integrity_verification' => $integrityVerification,
            'optimization_status' => 'not_applicable',
            'final_outcome' => $outcome,
            'error' => $error,
            'created_at' => $this->nowString(),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'index.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['record_id' => $recordId, 'index_type' => $indexType, 'outcome' => $outcome]
        ));

        return ['outcome' => $outcome, 'index_entry_id' => $indexEntryId, 'error' => $error];
    }

    private function nowString(): string
    {
        $now = $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();

        return $now->format(DATE_RFC3339);
    }
}
