<?php

declare(strict_types=1);

namespace SquirrelForge\Storage;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Locates, validates, and delivers stored data to authorized
 * components, per 37_STORAGE/RETRIEVAL-MANAGER.md.
 *
 * This is a genuinely distinct spec from `37_STORAGE/STORAGE-MANAGER.md`
 * (already real as `SqliteStorageManager`), not a duplicate: Storage
 * Manager's own `retrieve_object`/`retrieve_document`/`retrieve_vector`
 * operations only ever perform "direct identifier lookup", one of this
 * spec's eight named Retrieval Methods. What this class adds is
 * genuinely new composition Storage Manager does not have: "indexed
 * retrieval" (retrieveByIndex(), delegating entirely to the real
 * `SqliteIndexManager::search()` built for exactly this purpose, then
 * resolving each hit's real record), "historical retrieval"
 * (retrieveHistory(), the real version ledgers Object/Document Storage
 * already keep via their own versions() methods), and "batch retrieval"
 * (retrieveBatch(), repeating the same real single-reference lookup for
 * many references rather than inventing a bulk-fetch mechanism).
 *
 * "Archive retrieval" is intentionally unsupported: no real Archive
 * Storage component exists in this codebase (the same gap
 * `SqliteStorageManager`'s own docblock already documents), so an
 * archived-record request is rejected rather than routed to Object or
 * Document Storage as if archival were the same thing as ordinary
 * storage.
 *
 * "Verify data integrity" is not re-implemented here: Object Storage
 * already performs a real checksum comparison inside its own
 * retrieve() and reports a `found: false` result with a corruption
 * error when it fails, so `integrity_verification` on a successful
 * retrieval is `verified` (the underlying store already confirmed it)
 * and `not_applicable` on any retrieval that didn't succeed, rather
 * than a second redundant check duplicating that work.
 *
 * `authorization_status` is the fixed value `delegated`, the same
 * precedent `SqliteDataManager` and `SqliteStorageManager` already
 * established: each coordinated store already has its own optional
 * `AuthorizationManagerInterface` wiring, and re-checking it here would
 * be redundant, not more correct. Governance status is the fixed
 * constant `ungoverned` since Storage Governance has no code.
 *
 * Owns its own database (`Sqlite` prefix): the Audit Requirements name
 * a specific structured record shape every retrieval call records
 * unconditionally, including not-found and rejected retrievals, per
 * "Maintain audit continuity."
 */
final class SqliteRetrievalManager
{
    private const STORAGE_TYPES = ['object', 'document', 'vector'];

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteObjectStorage $objects = null,
        private readonly ?SqliteDocumentStorage $documents = null,
        private readonly ?SqliteVectorStorage $vectors = null,
        private readonly ?SqliteIndexManager $index = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS retrieval_operations (
                retrieval_id TEXT PRIMARY KEY,
                requesting_component TEXT,
                record_id TEXT,
                version_retrieved TEXT,
                authorization_status TEXT NOT NULL,
                integrity_verification TEXT NOT NULL,
                governance_status TEXT NOT NULL,
                final_outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $context
     * @return array{retrieval_id: string, outcome: string, record: mixed, error: ?string}
     */
    public function retrieveByReference(string $storageType, string $reference, ?int $version = null, array $context = []): array
    {
        if (!in_array($storageType, self::STORAGE_TYPES, true)) {
            return $this->finish($context, $reference, $version, 'not_applicable', 'rejected', sprintf('"%s" is not a supported storage type for retrieval.', $storageType), null);
        }

        [$found, $result, $error] = match ($storageType) {
            'object' => $this->retrieveObject($reference, $version, $context),
            'document' => $this->retrieveDocument($reference, $version, $context),
            'vector' => $this->retrieveVector($reference, $context),
        };

        return $this->finish($context, $reference, $version, $found ? 'verified' : 'not_applicable', $found ? 'retrieved' : 'not_found', $error, $result);
    }

    /**
     * @param array<int, string> $references
     * @param array{requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $context
     * @return array{retrieved: array<int, mixed>, not_found: array<int, string>}
     */
    public function retrieveBatch(string $storageType, array $references, array $context = []): array
    {
        $retrieved = [];
        $notFound = [];

        foreach ($references as $reference) {
            $result = $this->retrieveByReference($storageType, $reference, null, $context);

            if ($result['outcome'] === 'retrieved') {
                $retrieved[] = $result['record'];
            } else {
                $notFound[] = $reference;
            }
        }

        return ['retrieved' => $retrieved, 'not_found' => $notFound];
    }

    /**
     * Resolving an index hit back to its real stored record requires
     * knowing which storage backend owns that source type -- something
     * `SqliteIndexManager` itself doesn't track (it indexes attributes
     * only, per its own docblock, never the storage location). Most of
     * the ten Index Sources this spec names (workflow records, learning
     * experiences, system logs, and so on) have no corresponding
     * storage component in this codebase at all, so `storage_type_map`
     * lets the caller declare which source types resolve to a real
     * backend; only `documents => document` is assumed by default,
     * since Document Storage is the one backend genuinely certain to
     * hold that source type. An index entry whose source type has no
     * mapping still comes back with its real index metadata, just with
     * `record: null` rather than a fabricated guess at where it lives.
     *
     * @param array<int, string> $queryTerms
     * @param array{source_types?: array<int, string>, category?: ?string, related_to?: ?string, limit?: int, requesting_component?: ?string, storage_type_map?: array<string, string>} $options
     * @return array{results: array<int, array<string, mixed>>, outcome: string, error: ?string}
     */
    public function retrieveByIndex(array $queryTerms, array $options = []): array
    {
        if ($this->index === null) {
            return ['results' => [], 'outcome' => 'not_configured', 'error' => 'Index Manager is not configured.'];
        }

        $indexed = $this->index->search($queryTerms, $options);

        if ($indexed['outcome'] !== 'searched') {
            return $indexed;
        }

        $storageTypeMap = $options['storage_type_map'] ?? ['documents' => 'document'];
        $results = [];

        foreach ($indexed['results'] as $entry) {
            $storageType = $storageTypeMap[$entry['source_type']] ?? null;
            $record = $storageType !== null ? $this->retrieveByReference($storageType, $entry['record_id'], null, $options) : null;

            $results[] = ['index_entry' => $entry, 'record' => $record !== null && $record['outcome'] === 'retrieved' ? $record['record'] : null];
        }

        return ['results' => $results, 'outcome' => 'searched', 'error' => null];
    }

    /**
     * @return array{outcome: string, versions: array<int, array<string, mixed>>, error: ?string}
     */
    public function retrieveHistory(string $storageType, string $reference): array
    {
        if ($storageType === 'object' && $this->objects !== null) {
            return ['outcome' => 'retrieved', 'versions' => $this->objects->versions($reference), 'error' => null];
        }

        if ($storageType === 'document' && $this->documents !== null) {
            return ['outcome' => 'retrieved', 'versions' => $this->documents->versions($reference), 'error' => null];
        }

        return ['outcome' => 'not_applicable', 'versions' => [], 'error' => sprintf('No version history is available for storage type "%s".', $storageType)];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{0: bool, 1: mixed, 2: ?string}
     */
    private function retrieveObject(string $reference, ?int $version, array $context): array
    {
        if ($this->objects === null) {
            return [false, null, 'Object Storage is not configured.'];
        }

        $result = $this->objects->retrieve($reference, $version, $context);

        return [$result['found'], $result, $result['error']];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{0: bool, 1: mixed, 2: ?string}
     */
    private function retrieveDocument(string $reference, ?int $version, array $context): array
    {
        if ($this->documents === null) {
            return [false, null, 'Document Storage is not configured.'];
        }

        $result = $this->documents->retrieve($reference, $version, $context);

        return [$result['found'], $result, $result['error']];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{0: bool, 1: mixed, 2: ?string}
     */
    private function retrieveVector(string $reference, array $context): array
    {
        if ($this->vectors === null) {
            return [false, null, 'Vector Storage is not configured.'];
        }

        $result = $this->vectors->retrieve($reference, $context);

        return [$result['found'], $result, $result['error']];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{retrieval_id: string, outcome: string, record: mixed, error: ?string}
     */
    private function finish(
        array $context,
        string $reference,
        ?int $version,
        string $integrityVerification,
        string $outcome,
        ?string $error,
        mixed $record
    ): array {
        $retrievalId = 'retrieval_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO retrieval_operations (
                retrieval_id, requesting_component, record_id, version_retrieved,
                authorization_status, integrity_verification, governance_status, final_outcome, error, created_at
            ) VALUES (
                :id, :requesting_component, :record_id, :version_retrieved,
                :authorization_status, :integrity_verification, :governance_status, :final_outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'id' => $retrievalId,
            'requesting_component' => $context['requesting_component'] ?? null,
            'record_id' => $reference,
            'version_retrieved' => $version !== null ? (string) $version : null,
            'authorization_status' => 'delegated',
            'integrity_verification' => $integrityVerification,
            'governance_status' => 'ungoverned',
            'final_outcome' => $outcome,
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'retrieval.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['retrieval_id' => $retrievalId, 'record_id' => $reference, 'outcome' => $outcome]
        ));

        return ['retrieval_id' => $retrievalId, 'outcome' => $outcome, 'record' => $record, 'error' => $error];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function operation(string $retrievalId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM retrieval_operations WHERE retrieval_id = :id');
        $statement->execute(['id' => $retrievalId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare('SELECT * FROM retrieval_operations ORDER BY rowid DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
