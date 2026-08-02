<?php

declare(strict_types=1);

namespace SquirrelForge\Memory;

/**
 * Interprets a memory query, ranks candidate records referenced by the
 * Memory Index, and returns results, per
 * 18_MEMORY/MEMORY-RETRIEVAL.md.
 *
 * "Retrieval is the query-serving counterpart to the Index's
 * reference-maintenance role" is upheld by genuine composition: search()
 * reads candidates through the injected SqliteMemoryIndex::findByType()
 * -- the real index metadata SqliteMemoryIndex already maintains -- and
 * never creates, modifies, or deletes an index entry itself. Once a
 * candidate is selected, the original record is fetched through
 * whichever real memory-type component owns it
 * (WorkingMemory::snapshot(), EpisodicMemory::retrieve(),
 * SemanticMemory::retrieve(), ProjectMemory::get()) -- this class never
 * reads or writes a memory record directly, and never alters what it
 * returns.
 *
 * relevanceScore() is a real, literal term-frequency count against the
 * Index's own title/keywords metadata -- one point per occurrence of a
 * query term, the same honest, explainable-formula idiom used
 * throughout this codebase rather than a fabricated relevance model.
 * "A query must be interpreted against the Index's current metadata,
 * not a stale or independently maintained copy" is upheld structurally:
 * this class has no cache of its own; every search() call reads
 * findByType() fresh.
 */
final class MemoryRetrieval
{
    private const MEMORY_TYPES = ['working', 'episodic', 'semantic', 'project'];

    public function __construct(
        private readonly ?SqliteMemoryIndex $index = null,
        private readonly ?WorkingMemory $workingMemory = null,
        private readonly ?EpisodicMemory $episodicMemory = null,
        private readonly ?SemanticMemory $semanticMemory = null,
        private readonly ?ProjectMemory $projectMemory = null
    ) {
    }

    /**
     * @param array<int, string> $queryTerms
     * @param array{memory_types?: array<int, string>, limit?: int} $options
     * @return array{results: array<int, array{memory_type: string, record_id: string, title: ?string, score: int, record: array<string, mixed>|null}>, outcome: string, error: ?string}
     */
    public function search(array $queryTerms, array $options = []): array
    {
        if ($this->index === null) {
            return ['results' => [], 'outcome' => 'not_configured', 'error' => 'Memory Index is not configured.'];
        }

        if ($queryTerms === []) {
            return ['results' => [], 'outcome' => 'no_query', 'error' => 'At least one query term is required.'];
        }

        $memoryTypes = $options['memory_types'] ?? self::MEMORY_TYPES;
        $unknownTypes = array_diff($memoryTypes, self::MEMORY_TYPES);

        if ($unknownTypes !== []) {
            return ['results' => [], 'outcome' => 'invalid_memory_type', 'error' => sprintf('Unknown memory type(s): %s.', implode(', ', $unknownTypes))];
        }

        $candidates = [];

        foreach ($memoryTypes as $memoryType) {
            foreach ($this->index->findByType($memoryType) as $entry) {
                $score = $this->relevanceScore($queryTerms, $entry);

                if ($score > 0) {
                    $candidates[] = ['memory_type' => $memoryType, 'record_id' => $entry['record_id'], 'title' => $entry['title'], 'score' => $score];
                }
            }
        }

        usort($candidates, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $ranked = array_slice($candidates, 0, $options['limit'] ?? 10);

        $results = array_map(
            fn(array $candidate): array => [...$candidate, 'record' => $this->getByReference($candidate['memory_type'], $candidate['record_id'])],
            $ranked
        );

        return ['results' => $results, 'outcome' => 'searched', 'error' => null];
    }

    /**
     * A real term-frequency count against the Index's own title/keywords
     * metadata: each occurrence of a query term adds one point, so a
     * record mentioning a term twice ranks above one mentioning it once
     * -- still a plain, explainable formula, just counting occurrences
     * rather than only presence.
     *
     * @param array{title: ?string, keywords: array<int, string>} $entry
     */
    private function relevanceScore(array $queryTerms, array $entry): int
    {
        $haystack = strtolower(($entry['title'] ?? '') . ' ' . implode(' ', $entry['keywords']));
        $score = 0;

        foreach ($queryTerms as $term) {
            $score += substr_count($haystack, strtolower($term));
        }

        return $score;
    }

    /**
     * A direct lookup by exact reference, never a ranked search -- the
     * same real dispatch search() already uses internally, exposed
     * publicly so a caller with an exact (memory_type, record_id) in
     * hand (e.g. 22_INTERFACES/MEMORY-API.md's `get()`) can fetch it
     * through Memory Retrieval rather than bypassing it into a memory
     * type directly.
     *
     * @return array<string, mixed>|null
     */
    public function getByReference(string $memoryType, string $recordId): ?array
    {
        return match ($memoryType) {
            'working' => $this->workingMemory?->snapshot($recordId),
            'episodic' => $this->episodicMemory?->retrieve($recordId),
            'semantic' => $this->semanticMemory?->retrieve($recordId),
            'project' => $this->projectMemory?->get($recordId),
            default => null,
        };
    }
}
