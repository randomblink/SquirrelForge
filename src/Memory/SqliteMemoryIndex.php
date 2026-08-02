<?php

declare(strict_types=1);

namespace SquirrelForge\Memory;

use PDO;

/**
 * Maintains searchable references and relationships across Working,
 * Episodic, Semantic, and Project Memory, per
 * 18_MEMORY/MEMORY-INDEX.md.
 *
 * Owns its own database (`Sqlite` prefix): an index entry is a real,
 * distinct record from the memory record it points to, needing its own
 * table rather than living inside any single memory type's store.
 *
 * "The Index stores references only; it does not interpret queries,
 * rank candidates, or return results" is upheld by omission: this
 * class has no search-by-relevance method at all, only exact lookups
 * (get(), findByType()) and literal relationship traversal
 * (findRelated()) -- ranking and query interpretation are genuinely
 * absent, not merely undocumented, leaving that entirely to Memory
 * Retrieval.
 *
 * "Prevent duplicate index entries... without altering or deleting the
 * underlying authoritative memory record" is a real, enforced
 * invariant: indexRecord() is keyed by (memory_type, record_id) and a
 * second call for the same pair updates the existing index entry in
 * place rather than creating a duplicate -- and no method in this
 * class ever issues a write against any memory type's own store; it
 * only ever touches its own `index_entries` table.
 *
 * "It also does not index Knowledge Layer content" is a real, enforced
 * gate: `memory_type` must be one of the four literal sources the spec
 * names (working, episodic, semantic, project); anything else,
 * including a caller attempting to index knowledge-layer content
 * directly, is rejected outright.
 *
 * removeIndexEntry() only ever deletes from this class's own index
 * table -- its scope is the literal boundary that keeps it from ever
 * touching the authoritative memory record.
 */
