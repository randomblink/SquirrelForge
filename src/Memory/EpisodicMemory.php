<?php

declare(strict_types=1);

namespace SquirrelForge\Memory;

use SquirrelForge\Contracts\MemoryStoreInterface;

/**
 * Records the historical experience of completed tasks as a durable
 * reference record, per 18_MEMORY/EPISODIC-MEMORY.md.
 *
 * "Episodic Memory records; it does not evaluate" is upheld literally:
 * every field this class stores that has an authoritative owner
 * elsewhere (task, goal, execution plan, agents involved, validation
 * result, decision references) is accepted as a caller-supplied
 * reference and stored verbatim -- this class never calls
 * EngineValidation, DecisionEngine, or any State Manager runtime
 * itself to re-derive what those already own. Only Outcome Summary,
 * Completion Timestamp, and Retrieval Tags are this class's own
 * authoritative content, exactly as the spec's own Contents table
 * states.
 *
 * "Hand the record to Memory Index for indexing" has no real
 * MEMORY-INDEX.md implementation to call, so this class delegates
 * storage and lookup to the already-real, generic MemoryStoreInterface
 * (InMemoryStore/FileMemoryStore) instead -- the same "consume the real
 * infrastructure that exists, rather than fabricate the specific
 * component that doesn't" substitution used throughout this codebase
 * (e.g. Log Manager delegating to SqliteDocumentStorage rather than
 * inventing its own index).
 *
 * recordCompletedTask() requires the four fields that make an episodic
 * entry meaningful at all (task, goal, outcome summary, validation
 * reference) -- an entry can't honestly reference "the validation
 * result" without one. Retrieval tagging includes one small, real,
 * deterministic enrichment beyond passthrough: the validation
 * reference's own value and the presence of any decision reference are
 * automatically folded into the tag set, merged with the caller's own
 * tags, so a search by validation outcome or "has a decision behind it"
 * works without the caller having to remember to tag for it manually.
 *
 * Like ProjectLoader, this class has no database of its own: it owns no
 * persistence beyond the injected MemoryStoreInterface.
 */
final class EpisodicMemory
{
    private const REQUIRED_FIELDS = ['task_reference', 'goal_reference', 'outcome_summary', 'validation_result_reference'];

    public function __construct(private readonly ?MemoryStoreInterface $store = null)
    {
    }

    /**
     * @param array{task_reference: string, goal_reference: string, outcome_summary: string, validation_result_reference: string, execution_plan_reference?: ?string, agents_involved?: array<int, string>, decision_references?: array<int, string>, retrieval_tags?: array<int, string>, completed_at?: ?string} $entry
     * @return array{memory_id: ?string, outcome: string, error: ?string}
     */
    public function recordCompletedTask(array $entry): array
    {
        if ($this->store === null) {
            return ['memory_id' => null, 'outcome' => 'not_configured', 'error' => 'Memory Store is not configured.'];
        }

        foreach (self::REQUIRED_FIELDS as $field) {
            if (($entry[$field] ?? '') === '') {
                return ['memory_id' => null, 'outcome' => 'invalid_entry', 'error' => sprintf('"%s" is required.', $field)];
            }
        }

        $tags = array_values(array_unique([...($entry['retrieval_tags'] ?? []), ...$this->autoTags($entry)]));

        $record = [
            'type' => 'episodic',
            'task_reference' => $entry['task_reference'],
            'goal_reference' => $entry['goal_reference'],
            'execution_plan_reference' => $entry['execution_plan_reference'] ?? null,
            'agents_involved' => $entry['agents_involved'] ?? [],
            'outcome_summary' => $entry['outcome_summary'],
            'validation_result_reference' => $entry['validation_result_reference'],
            'decision_references' => $entry['decision_references'] ?? [],
            'completed_at' => $entry['completed_at'] ?? gmdate(DATE_RFC3339),
            'retrieval_tags' => $tags,
        ];

        return ['memory_id' => $this->store->store($record), 'outcome' => 'recorded', 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function retrieve(string $memoryId): ?array
    {
        return $this->store?->retrieve($memoryId);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array<int, array<string, mixed>>
     */
    public function search(array $criteria = []): array
    {
        return $this->store?->search(['type' => 'episodic', ...$criteria]) ?? [];
    }

    /**
     * @param array{validation_result_reference: string, decision_references?: array<int, string>} $entry
     * @return array<int, string>
     */
    private function autoTags(array $entry): array
    {
        $tags = ['validation:' . strtolower($entry['validation_result_reference'])];

        if (($entry['decision_references'] ?? []) !== []) {
            $tags[] = 'has_decision_reference';
        }

        return $tags;
    }
}
