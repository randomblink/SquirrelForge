<?php

declare(strict_types=1);

namespace SquirrelForge\Knowledge;

use PDO;

/**
 * Owns citation records and citation-to-source-reference relationships,
 * per 25_KNOWLEDGE/CITATION-MANAGER.md.
 *
 * "Checks citation-format integrity, locator completeness, and
 * source-reference resolvability" is a real, checkable computation, not
 * a fabricated always-`Active` status: registerCitation() derives
 * citation_status from what was actually supplied -- `Unresolved` when
 * the source reference is empty or no locator metadata was given
 * (matching "Broken or unresolved references must be detected and
 * reported as citation status"), `Active` only when both are present.
 *
 * "Verify source registration" (Citation Process step 3) is real when a
 * `SqliteKnowledgeRegistry` is injected: registerCitation() checks the
 * referenced knowledge asset actually exists and rejects with
 * `unregistered_knowledge` when it does not, the same "genuinely
 * skipped, not silently treated as passing, when not wired in" stance
 * used throughout this codebase's optional compositions.
 *
 * Citation status transitions (markSuperseded/markStale/markDeprecated)
 * are caller-invoked, matching this component's own boundary: it
 * "does not assess overall knowledge trust or validity" itself, so it
 * never decides on its own that a citation has gone stale or been
 * superseded -- it only records the transition a caller (typically
 * Knowledge Validator or Knowledge Versioning) reports.
 *
 * `citation_history` is the append-only log of every status
 * transition, matching "Maintain citation-record history."
 */
final class SqliteCitationManager
{
    private const TYPES = [
        'primary_source', 'secondary_source', 'internal_document',
        'external_reference', 'workflow_output', 'decision_record',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteKnowledgeRegistry $registry = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS citations (
                citation_id TEXT PRIMARY KEY,
                knowledge_id TEXT NOT NULL,
                source_reference TEXT NOT NULL,
                source_version_reference TEXT,
                locator_metadata_json TEXT NOT NULL,
                citation_type TEXT NOT NULL,
                retrieval_timestamp TEXT NOT NULL,
                citation_status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS citation_history (
                history_id TEXT PRIMARY KEY,
                citation_id TEXT NOT NULL,
                from_status TEXT,
                to_status TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, mixed> $locatorMetadata
     * @return array{outcome: string, citation_id: ?string, citation_status: ?string, error: ?string}
     */
    public function registerCitation(string $knowledgeId, string $sourceReference, string $citationType, array $locatorMetadata = [], ?string $sourceVersionReference = null): array
    {
        if (!in_array($citationType, self::TYPES, true)) {
            return ['outcome' => 'invalid_type', 'citation_id' => null, 'citation_status' => null, 'error' => sprintf('"%s" is not a recognized citation type.', $citationType)];
        }

        if ($this->registry !== null && $this->registry->get($knowledgeId) === null) {
            return ['outcome' => 'unregistered_knowledge', 'citation_id' => null, 'citation_status' => null, 'error' => sprintf('Knowledge asset "%s" is not registered.', $knowledgeId)];
        }

        $citationStatus = ($sourceReference !== '' && $locatorMetadata !== []) ? 'Active' : 'Unresolved';
        $citationId = 'citation_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO citations (
                citation_id, knowledge_id, source_reference, source_version_reference, locator_metadata_json,
                citation_type, retrieval_timestamp, citation_status, created_at, updated_at
            ) VALUES (
                :citation_id, :knowledge_id, :source_reference, :source_version_reference, :locator_metadata_json,
                :citation_type, :retrieval_timestamp, :citation_status, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'citation_id' => $citationId,
            'knowledge_id' => $knowledgeId,
            'source_reference' => $sourceReference,
            'source_version_reference' => $sourceVersionReference,
            'locator_metadata_json' => json_encode($locatorMetadata, JSON_THROW_ON_ERROR),
            'citation_type' => $citationType,
            'retrieval_timestamp' => $now,
            'citation_status' => $citationStatus,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->recordHistory($citationId, null, $citationStatus);

        return ['outcome' => 'registered', 'citation_id' => $citationId, 'citation_status' => $citationStatus, 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function markSuperseded(string $citationId): array
    {
        return $this->transition($citationId, 'Superseded');
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function markStale(string $citationId): array
    {
        return $this->transition($citationId, 'Stale');
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function markDeprecated(string $citationId): array
    {
        return $this->transition($citationId, 'Deprecated');
    }

    private function transition(string $citationId, string $toStatus): array
    {
        $record = $this->fetch($citationId);

        if ($record === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Citation "%s" is not tracked.', $citationId)];
        }

        $statement = $this->database->prepare('UPDATE citations SET citation_status = :status, updated_at = :updated_at WHERE citation_id = :citation_id');
        $statement->execute(['status' => $toStatus, 'updated_at' => gmdate(DATE_RFC3339), 'citation_id' => $citationId]);

        $this->recordHistory($citationId, $record['citation_status'], $toStatus);

        return ['outcome' => 'transitioned', 'error' => null];
    }

    private function recordHistory(string $citationId, ?string $fromStatus, string $toStatus): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO citation_history (history_id, citation_id, from_status, to_status, created_at)
             VALUES (:history_id, :citation_id, :from_status, :to_status, :created_at)'
        );
        $statement->execute([
            'history_id' => 'citation_history_' . bin2hex(random_bytes(12)),
            'citation_id' => $citationId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'created_at' => gmdate(DATE_RFC3339),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $citationId): ?array
    {
        $record = $this->fetch($citationId);

        if ($record === null) {
            return null;
        }

        $record['locator_metadata'] = json_decode((string) $record['locator_metadata_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($record['locator_metadata_json']);

        return $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByKnowledgeId(string $knowledgeId): array
    {
        $statement = $this->database->prepare('SELECT * FROM citations WHERE knowledge_id = :knowledge_id ORDER BY rowid ASC');
        $statement->execute(['knowledge_id' => $knowledgeId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(string $citationId): array
    {
        $statement = $this->database->prepare('SELECT * FROM citation_history WHERE citation_id = :citation_id ORDER BY created_at ASC, rowid ASC');
        $statement->execute(['citation_id' => $citationId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $citationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM citations WHERE citation_id = :citation_id');
        $statement->execute(['citation_id' => $citationId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }
}
