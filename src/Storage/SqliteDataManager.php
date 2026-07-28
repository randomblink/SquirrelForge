<?php

declare(strict_types=1);

namespace SquirrelForge\Storage;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Top-level coordinator for the Data Layer, per
 * 37_STORAGE/DATA-MANAGER.md, mirroring how SqliteResilienceManager and
 * SqliteCommunicationManager coordinate their own layers.
 *
 * Of the nine components the spec lists under "Coordination
 * Responsibilities", four have real code to route to: Data Validator
 * (validate), Document/Object Storage together standing in for Storage
 * Manager + Retrieval Manager + Version Manager (store/retrieve/
 * update_version, split by target since the two stores have different
 * shapes), Cache Manager (cache_put/cache_get/cache_forget), and Backup
 * Manager (backup/restore). Index Manager, Data Governance, and Data
 * Monitor have no implementation -- no real search index, no
 * 23_GOVERNANCE policy engine, no monitoring sink distinct from what
 * each component already logs on its own -- so operations that would
 * need them escalate rather than being routed somewhere invented, per
 * the spec's rule against bypassing governance or permitting
 * unauthorized access via an invented path.
 *
 * This class does not perform its own authorization check: each
 * coordinated component already has its own optional
 * AuthorizationManagerInterface wiring (SqliteDocumentStorage,
 * SqliteObjectStorage, SqliteBackupManager, and indirectly
 * SqliteDataValidator), and duplicating that check here would just be
 * redundant, not more correct -- authorization_status is recorded as
 * the fixed value `delegated` to make that explicit rather than
 * implying this layer enforces it itself. Governance status is the
 * fixed constant `ungoverned` since 23_GOVERNANCE has no code.
 */
final class SqliteDataManager
{
    private const REQUIRED_FIELDS = [
        'validate' => ['record_type', 'record_id', 'data'],
        'store_document' => ['type', 'title', 'content'],
        'store_object' => ['name', 'type', 'contents'],
        'retrieve_document' => ['document_ref'],
        'retrieve_object' => ['object_ref'],
        'update_document' => ['document_ref', 'content'],
        'update_object' => ['object_ref', 'contents'],
        'backup' => ['source_type', 'source_ref'],
        'restore' => ['backup_ref'],
        'cache_put' => ['key', 'value', 'ttl_seconds'],
        'cache_get' => ['key'],
        'cache_forget' => ['key'],
    ];

