<?php

declare(strict_types=1);

namespace SquirrelForge\Storage;

use PDO;

/**
 * Replicates real content held in SqliteDocumentStorage and
 * SqliteObjectStorage to registered replica instances of the same
 * component, per 37_STORAGE/STORAGE-REPLICATION.md.
 *
 * Replication targets are genuine, independently retrievable replica
 * storage instances -- not a fabricated status flag. replicate()
 * actually calls store() (first replication) or updateVersion()
 * (subsequent replications, matching the source's own append-only
 * versioning) on each registered target's real storage instance, the
 * same "capture real content, apply it for real" shape
 * SqliteBackupManager::backup()/restore() and
 * SqliteArchiveStorage::archive() already establish for these same two
 * source types. Integrity verification is real: after writing to a
 * target, this class re-retrieves what the target actually stored and
 * compares its checksum against the source's, the same fail-closed
 * check used throughout this layer, rather than assuming a successful
 * write means a correct one.
 *
 * "Detect replication lag" is a real, checkable comparison, not a
 * guess: every replicated target records the source version it was
 * last pushed at, and health() compares that recorded version against
 * the source's current live version to report `current` or `lagging`
 * without re-copying or re-hashing anything.
 *
 * "Must never decide or execute failover on its own authority" is
 * upheld structurally: this class has no method that chooses a new
 * primary or redirects traffic. It only replicates data and reports
 * health/integrity status -- 35_RESILIENCE/REDUNDANCY-MANAGER.md and
 * 35_RESILIENCE/FAILOVER-COORDINATOR.md are the real consumers of that
 * status, not something this class calls itself.
 *
 * Targets are registered in-memory per instance (registerTarget()),
 * not persisted as serialized object references -- the same reason
 * this codebase never persists EventBusInterface listeners. What is
 * persisted is the replication_records mapping from a source reference
 * to each target's replica reference and last-known state, so
 * replication history survives across calls even though the live
 * target storage objects themselves must be re-registered each time
 * this class is constructed.
 */
final class SqliteStorageReplication
{
    private const SOURCE_TYPES = ['document', 'object'];

    private PDO $database;

    /** @var array<string, array{source_type: string, storage: SqliteDocumentStorage|SqliteObjectStorage}> */
    private array $targets = [];

