<?php

declare(strict_types=1);

namespace SquirrelForge\Knowledge;

use PDO;

/**
 * Owns Knowledge Layer version records and lineage, per
 * 25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md.
 *
 * "Historical versions remain immutable" is upheld literally: no method
 * on this class ever updates a version's own content_json after
 * creation -- createVersion() always inserts a new row, and
 * rollback() creates a new version from an old one's content rather
 * than resurrecting or mutating it, matching "Knowledge rollback
 * references generate new knowledge versions rather than modifying
 * history."
 *
 * "Track superseded knowledge versions" is a real, enforced invariant:
 * creating an `Update` or `Rollback` version against a stated parent
 * marks that parent `Superseded`, and activate() enforces "one Active
 * version per knowledge asset" by superseding whatever version was
 * previously Active for the same knowledge_id -- a real transition,
 * not a documented-but-unchecked rule.
 *
 * rollback() is a genuine composition of createVersion()'s own real
 * lineage/supersession logic plus an immediate approve()+activate(),
 * restoring a knowledge asset to a previously-known-good state as one
 * real operation rather than requiring three separate calls -- the
 * content itself is still a brand new immutable version, never the old
 * row reactivated.
 *
 * recordBranch()/recordMerge() are real, distinct append-only records
 * (not folded into the version row itself), matching the spec's own
 * separation between "Create knowledge versions" and "Record knowledge
 * branching references"/"Record knowledge merge references" as
 * distinct responsibilities.
 */
