<?php

declare(strict_types=1);

namespace SquirrelForge\Storage;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Creates, verifies, and restores backups of real content held in
 * SqliteDocumentStorage and SqliteObjectStorage, per
 * 37_STORAGE/BACKUP-MANAGER.md.
 *
 * This is a genuine end-to-end integration, not a standalone snapshot
 * store: backup() actually calls retrieve() on the injected document/
 * object store to capture real current content, and restore() actually
 * calls updateVersion() to apply it back -- as a new version, never a
 * rewrite of history, since both stores are append-only by design. That
 * also makes "create a safety snapshot of current state" (Restoration
 * Workflow step 5) real: before applying a restore, this class calls its
 * own backup() again with type `pre_restoration` to capture what's about
 * to be overwritten, and aborts the restore if that safety snapshot
 * itself fails.
 *
 * Integrity verification is real but placed where it can actually catch
 * something: backup() records a checksum once (recomputing it
 * immediately after an in-process sqlite insert can't meaningfully fail
 * and would just be verification theater), and restore() recomputes the
 * checksum of the stored snapshot and compares it before use -- the gap
 * between backup and restore is exactly where real tampering or bit-rot
 * would show up, mirroring SqliteObjectStorage's fail-closed retrieval
 * check. Restoration itself is also verified: after updateVersion()
 * writes the restored content, this class re-retrieves that version and
 * confirms it matches, per workflow step 7.
 *
 * Of the eight listed backup types, all are accepted and recorded as a
 * label, but the underlying mechanism is always a full snapshot of the
 * source's current version -- there's no chunked or delta storage
 * engine to make "incremental"/"differential" a real distinction against,
 * and labeling it otherwise would fabricate one. Retention is real:
 * backup() prunes older, non-protected backups for the same source
 * beyond a caller-supplied count, and protectBackup() is the actual gate
 * behind the spec's "must never delete protected backups" rule -- unlike
 * Document/Object Storage's blanket tombstone-only stance, ordinary
 * (unprotected) backups are genuinely deleted on pruning, since managing
 * retention by deletion is this component's actual job. Authorization
 * delegates to AuthorizationManagerInterface, genuinely skipped when not
 * injected. Governance status is the fixed constant `ungoverned` since
 * 23_GOVERNANCE has no policy engine to gate on.
 */
final class SqliteBackupManager
{
    private const BACKUP_TYPES = [
        'full', 'incremental', 'differential', 'snapshot',
        'scheduled', 'manual', 'pre_deployment', 'pre_restoration',
    ];

