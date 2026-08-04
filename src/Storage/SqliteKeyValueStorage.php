<?php

declare(strict_types=1);

namespace SquirrelForge\Storage;

use PDO;
use SquirrelForge\Contracts\AuthorizationManagerInterface;

/**
 * Stores and retrieves durable key-value records, per
 * 37_STORAGE/KEY-VALUE-STORAGE.md.
 *
 * "Store and Update are deliberately separate operations... never
 * silently overwrite... never creates a new record on an Update
 * request" is a real, enforced distinction: store() refuses with
 * `exists` when the (namespace, key) pair is already present, and
 * update() refuses with `not_found` when it is not -- the same
 * store-vs-update separation `SqliteDocumentStorage` already
 * establishes for documents, applied here to simple key-addressed
 * values instead.
 *
 * Namespace isolation is real, not advisory: every lookup, list, and
 * uniqueness check is scoped to `(namespace, key)`, so two namespaces
 * may hold the same key with entirely independent values and neither
 * can see or overwrite the other's record.
 *
 * delete() is a genuine row removal rather than a tombstone (unlike
 * `SqliteDocumentStorage::delete()`): the spec names no retention or
 * legal-hold concept of its own for durable key-value records --
 * governed long-term retention is `37_STORAGE/ARCHIVE-STORAGE.md`'s
 * concern, not this one's -- so "must never return a deleted record"
 * is upheld by the record no longer existing at all.
 *
 * Authorization delegates to AuthorizationManagerInterface exactly
 * like SqliteObjectStorage/SqliteDocumentStorage: genuinely skipped
 * (`not_applicable`), not silently treated as passing, when not wired
 * in. Governance status is the fixed constant `ungoverned` for the
 * same reason established across this layer: no policy engine composes
 * into this class to produce a real per-record decision.
 */
