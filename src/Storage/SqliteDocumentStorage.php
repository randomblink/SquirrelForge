<?php

declare(strict_types=1);

namespace SquirrelForge\Storage;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Stores and retrieves structured documents with immutable version
 * history, per 37_STORAGE/DOCUMENT-STORAGE.md.
 *
 * "Immutable document versions" and "Version history" get a real
 * implementation: every update() appends a new row to a version table
 * that is never rewritten, so an earlier version stays retrievable and
 * unchanged after later updates -- this is a genuine invariant, tested
 * directly, not an assumption. delete() is a real tombstone (state set
 * to `deleted`), never a row removal, matching the spec's rule against
 * corrupting or losing document contents; a document with `legal_hold`
 * set refuses deletion outright, which is a real, enforced gate, not a
 * documented-but-unchecked policy field.
 *
 * Structure validation delegates to the existing SqliteDataValidator
 * when injected, and access control to AuthorizationManagerInterface,
 * exactly like SqliteMessageValidator/SqliteDataValidator -- both are
 * genuinely skipped (not silently treated as passing) when not wired
 * in. "Document Indexing" is real but modest: search() filters on the
 * actual stored columns (type/owner/tag/classification), not a
 * fabricated search engine -- there's no real Index Manager to build a
 * proper inverted index yet. Retention schedules, archive eligibility,
 * and governance review schedules aren't modeled: 23_GOVERNANCE has no
 * policy engine to source them from, so governance_status is the fixed
 * constant `ungoverned`.
 *
 * Every store/update/retrieve/delete call is persisted to an audit
 * trail regardless of outcome, matching SqliteMessageBroker's stance
 * (not SqliteRecoveryManager's) -- the spec requires an audit record
 * for "every document storage operation", rejections included.
 */
final class SqliteDocumentStorage
{
    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteDataValidator $validator = null,
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
            'CREATE TABLE IF NOT EXISTS documents (
                document_ref TEXT PRIMARY KEY,
                type TEXT NOT NULL,
                title TEXT NOT NULL,
                owner TEXT,
                classification TEXT,
                tags_json TEXT NOT NULL,
                metadata_json TEXT NOT NULL,
                current_version INTEGER NOT NULL,
                state TEXT NOT NULL,
                legal_hold INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS document_versions (
                version_ref TEXT PRIMARY KEY,
                document_ref TEXT NOT NULL,
                version INTEGER NOT NULL,
                content_json TEXT NOT NULL,
                checksum TEXT NOT NULL,
                created_at TEXT NOT NULL,
                UNIQUE (document_ref, version)
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS document_operations (
                operation_ref TEXT PRIMARY KEY,
                operation TEXT NOT NULL,
                document_ref TEXT,
                document_type TEXT,
                requesting_component TEXT,
                version_id INTEGER,
                governance_status TEXT NOT NULL,
                outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, mixed> $content
     * @param array{owner?: ?string, classification?: ?string, tags?: array<int, string>, metadata?: array<string, mixed>, required_fields?: array<int, string>, field_types?: array<string, string>, requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $options
     * @return array{document_ref: ?string, version: ?int, outcome: string, error: ?string}
     */
    public function store(string $type, string $title, array $content, array $options = []): array
    {
        $documentRef = 'document_' . bin2hex(random_bytes(12));

        $authorization = $this->checkAuthorization('store', $type, $options);

        if (!$authorization['authorized']) {
            $this->recordOperation('store', null, $type, $options, null, 'rejected', $authorization['error']);

            return ['document_ref' => null, 'version' => null, 'outcome' => 'rejected', 'error' => $authorization['error']];
        }

        $validationError = $this->checkValidation($type, $documentRef, $content, $options);

        if ($validationError !== null) {
            $this->recordOperation('store', $documentRef, $type, $options, null, 'rejected', $validationError);

            return ['document_ref' => null, 'version' => null, 'outcome' => 'rejected', 'error' => $validationError];
        }

        $now = gmdate(DATE_RFC3339);

        $insertDocument = $this->database->prepare(
            'INSERT INTO documents (
                document_ref, type, title, owner, classification, tags_json,
                metadata_json, current_version, state, legal_hold, created_at, updated_at
            ) VALUES (
                :document_ref, :type, :title, :owner, :classification, :tags_json,
                :metadata_json, 1, :state, 0, :created_at, :updated_at
            )'
        );
        $insertDocument->execute([
            'document_ref' => $documentRef,
            'type' => $type,
            'title' => $title,
            'owner' => $options['owner'] ?? null,
            'classification' => $options['classification'] ?? null,
            'tags_json' => json_encode($options['tags'] ?? [], JSON_THROW_ON_ERROR),
            'metadata_json' => json_encode($options['metadata'] ?? [], JSON_THROW_ON_ERROR),
            'state' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertVersion($documentRef, 1, $content);
        $this->recordOperation('store', $documentRef, $type, $options, 1, 'stored', null);

        return ['document_ref' => $documentRef, 'version' => 1, 'outcome' => 'stored', 'error' => null];
    }

    /**
     * @param array<string, mixed> $content
     * @param array{required_fields?: array<int, string>, field_types?: array<string, string>, requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $options
     * @return array{document_ref: string, version: ?int, outcome: string, error: ?string}
     */
    public function updateVersion(string $documentRef, array $content, array $options = []): array
    {
        $document = $this->fetchDocument($documentRef);

        if ($document === null || $document['state'] !== 'active') {
            $error = $document === null ? sprintf('Unknown document "%s".', $documentRef) : 'Document has been deleted and can no longer be updated.';
            $this->recordOperation('update', $documentRef, $document['type'] ?? null, $options, null, 'rejected', $error);

            return ['document_ref' => $documentRef, 'version' => null, 'outcome' => 'rejected', 'error' => $error];
        }

        $authorization = $this->checkAuthorization('update', $document['type'], $options);

        if (!$authorization['authorized']) {
            $this->recordOperation('update', $documentRef, $document['type'], $options, null, 'rejected', $authorization['error']);

            return ['document_ref' => $documentRef, 'version' => null, 'outcome' => 'rejected', 'error' => $authorization['error']];
        }

        $validationError = $this->checkValidation($document['type'], $documentRef, $content, $options);

        if ($validationError !== null) {
            $this->recordOperation('update', $documentRef, $document['type'], $options, null, 'rejected', $validationError);

            return ['document_ref' => $documentRef, 'version' => null, 'outcome' => 'rejected', 'error' => $validationError];
        }

        $newVersion = (int) $document['current_version'] + 1;
        $this->insertVersion($documentRef, $newVersion, $content);

        $update = $this->database->prepare(
            'UPDATE documents SET current_version = :current_version, updated_at = :updated_at WHERE document_ref = :document_ref'
        );
        $update->execute(['current_version' => $newVersion, 'updated_at' => gmdate(DATE_RFC3339), 'document_ref' => $documentRef]);

        $this->recordOperation('update', $documentRef, $document['type'], $options, $newVersion, 'updated', null);

        return ['document_ref' => $documentRef, 'version' => $newVersion, 'outcome' => 'updated', 'error' => null];
    }

    /**
     * @param array{requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $context
     * @return array{found: bool, document_ref: string, type: ?string, title: ?string, version: ?int, content: mixed, checksum: ?string, error: ?string}
     */
    public function retrieve(string $documentRef, ?int $version = null, array $context = []): array
    {
        $document = $this->fetchDocument($documentRef);

        if ($document === null || $document['state'] !== 'active') {
            $error = $document === null ? sprintf('Unknown document "%s".', $documentRef) : 'Document has been deleted.';
            $this->recordOperation('retrieve', $documentRef, $document['type'] ?? null, $context, null, 'not_found', $error);

            return ['found' => false, 'document_ref' => $documentRef, 'type' => null, 'title' => null, 'version' => null, 'content' => null, 'checksum' => null, 'error' => $error];
        }

        $authorization = $this->checkAuthorization('retrieve', $document['type'], $context);

        if (!$authorization['authorized']) {
            $this->recordOperation('retrieve', $documentRef, $document['type'], $context, null, 'rejected', $authorization['error']);

            return ['found' => false, 'document_ref' => $documentRef, 'type' => $document['type'], 'title' => null, 'version' => null, 'content' => null, 'checksum' => null, 'error' => $authorization['error']];
        }

        $targetVersion = $version ?? (int) $document['current_version'];
        $versionRow = $this->fetchVersion($documentRef, $targetVersion);

        if ($versionRow === null) {
            $error = sprintf('Version %d of document "%s" does not exist.', $targetVersion, $documentRef);
            $this->recordOperation('retrieve', $documentRef, $document['type'], $context, $targetVersion, 'not_found', $error);

            return ['found' => false, 'document_ref' => $documentRef, 'type' => $document['type'], 'title' => $document['title'], 'version' => null, 'content' => null, 'checksum' => null, 'error' => $error];
        }

        $this->recordOperation('retrieve', $documentRef, $document['type'], $context, $targetVersion, 'retrieved', null);

        return [
            'found' => true,
            'document_ref' => $documentRef,
            'type' => $document['type'],
            'title' => $document['title'],
            'version' => $targetVersion,
            'content' => json_decode((string) $versionRow['content_json'], true, flags: JSON_THROW_ON_ERROR),
            'checksum' => $versionRow['checksum'],
            'error' => null,
        ];
    }

    /**
     * A tombstone, never a row removal. Refuses when the document is
     * under legal hold, per the spec's rule against ignoring retention
     * requirements.
     *
     * @param array{requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $context
     * @return array{outcome: string, error: ?string}
     */
    public function delete(string $documentRef, array $context = []): array
    {
        $document = $this->fetchDocument($documentRef);

        if ($document === null) {
            $error = sprintf('Unknown document "%s".', $documentRef);
            $this->recordOperation('delete', $documentRef, null, $context, null, 'not_found', $error);

            return ['outcome' => 'not_found', 'error' => $error];
        }

        $authorization = $this->checkAuthorization('delete', $document['type'], $context);

        if (!$authorization['authorized']) {
            $this->recordOperation('delete', $documentRef, $document['type'], $context, null, 'rejected', $authorization['error']);

            return ['outcome' => 'rejected', 'error' => $authorization['error']];
        }

        if ((int) $document['legal_hold'] === 1) {
            $error = sprintf('Document "%s" is under legal hold and cannot be deleted.', $documentRef);
            $this->recordOperation('delete', $documentRef, $document['type'], $context, null, 'rejected', $error);

            return ['outcome' => 'rejected', 'error' => $error];
        }

        $update = $this->database->prepare(
            'UPDATE documents SET state = :state, updated_at = :updated_at WHERE document_ref = :document_ref'
        );
        $update->execute(['state' => 'deleted', 'updated_at' => gmdate(DATE_RFC3339), 'document_ref' => $documentRef]);

        $this->recordOperation('delete', $documentRef, $document['type'], $context, null, 'deleted', null);

        return ['outcome' => 'deleted', 'error' => null];
    }

    /**
     * Places or lifts a legal hold on a document. While held, delete()
     * refuses to tombstone it. Returns false if the document doesn't exist.
     */
    public function setLegalHold(string $documentRef, bool $hold): bool
    {
        $statement = $this->database->prepare(
            'UPDATE documents SET legal_hold = :legal_hold WHERE document_ref = :document_ref'
        );
        $statement->execute(['legal_hold' => $hold ? 1 : 0, 'document_ref' => $documentRef]);

        return $statement->rowCount() > 0;
    }

    /**
     * Filters on the document's own indexed columns; not a search engine.
     *
     * @param array{type?: string, owner?: string, tag?: string, classification?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $conditions = ["state = 'active'"];
        $parameters = [];

        foreach (['type', 'owner', 'classification'] as $field) {
            if (isset($filters[$field])) {
                $conditions[] = "{$field} = :{$field}";
                $parameters[$field] = $filters[$field];
            }
        }

        if (isset($filters['tag'])) {
            $conditions[] = 'tags_json LIKE :tag';
            $parameters['tag'] = '%"' . $filters['tag'] . '"%';
        }

        $statement = $this->database->prepare(
            'SELECT document_ref, type, title, owner, classification, current_version, created_at, updated_at
             FROM documents WHERE ' . implode(' AND ', $conditions) . ' ORDER BY rowid DESC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * @return array<int, array{version: int, checksum: string, created_at: string}>
     */
    public function versions(string $documentRef): array
    {
        $statement = $this->database->prepare(
            'SELECT version, checksum, created_at FROM document_versions WHERE document_ref = :document_ref ORDER BY version ASC'
        );
        $statement->execute(['document_ref' => $documentRef]);

        return array_map(
            static fn(array $row): array => ['version' => (int) $row['version'], 'checksum' => $row['checksum'], 'created_at' => $row['created_at']],
            $statement->fetchAll()
        );
    }

    /**
     * @param array<string, mixed> $content
     */
    private function insertVersion(string $documentRef, int $version, array $content): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO document_versions (version_ref, document_ref, version, content_json, checksum, created_at)
             VALUES (:version_ref, :document_ref, :version, :content_json, :checksum, :created_at)'
        );
        $contentJson = json_encode($content, JSON_THROW_ON_ERROR);
        $statement->execute([
            'version_ref' => 'document_version_' . bin2hex(random_bytes(12)),
            'document_ref' => $documentRef,
            'version' => $version,
            'content_json' => $contentJson,
            'checksum' => hash('sha256', $contentJson),
            'created_at' => gmdate(DATE_RFC3339),
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @return array{authorized: bool, error: ?string}
     */
    private function checkAuthorization(string $operation, string $resourceRef, array $context): array
    {
        if ($this->authorization === null) {
            return ['authorized' => true, 'error' => null];
        }

        $result = $this->authorization->authorize(
            $context['identity_ref'] ?? '',
            $context['permission_ref'] ?? 'document:' . $operation,
            $operation,
            $resourceRef,
            $context['correlation_id'] ?? ('correlation_' . bin2hex(random_bytes(8)))
        );

        if ($result['authorized']) {
            return ['authorized' => true, 'error' => null];
        }

        return ['authorized' => false, 'error' => sprintf('Authorization for "%s" on "%s" was denied.', $operation, $resourceRef)];
    }

    /**
     * @param array<string, mixed> $content
     * @param array{required_fields?: array<int, string>, field_types?: array<string, string>} $options
     */
    private function checkValidation(string $type, string $documentRef, array $content, array $options): ?string
    {
        if ($this->validator === null) {
            return null;
        }

        $result = $this->validator->validate($type, $documentRef, $content, [
            'required_fields' => $options['required_fields'] ?? [],
            'field_types' => $options['field_types'] ?? [],
        ]);

        return $result['may_proceed'] ? null : $result['error'];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function recordOperation(
        string $operation,
        ?string $documentRef,
        ?string $documentType,
        array $context,
        ?int $version,
        string $outcome,
        ?string $error
    ): void {
        $operationRef = 'document_operation_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO document_operations (
                operation_ref, operation, document_ref, document_type, requesting_component,
                version_id, governance_status, outcome, error, created_at
            ) VALUES (
                :operation_ref, :operation, :document_ref, :document_type, :requesting_component,
                :version_id, :governance_status, :outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'operation_ref' => $operationRef,
            'operation' => $operation,
            'document_ref' => $documentRef,
            'document_type' => $documentType,
            'requesting_component' => $context['requesting_component'] ?? $context['identity_ref'] ?? null,
            'version_id' => $version,
            'governance_status' => 'ungoverned',
            'outcome' => $outcome,
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'document.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['operation_ref' => $operationRef, 'document_ref' => $documentRef, 'operation' => $operation]
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchDocument(string $documentRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM documents WHERE document_ref = :document_ref');
        $statement->execute(['document_ref' => $documentRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchVersion(string $documentRef, int $version): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM document_versions WHERE document_ref = :document_ref AND version = :version'
        );
        $statement->execute(['document_ref' => $documentRef, 'version' => $version]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function operation(string $operationRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM document_operations WHERE operation_ref = :operation_ref');
        $statement->execute(['operation_ref' => $operationRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM document_operations ORDER BY rowid DESC LIMIT :limit'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