    private const SOURCE_TYPES = ['document', 'object'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteDocumentStorage $documents = null,
        private readonly ?SqliteObjectStorage $objects = null,
        private readonly ?AuthorizationManagerInterface $authorization = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS backup_snapshots (
                backup_ref TEXT PRIMARY KEY,
                source_type TEXT NOT NULL,
                source_ref TEXT NOT NULL,
                source_version INTEGER NOT NULL,
                backup_type TEXT NOT NULL,
                content_blob TEXT NOT NULL,
                content_encoding TEXT NOT NULL,
                checksum TEXT NOT NULL,
                protected INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS backup_operations (
                operation_ref TEXT PRIMARY KEY,
                operation TEXT NOT NULL,
                backup_ref TEXT,
                source_type TEXT,
                source_ref TEXT,
                authorization_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                integrity_status TEXT NOT NULL,
                outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{type?: string, retention_count?: ?int, requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $options
     * @return array{backup_ref: ?string, source_type: string, source_ref: string, source_version: ?int, outcome: string, error: ?string}
     */
    public function backup(string $sourceType, string $sourceRef, array $options = []): array
    {
        if (!in_array($sourceType, self::SOURCE_TYPES, true)) {
            return $this->rejectOperation('backup', null, $sourceType, $sourceRef, 'not_applicable', sprintf('Unknown source type "%s".', $sourceType));
        }

        $authorization = $this->checkAuthorization('backup', $sourceRef, $options);

        if (!$authorization['authorized']) {
            return $this->rejectOperation('backup', null, $sourceType, $sourceRef, $authorization['status'], $authorization['error']);
        }

        $backupType = $options['type'] ?? 'manual';

        if (!in_array($backupType, self::BACKUP_TYPES, true)) {
            return $this->rejectOperation('backup', null, $sourceType, $sourceRef, $authorization['status'], sprintf('Unknown backup type "%s".', $backupType));
        }

        $capture = $this->captureContent($sourceType, $sourceRef);

        if ($capture['error'] !== null) {
            return $this->rejectOperation('backup', null, $sourceType, $sourceRef, $authorization['status'], $capture['error']);
        }

        $backupRef = 'backup_' . bin2hex(random_bytes(12));
        $checksum = hash('sha256', $capture['blob']);

        $statement = $this->database->prepare(
            'INSERT INTO backup_snapshots (
                backup_ref, source_type, source_ref, source_version, backup_type,
                content_blob, content_encoding, checksum, protected, created_at
            ) VALUES (
                :backup_ref, :source_type, :source_ref, :source_version, :backup_type,
                :content_blob, :content_encoding, :checksum, 0, :created_at
            )'
        );
        $statement->execute([
            'backup_ref' => $backupRef,
            'source_type' => $sourceType,
            'source_ref' => $sourceRef,
            'source_version' => $capture['version'],
            'backup_type' => $backupType,
            'content_blob' => $capture['blob'],
            'content_encoding' => $capture['encoding'],
            'checksum' => $checksum,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        if (isset($options['retention_count'])) {
            $this->pruneRetention($sourceType, $sourceRef, $options['retention_count']);
        }

        $this->recordOperation('backup', $backupRef, $sourceType, $sourceRef, $authorization['status'], 'verified', 'created', null);

        return ['backup_ref' => $backupRef, 'source_type' => $sourceType, 'source_ref' => $sourceRef, 'source_version' => $capture['version'], 'outcome' => 'created', 'error' => null];
    }

    /**
     * @return array{blob: ?string, encoding: ?string, version: ?int, error: ?string}
     */
    private function captureContent(string $sourceType, string $sourceRef): array
    {
        if ($sourceType === 'document') {
            if ($this->documents === null) {
                return ['blob' => null, 'encoding' => null, 'version' => null, 'error' => 'Document Storage is not configured.'];
            }

            $result = $this->documents->retrieve($sourceRef);

            if (!$result['found']) {
                return ['blob' => null, 'encoding' => null, 'version' => null, 'error' => sprintf('Document "%s" was not found.', $sourceRef)];
            }

            return ['blob' => json_encode($result['content'], JSON_THROW_ON_ERROR), 'encoding' => 'json', 'version' => $result['version'], 'error' => null];
        }

        if ($this->objects === null) {
            return ['blob' => null, 'encoding' => null, 'version' => null, 'error' => 'Object Storage is not configured.'];
        }

        $result = $this->objects->retrieve($sourceRef);

        if (!$result['found']) {
            return ['blob' => null, 'encoding' => null, 'version' => null, 'error' => sprintf('Object "%s" was not found.', $sourceRef)];
        }

        return ['blob' => $result['contents'], 'encoding' => 'raw', 'version' => $result['version'], 'error' => null];
    }

    /**
     * @param array{requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $options
     * @return array{backup_ref: string, restored_version: ?int, safety_backup_ref: ?string, outcome: string, error: ?string}
     */
    public function restore(string $backupRef, array $options = []): array
    {
        $backup = $this->fetchBackup($backupRef);

        if ($backup === null) {
            $error = sprintf('Unknown backup "%s".', $backupRef);
            $this->recordOperation('restore', $backupRef, null, null, 'not_applicable', 'not_applicable', 'not_found', $error);

            return ['backup_ref' => $backupRef, 'restored_version' => null, 'safety_backup_ref' => null, 'outcome' => 'not_found', 'error' => $error];
        }

        $authorization = $this->checkAuthorization('restore', $backup['source_ref'], $options);

        if (!$authorization['authorized']) {
            $this->recordOperation('restore', $backupRef, $backup['source_type'], $backup['source_ref'], $authorization['status'], 'not_applicable', 'rejected', $authorization['error']);

            return ['backup_ref' => $backupRef, 'restored_version' => null, 'safety_backup_ref' => null, 'outcome' => 'rejected', 'error' => $authorization['error']];
        }

        $actualChecksum = hash('sha256', $backup['content_blob']);

        if (!hash_equals($backup['checksum'], $actualChecksum)) {
            $error = sprintf('Backup "%s" failed integrity verification; the stored snapshot does not match its recorded checksum.', $backupRef);
            $this->recordOperation('restore', $backupRef, $backup['source_type'], $backup['source_ref'], $authorization['status'], 'failed', 'corrupted', $error);

            return ['backup_ref' => $backupRef, 'restored_version' => null, 'safety_backup_ref' => null, 'outcome' => 'corrupted', 'error' => $error];
        }

        $safetySnapshot = $this->backup($backup['source_type'], $backup['source_ref'], ['type' => 'pre_restoration']);

        if ($safetySnapshot['outcome'] !== 'created') {
            $error = sprintf('Aborted restoration: could not create a safety snapshot of current state (%s).', $safetySnapshot['error']);
            $this->recordOperation('restore', $backupRef, $backup['source_type'], $backup['source_ref'], $authorization['status'], 'verified', 'aborted', $error);

            return ['backup_ref' => $backupRef, 'restored_version' => null, 'safety_backup_ref' => null, 'outcome' => 'aborted', 'error' => $error];
        }

        $decodedContent = $backup['content_encoding'] === 'json'
            ? json_decode($backup['content_blob'], true, flags: JSON_THROW_ON_ERROR)
            : $backup['content_blob'];

        $apply = $backup['source_type'] === 'document'
            ? $this->documents->updateVersion($backup['source_ref'], $decodedContent)
            : $this->objects->updateVersion($backup['source_ref'], $decodedContent);

        if ($apply['outcome'] !== 'updated') {
            $error = sprintf('Restoration failed while applying the backup: %s', $apply['error']);
            $this->recordOperation('restore', $backupRef, $backup['source_type'], $backup['source_ref'], $authorization['status'], 'verified', 'failed', $error);

            return ['backup_ref' => $backupRef, 'restored_version' => null, 'safety_backup_ref' => $safetySnapshot['backup_ref'], 'outcome' => 'failed', 'error' => $error];
        }

        $verification = $backup['source_type'] === 'document'
            ? $this->documents->retrieve($backup['source_ref'], $apply['version'])
            : $this->objects->retrieve($backup['source_ref'], $apply['version']);

        $verifiedContent = $backup['source_type'] === 'document' ? json_encode($verification['content'] ?? null, JSON_THROW_ON_ERROR) : ($verification['contents'] ?? null);

        if (!$verification['found'] || $verifiedContent !== $backup['content_blob']) {
            $error = 'Post-restoration verification failed: the restored content does not match the backup.';
            $this->recordOperation('restore', $backupRef, $backup['source_type'], $backup['source_ref'], $authorization['status'], 'failed', 'failed', $error);

            return ['backup_ref' => $backupRef, 'restored_version' => null, 'safety_backup_ref' => $safetySnapshot['backup_ref'], 'outcome' => 'failed', 'error' => $error];
        }

        $this->recordOperation('restore', $backupRef, $backup['source_type'], $backup['source_ref'], $authorization['status'], 'verified', 'restored', null);

        return ['backup_ref' => $backupRef, 'restored_version' => $apply['version'], 'safety_backup_ref' => $safetySnapshot['backup_ref'], 'outcome' => 'restored', 'error' => null];
    }

    /**
     * Places or lifts protection on a backup. A protected backup is never
     * removed by pruneRetention(). Returns false if the backup doesn't exist.
     */
    public function protectBackup(string $backupRef, bool $protected): bool
    {
        $statement = $this->database->prepare('UPDATE backup_snapshots SET protected = :protected WHERE backup_ref = :backup_ref');
        $statement->execute(['protected' => $protected ? 1 : 0, 'backup_ref' => $backupRef]);

        return $statement->rowCount() > 0;
    }

    /**
     * Deletes the oldest unprotected backups for a source beyond
     * $keepCount, newest first. Real deletion -- unlike Document/Object
     * Storage's tombstone-only stance, managing retention by removal is
     * this component's actual job.
     *
     * @return array<int, string> the backup_ref values that were pruned
     */
    public function pruneRetention(string $sourceType, string $sourceRef, int $keepCount): array
    {
        $statement = $this->database->prepare(
            'SELECT backup_ref FROM backup_snapshots
             WHERE source_type = :source_type AND source_ref = :source_ref AND protected = 0
             ORDER BY rowid DESC'
        );
        $statement->execute(['source_type' => $sourceType, 'source_ref' => $sourceRef]);
        $refs = array_column($statement->fetchAll(), 'backup_ref');
        $toPrune = array_slice($refs, max(0, $keepCount));

        if ($toPrune === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($toPrune), '?'));
        $delete = $this->database->prepare("DELETE FROM backup_snapshots WHERE backup_ref IN ({$placeholders})");
        $delete->execute($toPrune);

        return $toPrune;
    }

    /**
     * @return array<int, array{backup_ref: string, source_version: int, backup_type: string, protected: bool, created_at: string}>
     */
    public function listBackups(string $sourceType, string $sourceRef): array
    {
        $statement = $this->database->prepare(
            'SELECT backup_ref, source_version, backup_type, protected, created_at FROM backup_snapshots
             WHERE source_type = :source_type AND source_ref = :source_ref ORDER BY rowid DESC'
        );
        $statement->execute(['source_type' => $sourceType, 'source_ref' => $sourceRef]);

        return array_map(
            static fn(array $row): array => [
                'backup_ref' => $row['backup_ref'],
                'source_version' => (int) $row['source_version'],
                'backup_type' => $row['backup_type'],
                'protected' => (bool) $row['protected'],
                'created_at' => $row['created_at'],
            ],
            $statement->fetchAll()
        );
    }

    /**
     * @param array<string, mixed> $options
     * @return array{authorized: bool, status: string, error: ?string}
     */
    private function checkAuthorization(string $operation, string $resourceRef, array $options): array
    {
        if ($this->authorization === null) {
            return ['authorized' => true, 'status' => 'not_applicable', 'error' => null];
        }

        $result = $this->authorization->authorize(
            $options['identity_ref'] ?? '',
            $options['permission_ref'] ?? 'backup:' . $operation,
            $operation,
            $resourceRef,
            $options['correlation_id'] ?? ('correlation_' . bin2hex(random_bytes(8)))
        );

        if ($result['authorized']) {
            return ['authorized' => true, 'status' => 'granted', 'error' => null];
        }

        return ['authorized' => false, 'status' => 'denied', 'error' => sprintf('Authorization for "%s" on "%s" was denied.', $operation, $resourceRef)];
    }

    /**
     * @return array{backup_ref: ?string, source_type: string, source_ref: string, source_version: ?int, outcome: string, error: ?string}
     */
    private function rejectOperation(string $operation, ?string $backupRef, string $sourceType, string $sourceRef, string $authorizationStatus, string $error): array
    {
        $this->recordOperation($operation, $backupRef, $sourceType, $sourceRef, $authorizationStatus, 'not_applicable', 'rejected', $error);

        return ['backup_ref' => null, 'source_type' => $sourceType, 'source_ref' => $sourceRef, 'source_version' => null, 'outcome' => 'rejected', 'error' => $error];
    }

    private function recordOperation(
        string $operation,
        ?string $backupRef,
        ?string $sourceType,
        ?string $sourceRef,
        string $authorizationStatus,
        string $integrityStatus,
        string $outcome,
        ?string $error
    ): void {
        $operationRef = 'backup_operation_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO backup_operations (
                operation_ref, operation, backup_ref, source_type, source_ref,
                authorization_status, governance_status, integrity_status, outcome, error, created_at
            ) VALUES (
                :operation_ref, :operation, :backup_ref, :source_type, :source_ref,
                :authorization_status, :governance_status, :integrity_status, :outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'operation_ref' => $operationRef,
            'operation' => $operation,
            'backup_ref' => $backupRef,
            'source_type' => $sourceType,
            'source_ref' => $sourceRef,
            'authorization_status' => $authorizationStatus,
            'governance_status' => 'ungoverned',
            'integrity_status' => $integrityStatus,
            'outcome' => $outcome,
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'backup.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['operation_ref' => $operationRef, 'backup_ref' => $backupRef, 'operation' => $operation]
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchBackup(string $backupRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM backup_snapshots WHERE backup_ref = :backup_ref');
        $statement->execute(['backup_ref' => $backupRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function operation(string $operationRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM backup_operations WHERE operation_ref = :operation_ref');
        $statement->execute(['operation_ref' => $operationRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare('SELECT * FROM backup_operations ORDER BY rowid DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
