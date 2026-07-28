<?php

declare(strict_types=1);

namespace SquirrelForge\Knowledge;

use DateTimeImmutable;
use SquirrelForge\Contracts\EventBusInterface;
use SquirrelForge\Events\Event;

/**
 * Executes semantic retrieval over registered, validated, and authorized
 * knowledge assets, per 25_KNOWLEDGE/SEMANTIC-SEARCH.md.
 *
 * This class has no database of its own -- not even a mutable state
 * table like SqliteDocumentRepository's, since search results are
 * inherently ephemeral per-request output, not something this spec
 * assigns it to own. Activity is only ever announced via the optional
 * EventBusInterface.
 *
 * The query is turned into a vector via SqliteEmbeddingsManager::
 * queryVector() -- the same real feature-hashing math generate() uses,
 * but without creating a persisted embedding record, since this class
 * must not generate embeddings itself. Candidate assets come from
 * SqliteDocumentRepository::search(), with `archived` references
 * excluded after the fact (a `draft` reference is still searchable --
 * it just has no storage_reference yet -- so the exclusion is
 * specifically "not archived", not "must be active"), which is the
 * real implementation of "favor current versions over deprecated
 * content".
 * Similarity is real cosine similarity computed directly against each
 * candidate's persisted vector (fetched via SqliteEmbeddingsManager::
 * vector()) -- not a fabricated score.
 *
 * "Consume authorization decisions" is implemented by reusing
 * SqliteDocumentRepository's own protected-reference gate rather than
 * reinventing one: readMetadata() is called for every candidate with
 * whatever authorization result the caller supplies, and Document
 * Repository's existing logic decides whether a protected reference is
 * visible. This class never computes that decision itself.
 *
 * "Support deterministic ranking when scores are equal" is a real tie-
 * break: results are sorted by score, then by document_id, so repeated
 * searches over unchanged data return results in a stable order.
 *
 * Of the spec's six listed retrieval methods, only Semantic Search is
 * implemented here -- Metadata/Filtered search is already
 * SqliteDocumentRepository::search() directly, and Keyword/Hybrid/
 * Citation search would need a real token index or citation graph that
 * doesn't exist. Trust scoring from a Knowledge Validator and version
 * awareness beyond "active vs. archived" are out of scope for the same
 * reason: neither KNOWLEDGE-VALIDATOR nor KNOWLEDGE-VERSIONING has code
 * to source a real signal from.
 */
final class SemanticSearchManager
{
    public function __construct(
        private readonly ?SqliteEmbeddingsManager $embeddings = null,
        private readonly ?SqliteDocumentRepository $documents = null,
        private readonly ?EventBusInterface $events = null
    ) {
    }

    /**
     * @param array{type?: string, owner?: string, top_k?: int, score_threshold?: ?float, dimension?: int, authorization_result?: array{authorized?: bool}|null, requesting_component?: ?string} $options
     * @return array{results: array<int, array{document_id: string, title: string, type: string, score: float, citation_references: array<int, string>, storage_reference: ?string}>, result_count: int, error: ?string}
     */
    public function search(string $query, array $options = []): array
    {
        if ($this->embeddings === null) {
            return $this->finish($query, [], 'Embeddings Manager is not configured.', $options);
        }

        if ($this->documents === null) {
            return $this->finish($query, [], 'Document Repository is not configured.', $options);
        }

        $dimension = $options['dimension'] ?? SqliteEmbeddingsManager::DEFAULT_DIMENSION;
        $queryVector = $this->embeddings->queryVector($query, $dimension);

        $candidates = $this->documents->search([
            ...isset($options['type']) ? ['type' => $options['type']] : [],
            ...isset($options['owner']) ? ['owner' => $options['owner']] : [],
        ]);

        $scored = [];

        foreach ($candidates as $candidate) {
            if ($candidate['status'] === 'archived') {
                continue;
            }

            $metadata = $this->documents->readMetadata($candidate['document_id'], $options['authorization_result'] ?? null);

            if (!$metadata['found']) {
                continue;
            }

            $activeEmbedding = $this->embeddings->activeFor($candidate['document_id']);

            if ($activeEmbedding === null) {
                continue;
            }

            $vector = $this->embeddings->vector($activeEmbedding['embedding_id']);

            if ($vector === null) {
                continue;
            }

            $score = $this->cosineSimilarity($queryVector, $vector);

            if (isset($options['score_threshold']) && $score < $options['score_threshold']) {
                continue;
            }

            $scored[] = [
                'document_id' => $metadata['document_id'],
                'title' => $metadata['title'],
                'type' => $metadata['type'],
                'score' => $score,
                'citation_references' => $metadata['citation_references'],
                'storage_reference' => $metadata['storage_reference'],
            ];
        }

        usort($scored, static function (array $a, array $b): int {
            return $b['score'] <=> $a['score'] ?: $a['document_id'] <=> $b['document_id'];
        });

        $results = array_slice($scored, 0, $options['top_k'] ?? 10);

        return $this->finish($query, $results, null, $options);
    }

    /**
     * @param array<int, float|int> $a
     * @param array<int, float|int> $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        foreach (array_values($a) as $index => $value) {
            $dot += $value * $b[$index];
            $magnitudeA += $value ** 2;
            $magnitudeB += $b[$index] ** 2;
        }

        if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($magnitudeA) * sqrt($magnitudeB));
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @param array<string, mixed> $options
     * @return array{results: array<int, array<string, mixed>>, result_count: int, error: ?string}
     */
    private function finish(string $query, array $results, ?string $error, array $options): array
    {
        $this->events?->dispatch(new Event(
            uniqid('evt_', true),
            'semantic_search.completed',
            new DateTimeImmutable(),
            self::class,
            ['query' => $query, 'result_count' => count($results), 'requesting_component' => $options['requesting_component'] ?? null]
        ));

        return ['results' => $results, 'result_count' => count($results), 'error' => $error];
    }
}