    public function __construct(
        string $databasePath,
        private readonly ?SqliteDocumentStorage $documents = null,
        private readonly ?SqliteObjectStorage $objects = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS replication_records (
                source_type TEXT NOT NULL,
                source_ref TEXT NOT NULL,
                target_id TEXT NOT NULL,
                replica_ref TEXT NOT NULL,
                replicated_source_version INTEGER NOT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                PRIMARY KEY (source_type, source_ref, target_id)
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS replication_operations (
                operation_id TEXT PRIMARY KEY,
                operation TEXT NOT NULL,
                source_type TEXT,
                source_ref TEXT,
                target_id TEXT,
                governance_status TEXT NOT NULL,
                integrity_status TEXT NOT NULL,
                outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function registerTarget(string $targetId, string $sourceType, SqliteDocumentStorage|SqliteObjectStorage $storage): array
    {
        if (!in_array($sourceType, self::SOURCE_TYPES, true)) {
            return ['outcome' => 'rejected', 'error' => sprintf('Unknown source type "%s".', $sourceType)];
        }

        $this->targets[$targetId] = ['source_type' => $sourceType, 'storage' => $storage];

        return ['outcome' => 'registered', 'error' => null];
    }

    /**
     * @return array{outcome: string, targets: array<string, array{outcome: string, error: ?string}>, error: ?string}
     */
    public function replicate(string $sourceType, string $sourceRef): array
    {
        if (!in_array($sourceType, self::SOURCE_TYPES, true)) {
            $this->recordOperation('replicate', $sourceType, $sourceRef, null, 'not_applicable', 'rejected', sprintf('Unknown source type "%s".', $sourceType));

            return ['outcome' => 'rejected', 'targets' => [], 'error' => sprintf('Unknown source type "%s".', $sourceType)];
        }

        $capture = $this->captureContent($sourceType, $sourceRef);

        if ($capture['error'] !== null) {
            $this->recordOperation('replicate', $sourceType, $sourceRef, null, 'failed', 'rejected', $capture['error']);

            return ['outcome' => 'rejected', 'targets' => [], 'error' => $capture['error']];
        }

        $matchingTargets = array_filter($this->targets, static fn(array $target): bool => $target['source_type'] === $sourceType);

        if ($matchingTargets === []) {
            return ['outcome' => 'no_targets', 'targets' => [], 'error' => null];
        }

        $results = [];

        foreach ($matchingTargets as $targetId => $target) {
            $results[$targetId] = $this->replicateToTarget($sourceType, $sourceRef, $targetId, $target['storage'], $capture);
        }

        return ['outcome' => 'replicated', 'targets' => $results, 'error' => null];
    }

    /**
     * @param array{blob: ?string, encoding: ?string, version: ?int, label: ?string, error: ?string} $capture
     * @return array{outcome: string, error: ?string}
     */
    private function replicateToTarget(string $sourceType, string $sourceRef, string $targetId, SqliteDocumentStorage|SqliteObjectStorage $storage, array $capture): array
    {
        $decodedContent = $capture['encoding'] === 'json'
            ? json_decode((string) $capture['blob'], true, flags: JSON_THROW_ON_ERROR)
            : $capture['blob'];

        $existing = $this->fetchRecord($sourceType, $sourceRef, $targetId);

        if ($existing === null) {
            $write = $sourceType === 'document'
                ? $storage->store('replica', $capture['label'], $decodedContent)
                : $storage->store($capture['label'], 'application/octet-stream', $decodedContent);
            $replicaRef = $write[$sourceType === 'document' ? 'document_ref' : 'object_ref'] ?? null;
        } else {
            $replicaRef = $existing['replica_ref'];
            $write = $sourceType === 'document'
                ? $storage->updateVersion($replicaRef, $decodedContent)
                : $storage->updateVersion($replicaRef, $decodedContent);
        }

        if ($write['outcome'] !== 'stored' && $write['outcome'] !== 'updated') {
            $this->upsertRecord($sourceType, $sourceRef, $targetId, $replicaRef ?? '', (int) $capture['version'], 'failed');
            $this->recordOperation('replicate', $sourceType, $sourceRef, $targetId, 'failed', 'failed', (string) ($write['error'] ?? 'Replication write failed.'));

            return ['outcome' => 'failed', 'error' => $write['error'] ?? 'Replication write failed.'];
        }

        $verification = $storage->retrieve($replicaRef);
        $verifiedBlob = $sourceType === 'document' ? json_encode($verification['content'] ?? null, JSON_THROW_ON_ERROR) : ($verification['contents'] ?? null);

        if (!$verification['found'] || $verifiedBlob !== $capture['blob']) {
            $this->upsertRecord($sourceType, $sourceRef, $targetId, $replicaRef, (int) $capture['version'], 'failed');
            $error = sprintf('Replica "%s" for target "%s" failed post-write integrity verification.', $replicaRef, $targetId);
            $this->recordOperation('replicate', $sourceType, $sourceRef, $targetId, 'failed', 'failed', $error);

            return ['outcome' => 'failed', 'error' => $error];
        }

        $this->upsertRecord($sourceType, $sourceRef, $targetId, $replicaRef, (int) $capture['version'], 'healthy');
        $this->recordOperation('replicate', $sourceType, $sourceRef, $targetId, 'verified', 'replicated', null);

        return ['outcome' => 'replicated', 'error' => null];
    }

    /**
     * Re-checks an already-replicated target against the source's
     * current content without pushing a new write, per the spec's
     * standalone Replica Verification Workflow.
     *
     * @return array{outcome: string, error: ?string}
     */
    public function verify(string $sourceType, string $sourceRef, string $targetId): array
    {
        $record = $this->fetchRecord($sourceType, $sourceRef, $targetId);

        if ($record === null || !isset($this->targets[$targetId])) {
            $error = $record === null
                ? sprintf('No replication record for source "%s" on target "%s".', $sourceRef, $targetId)
                : sprintf('Target "%s" is not currently registered.', $targetId);
            $this->recordOperation('verify', $sourceType, $sourceRef, $targetId, 'not_applicable', 'unreachable', $error);

            return ['outcome' => 'unreachable', 'error' => $error];
        }

        $capture = $this->captureContent($sourceType, $sourceRef);

        if ($capture['error'] !== null) {
            $this->recordOperation('verify', $sourceType, $sourceRef, $targetId, 'not_applicable', 'unreachable', $capture['error']);

            return ['outcome' => 'unreachable', 'error' => $capture['error']];
        }

        $storage = $this->targets[$targetId]['storage'];
        $verification = $storage->retrieve($record['replica_ref']);
        $verifiedBlob = $sourceType === 'document' ? json_encode($verification['content'] ?? null, JSON_THROW_ON_ERROR) : ($verification['contents'] ?? null);

        if (!$verification['found'] || $verifiedBlob !== $capture['blob']) {
            $this->upsertRecord($sourceType, $sourceRef, $targetId, $record['replica_ref'], (int) $record['replicated_source_version'], 'diverged');
            $this->recordOperation('verify', $sourceType, $sourceRef, $targetId, 'diverged', 'diverged', null);

            return ['outcome' => 'diverged', 'error' => null];
        }

        $this->recordOperation('verify', $sourceType, $sourceRef, $targetId, 'verified', 'healthy', null);

        return ['outcome' => 'healthy', 'error' => null];
    }

    /**
     * Cheap, metadata-only lag check: compares each registered target's
     * last-replicated source version against the source's current live
     * version, without re-reading or re-hashing target content.
     *
     * @return array<string, string> target_id => 'current'|'lagging'|'never_replicated'
     */
    public function health(string $sourceType, string $sourceRef): array
    {
        $capture = $this->captureContent($sourceType, $sourceRef);
        $currentVersion = $capture['version'];

        $results = [];

        foreach ($this->targets as $targetId => $target) {
            if ($target['source_type'] !== $sourceType) {
                continue;
            }

            $record = $this->fetchRecord($sourceType, $sourceRef, $targetId);

            if ($record === null) {
                $results[$targetId] = 'never_replicated';

                continue;
            }

            $results[$targetId] = ($currentVersion !== null && (int) $record['replicated_source_version'] >= $currentVersion) ? 'current' : 'lagging';
        }

        return $results;
    }

    /**
     * @return array{blob: ?string, encoding: ?string, version: ?int, label: ?string, error: ?string}
     */
    private function captureContent(string $sourceType, string $sourceRef): array
    {
        if ($sourceType === 'document') {
            if ($this->documents === null) {
                return ['blob' => null, 'encoding' => null, 'version' => null, 'label' => null, 'error' => 'Document Storage is not configured.'];
            }

            $result = $this->documents->retrieve($sourceRef);

            if (!$result['found']) {
                return ['blob' => null, 'encoding' => null, 'version' => null, 'label' => null, 'error' => sprintf('Document "%s" was not found.', $sourceRef)];
            }

            return ['blob' => json_encode($result['content'], JSON_THROW_ON_ERROR), 'encoding' => 'json', 'version' => $result['version'], 'label' => $result['title'], 'error' => null];
        }

        if ($this->objects === null) {
            return ['blob' => null, 'encoding' => null, 'version' => null, 'label' => null, 'error' => 'Object Storage is not configured.'];
        }

        $result = $this->objects->retrieve($sourceRef);

        if (!$result['found']) {
            return ['blob' => null, 'encoding' => null, 'version' => null, 'label' => null, 'error' => sprintf('Object "%s" was not found.', $sourceRef)];
        }

        return ['blob' => $result['contents'], 'encoding' => 'raw', 'version' => $result['version'], 'label' => $sourceRef, 'error' => null];
    }

    private function upsertRecord(string $sourceType, string $sourceRef, string $targetId, string $replicaRef, int $version, string $status): void
    {
        $existing = $this->fetchRecord($sourceType, $sourceRef, $targetId);
        $now = gmdate(DATE_RFC3339);

        if ($existing === null) {
            $statement = $this->database->prepare(
                'INSERT INTO replication_records (
                    source_type, source_ref, target_id, replica_ref, replicated_source_version, status, created_at, updated_at
                ) VALUES (
                    :source_type, :source_ref, :target_id, :replica_ref, :replicated_source_version, :status, :created_at, :updated_at
                )'
            );
            $statement->execute([
                'source_type' => $sourceType,
                'source_ref' => $sourceRef,
                'target_id' => $targetId,
                'replica_ref' => $replicaRef,
                'replicated_source_version' => $version,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        $statement = $this->database->prepare(
            'UPDATE replication_records SET replicated_source_version = :version, status = :status, updated_at = :updated_at
             WHERE source_type = :source_type AND source_ref = :source_ref AND target_id = :target_id'
        );
        $statement->execute([
            'version' => $version,
            'status' => $status,
            'updated_at' => $now,
            'source_type' => $sourceType,
            'source_ref' => $sourceRef,
            'target_id' => $targetId,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRecord(string $sourceType, string $sourceRef, string $targetId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM replication_records WHERE source_type = :source_type AND source_ref = :source_ref AND target_id = :target_id'
        );
        $statement->execute(['source_type' => $sourceType, 'source_ref' => $sourceRef, 'target_id' => $targetId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function recordOperation(
        string $operation,
        ?string $sourceType,
        ?string $sourceRef,
        ?string $targetId,
        string $integrityStatus,
        string $outcome,
        ?string $error
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO replication_operations (
                operation_id, operation, source_type, source_ref, target_id,
                governance_status, integrity_status, outcome, error, created_at
            ) VALUES (
                :operation_id, :operation, :source_type, :source_ref, :target_id,
                :governance_status, :integrity_status, :outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'operation_id' => 'replication_op_' . bin2hex(random_bytes(12)),
            'operation' => $operation,
            'source_type' => $sourceType,
            'source_ref' => $sourceRef,
            'target_id' => $targetId,
            'governance_status' => 'ungoverned',
            'integrity_status' => $integrityStatus,
            'outcome' => $outcome,
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare('SELECT * FROM replication_operations ORDER BY rowid DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