    private const UNSUPPORTED_OPERATIONS = ['search_index', 'index_update', 'governance_review', 'monitor_report'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteDocumentStorage $documents = null,
        private readonly ?SqliteObjectStorage $objects = null,
        private readonly ?CacheManager $cache = null,
        private readonly ?SqliteBackupManager $backups = null,
        private readonly ?SqliteDataValidator $validator = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS data_operations (
                operation_ref TEXT PRIMARY KEY,
                operation TEXT NOT NULL,
                requesting_component TEXT,
                target_component TEXT NOT NULL,
                authorization_status TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                validation_status TEXT NOT NULL,
                outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options forwarded to whichever component handles this operation
     * @return array{operation_ref: string, operation: string, target_component: string, authorization_status: string, governance_status: string, validation_status: string, outcome: string, error: ?string, result: mixed}
     */
    public function coordinate(string $operation, array $payload, array $options = []): array
    {
        if (in_array($operation, self::UNSUPPORTED_OPERATIONS, true)) {
            return $this->persist($operation, 'none', 'not_applicable', 'escalated', sprintf('Data operation "%s" has no implemented component.', $operation), $options, null);
        }

        if (!isset(self::REQUIRED_FIELDS[$operation])) {
            return $this->persist($operation, 'none', 'invalid', 'rejected', sprintf('Unknown data operation "%s".', $operation), $options, null);
        }

        $missingField = $this->firstMissingField($operation, $payload);

        if ($missingField !== null) {
            return $this->persist($operation, 'none', 'invalid', 'rejected', sprintf('Missing required field "%s" for operation "%s".', $missingField, $operation), $options, null);
        }

        return match ($operation) {
            'validate' => $this->coordinateValidate($payload),
            'store_document' => $this->coordinateStoreDocument($payload, $options),
            'store_object' => $this->coordinateStoreObject($payload, $options),
            'retrieve_document' => $this->coordinateRetrieveDocument($payload, $options),
            'retrieve_object' => $this->coordinateRetrieveObject($payload, $options),
            'update_document' => $this->coordinateUpdateDocument($payload, $options),
            'update_object' => $this->coordinateUpdateObject($payload, $options),
            'backup' => $this->coordinateBackup($payload, $options),
            'restore' => $this->coordinateRestore($payload, $options),
            'cache_put' => $this->coordinateCachePut($payload),
            'cache_get' => $this->coordinateCacheGet($payload),
            'cache_forget' => $this->coordinateCacheForget($payload),
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

    /**
     * @param array<string, mixed> $payload
     */
    private function coordinateValidate(array $payload): array
    {
        if ($this->validator === null) {
            return $this->persist('validate', 'data_validator', 'not_applicable', 'rejected', 'Data Validator is not configured.', [], null);
        }

        $result = $this->validator->validate($payload['record_type'], $payload['record_id'], $payload['data'], $payload['rules'] ?? []);

        return $this->persist('validate', 'data_validator', $result['may_proceed'] ? 'validated' : 'invalid', $result['result'], $result['error'], [], $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateStoreDocument(array $payload, array $options): array
    {
        if ($this->documents === null) {
            return $this->persist('store_document', 'document_storage', 'not_applicable', 'rejected', 'Document Storage is not configured.', $options, null);
        }

        $result = $this->documents->store($payload['type'], $payload['title'], $payload['content'], $options);

        return $this->persist('store_document', 'document_storage', 'validated', $result['outcome'], $result['error'], $options, $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateStoreObject(array $payload, array $options): array
    {
        if ($this->objects === null) {
            return $this->persist('store_object', 'object_storage', 'not_applicable', 'rejected', 'Object Storage is not configured.', $options, null);
        }

        $result = $this->objects->store($payload['name'], $payload['type'], $payload['contents'], $options);

        return $this->persist('store_object', 'object_storage', 'validated', $result['outcome'], $result['error'], $options, $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateRetrieveDocument(array $payload, array $options): array
    {
        if ($this->documents === null) {
            return $this->persist('retrieve_document', 'document_storage', 'not_applicable', 'rejected', 'Document Storage is not configured.', $options, null);
        }

        $result = $this->documents->retrieve($payload['document_ref'], $payload['version'] ?? null, $options);

        return $this->persist('retrieve_document', 'document_storage', 'validated', $result['found'] ? 'retrieved' : 'not_found', $result['error'], $options, $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateRetrieveObject(array $payload, array $options): array
    {
        if ($this->objects === null) {
            return $this->persist('retrieve_object', 'object_storage', 'not_applicable', 'rejected', 'Object Storage is not configured.', $options, null);
        }

        $result = $this->objects->retrieve($payload['object_ref'], $payload['version'] ?? null, $options);

        return $this->persist('retrieve_object', 'object_storage', 'validated', $result['found'] ? 'retrieved' : 'not_found', $result['error'], $options, $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateUpdateDocument(array $payload, array $options): array
    {
        if ($this->documents === null) {
            return $this->persist('update_document', 'document_storage', 'not_applicable', 'rejected', 'Document Storage is not configured.', $options, null);
        }

        $result = $this->documents->updateVersion($payload['document_ref'], $payload['content'], $options);

        return $this->persist('update_document', 'document_storage', 'validated', $result['outcome'], $result['error'], $options, $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateUpdateObject(array $payload, array $options): array
    {
        if ($this->objects === null) {
            return $this->persist('update_object', 'object_storage', 'not_applicable', 'rejected', 'Object Storage is not configured.', $options, null);
        }

        $result = $this->objects->updateVersion($payload['object_ref'], $payload['contents'], $options);

        return $this->persist('update_object', 'object_storage', 'validated', $result['outcome'], $result['error'], $options, $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateBackup(array $payload, array $options): array
    {
        if ($this->backups === null) {
            return $this->persist('backup', 'backup_manager', 'not_applicable', 'rejected', 'Backup Manager is not configured.', $options, null);
        }

        $result = $this->backups->backup($payload['source_type'], $payload['source_ref'], $options);

        return $this->persist('backup', 'backup_manager', 'validated', $result['outcome'], $result['error'], $options, $result);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function coordinateRestore(array $payload, array $options): array
    {
        if ($this->backups === null) {
            return $this->persist('restore', 'backup_manager', 'not_applicable', 'rejected', 'Backup Manager is not configured.', $options, null);
        }

        $result = $this->backups->restore($payload['backup_ref'], $options);

        return $this->persist('restore', 'backup_manager', 'validated', $result['outcome'], $result['error'], $options, $result);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function coordinateCachePut(array $payload): array
    {
        if ($this->cache === null) {
            return $this->persist('cache_put', 'cache_manager', 'not_applicable', 'rejected', 'Cache Manager is not configured.', [], null);
        }

        $this->cache->set($payload['key'], $payload['value'], $payload['ttl_seconds'], $payload['sliding'] ?? false);

        return $this->persist('cache_put', 'cache_manager', 'validated', 'cached', null, [], ['key' => $payload['key']]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function coordinateCacheGet(array $payload): array
    {
        if ($this->cache === null) {
            return $this->persist('cache_get', 'cache_manager', 'not_applicable', 'rejected', 'Cache Manager is not configured.', [], null);
        }

        $hit = $this->cache->has($payload['key']);
        $value = $hit ? $this->cache->get($payload['key']) : null;

        return $this->persist('cache_get', 'cache_manager', 'validated', $hit ? 'hit' : 'miss', null, [], ['key' => $payload['key'], 'value' => $value]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function coordinateCacheForget(array $payload): array
    {
        if ($this->cache === null) {
            return $this->persist('cache_forget', 'cache_manager', 'not_applicable', 'rejected', 'Cache Manager is not configured.', [], null);
        }

        $existed = $this->cache->has($payload['key']);
        $this->cache->invalidate($payload['key']);

        return $this->persist('cache_forget', 'cache_manager', 'validated', $existed ? 'forgotten' : 'not_found', null, [], ['key' => $payload['key']]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function persist(
        string $operation,
        string $targetComponent,
        string $validationStatus,
        string $outcome,
        ?string $error,
        array $options,
        mixed $result
    ): array {
        $operationRef = 'data_operation_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO data_operations (
                operation_ref, operation, requesting_component, target_component,
                authorization_status, governance_status, validation_status, outcome, error, created_at
            ) VALUES (
                :operation_ref, :operation, :requesting_component, :target_component,
                :authorization_status, :governance_status, :validation_status, :outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'operation_ref' => $operationRef,
            'operation' => $operation,
            'requesting_component' => $options['requesting_component'] ?? $options['identity_ref'] ?? null,
            'target_component' => $targetComponent,
            'authorization_status' => 'delegated',
            'governance_status' => 'ungoverned',
            'validation_status' => $validationStatus,
            'outcome' => $outcome,
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'data.finished',
            new DateTimeImmutable(),
            self::class,
            ['operation_ref' => $operationRef, 'operation' => $operation, 'outcome' => $outcome]
        ));

        return [
            'operation_ref' => $operationRef,
            'operation' => $operation,
            'target_component' => $targetComponent,
            'authorization_status' => 'delegated',
            'governance_status' => 'ungoverned',
            'validation_status' => $validationStatus,
            'outcome' => $outcome,
            'error' => $error,
            'result' => $result,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function operation(string $operationRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM data_operations WHERE operation_ref = :operation_ref');
        $statement->execute(['operation_ref' => $operationRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare('SELECT * FROM data_operations ORDER BY rowid DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