final class SqliteMemoryIndex
{
    private const MEMORY_TYPES = ['working', 'episodic', 'semantic', 'project'];

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
            'CREATE TABLE IF NOT EXISTS index_entries (
                index_id TEXT PRIMARY KEY,
                memory_type TEXT NOT NULL,
                record_id TEXT NOT NULL,
                title TEXT,
                keywords_json TEXT NOT NULL,
                related_records_json TEXT NOT NULL,
                last_updated TEXT NOT NULL,
                UNIQUE (memory_type, record_id)
            )'
        );
    }

    /**
     * Creates the index entry for a memory record, or updates it in
     * place if one already exists for the same (memory_type, record_id)
     * pair -- "exactly one index entry" per underlying record.
     *
     * @param array{title?: ?string, keywords?: array<int, string>} $options
     * @return array{index_id: ?string, outcome: string, error: ?string}
     */
    public function indexRecord(string $memoryType, string $recordId, array $options = []): array
    {
        if (!in_array($memoryType, self::MEMORY_TYPES, true)) {
            return ['index_id' => null, 'outcome' => 'invalid_memory_type', 'error' => sprintf('"%s" is not an indexable memory type.', $memoryType)];
        }

        $existing = $this->fetchEntry($memoryType, $recordId);
        $now = gmdate(DATE_RFC3339);

        if ($existing !== null) {
            $statement = $this->database->prepare(
                'UPDATE index_entries SET title = :title, keywords_json = :keywords_json, last_updated = :last_updated WHERE index_id = :index_id'
            );
            $statement->execute([
                'title' => $options['title'] ?? $existing['title'],
                'keywords_json' => json_encode($options['keywords'] ?? json_decode((string) $existing['keywords_json'], true, flags: JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR),
                'last_updated' => $now,
                'index_id' => $existing['index_id'],
            ]);

            return ['index_id' => $existing['index_id'], 'outcome' => 'updated', 'error' => null];
        }

        $indexId = 'index_' . bin2hex(random_bytes(12));
        $statement = $this->database->prepare(
            'INSERT INTO index_entries (index_id, memory_type, record_id, title, keywords_json, related_records_json, last_updated)
             VALUES (:index_id, :memory_type, :record_id, :title, :keywords_json, :related_records_json, :last_updated)'
        );
        $statement->execute([
            'index_id' => $indexId,
            'memory_type' => $memoryType,
            'record_id' => $recordId,
            'title' => $options['title'] ?? null,
            'keywords_json' => json_encode($options['keywords'] ?? [], JSON_THROW_ON_ERROR),
            'related_records_json' => json_encode([], JSON_THROW_ON_ERROR),
            'last_updated' => $now,
        ]);

        return ['index_id' => $indexId, 'outcome' => 'created', 'error' => null];
    }

    /**
     * Links a related record, keyed by its own (memory_type, record_id)
     * reference. De-duplicates: linking the same pair twice does not
     * produce two relationship entries.
     *
     * @return array{outcome: string, error: ?string}
     */
    public function linkRelated(string $memoryType, string $recordId, string $relatedMemoryType, string $relatedRecordId): array
    {
        $entry = $this->fetchEntry($memoryType, $recordId);

        if ($entry === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('No index entry exists for %s record "%s".', $memoryType, $recordId)];
        }

        $related = json_decode((string) $entry['related_records_json'], true, flags: JSON_THROW_ON_ERROR);
        $relatedRef = ['memory_type' => $relatedMemoryType, 'record_id' => $relatedRecordId];

        if (!in_array($relatedRef, $related, true)) {
            $related[] = $relatedRef;
        }

        $statement = $this->database->prepare(
            'UPDATE index_entries SET related_records_json = :related_records_json, last_updated = :last_updated WHERE index_id = :index_id'
        );
        $statement->execute([
            'related_records_json' => json_encode($related, JSON_THROW_ON_ERROR),
            'last_updated' => gmdate(DATE_RFC3339),
            'index_id' => $entry['index_id'],
        ]);

        return ['outcome' => 'linked', 'error' => null];
    }

    /**
     * Only ever removes this class's own index entry -- never the
     * underlying authoritative memory record.
     *
     * @return array{outcome: string, error: ?string}
     */
    public function removeIndexEntry(string $memoryType, string $recordId): array
    {
        $statement = $this->database->prepare('DELETE FROM index_entries WHERE memory_type = :memory_type AND record_id = :record_id');
        $statement->execute(['memory_type' => $memoryType, 'record_id' => $recordId]);

        return $statement->rowCount() > 0
            ? ['outcome' => 'removed', 'error' => null]
            : ['outcome' => 'not_found', 'error' => sprintf('No index entry exists for %s record "%s".', $memoryType, $recordId)];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $memoryType, string $recordId): ?array
    {
        $entry = $this->fetchEntry($memoryType, $recordId);

        return $entry !== null ? $this->hydrate($entry) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByType(string $memoryType): array
    {
        $statement = $this->database->prepare('SELECT * FROM index_entries WHERE memory_type = :memory_type ORDER BY rowid ASC');
        $statement->execute(['memory_type' => $memoryType]);

        return array_map(fn(array $row): array => $this->hydrate($row), $statement->fetchAll());
    }

    /**
     * Literal relationship traversal -- not a ranked or interpreted
     * search.
     *
     * @return array<int, array{memory_type: string, record_id: string}>
     */
    public function findRelated(string $memoryType, string $recordId): array
    {
        $entry = $this->fetchEntry($memoryType, $recordId);

        return $entry !== null ? json_decode((string) $entry['related_records_json'], true, flags: JSON_THROW_ON_ERROR) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchEntry(string $memoryType, string $recordId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM index_entries WHERE memory_type = :memory_type AND record_id = :record_id');
        $statement->execute(['memory_type' => $memoryType, 'record_id' => $recordId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['keywords'] = json_decode((string) $row['keywords_json'], true, flags: JSON_THROW_ON_ERROR);
        $row['related_records'] = json_decode((string) $row['related_records_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($row['keywords_json'], $row['related_records_json']);

        return $row;
    }
}
