<?php

declare(strict_types=1);

namespace SquirrelForge\Storage;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\AuthorizationManagerInterface;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Stores embedding vectors and performs real nearest-neighbor similarity
 * search over them, per 37_STORAGE/VECTOR-STORAGE.md.
 *
 * Similarity search is genuine vector math, not a fabricated ranking:
 * cosine similarity, dot product, and negative Euclidean distance are
 * all computed directly from the stored float arrays, with higher always
 * meaning "more similar" so callers don't need per-metric sort direction
 * logic. "Must never mix incompatible embedding spaces" is a real,
 * enforced check: a collection's dimension is established by its first
 * vector, and store() rejects any later vector for that collection whose
 * dimension doesn't match -- not a documented policy nobody checks.
 * "Prevent cross-scope leakage" is structural: search() only ever
 * compares vectors within the single collection requested, never across
 * collections.
 *
 * This mirrors SqliteObjectStorage's shape (its own audit table,
 * tombstone delete, legal_hold gate, optional AuthorizationManagerInterface)
 * since this spec -- unlike 25_KNOWLEDGE's newer-style specs -- explicitly
 * assigns Vector Storage its own audit and governance responsibilities.
 * ANN/approximate search, reindexing, and semantic-index health checks
 * are out of scope: at this scale a linear scan is the real, correct
 * algorithm, and there's no real index structure to rebuild or check.
 * Governance status is the fixed constant `ungoverned` since
 * 23_GOVERNANCE has no policy engine to gate on.
 */
final class SqliteVectorStorage
{
    private const DISTANCE_METRICS = ['cosine', 'dot', 'euclidean'];

    private PDO $database;

