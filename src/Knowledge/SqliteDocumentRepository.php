<?php

declare(strict_types=1);

namespace SquirrelForge\Knowledge;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Storage\SqliteDocumentStorage;

/**
 * Owns knowledge-facing document references, metadata, and their links to
 * storage/version/citation references, per
 * 25_KNOWLEDGE/DOCUMENT-REPOSITORY.md.
 *
 * This spec's permission boundary is more restrictive than every other
 * component built so far, and the design follows it precisely rather
 * than reusing the usual pattern:
 *
 * - No dedicated audit table. The spec explicitly says this component
 *   must not "own general logging, audit, storage, or observability
 *   infrastructure" -- unlike SqliteDocumentStorage et al., there is no
 *   *_operations table here. Activity is only ever announced through the
 *   optional EventBusInterface ("requests observability/audit records
 *   through owning infrastructure"), never recorded by this class itself.
 * - No AuthorizationManagerInterface dependency. The spec says this
 *   component must "consume authorization results" but must not "make
 *   authorization decisions" -- so readMetadata() takes an
 *   already-computed authorization result as a parameter rather than
 *   calling an authorizer itself, the opposite of how
 *   SqliteMessageValidator/SqliteDocumentStorage/etc. are wired.
 * - Raw content is never touched. registerReference() only *verifies*
 *   that a supplied storage_reference resolves to a real document in the
 *   injected SqliteDocumentStorage (a real, non-fabricated check against
 *   real content) -- it never reads or returns the content itself, and
 *   syncStatusFromStorage() is the one real cross-check against storage
 *   state ("archive... based on storage status"): if the underlying
 *   stored document has been deleted, the reference is auto-archived.
 *   Manual archiveReference()/restoreReference() remain independent,
 *   caller-initiated knowledge-layer decisions.
 * - search() is metadata-only filtering on real columns (type/status/
 *   owner), explicitly not semantic search, which the spec assigns to
 *   25_KNOWLEDGE/SEMANTIC-SEARCH.md.
 *
 * Citation references and version references are verified, not just
 * recorded, when a `SqliteKnowledgeVersioning` and/or
 * `SqliteCitationManager` are injected: `25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md`
 * and `25_KNOWLEDGE/CITATION-MANAGER.md` had no implementation when this
 * class was first written, so both were accepted as opaque strings --
 * the same "coordinator predates its own specialist" resolution this
 * codebase's other coordinators have already gone through. registerReference(),
 * attachVersionReference(), and attachCitation() each check that the
 * supplied reference actually resolves to a real version/citation
 * record before accepting it, genuinely skipped (not silently treated
 * as passing) when the corresponding component isn't configured -- the
 * same optional-composition stance already established for
 * storage_reference against SqliteDocumentStorage.
 */
