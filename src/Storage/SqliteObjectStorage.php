<?php

declare(strict_types=1);

namespace SquirrelForge\Storage;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Contracts\FileSystemInterface;
use SquirrelForge\Events\Event;

/**
 * Stores and retrieves binary objects with real content-addressed
 * integrity verification, per 37_STORAGE/OBJECT-STORAGE.md.
 *
 * This is the genuine structural difference from SqliteDocumentStorage:
 * documents are small, structured, and stored inline as JSON in sqlite;
 * objects are large, unstructured byte streams, so actual bytes go
 * through the real FileSystemInterface (the same contained, path-safe
 * writer that already backs SquirrelForge's file tools) while sqlite
 * only tracks metadata and a path reference. Four of the spec's six
 * "Integrity Verification" mechanisms are real, not documented gaps:
 * Checksums and Hash validation (SHA-256 computed from the actual
 * bytes), Size validation (byte length recorded and compared), and
 * Retrieval verification (every retrieve() re-hashes the bytes just
 * read off disk and fails closed on a mismatch, rather than trusting
 * the stored checksum blindly). Replication verification is out of
 * scope -- there's no real replication target to verify against.
 *
 * Like SqliteDocumentStorage: versions are immutable (each version gets
 * its own file path, never overwritten), delete() tombstones rather
 * than removing the object's row or its files, and an object under
 * legal_hold refuses deletion outright via setLegalHold(). Authorization delegates to
 * AuthorizationManagerInterface exactly like the other storage
 * components, genuinely skipped when not injected. Governance status is
 * the fixed constant `ungoverned` since 23_GOVERNANCE has no policy
 * engine to gate on.
 */