    public function __construct(
        string $databasePath,
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
            'CREATE TABLE IF NOT EXISTS vectors (
                vector_ref TEXT PRIMARY KEY,
                collection_id TEXT NOT NULL,
                source_content_id TEXT,
                embedding_model TEXT,
                embedding_model_version TEXT,
                dimension INTEGER NOT NULL,
                distance_metric TEXT NOT NULL,
                vector_json TEXT NOT NULL,
                owner TEXT,
                state TEXT NOT NULL,
                legal_hold INTEGER NOT NULL DEFAULT 0,
                metadata_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS vector_operations (
                operation_ref TEXT PRIMARY KEY,
                operation TEXT NOT NULL,
                vector_ref TEXT,
                collection_id TEXT,
                source_content_id TEXT,
                embedding_model_version TEXT,
                requesting_component TEXT,
                governance_status TEXT NOT NULL,
                outcome TEXT NOT NULL,
                error TEXT,
                created_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<int, float|int> $vector
     * @param array{source_content_id?: ?string, embedding_model?: ?string, embedding_model_version?: ?string, distance_metric?: string, owner?: ?string, metadata?: array<string, mixed>, requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $options
     * @return array{vector_ref: ?string, collection_id: string, dimension: ?int, outcome: string, error: ?string}
     */
    public function store(string $collectionId, array $vector, array $options = []): array
    {
        $authorization = $this->checkAuthorization('store', $collectionId, $options);

        if (!$authorization['authorized']) {
            return $this->reject('store', null, $collectionId, $options, $authorization['error']);
        }

        if ($vector === [] || !$this->isNumericVector($vector)) {
            return $this->reject('store', null, $collectionId, $options, 'Vector must be a non-empty array of numbers.');
        }

        $distanceMetric = $options['distance_metric'] ?? 'cosine';

        if (!in_array($distanceMetric, self::DISTANCE_METRICS, true)) {
            return $this->reject('store', null, $collectionId, $options, sprintf('Unknown distance metric "%s".', $distanceMetric));
        }

        $dimension = count($vector);
        $establishedDimension = $this->collectionDimension($collectionId);

        if ($establishedDimension !== null && $establishedDimension !== $dimension) {
            return $this->reject('store', null, $collectionId, $options, sprintf(
                'Vector dimension %d is incompatible with collection "%s", which is established at dimension %d.',
                $dimension,
                $collectionId,
                $establishedDimension
            ));
        }

        $vectorRef = 'vector_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO vectors (
                vector_ref, collection_id, source_content_id, embedding_model, embedding_model_version,
                dimension, distance_metric, vector_json, owner, state, legal_hold, metadata_json, created_at, updated_at
            ) VALUES (
                :vector_ref, :collection_id, :source_content_id, :embedding_model, :embedding_model_version,
                :dimension, :distance_metric, :vector_json, :owner, :state, 0, :metadata_json, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'vector_ref' => $vectorRef,
            'collection_id' => $collectionId,
            'source_content_id' => $options['source_content_id'] ?? null,
            'embedding_model' => $options['embedding_model'] ?? null,
            'embedding_model_version' => $options['embedding_model_version'] ?? null,
            'dimension' => $dimension,
            'distance_metric' => $distanceMetric,
            'vector_json' => json_encode(array_values($vector), JSON_THROW_ON_ERROR),
            'owner' => $options['owner'] ?? null,
            'state' => 'active',
            'metadata_json' => json_encode($options['metadata'] ?? [], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->recordOperation('store', $vectorRef, $collectionId, $options['source_content_id'] ?? null, $options['embedding_model_version'] ?? null, $options, 'stored', null);

        return ['vector_ref' => $vectorRef, 'collection_id' => $collectionId, 'dimension' => $dimension, 'outcome' => 'stored', 'error' => null];
    }

    /**
     * @param array{requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $context
     * @return array{found: bool, vector_ref: string, collection_id: ?string, vector: ?array<int, float>, metadata: ?array<string, mixed>, error: ?string}
     */
    public function retrieve(string $vectorRef, array $context = []): array
    {
        $record = $this->fetch($vectorRef);

        if ($record === null || $record['state'] !== 'active') {
            $error = $record === null ? sprintf('Unknown vector "%s".', $vectorRef) : 'Vector has been deleted.';
            $this->recordOperation('retrieve', $vectorRef, $record['collection_id'] ?? null, $record['source_content_id'] ?? null, $record['embedding_model_version'] ?? null, $context, 'not_found', $error);

            return ['found' => false, 'vector_ref' => $vectorRef, 'collection_id' => null, 'vector' => null, 'metadata' => null, 'error' => $error];
        }

        $authorization = $this->checkAuthorization('retrieve', $record['collection_id'], $context);

        if (!$authorization['authorized']) {
            $this->recordOperation('retrieve', $vectorRef, $record['collection_id'], $record['source_content_id'], $record['embedding_model_version'], $context, 'rejected', $authorization['error']);

            return ['found' => false, 'vector_ref' => $vectorRef, 'collection_id' => $record['collection_id'], 'vector' => null, 'metadata' => null, 'error' => $authorization['error']];
        }

        $this->recordOperation('retrieve', $vectorRef, $record['collection_id'], $record['source_content_id'], $record['embedding_model_version'], $context, 'retrieved', null);

        return [
            'found' => true,
            'vector_ref' => $vectorRef,
            'collection_id' => $record['collection_id'],
            'vector' => json_decode((string) $record['vector_json'], true, flags: JSON_THROW_ON_ERROR),
            'metadata' => json_decode((string) $record['metadata_json'], true, flags: JSON_THROW_ON_ERROR),
            'error' => null,
        ];
    }

    /**
     * Real nearest-neighbor search: computes cosine similarity, dot
     * product, or negative Euclidean distance directly against every
     * active vector in the requested collection, always scored so higher
     * means more similar.
     *
     * @param array<int, float|int> $queryVector
     * @param array{top_k?: int, distance_metric?: string, score_threshold?: ?float, metadata_filters?: array<string, mixed>, requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $options
     * @return array{results: array<int, array{vector_ref: string, score: float, metadata: array<string, mixed>}>, outcome: string, error: ?string}
     */
    public function search(string $collectionId, array $queryVector, array $options = []): array
    {
        $authorization = $this->checkAuthorization('search', $collectionId, $options);

        if (!$authorization['authorized']) {
            $this->recordOperation('search', null, $collectionId, null, null, $options, 'rejected', $authorization['error']);

            return ['results' => [], 'outcome' => 'rejected', 'error' => $authorization['error']];
        }

        if ($queryVector === [] || !$this->isNumericVector($queryVector)) {
            $error = 'Query vector must be a non-empty array of numbers.';
            $this->recordOperation('search', null, $collectionId, null, null, $options, 'rejected', $error);

            return ['results' => [], 'outcome' => 'rejected', 'error' => $error];
        }

        $distanceMetric = $options['distance_metric'] ?? 'cosine';

        if (!in_array($distanceMetric, self::DISTANCE_METRICS, true)) {
            $error = sprintf('Unknown distance metric "%s".', $distanceMetric);
            $this->recordOperation('search', null, $collectionId, null, null, $options, 'rejected', $error);

            return ['results' => [], 'outcome' => 'rejected', 'error' => $error];
        }

        $establishedDimension = $this->collectionDimension($collectionId);

        if ($establishedDimension !== null && $establishedDimension !== count($queryVector)) {
            $error = sprintf('Query vector dimension %d is incompatible with collection "%s" (dimension %d).', count($queryVector), $collectionId, $establishedDimension);
            $this->recordOperation('search', null, $collectionId, null, null, $options, 'rejected', $error);

            return ['results' => [], 'outcome' => 'rejected', 'error' => $error];
        }

        $candidates = $this->activeVectorsIn($collectionId, $options['metadata_filters'] ?? []);
        $scored = [];

        foreach ($candidates as $candidate) {
            $vector = json_decode((string) $candidate['vector_json'], true, flags: JSON_THROW_ON_ERROR);
            $score = $this->score($distanceMetric, $queryVector, $vector);

            if (isset($options['score_threshold']) && $score < $options['score_threshold']) {
                continue;
            }

            $scored[] = [
                'vector_ref' => $candidate['vector_ref'],
                'score' => $score,
                'metadata' => json_decode((string) $candidate['metadata_json'], true, flags: JSON_THROW_ON_ERROR),
            ];
        }

        usort($scored, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $results = array_slice($scored, 0, $options['top_k'] ?? 10);

        $this->recordOperation('search', null, $collectionId, null, null, $options, 'searched', null);

        return ['results' => $results, 'outcome' => 'searched', 'error' => null];
    }

    /**
     * @param array<int, float|int> $a
     * @param array<int, float|int> $b
     */
    private function score(string $metric, array $a, array $b): float
    {
        return match ($metric) {
            'cosine' => $this->cosineSimilarity($a, $b),
            'dot' => $this->dotProduct($a, $b),
            'euclidean' => -$this->euclideanDistance($a, $b),
        };
    }

    /**
     * @param array<int, float|int> $a
     * @param array<int, float|int> $b
     */
    private function dotProduct(array $a, array $b): float
    {
        $sum = 0.0;

        foreach (array_values($a) as $index => $value) {
            $sum += $value * $b[$index];
        }

        return $sum;
    }

    /**
     * @param array<int, float|int> $a
     * @param array<int, float|int> $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $magnitudeA = sqrt($this->dotProduct($a, $a));
        $magnitudeB = sqrt($this->dotProduct($b, $b));

        if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
            return 0.0;
        }

        return $this->dotProduct($a, $b) / ($magnitudeA * $magnitudeB);
    }

    /**
     * @param array<int, float|int> $a
     * @param array<int, float|int> $b
     */
    private function euclideanDistance(array $a, array $b): float
    {
        $sumOfSquares = 0.0;

        foreach (array_values($a) as $index => $value) {
            $sumOfSquares += ($value - $b[$index]) ** 2;
        }

        return sqrt($sumOfSquares);
    }

    /**
     * @param array<int, mixed> $vector
     */
    private function isNumericVector(array $vector): bool
    {
        foreach ($vector as $component) {
            if (!is_int($component) && !is_float($component)) {
                return false;
            }
        }

        return true;
    }

    private function collectionDimension(string $collectionId): ?int
    {
        $statement = $this->database->prepare(
            "SELECT dimension FROM vectors WHERE collection_id = :collection_id AND state = 'active' LIMIT 1"
        );
        $statement->execute(['collection_id' => $collectionId]);
        $row = $statement->fetch();

        return is_array($row) ? (int) $row['dimension'] : null;
    }

    /**
     * @param array<string, mixed> $metadataFilters
     * @return array<int, array<string, mixed>>
     */
    private function activeVectorsIn(string $collectionId, array $metadataFilters): array
    {
        $statement = $this->database->prepare(
            "SELECT vector_ref, vector_json, metadata_json FROM vectors WHERE collection_id = :collection_id AND state = 'active'"
        );
        $statement->execute(['collection_id' => $collectionId]);

        if ($metadataFilters === []) {
            return $statement->fetchAll();
        }

        return array_values(array_filter(
            $statement->fetchAll(),
            function (array $row) use ($metadataFilters): bool {
                $metadata = json_decode((string) $row['metadata_json'], true, flags: JSON_THROW_ON_ERROR);

                foreach ($metadataFilters as $key => $value) {
                    if (($metadata[$key] ?? null) !== $value) {
                        return false;
                    }
                }

                return true;
            }
        ));
    }

    /**
     * A tombstone, never a row removal. Refuses when the vector is under
     * legal hold.
     *
     * @param array{requesting_component?: ?string, identity_ref?: ?string, permission_ref?: ?string, correlation_id?: ?string} $context
     * @return array{outcome: string, error: ?string}
     */
    public function delete(string $vectorRef, array $context = []): array
    {
        $record = $this->fetch($vectorRef);

        if ($record === null) {
            $error = sprintf('Unknown vector "%s".', $vectorRef);
            $this->recordOperation('delete', $vectorRef, null, null, null, $context, 'not_found', $error);

            return ['outcome' => 'not_found', 'error' => $error];
        }

        $authorization = $this->checkAuthorization('delete', $record['collection_id'], $context);

        if (!$authorization['authorized']) {
            $this->recordOperation('delete', $vectorRef, $record['collection_id'], $record['source_content_id'], $record['embedding_model_version'], $context, 'rejected', $authorization['error']);

            return ['outcome' => 'rejected', 'error' => $authorization['error']];
        }

        if ((int) $record['legal_hold'] === 1) {
            $error = sprintf('Vector "%s" is under legal hold and cannot be deleted.', $vectorRef);
            $this->recordOperation('delete', $vectorRef, $record['collection_id'], $record['source_content_id'], $record['embedding_model_version'], $context, 'rejected', $error);

            return ['outcome' => 'rejected', 'error' => $error];
        }

        $statement = $this->database->prepare("UPDATE vectors SET state = 'deleted', updated_at = :updated_at WHERE vector_ref = :vector_ref");
        $statement->execute(['updated_at' => gmdate(DATE_RFC3339), 'vector_ref' => $vectorRef]);

        $this->recordOperation('delete', $vectorRef, $record['collection_id'], $record['source_content_id'], $record['embedding_model_version'], $context, 'deleted', null);

        return ['outcome' => 'deleted', 'error' => null];
    }

    /**
     * Places or lifts a legal hold on a vector. While held, delete()
     * refuses to tombstone it. Returns false if the vector doesn't exist.
     */
    public function setLegalHold(string $vectorRef, bool $hold): bool
    {
        $statement = $this->database->prepare('UPDATE vectors SET legal_hold = :legal_hold WHERE vector_ref = :vector_ref');
        $statement->execute(['legal_hold' => $hold ? 1 : 0, 'vector_ref' => $vectorRef]);

        return $statement->rowCount() > 0;
    }

    /**
     * @return array<int, array{vector_ref: string, source_content_id: ?string, dimension: int, distance_metric: string, created_at: string}>
     */
    public function listCollection(string $collectionId): array
    {
        $statement = $this->database->prepare(
            "SELECT vector_ref, source_content_id, dimension, distance_metric, created_at
             FROM vectors WHERE collection_id = :collection_id AND state = 'active' ORDER BY rowid DESC"
        );
        $statement->execute(['collection_id' => $collectionId]);

        return array_map(
            static fn(array $row): array => [
                'vector_ref' => $row['vector_ref'],
                'source_content_id' => $row['source_content_id'],
                'dimension' => (int) $row['dimension'],
                'distance_metric' => $row['distance_metric'],
                'created_at' => $row['created_at'],
            ],
            $statement->fetchAll()
        );
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
            $context['permission_ref'] ?? 'vector:' . $operation,
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
     * @param array<string, mixed> $options
     * @return array{vector_ref: null, collection_id: string, dimension: null, outcome: string, error: string}
     */
    private function reject(string $operation, ?string $vectorRef, string $collectionId, array $options, string $error): array
    {
        $this->recordOperation($operation, $vectorRef, $collectionId, $options['source_content_id'] ?? null, $options['embedding_model_version'] ?? null, $options, 'rejected', $error);

        return ['vector_ref' => null, 'collection_id' => $collectionId, 'dimension' => null, 'outcome' => 'rejected', 'error' => $error];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function recordOperation(
        string $operation,
        ?string $vectorRef,
        ?string $collectionId,
        ?string $sourceContentId,
        ?string $embeddingModelVersion,
        array $context,
        string $outcome,
        ?string $error
    ): void {
        $operationRef = 'vector_operation_' . bin2hex(random_bytes(12));

        $statement = $this->database->prepare(
            'INSERT INTO vector_operations (
                operation_ref, operation, vector_ref, collection_id, source_content_id,
                embedding_model_version, requesting_component, governance_status, outcome, error, created_at
            ) VALUES (
                :operation_ref, :operation, :vector_ref, :collection_id, :source_content_id,
                :embedding_model_version, :requesting_component, :governance_status, :outcome, :error, :created_at
            )'
        );
        $statement->execute([
            'operation_ref' => $operationRef,
            'operation' => $operation,
            'vector_ref' => $vectorRef,
            'collection_id' => $collectionId,
            'source_content_id' => $sourceContentId,
            'embedding_model_version' => $embeddingModelVersion,
            'requesting_component' => $context['requesting_component'] ?? $context['identity_ref'] ?? null,
            'governance_status' => 'ungoverned',
            'outcome' => $outcome,
            'error' => $error,
            'created_at' => gmdate(DATE_RFC3339),
        ]);

        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'vector.' . $outcome,
            new DateTimeImmutable(),
            self::class,
            ['operation_ref' => $operationRef, 'vector_ref' => $vectorRef, 'operation' => $operation]
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $vectorRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM vectors WHERE vector_ref = :vector_ref');
        $statement->execute(['vector_ref' => $vectorRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function operation(string $operationRef): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM vector_operations WHERE operation_ref = :operation_ref');
        $statement->execute(['operation_ref' => $operationRef]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $statement = $this->database->prepare('SELECT * FROM vector_operations ORDER BY rowid DESC LIMIT :limit');
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
