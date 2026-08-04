<?php

declare(strict_types=1);

namespace SquirrelForge\Storage;

use Closure;
use DateInterval;
use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\AuthorizationManagerInterface;

/**
 * Moves real content held in SqliteDocumentStorage and SqliteObjectStorage
 * into governed long-term retention, and retrieves or disposes of it
 * again, per 37_STORAGE/ARCHIVE-STORAGE.md.
 *
 * archive() captures real current content the same way
 * SqliteBackupManager::captureContent() does -- this is not a
 * standalone snapshot store either. The distinction from Backup Manager
 * is what happens next: a backup exists to be restored after failure
 * and is pruned by simple retention count; an archived record exists
 * because its active lifecycle has already ended, and its lifetime is
 * governed by a retention period and disposal authorization instead.
 *
 * "Must never dispose of a record before its retention period has
 * elapsed" and "must never dispose... without governance disposal
 * authorization" are both real, enforced gates on dispose(): it refuses
 * with `retention_not_elapsed` while `disposal_eligible_at` is still in
 * the future, and refuses with `authorization_required` when no
 * non-empty governance authorization reference is supplied -- the same
 * "risk acceptance requires a real reference" shape
 * SqliteVulnerabilityManager::acceptRisk() already established.
 * Disposal never deletes the archive record itself: it purges the
 * content and checksum and marks the record `disposed`, so "record
 * disposal history" stays queryable against the same archive_ref
 * indefinitely, matching this spec's own Audit Requirements rather than
 * losing the record's existence entirely.
 *
 * retrieve() recomputes and compares the checksum before returning
 * content, the same fail-closed integrity check
 * SqliteBackupManager::restore() performs, catching tampering or
 * bit-rot between archive and retrieval.
 *
 * Authorization delegates to AuthorizationManagerInterface, genuinely
 * skipped when not injected. Governance status is the fixed constant
 * `ungoverned` for the same reason established across this layer: no
 * policy engine composes into this class to produce a real per-record
 * decision -- the retention period and disposal authorization reference
 * are supplied by the caller as real inputs instead.
 */
