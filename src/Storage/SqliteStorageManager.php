<?php

declare(strict_types=1);

namespace SquirrelForge\Storage;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Governance\SqliteVersionManager;

/**
 * Top-level coordinator for the Storage Layer, per
 * 37_STORAGE/STORAGE-MANAGER.md, mirroring how SqliteDataManager,
 * SqliteResilienceManager, and SqliteCommunicationManager coordinate
 * their own layers.
 *
 * This is a genuinely distinct spec from `37_STORAGE/DATA-MANAGER.md`
 * (already real as `SqliteDataManager`), not a duplicate: both name
 * Object/Document/Cache/Backup Storage among their own "Coordination
 * Responsibilities", so routing to those four real components is
 * repeated here rather than delegated to SqliteDataManager, the same
 * way this codebase never merges two separately-specified coordinators
 * into one class elsewhere. What this class adds beyond
 * SqliteDataManager is genuinely new composition that spec never had:
 * Vector Storage (store/retrieve/search) and the real Governance-layer
 * `SqliteVersionManager` (SqliteDataManager's own docblock explicitly
 * says Document/Object Storage merely "stand in for" a Version Manager
 * it doesn't compose -- this class composes the real one).
 * register_version deliberately does not fire automatically on every
 * store -- it is a separate, caller-invoked operation, since a
 * platform-governance version record is a distinct thing from the
 * version history Object/Document Storage already keep internally for
 * their own updateVersion() calls, and firing it unconditionally on
 * every write would be a surprising, uninvited side effect.
 *
 * Of the ten components the spec's own "Coordination Responsibilities"
 * lists, four have no real implementation to route to (Key-Value
 * Storage, Archive Storage, Storage Replication, Storage Governance),
 * so operations that would need them are rejected as unsupported
 * rather than routed somewhere invented, matching SqliteDataManager's
 * own established precedent for the same situation.
 *
 * This class does not perform its own authorization check: each
 * coordinated component already has its own optional
 * AuthorizationManagerInterface wiring, and duplicating that check here
 * would be redundant, not more correct -- `permission_status` is
 * recorded as the fixed value `delegated`. Governance status is the
 * fixed constant `ungoverned` since Storage Governance has no code.
 */
final class SqliteStorageManager
{
    private const REQUIRED_FIELDS = [
        'store_object' => ['name', 'type', 'contents'],
        'store_document' => ['type', 'title', 'content'],
        'store_vector' => ['collection_id', 'vector'],
        'retrieve_object' => ['object_ref'],
        'retrieve_document' => ['document_ref'],
        'retrieve_vector' => ['vector_ref'],
        'search_vector' => ['collection_id', 'query_vector'],
        'cache_put' => ['key', 'value', 'ttl_seconds'],
        'cache_get' => ['key'],
        'cache_forget' => ['key'],
        'backup' => ['source_type', 'source_ref'],
        'restore' => ['backup_ref'],
        'register_version' => ['record_id', 'record_type', 'content'],
    ];

