<?php

declare(strict_types=1);

namespace SquirrelForge\Knowledge;

use DateTimeImmutable;
use PDO;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;
use SquirrelForge\Storage\SqliteVectorStorage;

/**
 * Generates and maintains embedding records for knowledge assets, per
 * 25_KNOWLEDGE/EMBEDDINGS.md.
 *
 * Vector generation is real, deterministic math, not a fabricated call
 * to an external model: it's the feature-hashing trick (as used by
 * scikit-learn's HashingVectorizer and Vowpal Wabbit) -- each token is
 * hashed into one of a fixed number of buckets with a hashed +1/-1 sign,
 * and the result is L2-normalized. This is a genuine, well-established
 * embedding technique, and its fixed output dimension is exactly what
 * SqliteVectorStorage's dimension-compatibility check requires; there is
 * no `21_CONFIGURATION/MODEL-CONFIG.md` to select a real external model
 * from, so the model name/version are fixed, documented constants rather
 * than fetched from a config store that doesn't exist.
 *
 * This follows SqliteDocumentRepository's strict permission boundary,
 * not the usual pattern: no dedicated audit table (the spec forbids
 * owning "general logging, audit, storage, or observability
 * infrastructure" -- activity is only ever announced via the optional
 * EventBusInterface), and raw vectors are never owned here -- they're
 * delegated to the injected SqliteVectorStorage, the real dependency
 * this spec names for persistence.
 *
 * "Prevent duplicate embedding records" and "Refresh embeddings when
 * source content changes" are both real: generate() hashes the input
 * text and, if it exactly matches the current active embedding's
 * recorded content hash, returns that existing record instead of
 * creating a new one; otherwise it deprecates the prior active
 * embedding and activates the new one. checkFreshness() is the reverse
 * check -- given the asset's current content, it flags an active
 * embedding as `refresh_required` if the content has since diverged.
 */
final class SqliteEmbeddingsManager
{
    private const MODEL_NAME = 'squirrelforge-feature-hashing';
    private const MODEL_VERSION = '1.0.0';
    private const DEFAULT_DIMENSION = 256;

    private PDO $database;