final class SqliteKeyValueStorage
{
    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?AuthorizationManagerInterface $authorization = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS key_value_records (
                namespace TEXT NOT NULL,
                key TEXT NOT NULL,
                value_json TEXT NOT NULL,
                version INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                PRIMARY KEY (namespace, key)
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS key_value_operations (
                operation_id TEXT PRIMARY KEY,
                operation TEXT NOT NULL,
                namespace TEXT NOT NULL,
                key TEXT,
                version INTEGER,
                permission_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param mixed $value
     * @param array{identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $context
     * @return array{outcome: string, version: ?int, error: ?string}
     */
    public function store(string $namespace, string $key, mixed $value, array $context = []): array
    {
        $authorization = $this->checkAuthorization('store', $namespace, $context);

        if (!$authorization['authorized']) {
            $this->recordOperation('store', $namespace, $key, null, $authorization['status'], 'rejected', $authorization['error']);

            return ['outcome' => 'rejected', 'version' => null, 'error' => $authorization['error']];
        }

        if ($this->fetch($namespace, $key) !== null) {
            $error = sprintf('Key "%s" already exists in namespace "%s"; use update() instead.', $key, $namespace);
            $this->recordOperation('store', $namespace, $key, null, $authorization['status'], 'exists', $error);

            return ['outcome' => 'exists', 'version' => null, 'error' => $error];
        }

        $now = gmdate(DATE_RFC3339);
        $statement = $this->database->prepare(
            'INSERT INTO key_value_records (namespace, key, value_json, version, created_at, updated_at)
             VALUES (:namespace, :key, :value_json, 1, :created_at, :updated_at)'
        );
        $statement->execute([
            'namespace' => $namespace,
            'key' => $key,
            'value_json' => json_encode($value, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->recordOperation('store', $namespace, $key, 1, $authorization['status'], 'stored', null);

        return ['outcome' => 'stored', 'version' => 1, 'error' => null];
    }

    /**
     * @param mixed $value
     * @param array{identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $context
     * @return array{outcome: string, version: ?int, error: ?string}
     */
    public function update(string $namespace, string $key, mixed $value, array $context = []): array
    {
        $authorization = $this->checkAuthorization('update', $namespace, $context);

        if (!$authorization['authorized']) {
            $this->recordOperation('update', $namespace, $key, null, $authorization['status'], 'rejected', $authorization['error']);

            return ['outcome' => 'rejected', 'version' => null, 'error' => $authorization['error']];
        }

        $record = $this->fetch($namespace, $key);

        if ($record === null) {
            $error = sprintf('Key "%s" does not exist in namespace "%s"; use store() instead.', $key, $namespace);
            $this->recordOperation('update', $namespace, $key, null, $authorization['status'], 'not_found', $error);

            return ['outcome' => 'not_found', 'version' => null, 'error' => $error];
        }

        $newVersion = (int) $record['version'] + 1;
        $statement = $this->database->prepare(
            'UPDATE key_value_records SET value_json = :value_json, version = :version, updated_at = :updated_at
             WHERE namespace = :namespace AND key = :key'
        );
        $statement->execute([
            'value_json' => json_encode($value, JSON_THROW_ON_ERROR),
            'version' => $newVersion,
            'updated_at' => gmdate(DATE_RFC3339),
            'namespace' => $namespace,
            'key' => $key,
        ]);

        $this->recordOperation('update', $namespace, $key, $newVersion, $authorization['status'], 'updated', null);

        return ['outcome' => 'updated', 'version' => $newVersion, 'error' => null];
    }

    /**
     * @param array{identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $context
     * @return array{found: bool, value: mixed, version: ?int, error: ?string}
     */
    public function retrieve(string $namespace, string $key, array $context = []): array
    {
        $authorization = $this->checkAuthorization('retrieve', $namespace, $context);

        if (!$authorization['authorized']) {
            $this->recordOperation('retrieve', $namespace, $key, null, $authorization['status'], 'rejected', $authorization['error']);

            return ['found' => false, 'value' => null, 'version' => null, 'error' => $authorization['error']];
        }

        $record = $this->fetch($namespace, $key);

        if ($record === null) {
            $error = sprintf('Key "%s" does not exist in namespace "%s".', $key, $namespace);
            $this->recordOperation('retrieve', $namespace, $key, null, $authorization['status'], 'not_found', $error);

            return ['found' => false, 'value' => null, 'version' => null, 'error' => $error];
        }

        $this->recordOperation('retrieve', $namespace, $key, (int) $record['version'], $authorization['status'], 'retrieved', null);

        return ['found' => true, 'value' => json_decode((string) $record['value_json'], true, flags: JSON_THROW_ON_ERROR), 'version' => (int) $record['version'], 'error' => null];
    }

    /**
     * @param array{identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $context
     * @return array{outcome: string, error: ?string}
     */
    public function delete(string $namespace, string $key, array $context = []): array
    {
        $authorization = $this->checkAuthorization('delete', $namespace, $context);

        if (!$authorization['authorized']) {
            $this->recordOperation('delete', $namespace, $key, null, $authorization['status'], 'rejected', $authorization['error']);

            return ['outcome' => 'rejected', 'error' => $authorization['error']];
        }

        if ($this->fetch($namespace, $key) === null) {
            $error = sprintf('Key "%s" does not exist in namespace "%s".', $key, $namespace);
            $this->recordOperation('delete', $namespace, $key, null, $authorization['status'], 'not_found', $error);

            return ['outcome' => 'not_found', 'error' => $error];
        }

        $statement = $this->database->prepare('DELETE FROM key_value_records WHERE namespace = :namespace AND key = :key');
        $statement->execute(['namespace' => $namespace, 'key' => $key]);

        $this->recordOperation('delete', $namespace, $key, null, $authorization['status'], 'deleted', null);

        return ['outcome' => 'deleted', 'error' => null];
    }

    /**
     * @return array<int, string>
     */
    public function list(string $namespace, ?string $prefix = null): array
    {
        if ($prefix === null) {
            $statement = $this->database->prepare('SELECT key FROM key_value_records WHERE namespace = :namespace ORDER BY key ASC');
            $statement->execute(['namespace' => $namespace]);
        } else {
            $statement = $this->database->prepare("SELECT key FROM key_value_records WHERE namespace = :namespace AND key LIKE :prefix ESCAPE '\\' ORDER BY key ASC");
            $statement->execute(['namespace' => $namespace, 'prefix' => str_replace(['%', '_'], ['\%', '\_'], $prefix) . '%']);
        }

        return array_column($statement->fetchAll(), 'key');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $namespace, string $key): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM key_value_records WHERE namespace = :namespace AND key = :key');
        $statement->execute(['namespace' => $namespace, 'key' => $key]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $context
     * @return array{authorized: bool, status: string, error: ?string}
     */
    private function checkAuthorization(string $operation, string $namespace, array $context): array
    {
        if ($this->authorization === null) {
            return ['authorized' => true, 'status' => 'not_applicable', 'error' => null];
        }

        $result = $this->authorization->authorize(
            $context['identity_ref'] ?? '',
            $context['permission_ref'] ?? 'key_value:' . $operation,
            $operation,
            $namespace,
            $context['correlation_id'] ?? ('correlation_' . bin2hex(random_bytes(8)))
        );

        if ($result['authorized']) {
            return ['authorized' => true, 'status' => 'granted', 'error' => null];
        }

        return ['authorized' => false, 'status' => 'denied', 'error' => sprintf('Authorization for "%s" in namespace "%s" was denied.', $operation, $namespace)];
    }

    private function recordOperation(
        string $operation,
        string $namespace,
        ?string $key,
        ?int $version,
        string $permissionStatus,
        string $outcome,
        ?string $error
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO key_value_operations (
                operation_id, operation, namespace, key, version, permission_status, governance_status, outcome, error, created_at
            ) VALUES (
                :operation_id, :operation, :namespace, :key, :version, :permission_status, :governance_status, :outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'operation_id' => 'kv_op_' . bin2hex(random_bytes(12)),
            'operation' => $operation,
            'namespace' => $namespace,
            'key' => $key,
            'version' => $version,
            'permission_status' => $permissionStatus,
            'governance_status' => 'ungoverned',
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
        $statement = $this->database->prepare('SELECT * FROM key_value_operations ORDER BY rowid DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