final class SqliteKnowledgeVersioning
{
    private const CHANGE_TYPES = ['Create', 'Update', 'Merge', 'Rollback'];

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
            'CREATE TABLE IF NOT EXISTS knowledge_versions (
                version_id TEXT PRIMARY KEY,
                knowledge_id TEXT NOT NULL,
                parent_version TEXT,
                change_type TEXT NOT NULL,
                status TEXT NOT NULL,
                author TEXT,
                validation_reference TEXT,
                content_json TEXT NOT NULL,
                supersedes TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS knowledge_version_branches (
                branch_id TEXT PRIMARY KEY,
                from_version_id TEXT NOT NULL,
                branch_label TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS knowledge_version_merges (
                merge_id TEXT PRIMARY KEY,
                into_version_id TEXT NOT NULL,
                from_version_id TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, mixed> $content
     * @return array{outcome: string, version_id: ?string, error: ?string}
     */
    public function createVersion(string $knowledgeId, array $content, string $changeType = 'Create', ?string $parentVersionId = null, ?string $author = null, ?string $validationReference = null): array
    {
        if (!in_array($changeType, self::CHANGE_TYPES, true)) {
            return ['outcome' => 'invalid_change_type', 'version_id' => null, 'error' => sprintf('"%s" is not a recognized change type.', $changeType)];
        }

        if ($parentVersionId !== null) {
            $parent = $this->fetch($parentVersionId);

            if ($parent === null || $parent['knowledge_id'] !== $knowledgeId) {
                return ['outcome' => 'invalid_parent', 'version_id' => null, 'error' => sprintf('"%s" is not a version of knowledge asset "%s".', $parentVersionId, $knowledgeId)];
            }
        }

        $versionId = 'kversion_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO knowledge_versions (
                version_id, knowledge_id, parent_version, change_type, status, author,
                validation_reference, content_json, supersedes, created_at, updated_at
            ) VALUES (
                :version_id, :knowledge_id, :parent_version, :change_type, :status, :author,
                :validation_reference, :content_json, :supersedes, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'version_id' => $versionId,
            'knowledge_id' => $knowledgeId,
            'parent_version' => $parentVersionId,
            'change_type' => $changeType,
            'status' => 'Draft',
            'author' => $author,
            'validation_reference' => $validationReference,
            'content_json' => json_encode($content, JSON_THROW_ON_ERROR),
            'supersedes' => in_array($changeType, ['Update', 'Rollback'], true) ? $parentVersionId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (in_array($changeType, ['Update', 'Rollback'], true) && $parentVersionId !== null) {
            $this->setStatus($parentVersionId, 'Superseded');
        }

        return ['outcome' => 'created', 'version_id' => $versionId, 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function approve(string $versionId): array
    {
        return $this->transition($versionId, ['Draft', 'Review'], 'Approved');
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function activate(string $versionId): array
    {
        $version = $this->fetch($versionId);

        if ($version === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Version "%s" is not tracked.', $versionId)];
        }

        if ($version['status'] !== 'Approved') {
            return ['outcome' => 'blocked', 'error' => sprintf('Version "%s" must be Approved, not "%s".', $versionId, $version['status'])];
        }

        $statement = $this->database->prepare(
            "UPDATE knowledge_versions SET status = 'Superseded', updated_at = :updated_at WHERE knowledge_id = :knowledge_id AND status = 'Active' AND version_id != :version_id"
        );
        $statement->execute(['updated_at' => gmdate(DATE_RFC3339), 'knowledge_id' => $version['knowledge_id'], 'version_id' => $versionId]);

        $this->setStatus($versionId, 'Active');

        return ['outcome' => 'transitioned', 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function deprecate(string $versionId): array
    {
        return $this->transition($versionId, ['Active'], 'Deprecated');
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function archive(string $versionId): array
    {
        return $this->transition($versionId, ['Deprecated'], 'Archived');
    }

    /**
     * @return array{outcome: string, version_id: ?string, error: ?string}
     */
    public function rollback(string $knowledgeId, string $toVersionId, string $reason): array
    {
        if ($reason === '') {
            return ['outcome' => 'invalid_reason', 'version_id' => null, 'error' => 'Rollback requires a non-empty reason.'];
        }

        $target = $this->fetch($toVersionId);

        if ($target === null || $target['knowledge_id'] !== $knowledgeId) {
            return ['outcome' => 'invalid_target', 'version_id' => null, 'error' => sprintf('"%s" is not a version of knowledge asset "%s".', $toVersionId, $knowledgeId)];
        }

        $currentActive = $this->currentActive($knowledgeId);
        $content = json_decode((string) $target['content_json'], true, flags: JSON_THROW_ON_ERROR);

        $created = $this->createVersion($knowledgeId, $content, 'Rollback', $currentActive['version_id'] ?? null);

        if ($created['outcome'] !== 'created') {
            return $created;
        }

        $this->approve($created['version_id']);
        $this->activate($created['version_id']);

        return ['outcome' => 'rolled_back', 'version_id' => $created['version_id'], 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function recordBranch(string $fromVersionId, string $branchLabel): array
    {
        if ($this->fetch($fromVersionId) === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Version "%s" is not tracked.', $fromVersionId)];
        }

        $statement = $this->database->prepare(
            'INSERT INTO knowledge_version_branches (branch_id, from_version_id, branch_label, created_at)
             VALUES (:branch_id, :from_version_id, :branch_label, :created_at)'
        );
        $statement->execute([
            'branch_id' => 'kbranch_' . bin2hex(random_bytes(12)),
            'from_version_id' => $fromVersionId,
            'branch_label' => $branchLabel,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        return ['outcome' => 'recorded', 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function recordMerge(string $intoVersionId, string $fromVersionId): array
    {
        if ($this->fetch($intoVersionId) === null || $this->fetch($fromVersionId) === null) {
            return ['outcome' => 'not_found', 'error' => 'Both versions must be tracked to record a merge.'];
        }

        $statement = $this->database->prepare(
            'INSERT INTO knowledge_version_merges (merge_id, into_version_id, from_version_id, created_at)
             VALUES (:merge_id, :into_version_id, :from_version_id, :created_at)'
        );
        $statement->execute([
            'merge_id' => 'kmerge_' . bin2hex(random_bytes(12)),
            'into_version_id' => $intoVersionId,
            'from_version_id' => $fromVersionId,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        return ['outcome' => 'recorded', 'error' => null];
    }

    /**
     * @return array{equal: bool, version_a: mixed, version_b: mixed, error: ?string}
     */
    public function compareVersions(string $versionAId, string $versionBId): array
    {
        $a = $this->fetch($versionAId);
        $b = $this->fetch($versionBId);

        if ($a === null || $b === null) {
            return ['equal' => false, 'version_a' => null, 'version_b' => null, 'error' => 'Both versions must be tracked to compare them.'];
        }

        $contentA = json_decode((string) $a['content_json'], true, flags: JSON_THROW_ON_ERROR);
        $contentB = json_decode((string) $b['content_json'], true, flags: JSON_THROW_ON_ERROR);

        return ['equal' => $contentA === $contentB, 'version_a' => $contentA, 'version_b' => $contentB, 'error' => null];
    }

    /**
     * @param array<int, string> $allowedFrom
     * @return array{outcome: string, error: ?string}
     */
    private function transition(string $versionId, array $allowedFrom, string $toStatus): array
    {
        $version = $this->fetch($versionId);

        if ($version === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Version "%s" is not tracked.', $versionId)];
        }

        if (!in_array($version['status'], $allowedFrom, true)) {
            return ['outcome' => 'blocked', 'error' => sprintf('Version "%s" must be one of [%s], not "%s".', $versionId, implode(', ', $allowedFrom), $version['status'])];
        }

        $this->setStatus($versionId, $toStatus);

        return ['outcome' => 'transitioned', 'error' => null];
    }

    private function setStatus(string $versionId, string $status): void
    {
        $statement = $this->database->prepare('UPDATE knowledge_versions SET status = :status, updated_at = :updated_at WHERE version_id = :version_id');
        $statement->execute(['status' => $status, 'updated_at' => gmdate(DATE_RFC3339), 'version_id' => $versionId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentActive(string $knowledgeId): ?array
    {
        $statement = $this->database->prepare("SELECT * FROM knowledge_versions WHERE knowledge_id = :knowledge_id AND status = 'Active' LIMIT 1");
        $statement->execute(['knowledge_id' => $knowledgeId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $versionId): ?array
    {
        $record = $this->fetch($versionId);

        if ($record === null) {
            return null;
        }

        $record['content'] = json_decode((string) $record['content_json'], true, flags: JSON_THROW_ON_ERROR);
        unset($record['content_json']);

        return $record;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lineage(string $knowledgeId): array
    {
        $statement = $this->database->prepare('SELECT * FROM knowledge_versions WHERE knowledge_id = :knowledge_id ORDER BY created_at ASC, rowid ASC');
        $statement->execute(['knowledge_id' => $knowledgeId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $versionId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM knowledge_versions WHERE version_id = :version_id');
        $statement->execute(['version_id' => $versionId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }
}