    private const UNSUPPORTED_OPERATIONS = ['key_value_store', 'archive', 'replicate', 'governance_review'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteObjectStorage $objects = null,
        private readonly ?SqliteDocumentStorage $documents = null,
        private readonly ?SqliteVectorStorage $vectors = null,
        private readonly ?CacheManager $cache = null,
        private readonly ?SqliteBackupManager $backups = null,
        private readonly ?SqliteVersionManager $versions = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS storage_operations (
                storage_operation_id TEXT PRIMARY KEY,
                operation TEXT NOT NULL,
                storage_type TEXT NOT NULL,
                requesting_component TEXT,
                storage_destination TEXT,
                permission_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options forwarded to whichever component handles this operation
     * @return array{storage_operation_id: string, operation: string, storage_type: string, storage_destination: ?string, permission_status: string, governance_status: string, outcome: string, error: ?string, result: mixed}
     */
    public function coordinate(string $operation, array $payload, array $options = []): array
    {
        if (in_array($operation, self::UNSUPPORTED_OPERATIONS, true)) {
            return $this->finish($operation, 'unsupported', $options, null, 'escalated', sprintf('Storage operation "%s" has no implemented component.', $operation));
        }

        if (!isset(self::REQUIRED_FIELDS[$operation])) {
            return $this->finish($operation, 'unknown', $options, null, 'rejected', sprintf('Unknown storage operation "%s".', $operation));
        }

        $missingField = $this->firstMissingField($operation, $payload);

        if ($missingField !== null) {
            return $this->finish($operation, 'invalid', $options, null, 'rejected', sprintf('Missing required field "%s" for operation "%s".', $missingField, $operation));
        }

        return match ($operation) {
            'store_object' => $this->coordinateStoreObject($payload, $options),
            'store_document' => $this->coordinateStoreDocument($payload, $options),
            'store_vector' => $this->coordinateStoreVector($payload, $options),
            'retrieve_object' => $this->coordinateRetrieveObject($payload, $options),
            'retrieve_document' => $this->coordinateRetrieveDocument($payload, $options),
            'retrieve_vector' => $this->coordinateRetrieveVector($payload, $options),
            'search_vector' => $this->coordinateSearchVector($payload, $options),
            'cache_put' => $this->coordinateCachePut($payload),
            'cache_get' => $this->coordinateCacheGet($payload),
            'cache_forget' => $this->coordinateCacheForget($payload),
            'backup' => $this->coordinateBackup($payload, $options),
            'restore' => $this->coordinateRestore($payload, $options),
            'register_version' => $this->coordinateRegisterVersion($payload, $options),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function firstMissingField(string $operation, array $payload): ?string
    {
        foreach (self::REQUIRED_FIELDS[$operation] as $field) {
            if (!array_key_exists($field, $payload)) {
                return $field;
            }
        }

        return null;
    }

    private function coordinateStoreObject(array $payload, array $options): array
    {
        if ($this->objects === null) {
            return $this->finish('store_object', 'object', $options, null, 'rejected', 'Object Storage is not configured.');
        }

        $result = $this->objects->store($payload['name'], $payload['type'], $payload['contents'], $options);

        return $this->finish('store_object', 'object', $options, $result['object_ref'] ?? null, $result['outcome'], $result['error'], $result);
    }

    private function coordinateStoreDocument(array $payload, array $options): array
    {
        if ($this->documents === null) {
            return $this->finish('store_document', 'document', $options, null, 'rejected', 'Document Storage is not configured.');
        }

        $result = $this->documents->store($payload['type'], $payload['title'], $payload['content'], $options);

        return $this->finish('store_document', 'document', $options, $result['document_ref'] ?? null, $result['outcome'], $result['error'], $result);
    }

    private function coordinateStoreVector(array $payload, array $options): array
    {
        if ($this->vectors === null) {
            return $this->finish('store_vector', 'vector', $options, null, 'rejected', 'Vector Storage is not configured.');
        }

        $result = $this->vectors->store($payload['collection_id'], $payload['vector'], $options);

        return $this->finish('store_vector', 'vector', $options, $result['vector_ref'] ?? null, $result['outcome'], $result['error'], $result);
    }

    private function coordinateRetrieveObject(array $payload, array $options): array
    {
        if ($this->objects === null) {
            return $this->finish('retrieve_object', 'object', $options, null, 'rejected', 'Object Storage is not configured.');
        }

        $result = $this->objects->retrieve($payload['object_ref'], $payload['version'] ?? null, $options);

        return $this->finish('retrieve_object', 'object', $options, $payload['object_ref'], $result['found'] ? 'retrieved' : 'not_found', $result['error'], $result);
    }

    private function coordinateRetrieveDocument(array $payload, array $options): array
    {
        if ($this->documents === null) {
            return $this->finish('retrieve_document', 'document', $options, null, 'rejected', 'Document Storage is not configured.');
        }

        $result = $this->documents->retrieve($payload['document_ref'], $payload['version'] ?? null, $options);

        return $this->finish('retrieve_document', 'document', $options, $payload['document_ref'], $result['found'] ? 'retrieved' : 'not_found', $result['error'], $result);
    }

    private function coordinateRetrieveVector(array $payload, array $options): array
    {
        if ($this->vectors === null) {
            return $this->finish('retrieve_vector', 'vector', $options, null, 'rejected', 'Vector Storage is not configured.');
        }

        $result = $this->vectors->retrieve($payload['vector_ref'], $options);

        return $this->finish('retrieve_vector', 'vector', $options, $payload['vector_ref'], $result['found'] ? 'retrieved' : 'not_found', $result['error'], $result);
    }

    private function coordinateSearchVector(array $payload, array $options): array
    {
        if ($this->vectors === null) {
            return $this->finish('search_vector', 'vector', $options, null, 'rejected', 'Vector Storage is not configured.');
        }

        $result = $this->vectors->search($payload['collection_id'], $payload['query_vector'], $options);

        return $this->finish('search_vector', 'vector', $options, $payload['collection_id'], $result['outcome'] ?? 'searched', $result['error'] ?? null, $result);
    }

    private function coordinateCachePut(array $payload): array
    {
        if ($this->cache === null) {
            return $this->finish('cache_put', 'cache', [], null, 'rejected', 'Cache Manager is not configured.');
        }

        $this->cache->set($payload['key'], $payload['value'], $payload['ttl_seconds'], $payload['sliding'] ?? false);

        return $this->finish('cache_put', 'cache', [], $payload['key'], 'cached', null, ['key' => $payload['key']]);
    }

    private function coordinateCacheGet(array $payload): array
    {
        if ($this->cache === null) {
            return $this->finish('cache_get', 'cache', [], null, 'rejected', 'Cache Manager is not configured.');
        }

        $hit = $this->cache->has($payload['key']);
        $value = $hit ? $this->cache->get($payload['key']) : null;

        return $this->finish('cache_get', 'cache', [], $payload['key'], $hit ? 'hit' : 'miss', null, ['key' => $payload['key'], 'value' => $value]);
    }

    private function coordinateCacheForget(array $payload): array
    {
        if ($this->cache === null) {
            return $this->finish('cache_forget', 'cache', [], null, 'rejected', 'Cache Manager is not configured.');
        }

        $existed = $this->cache->has($payload['key']);
        $this->cache->invalidate($payload['key']);

        return $this->finish('cache_forget', 'cache', [], $payload['key'], $existed ? 'forgotten' : 'not_found', null, ['key' => $payload['key']]);
    }

    private function coordinateBackup(array $payload, array $options): array
    {
        if ($this->backups === null) {
            return $this->finish('backup', 'backup', $options, null, 'rejected', 'Backup Manager is not configured.');
        }

        $result = $this->backups->backup($payload['source_type'], $payload['source_ref'], $options);

        return $this->finish('backup', 'backup', $options, $result['backup_ref'] ?? null, $result['outcome'], $result['error'], $result);
    }

    private function coordinateRestore(array $payload, array $options): array
    {
        if ($this->backups === null) {
            return $this->finish('restore', 'backup', $options, null, 'rejected', 'Backup Manager is not configured.');
        }

        $result = $this->backups->restore($payload['backup_ref'], $options);

        return $this->finish('restore', 'backup', $options, $payload['backup_ref'], $result['outcome'], $result['error'], $result);
    }

    private function coordinateRegisterVersion(array $payload, array $options): array
    {
        if ($this->versions === null) {
            return $this->finish('register_version', 'version', $options, null, 'rejected', 'Version Manager is not configured.');
        }

        $result = $this->versions->createVersion($payload['record_id'], $payload['record_type'], $payload['content'], $options);

        return $this->finish('register_version', 'version', $options, $result['version_id'], $result['outcome'], $result['error'], $result);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{storage_operation_id: string, operation: string, storage_type: string, storage_destination: ?string, permission_status: string, governance_status: string, outcome: string, error: ?string, result: mixed}
     */
    private function finish(
        string $operation,
        string $storageType,
        array $options,
        ?string $storageDestination,
        string $outcome,
        ?string $error,
        mixed $result = null
    ): array {
        $operationId = 'storage_op_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO storage_operations (
                storage_operation_id, operation, storage_type, requesting_component, storage_destination,
                permission_status, governance_status, final_outcome, error, created_at
            ) VALUES (
                :id, :operation, :storage_type, :requesting_component, :storage_destination,
                :permission_status, :governance_status, :final_outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'id' => $operationId,
            'operation' => $operation,
            'storage_type' => $storageType,
            'requesting_component' => $options['requesting_component'] ?? null,
            'storage_destination' => $storageDestination,
            'permission_status' => 'delegated',
            'governance_status' => 'ungoverned',
            'final_outcome' => $outcome,
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'storage.finished',
            new DateTimeImmutable(),
            self::class,
            ['storage_operation_id' => $operationId, 'operation' => $operation, 'outcome' => $outcome]
        ));

        return [
            'storage_operation_id' => $operationId,
            'operation' => $operation,
            'storage_type' => $storageType,
            'storage_destination' => $storageDestination,
            'permission_status' => 'delegated',
            'governance_status' => 'ungoverned',
            'outcome' => $outcome,
            'error' => $error,
            'result' => $result,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function operation(string $operationId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM storage_operations WHERE storage_operation_id = :id');
        $statement->execute(['id' => $operationId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare('SELECT * FROM storage_operations ORDER BY rowid DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