final class SqliteArchiveStorage
{
    private const SOURCE_TYPES = ['document', 'object'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteDocumentStorage $documents = null,
        private readonly ?SqliteObjectStorage $objects = null,
        private readonly ?AuthorizationManagerInterface $authorization = null,
        private readonly ?Closure $clock = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS archived_records (
                archive_ref TEXT PRIMARY KEY,
                source_type TEXT NOT NULL,
                source_ref TEXT NOT NULL,
                source_version INTEGER NOT NULL,
                content_blob TEXT,
                content_encoding TEXT,
                checksum TEXT,
                access_restriction_level TEXT NOT NULL,
                retention_days INTEGER NOT NULL,
                archived_at TEXT NOT NULL,
                disposal_eligible_at TEXT NOT NULL,
                disposed INTEGER NOT NULL DEFAULT 0,
                disposed_at TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS archive_operations (
                operation_id TEXT PRIMARY KEY,
                operation TEXT NOT NULL,
                archive_ref TEXT,
                source_type TEXT,
                source_ref TEXT,
                permission_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                integrity_status TEXT NOT NULL,
                outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{access_restriction_level?: string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $options
     * @return array{outcome: string, archive_ref: ?string, error: ?string}
     */
    public function archive(string $sourceType, string $sourceRef, int $retentionDays, array $options = []): array
    {
        if (!in_array($sourceType, self::SOURCE_TYPES, true)) {
            return $this->rejectArchive($sourceType, $sourceRef, 'not_applicable', sprintf('Unknown source type "%s".', $sourceType));
        }

        if ($retentionDays < 1) {
            return $this->rejectArchive($sourceType, $sourceRef, 'not_applicable', 'Retention period must be at least one day.');
        }

        $authorization = $this->checkAuthorization('archive', $sourceRef, $options);

        if (!$authorization['authorized']) {
            return $this->rejectArchive($sourceType, $sourceRef, $authorization['status'], $authorization['error']);
        }

        $capture = $this->captureContent($sourceType, $sourceRef);

        if ($capture['error'] !== null) {
            return $this->rejectArchive($sourceType, $sourceRef, $authorization['status'], $capture['error']);
        }

        $archiveRef = 'archive_' . bin2hex(random_bytes(12));
        $now = $this->now();
        $disposalEligibleAt = $now->add(new DateInterval('P' . $retentionDays . 'D'));

        $statement = $this->database->prepare(
            'INSERT INTO archived_records (
                archive_ref, source_type, source_ref, source_version, content_blob, content_encoding,
                checksum, access_restriction_level, retention_days, archived_at, disposal_eligible_at, disposed, disposed_at
            ) VALUES (
                :archive_ref, :source_type, :source_ref, :source_version, :content_blob, :content_encoding,
                :checksum, :access_restriction_level, :retention_days, :archived_at, :disposal_eligible_at, 0, NULL
            )'
        );
        $statement->execute([
            'archive_ref' => $archiveRef,
            'source_type' => $sourceType,
            'source_ref' => $sourceRef,
            'source_version' => $capture['version'],
            'content_blob' => $capture['blob'],
            'content_encoding' => $capture['encoding'],
            'checksum' => hash('sha256', $capture['blob']),
            'access_restriction_level' => $options['access_restriction_level'] ?? 'standard',
            'retention_days' => $retentionDays,
            'archived_at' => $now->format(DATE_RFC3339),
            'disposal_eligible_at' => $disposalEligibleAt->format(DATE_RFC3339),
        ]);

        $this->recordOperation('archive', $archiveRef, $sourceType, $sourceRef, $authorization['status'], 'verified', 'archived', null);

        return ['outcome' => 'archived', 'archive_ref' => $archiveRef, 'error' => null];
    }

    /**
     * @param array{identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $context
     * @return array{found: bool, content: mixed, source_type: ?string, source_ref: ?string, error: ?string}
     */
    public function retrieve(string $archiveRef, array $context = []): array
    {
        $record = $this->fetch($archiveRef);

        if ($record === null || (int) $record['disposed'] === 1) {
            $error = $record === null ? sprintf('Unknown archive "%s".', $archiveRef) : sprintf('Archive "%s" has been disposed.', $archiveRef);
            $this->recordOperation('retrieve', $archiveRef, $record['source_type'] ?? null, $record['source_ref'] ?? null, 'not_applicable', 'not_applicable', 'not_found', $error);

            return ['found' => false, 'content' => null, 'source_type' => null, 'source_ref' => null, 'error' => $error];
        }

        $authorization = $this->checkAuthorization('retrieve', $record['source_ref'], $context);

        if (!$authorization['authorized']) {
            $this->recordOperation('retrieve', $archiveRef, $record['source_type'], $record['source_ref'], $authorization['status'], 'not_applicable', 'rejected', $authorization['error']);

            return ['found' => false, 'content' => null, 'source_type' => $record['source_type'], 'source_ref' => $record['source_ref'], 'error' => $authorization['error']];
        }

        if (!hash_equals((string) $record['checksum'], hash('sha256', (string) $record['content_blob']))) {
            $error = sprintf('Archive "%s" failed integrity verification; the stored snapshot does not match its recorded checksum.', $archiveRef);
            $this->recordOperation('retrieve', $archiveRef, $record['source_type'], $record['source_ref'], $authorization['status'], 'failed', 'corrupted', $error);

            return ['found' => false, 'content' => null, 'source_type' => $record['source_type'], 'source_ref' => $record['source_ref'], 'error' => $error];
        }

        $this->recordOperation('retrieve', $archiveRef, $record['source_type'], $record['source_ref'], $authorization['status'], 'verified', 'retrieved', null);

        $content = $record['content_encoding'] === 'json'
            ? json_decode((string) $record['content_blob'], true, flags: JSON_THROW_ON_ERROR)
            : $record['content_blob'];

        return ['found' => true, 'content' => $content, 'source_type' => $record['source_type'], 'source_ref' => $record['source_ref'], 'error' => null];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function dispose(string $archiveRef, string $governanceAuthorizationReference): array
    {
        $record = $this->fetch($archiveRef);

        if ($record === null) {
            $error = sprintf('Unknown archive "%s".', $archiveRef);
            $this->recordOperation('dispose', $archiveRef, null, null, 'not_applicable', 'not_applicable', 'not_found', $error);

            return ['outcome' => 'not_found', 'error' => $error];
        }

        if ((int) $record['disposed'] === 1) {
            return ['outcome' => 'already_disposed', 'error' => sprintf('Archive "%s" has already been disposed.', $archiveRef)];
        }

        if ($governanceAuthorizationReference === '') {
            $error = 'Disposal requires a non-empty governance authorization reference.';
            $this->recordOperation('dispose', $archiveRef, $record['source_type'], $record['source_ref'], 'not_applicable', 'verified', 'authorization_required', $error);

            return ['outcome' => 'authorization_required', 'error' => $error];
        }

        if ($this->now() < new DateTimeImmutable($record['disposal_eligible_at'])) {
            $error = sprintf('Archive "%s" is not eligible for disposal until %s.', $archiveRef, $record['disposal_eligible_at']);
            $this->recordOperation('dispose', $archiveRef, $record['source_type'], $record['source_ref'], 'not_applicable', 'verified', 'retention_not_elapsed', $error);

            return ['outcome' => 'retention_not_elapsed', 'error' => $error];
        }

        $statement = $this->database->prepare(
            'UPDATE archived_records SET content_blob = NULL, checksum = NULL, disposed = 1, disposed_at = :disposed_at WHERE archive_ref = :archive_ref'
        );
        $statement->execute(['disposed_at' => $this->now()->format(DATE_RFC3339), 'archive_ref' => $archiveRef]);

        $this->recordOperation('dispose', $archiveRef, $record['source_type'], $record['source_ref'], 'not_applicable', 'verified', 'disposed', null);

        return ['outcome' => 'disposed', 'error' => null];
    }

    /**
     * Metadata only, never content -- safe to call regardless of
     * disposal state or authorization.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $archiveRef): ?array
    {
        $record = $this->fetch($archiveRef);

        if ($record === null) {
            return null;
        }

        unset($record['content_blob'], $record['checksum']);
        $record['disposed'] = (bool) $record['disposed'];

        return $record;
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
     * @return array{outcome: string, archive_ref: ?string, error: string}
     */
    private function rejectArchive(string $sourceType, string $sourceRef, string $permissionStatus, string $error): array
    {
        $this->recordOperation('archive', null, $sourceType, $sourceRef, $permissionStatus, 'not_applicable', 'rejected', $error);

        return ['outcome' => 'rejected', 'archive_ref' => null, 'error' => $error];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{authorized: bool, status: string, error: ?string}
     */
    private function checkAuthorization(string $operation, string $resourceRef, array $context): array
    {
        if ($this->authorization === null) {
            return ['authorized' => true, 'status' => 'not_applicable', 'error' => null];
        }

        $result = $this->authorization->authorize(
            $context['identity_ref'] ?? '',
            $context['permission_ref'] ?? 'archive:' . $operation,
            $operation,
            $resourceRef,
            $context['correlation_id'] ?? ('correlation_' . bin2hex(random_bytes(8)))
        );

        if ($result['authorized']) {
            return ['authorized' => true, 'status' => 'granted', 'error' => null];
        }

        return ['authorized' => false, 'status' => 'denied', 'error' => sprintf('Authorization for "%s" on "%s" was denied.', $operation, $resourceRef)];
    }

    private function recordOperation(
        string $operation,
        ?string $archiveRef,
        ?string $sourceType,
        ?string $sourceRef,
        string $permissionStatus,
        string $integrityStatus,
        string $outcome,
        ?string $error
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO archive_operations (
                operation_id, operation, archive_ref, source_type, source_ref,
                permission_status, governance_status, integrity_status, outcome, error, created_at
            ) VALUES (
                :operation_id, :operation, :archive_ref, :source_type, :source_ref,
                :permission_status, :governance_status, :integrity_status, :outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'operation_id' => 'archive_op_' . bin2hex(random_bytes(12)),
            'operation' => $operation,
            'archive_ref' => $archiveRef,
            'source_type' => $sourceType,
            'source_ref' => $sourceRef,
            'permission_status' => $permissionStatus,
            'governance_status' => 'ungoverned',
            'integrity_status' => $integrityStatus,
            'outcome' => $outcome,
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $archiveRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM archived_records WHERE archive_ref = :archive_ref');
        $statement->execute(['archive_ref' => $archiveRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare('SELECT * FROM archive_operations ORDER BY rowid DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock !== null ? ($this->clock)() : new DateTimeImmutable();
    }
}