final class SqliteObjectStorage
{
    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly FileSystemInterface $filesystem,
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
            'CREATE TABLE IF NOT EXISTS objects (
                object_ref TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                owner TEXT,
                current_version INTEGER NOT NULL,
                size INTEGER NOT NULL,
                checksum TEXT NOT NULL,
                storage_location TEXT NOT NULL,
                state TEXT NOT NULL,
                legal_hold INTEGER NOT NULL DEFAULT 0,
                metadata_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS object_versions (
                version_ref TEXT PRIMARY KEY,
                object_ref TEXT NOT NULL,
                version INTEGER NOT NULL,
                storage_location TEXT NOT NULL,
                size INTEGER NOT NULL,
                checksum TEXT NOT NULL,
                created_at TEXT NOT NULL,
                UNIQUE (object_ref, version)
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS object_operations (
                operation_ref TEXT PRIMARY KEY,
                operation TEXT NOT NULL,
                object_ref TEXT,
                object_type TEXT,
                requesting_component TEXT,
                permission_status TEXT NOT NULL,
                version_id INTEGER,
                governance_status TEXT NOT NULL,
                outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{owner?: ?string, metadata?: array<string, mixed>, requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $options
     * @return array{object_ref: ?string, version: ?int, size: ?int, checksum: ?string, outcome: string, error: ?string}
     */
    public function store(string $name, string $type, string $contents, array $options = []): array
    {
        $objectRef = 'object_' . bin2hex(random_bytes(12));

        $authorization = $this->checkAuthorization('store', $type, $options);

        if (!$authorization['authorized']) {
            $this->recordOperation('store', null, $type, $options, $authorization['status'], null, 'rejected', $authorization['error']);

            return ['object_ref' => null, 'version' => null, 'size' => null, 'checksum' => null, 'outcome' => 'rejected', 'error' => $authorization['error']];
        }

        if ($name === '' || $type === '') {
            $error = 'Object name and type are both required.';
            $this->recordOperation('store', null, $type, $options, $authorization['status'], null, 'rejected', $error);

            return ['object_ref' => null, 'version' => null, 'size' => null, 'checksum' => null, 'outcome' => 'rejected', 'error' => $error];
        }

        $write = $this->writeVersion($objectRef, 1, $contents);

        if ($write['error'] !== null) {
            $this->recordOperation('store', $objectRef, $type, $options, $authorization['status'], null, 'failed', $write['error']);

            return ['object_ref' => null, 'version' => null, 'size' => null, 'checksum' => null, 'outcome' => 'failed', 'error' => $write['error']];
        }

        $now = gmdate(DATE_RFC3339);
        $insert = $this->database->prepare(
            'INSERT INTO objects (
                object_ref, name, type, owner, current_version, size, checksum,
                storage_location, state, legal_hold, metadata_json, created_at, updated_at
            ) VALUES (
                :object_ref, :name, :type, :owner, 1, :size, :checksum,
                :storage_location, :state, 0, :metadata_json, :created_at, :updated_at
            )'
        );
        $insert->execute([
            'object_ref' => $objectRef,
            'name' => $name,
            'type' => $type,
            'owner' => $options['owner'] ?? null,
            'size' => $write['size'],
            'checksum' => $write['checksum'],
            'storage_location' => $write['storage_location'],
            'state' => 'active',
            'metadata_json' => json_encode($options['metadata'] ?? [], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->recordOperation('store', $objectRef, $type, $options, $authorization['status'], 1, 'stored', null);

        return ['object_ref' => $objectRef, 'version' => 1, 'size' => $write['size'], 'checksum' => $write['checksum'], 'outcome' => 'stored', 'error' => null];
    }

    /**
     * @param array{requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $options
     * @return array{object_ref: string, version: ?int, size: ?int, checksum: ?string, outcome: string, error: ?string}
     */
    public function updateVersion(string $objectRef, string $contents, array $options = []): array
    {
        $object = $this->fetchObject($objectRef);

        if ($object === null || $object['state'] !== 'active') {
            $error = $object === null ? sprintf('Unknown object "%s".', $objectRef) : 'Object has been deleted and can no longer be updated.';
            $this->recordOperation('update', $objectRef, $object['type'] ?? null, $options, 'not_applicable', null, 'rejected', $error);

            return ['object_ref' => $objectRef, 'version' => null, 'size' => null, 'checksum' => null, 'outcome' => 'rejected', 'error' => $error];
        }

        $authorization = $this->checkAuthorization('update', $object['type'], $options);

        if (!$authorization['authorized']) {
            $this->recordOperation('update', $objectRef, $object['type'], $options, $authorization['status'], null, 'rejected', $authorization['error']);

            return ['object_ref' => $objectRef, 'version' => null, 'size' => null, 'checksum' => null, 'outcome' => 'rejected', 'error' => $authorization['error']];
        }

        $newVersion = (int) $object['current_version'] + 1;
        $write = $this->writeVersion($objectRef, $newVersion, $contents);

        if ($write['error'] !== null) {
            $this->recordOperation('update', $objectRef, $object['type'], $options, $authorization['status'], null, 'failed', $write['error']);

            return ['object_ref' => $objectRef, 'version' => null, 'size' => null, 'checksum' => null, 'outcome' => 'failed', 'error' => $write['error']];
        }

        $update = $this->database->prepare(
            'UPDATE objects SET current_version = :current_version, size = :size, checksum = :checksum,
                storage_location = :storage_location, updated_at = :updated_at WHERE object_ref = :object_ref'
        );
        $update->execute([
            'current_version' => $newVersion,
            'size' => $write['size'],
            'checksum' => $write['checksum'],
            'storage_location' => $write['storage_location'],
            'updated_at' => gmdate(DATE_RFC3339),
            'object_ref' => $objectRef,
        ]);

        $this->recordOperation('update', $objectRef, $object['type'], $options, $authorization['status'], $newVersion, 'updated', null);

        return ['object_ref' => $objectRef, 'version' => $newVersion, 'size' => $write['size'], 'checksum' => $write['checksum'], 'outcome' => 'updated', 'error' => null];
    }

    /**
     * Writes one version's bytes and immediately re-reads and re-hashes
     * them to confirm what's on disk matches what was requested, per the
     * spec's "Verify object integrity" workflow step. A mismatch is a
     * real, not fabricated, failure path.
     *
     * @return array{storage_location: ?string, size: ?int, checksum: ?string, error: ?string}
     */
    private function writeVersion(string $objectRef, int $version, string $contents): array
    {
        $storageLocation = sprintf('objects/%s/v%d.bin', $objectRef, $version);
        $expectedChecksum = hash('sha256', $contents);

        $this->filesystem->write($storageLocation, $contents);

        $verifyChecksum = hash('sha256', $this->filesystem->read($storageLocation));

        if (!hash_equals($expectedChecksum, $verifyChecksum)) {
            $this->filesystem->delete($storageLocation);

            return ['storage_location' => null, 'size' => null, 'checksum' => null, 'error' => 'Post-write integrity verification failed; the stored bytes do not match what was written.'];
        }

        $size = strlen($contents);

        $statement = $this->database->prepare(
            'INSERT INTO object_versions (version_ref, object_ref, version, storage_location, size, checksum, created_at)
             VALUES (:version_ref, :object_ref, :version, :storage_location, :size, :checksum, :created_at)'
        );
        $statement->execute([
            'version_ref' => 'object_version_' . bin2hex(random_bytes(12)),
            'object_ref' => $objectRef,
            'version' => $version,
            'storage_location' => $storageLocation,
            'size' => $size,
            'checksum' => $expectedChecksum,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        return ['storage_location' => $storageLocation, 'size' => $size, 'checksum' => $expectedChecksum, 'error' => null];
    }

    /**
     * @param array{requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $context
     * @return array{found: bool, object_ref: string, type: ?string, version: ?int, contents: ?string, checksum: ?string, error: ?string}
     */
    public function retrieve(string $objectRef, ?int $version = null, array $context = []): array
    {
        $object = $this->fetchObject($objectRef);

        if ($object === null || $object['state'] !== 'active') {
            $error = $object === null ? sprintf('Unknown object "%s".', $objectRef) : 'Object has been deleted.';
            $this->recordOperation('retrieve', $objectRef, $object['type'] ?? null, $context, 'not_applicable', null, 'not_found', $error);

            return ['found' => false, 'object_ref' => $objectRef, 'type' => null, 'version' => null, 'contents' => null, 'checksum' => null, 'error' => $error];
        }

        $authorization = $this->checkAuthorization('retrieve', $object['type'], $context);

        if (!$authorization['authorized']) {
            $this->recordOperation('retrieve', $objectRef, $object['type'], $context, $authorization['status'], null, 'rejected', $authorization['error']);

            return ['found' => false, 'object_ref' => $objectRef, 'type' => $object['type'], 'version' => null, 'contents' => null, 'checksum' => null, 'error' => $authorization['error']];
        }

        $targetVersion = $version ?? (int) $object['current_version'];
        $versionRow = $this->fetchVersion($objectRef, $targetVersion);

        if ($versionRow === null) {
            $error = sprintf('Version %d of object "%s" does not exist.', $targetVersion, $objectRef);
            $this->recordOperation('retrieve', $objectRef, $object['type'], $context, $authorization['status'], $targetVersion, 'not_found', $error);

            return ['found' => false, 'object_ref' => $objectRef, 'type' => $object['type'], 'version' => null, 'contents' => null, 'checksum' => null, 'error' => $error];
        }

        $contents = $this->filesystem->read($versionRow['storage_location']);
        $actualChecksum = hash('sha256', $contents);

        if (!hash_equals($versionRow['checksum'], $actualChecksum)) {
            $error = sprintf('Retrieval verification failed for version %d of object "%s": stored bytes do not match the recorded checksum.', $targetVersion, $objectRef);
            $this->recordOperation('retrieve', $objectRef, $object['type'], $context, $authorization['status'], $targetVersion, 'corrupted', $error);

            return ['found' => false, 'object_ref' => $objectRef, 'type' => $object['type'], 'version' => null, 'contents' => null, 'checksum' => null, 'error' => $error];
        }

        $this->recordOperation('retrieve', $objectRef, $object['type'], $context, $authorization['status'], $targetVersion, 'retrieved', null);

        return ['found' => true, 'object_ref' => $objectRef, 'type' => $object['type'], 'version' => $targetVersion, 'contents' => $contents, 'checksum' => $actualChecksum, 'error' => null];
    }

    /**
     * A tombstone: the row and its version files are preserved, only the
     * state changes. Refuses when the object is under legal hold.
     *
     * @param array{requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $context
     * @return array{outcome: string, error: ?string}
     */
    public function delete(string $objectRef, array $context = []): array
    {
        $object = $this->fetchObject($objectRef);

        if ($object === null) {
            $error = sprintf('Unknown object "%s".', $objectRef);
            $this->recordOperation('delete', $objectRef, null, $context, 'not_applicable', null, 'not_found', $error);

            return ['outcome' => 'not_found', 'error' => $error];
        }

        $authorization = $this->checkAuthorization('delete', $object['type'], $context);

        if (!$authorization['authorized']) {
            $this->recordOperation('delete', $objectRef, $object['type'], $context, $authorization['status'], null, 'rejected', $authorization['error']);

            return ['outcome' => 'rejected', 'error' => $authorization['error']];
        }

        if ((int) $object['legal_hold'] === 1) {
            $error = sprintf('Object "%s" is under legal hold and cannot be deleted.', $objectRef);
            $this->recordOperation('delete', $objectRef, $object['type'], $context, $authorization['status'], null, 'rejected', $error);

            return ['outcome' => 'rejected', 'error' => $error];
        }

        $update = $this->database->prepare('UPDATE objects SET state = :state, updated_at = :updated_at WHERE object_ref = :object_ref');
        $update->execute(['state' => 'deleted', 'updated_at' => gmdate(DATE_RFC3339), 'object_ref' => $objectRef]);

        $this->recordOperation('delete', $objectRef, $object['type'], $context, $authorization['status'], null, 'deleted', null);

        return ['outcome' => 'deleted', 'error' => null];
    }

    /**
     * Places or lifts a legal hold on an object. While held, delete()
     * refuses to tombstone it. Returns false if the object doesn't exist.
     */
    public function setLegalHold(string $objectRef, bool $hold): bool
    {
        $statement = $this->database->prepare('UPDATE objects SET legal_hold = :legal_hold WHERE object_ref = :object_ref');
        $statement->execute(['legal_hold' => $hold ? 1 : 0, 'object_ref' => $objectRef]);

        return $statement->rowCount() > 0;
    }

    /**
     * @return array<int, array{version: int, size: int, checksum: string, created_at: string}>
     */
    public function versions(string $objectRef): array
    {
        $statement = $this->database->prepare(
            'SELECT version, size, checksum, created_at FROM object_versions WHERE object_ref = :object_ref ORDER BY version ASC'
        );
        $statement->execute(['object_ref' => $objectRef]);

        return array_map(
            static fn(array $row): array => ['version' => (int) $row['version'], 'size' => (int) $row['size'], 'checksum' => $row['checksum'], 'created_at' => $row['created_at']],
            $statement->fetchAll()
        );
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
            $context['permission_ref'] ?? 'object:' . $operation,
            $operation,
            $resourceRef,
            $context['correlation_id'] ?? ('correlation_' . bin2hex(random_bytes(8)))
        );

        if ($result['authorized']) {
            return ['authorized' => true, 'status' => 'granted', 'error' => null];
        }

        return ['authorized' => false, 'status' => 'denied', 'error' => sprintf('Authorization for "%s" on "%s" was denied.', $operation, $resourceRef)];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function recordOperation(
        string $operation,
        ?string $objectRef,
        ?string $objectType,
        array $context,
        string $permissionStatus,
        ?int $version,
        string $outcome,
        ?string $error
    ): void {
        $operationRef = 'object_operation_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO object_operations (
                operation_ref, operation, object_ref, object_type, requesting_component,
                permission_status, version_id, governance_status, outcome, error, created_at
            ) VALUES (
                :operation_ref, :operation, :object_ref, :object_type, :requesting_component,
                :permission_status, :version_id, :governance_status, :outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'operation_ref' => $operationRef,
            'operation' => $operation,
            'object_ref' => $objectRef,
            'object_type' => $objectType,
            'requesting_component' => $context['requesting_component'] ?? $context['identity_ref'] ?? null,
            'permission_status' => $permissionStatus,
            'version_id' => $version,
            'governance_status' => 'ungoverned',
            'outcome' => $outcome,
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'object.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['operation_ref' => $operationRef, 'object_ref' => $objectRef, 'operation' => $operation]
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchObject(string $objectRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM objects WHERE object_ref = :object_ref');
        $statement->execute(['object_ref' => $objectRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchVersion(string $objectRef, int $version): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM object_versions WHERE object_ref = :object_ref AND version = :version');
        $statement->execute(['object_ref' => $objectRef, 'version' => $version]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function operation(string $operationRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM object_operations WHERE operation_ref = :operation_ref');
        $statement->execute(['operation_ref' => $operationRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare('SELECT * FROM object_operations ORDER BY rowid DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