final class SqliteDocumentRepository
{
    private const DOCUMENT_TYPES = [
        'specification', 'policy', 'workflow', 'knowledge',
        'design', 'validation', 'audit_reference', 'user_documentation',
    ];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteDocumentStorage $documentStorage = null,
        private readonly ?SqliteKnowledgeVersioning $versioning = null,
        private readonly ?SqliteCitationManager $citations = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS knowledge_documents (
                document_id TEXT PRIMARY KEY,
                title TEXT NOT NULL,
                type TEXT NOT NULL,
                storage_reference TEXT,
                version_reference TEXT,
                citation_references_json TEXT NOT NULL,
                status TEXT NOT NULL,
                owner TEXT,
                protected INTEGER NOT NULL DEFAULT 0,
                metadata_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{storage_reference?: ?string, version_reference?: ?string, owner?: ?string, protected?: bool, metadata?: array<string, mixed>} $options
     * @return array{found: bool, document_id: ?string, error: ?string}
     */
    public function registerReference(string $title, string $type, array $options = []): array
    {
        if (!in_array($type, self::DOCUMENT_TYPES, true)) {
            return ['found' => false, 'document_id' => null, 'error' => sprintf('Unknown document type "%s".', $type)];
        }

        $storageReference = $options['storage_reference'] ?? null;

        if ($storageReference !== null && $this->documentStorage !== null) {
            $resolved = $this->documentStorage->retrieve($storageReference);

            if (!$resolved['found']) {
                return ['found' => false, 'document_id' => null, 'error' => sprintf('Storage reference "%s" does not resolve to a stored document.', $storageReference)];
            }
        }

        $versionReference = $options['version_reference'] ?? null;

        if ($versionReference !== null && $this->versioning !== null && $this->versioning->get($versionReference) === null) {
            return ['found' => false, 'document_id' => null, 'error' => sprintf('Version reference "%s" does not resolve to a known Knowledge Versioning record.', $versionReference)];
        }

        $documentId = 'knowledge_document_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO knowledge_documents (
                document_id, title, type, storage_reference, version_reference,
                citation_references_json, status, owner, protected, metadata_json, created_at, updated_at
            ) VALUES (
                :document_id, :title, :type, :storage_reference, :version_reference,
                :citation_references_json, :status, :owner, :protected, :metadata_json, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'document_id' => $documentId,
            'title' => $title,
            'type' => $type,
            'storage_reference' => $storageReference,
            'version_reference' => $versionReference,
            'citation_references_json' => json_encode([], JSON_THROW_ON_ERROR),
            'status' => $storageReference !== null ? 'active' : 'draft',
            'owner' => $options['owner'] ?? null,
            'protected' => ($options['protected'] ?? false) ? 1 : 0,
            'metadata_json' => json_encode($options['metadata'] ?? [], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->publish('registered', $documentId);

        return ['found' => true, 'document_id' => $documentId, 'error' => null];
    }

    /**
     * @param array{authorized?: bool}|null $authorizationResult a decision
     *        already computed by the caller via 24_SECURITY's
     *        AuthorizationManagerInterface -- this method never computes
     *        one itself.
     * @return array<string, mixed>
     */
    public function readMetadata(string $documentId, ?array $authorizationResult = null): array
    {
        $record = $this->fetch($documentId);

        if ($record === null) {
            return ['found' => false, 'document_id' => $documentId, 'error' => sprintf('Unknown document reference "%s".', $documentId)];
        }

        if ((int) $record['protected'] === 1 && ($authorizationResult['authorized'] ?? false) !== true) {
            return ['found' => false, 'document_id' => $documentId, 'error' => 'Authorization required for this protected document reference.'];
        }

        return $this->hydrate($record);
    }

    /**
     * Whether a document reference exists at all, regardless of
     * protection -- for internal existence checks (e.g. verifying a
     * knowledge_id before generating an embedding) that don't expose any
     * metadata and so aren't subject to the protected-reference
     * authorization gate the way readMetadata() is.
     */
    public function exists(string $documentId): bool
    {
        return $this->fetch($documentId) !== null;
    }

    /**
     * @param array{title?: string, type?: string, owner?: ?string, metadata?: array<string, mixed>} $changes
     * @return array{found: bool, error: ?string}
     */
    public function updateMetadata(string $documentId, array $changes): array
    {
        $record = $this->fetch($documentId);

        if ($record === null) {
            return ['found' => false, 'error' => sprintf('Unknown document reference "%s".', $documentId)];
        }

        if (isset($changes['type']) && !in_array($changes['type'], self::DOCUMENT_TYPES, true)) {
            return ['found' => false, 'error' => sprintf('Unknown document type "%s".', $changes['type'])];
        }

        $statement = $this->database->prepare(
            'UPDATE knowledge_documents SET title = :title, type = :type, owner = :owner, metadata_json = :metadata_json, updated_at = :updated_at
             WHERE document_id = :document_id'
        );
        $statement->execute([
            'title' => $changes['title'] ?? $record['title'],
            'type' => $changes['type'] ?? $record['type'],
            'owner' => array_key_exists('owner', $changes) ? $changes['owner'] : $record['owner'],
            'metadata_json' => json_encode($changes['metadata'] ?? json_decode((string) $record['metadata_json'], true, flags: JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR),
            'updated_at' => gmdate(DATE_RFC3339),
            'document_id' => $documentId,
        ]);

        $this->publish('metadata_updated', $documentId);

        return ['found' => true, 'error' => null];
    }

    /**
     * @return array{found: bool, error: ?string}
     */
    public function archiveReference(string $documentId): array
    {
        return $this->transitionStatus($documentId, ['draft', 'active'], 'archived', 'archived');
    }

    /**
     * @return array{found: bool, error: ?string}
     */
    public function restoreReference(string $documentId): array
    {
        return $this->transitionStatus($documentId, ['archived'], 'active', 'restored');
    }

    /**
     * @param array<int, string> $allowedFrom
     * @return array{found: bool, error: ?string}
     */
    private function transitionStatus(string $documentId, array $allowedFrom, string $toStatus, string $eventSuffix): array
    {
        $record = $this->fetch($documentId);

        if ($record === null) {
            return ['found' => false, 'error' => sprintf('Unknown document reference "%s".', $documentId)];
        }

        if (!in_array($record['status'], $allowedFrom, true)) {
            return ['found' => false, 'error' => sprintf('Cannot transition from "%s" to "%s".', $record['status'], $toStatus)];
        }

        $statement = $this->database->prepare('UPDATE knowledge_documents SET status = :status, updated_at = :updated_at WHERE document_id = :document_id');
        $statement->execute(['status' => $toStatus, 'updated_at' => gmdate(DATE_RFC3339), 'document_id' => $documentId]);

        $this->publish($eventSuffix, $documentId);

        return ['found' => true, 'error' => null];
    }

    /**
     * The one genuine cross-check against real storage state: if the
     * referenced document has been deleted in Document Storage, the
     * knowledge-facing reference is auto-archived to match.
     *
     * @return array{synced: bool, archived: bool, reason: ?string}
     */
    public function syncStatusFromStorage(string $documentId): array
    {
        if ($this->documentStorage === null) {
            return ['synced' => false, 'archived' => false, 'reason' => 'Document Storage is not configured.'];
        }

        $record = $this->fetch($documentId);

        if ($record === null || $record['storage_reference'] === null) {
            return ['synced' => false, 'archived' => false, 'reason' => 'No storage reference to sync against.'];
        }

        if ($record['status'] === 'archived') {
            return ['synced' => false, 'archived' => false, 'reason' => 'Already archived.'];
        }

        $resolved = $this->documentStorage->retrieve($record['storage_reference']);

        if ($resolved['found']) {
            return ['synced' => false, 'archived' => false, 'reason' => 'Storage reference is still active.'];
        }

        $this->transitionStatus($documentId, [$record['status']], 'archived', 'archived');

        return ['synced' => true, 'archived' => true, 'reason' => 'Storage reference has been deleted; reference archived to match.'];
    }

    /**
     * @return array{found: bool, error: ?string}
     */
    public function attachVersionReference(string $documentId, string $versionReference): array
    {
        $record = $this->fetch($documentId);

        if ($record === null) {
            return ['found' => false, 'error' => sprintf('Unknown document reference "%s".', $documentId)];
        }

        if ($this->versioning !== null && $this->versioning->get($versionReference) === null) {
            return ['found' => false, 'error' => sprintf('Version reference "%s" does not resolve to a known Knowledge Versioning record.', $versionReference)];
        }

        $statement = $this->database->prepare('UPDATE knowledge_documents SET version_reference = :version_reference, updated_at = :updated_at WHERE document_id = :document_id');
        $statement->execute(['version_reference' => $versionReference, 'updated_at' => gmdate(DATE_RFC3339), 'document_id' => $documentId]);

        $this->publish('version_attached', $documentId);

        return ['found' => true, 'error' => null];
    }

    /**
     * @return array{found: bool, error: ?string}
     */
    public function attachCitation(string $documentId, string $citationReference): array
    {
        $record = $this->fetch($documentId);

        if ($record === null) {
            return ['found' => false, 'error' => sprintf('Unknown document reference "%s".', $documentId)];
        }

        if ($this->citations !== null && $this->citations->get($citationReference) === null) {
            return ['found' => false, 'error' => sprintf('Citation reference "%s" does not resolve to a known Citation Manager record.', $citationReference)];
        }

        $citations = json_decode((string) $record['citation_references_json'], true, flags: JSON_THROW_ON_ERROR);

        if (!in_array($citationReference, $citations, true)) {
            $citations[] = $citationReference;
        }

        $statement = $this->database->prepare('UPDATE knowledge_documents SET citation_references_json = :citation_references_json, updated_at = :updated_at WHERE document_id = :document_id');
        $statement->execute(['citation_references_json' => json_encode($citations, JSON_THROW_ON_ERROR), 'updated_at' => gmdate(DATE_RFC3339), 'document_id' => $documentId]);

        $this->publish('citation_attached', $documentId);

        return ['found' => true, 'error' => null];
    }

    /**
     * Metadata filtering on real columns -- not semantic search, which
     * belongs to 25_KNOWLEDGE/SEMANTIC-SEARCH.md.
     *
     * @param array{type?: string, status?: string, owner?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters = []): array
    {
        $conditions = ['1 = 1'];
        $parameters = [];

        foreach (['type', 'status', 'owner'] as $field) {
            if (isset($filters[$field])) {
                $conditions[] = "{$field} = :{$field}";
                $parameters[$field] = $filters[$field];
            }
        }

        $statement = $this->database->prepare(
            'SELECT document_id, title, type, status, owner, storage_reference, created_at, updated_at
             FROM knowledge_documents WHERE ' . implode(' AND ', $conditions) . ' ORDER BY rowid DESC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $documentId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM knowledge_documents WHERE document_id = :document_id');
        $statement->execute(['document_id' => $documentId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function hydrate(array $record): array
    {
        return [
            'found' => true,
            'document_id' => $record['document_id'],
            'title' => $record['title'],
            'type' => $record['type'],
            'storage_reference' => $record['storage_reference'],
            'version_reference' => $record['version_reference'],
            'citation_references' => json_decode((string) $record['citation_references_json'], true, flags: JSON_THROW_ON_ERROR),
            'status' => $record['status'],
            'owner' => $record['owner'],
            'protected' => (bool) $record['protected'],
            'metadata' => json_decode((string) $record['metadata_json'], true, flags: JSON_THROW_ON_ERROR),
            'created_at' => $record['created_at'],
            'updated_at' => $record['updated_at'],
            'error' => null,
        ];
    }

    private function publish(string $eventSuffix, string $documentId): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'knowledge_document.' . $eventSuffix,
            new DateTimeImmutable(),
            self::class,
            ['document_id' => $documentId]
        ));
    }
}