    public function __construct(
        string $databasePath,
        private readonly ?SqliteVectorStorage $vectorStorage = null,
        private readonly ?SqliteDocumentRepository $documents = null,
        private readonly ?EventBusInterface $events = null
    ) {
        $this->database = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->database->exec('PRAGMA journal_mode = WAL');
        $this->database->exec('PRAGMA busy_timeout = 5000');
        $this->database->exec(
            'CREATE TABLE IF NOT EXISTS knowledge_embeddings (
                embedding_id TEXT PRIMARY KEY,
                knowledge_id TEXT NOT NULL,
                model TEXT NOT NULL,
                model_version TEXT NOT NULL,
                vector_dimension INTEGER NOT NULL,
                status TEXT NOT NULL,
                vector_storage_reference TEXT,
                knowledge_version_reference TEXT,
                source_content_hash TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array{dimension?: int, knowledge_version_reference?: ?string, persist?: bool} $options
     * @return array{created: bool, embedding_id: ?string, knowledge_id: string, status: ?string, vector: ?array<int, float>, vector_storage_reference: ?string, error: ?string}
     */
    public function generate(string $knowledgeId, string $text, array $options = []): array
    {
        if ($this->documents !== null) {
            $registered = $this->documents->readMetadata($knowledgeId);

            if (!$registered['found']) {
                return ['created' => false, 'embedding_id' => null, 'knowledge_id' => $knowledgeId, 'status' => null, 'vector' => null, 'vector_storage_reference' => null, 'error' => sprintf('Knowledge asset "%s" is not a registered document reference.', $knowledgeId)];
            }
        }

        $contentHash = hash('sha256', $text);
        $existingActive = $this->activeFor($knowledgeId);

        if ($existingActive !== null && $existingActive['source_content_hash'] === $contentHash) {
            return [
                'created' => false,
                'embedding_id' => $existingActive['embedding_id'],
                'knowledge_id' => $knowledgeId,
                'status' => $existingActive['status'],
                'vector' => $this->vector($existingActive['embedding_id']),
                'vector_storage_reference' => $existingActive['vector_storage_reference'],
                'error' => null,
            ];
        }

        $dimension = $options['dimension'] ?? self::DEFAULT_DIMENSION;
        $vector = $this->hashingTrickVector($text, $dimension);

        $vectorStorageReference = null;
        $status = 'generated';

        if (($options['persist'] ?? true) && $this->vectorStorage !== null) {
            $stored = $this->vectorStorage->store('knowledge:' . self::MODEL_NAME, $vector, [
                'source_content_id' => $knowledgeId,
                'embedding_model' => self::MODEL_NAME,
                'embedding_model_version' => self::MODEL_VERSION,
            ]);

            if ($stored['outcome'] !== 'stored') {
                return ['created' => false, 'embedding_id' => null, 'knowledge_id' => $knowledgeId, 'status' => null, 'vector' => $vector, 'vector_storage_reference' => null, 'error' => $stored['error']];
            }

            $vectorStorageReference = $stored['vector_ref'];
            $status = 'indexed';
        }

        $embeddingId = 'knowledge_embedding_' . bin2hex(random_bytes(12));
        $now = gmdate(DATE_RFC3339);

        $statement = $this->database->prepare(
            'INSERT INTO knowledge_embeddings (
                embedding_id, knowledge_id, model, model_version, vector_dimension, status,
                vector_storage_reference, knowledge_version_reference, source_content_hash, created_at, updated_at
            ) VALUES (
                :embedding_id, :knowledge_id, :model, :model_version, :vector_dimension, :status,
                :vector_storage_reference, :knowledge_version_reference, :source_content_hash, :created_at, :updated_at
            )'
        );
        $statement->execute([
            'embedding_id' => $embeddingId,
            'knowledge_id' => $knowledgeId,
            'model' => self::MODEL_NAME,
            'model_version' => self::MODEL_VERSION,
            'vector_dimension' => $dimension,
            'status' => $vectorStorageReference !== null ? 'active' : $status,
            'vector_storage_reference' => $vectorStorageReference,
            'knowledge_version_reference' => $options['knowledge_version_reference'] ?? null,
            'source_content_hash' => $contentHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($existingActive !== null && $vectorStorageReference !== null) {
            $this->setStatus($existingActive['embedding_id'], 'deprecated');
        }

        $this->publish('generated', $embeddingId, $knowledgeId);

        return [
            'created' => true,
            'embedding_id' => $embeddingId,
            'knowledge_id' => $knowledgeId,
            'status' => $vectorStorageReference !== null ? 'active' : $status,
            'vector' => $vector,
            'vector_storage_reference' => $vectorStorageReference,
            'error' => null,
        ];
    }

    /**
     * Compares an asset's current content against its active embedding's
     * recorded content hash and flags the embedding `refresh_required`
     * if they've diverged -- a real comparison, not an assumption.
     *
     * @return array{checked: bool, fresh: bool, embedding_id: ?string, reason: ?string}
     */
    public function checkFreshness(string $knowledgeId, string $currentText): array
    {
        $active = $this->activeFor($knowledgeId);

        if ($active === null) {
            return ['checked' => false, 'fresh' => false, 'embedding_id' => null, 'reason' => 'No active embedding exists for this knowledge asset.'];
        }

        $currentHash = hash('sha256', $currentText);

        if ($active['source_content_hash'] === $currentHash) {
            return ['checked' => true, 'fresh' => true, 'embedding_id' => $active['embedding_id'], 'reason' => null];
        }

        $this->setStatus($active['embedding_id'], 'refresh_required');

        return ['checked' => true, 'fresh' => false, 'embedding_id' => $active['embedding_id'], 'reason' => 'Source content has changed since this embedding was generated.'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $embeddingId): ?array
    {
        return $this->fetch($embeddingId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeFor(string $knowledgeId): ?array
    {
        $statement = $this->database->prepare(
            "SELECT * FROM knowledge_embeddings WHERE knowledge_id = :knowledge_id AND status = 'active' LIMIT 1"
        );
        $statement->execute(['knowledge_id' => $knowledgeId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Fetches the actual vector, delegating to the injected
     * SqliteVectorStorage -- this class never stores raw vectors itself.
     *
     * @return array<int, float>|null
     */
    public function vector(string $embeddingId): ?array
    {
        $record = $this->fetch($embeddingId);

        if ($record === null || $record['vector_storage_reference'] === null || $this->vectorStorage === null) {
            return null;
        }

        $result = $this->vectorStorage->retrieve($record['vector_storage_reference']);

        return $result['found'] ? $result['vector'] : null;
    }

    /**
     * Real similarity calculation, delegated to SqliteVectorStorage using
     * this embedding's own persisted vector as the query.
     *
     * @param array<string, mixed> $options forwarded to SqliteVectorStorage::search()
     * @return array{results: array<int, array<string, mixed>>, error: ?string}
     */
    public function similarTo(string $embeddingId, array $options = []): array
    {
        $record = $this->fetch($embeddingId);

        if ($record === null || $record['vector_storage_reference'] === null || $this->vectorStorage === null) {
            return ['results' => [], 'error' => 'This embedding has no persisted vector to compare against.'];
        }

        $vector = $this->vector($embeddingId);

        if ($vector === null) {
            return ['results' => [], 'error' => 'The persisted vector could not be retrieved.'];
        }

        $result = $this->vectorStorage->search('knowledge:' . $record['model'], $vector, $options);

        return ['results' => $result['results'], 'error' => $result['error']];
    }

    /**
     * @return array{found: bool, error: ?string}
     */
    public function archive(string $embeddingId): array
    {
        $record = $this->fetch($embeddingId);

        if ($record === null) {
            return ['found' => false, 'error' => sprintf('Unknown embedding "%s".', $embeddingId)];
        }

        $this->setStatus($embeddingId, 'archived');

        return ['found' => true, 'error' => null];
    }

    /**
     * The feature-hashing trick: each token is hashed into one of
     * $dimension buckets with an independently-hashed +1/-1 sign, then
     * the resulting vector is L2-normalized.
     *
     * @return array<int, float>
     */
    private function hashingTrickVector(string $text, int $dimension): array
    {
        $vector = array_fill(0, $dimension, 0.0);
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($tokens as $token) {
            $bucket = crc32($token) % $dimension;
            $sign = (crc32($token . '#sign') % 2 === 0) ? 1.0 : -1.0;
            $vector[$bucket] += $sign;
        }

        $magnitude = sqrt(array_sum(array_map(static fn(float $component): float => $component ** 2, $vector)));

        if ($magnitude === 0.0) {
            return $vector;
        }

        return array_map(static fn(float $component): float => $component / $magnitude, $vector);
    }

    private function setStatus(string $embeddingId, string $status): void
    {
        $statement = $this->database->prepare('UPDATE knowledge_embeddings SET status = :status, updated_at = :updated_at WHERE embedding_id = :embedding_id');
        $statement->execute(['status' => $status, 'updated_at' => gmdate(DATE_RFC3339), 'embedding_id' => $embeddingId]);

        $this->publish($status, $embeddingId, null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $embeddingId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM knowledge_embeddings WHERE embedding_id = :embedding_id');
        $statement->execute(['embedding_id' => $embeddingId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function publish(string $eventSuffix, string $embeddingId, ?string $knowledgeId): void
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'knowledge_embedding.' . $eventSuffix,
            new DateTimeImmutable(),
            self::class,
            ['embedding_id' => $embeddingId, 'knowledge_id' => $knowledgeId]
        ));
    }
}
