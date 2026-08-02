<?php

declare(strict_types=1);

namespace SquirrelForge\Memory;

use SquirrelForge\Contracts\MemoryStoreInterface;

/**
 * Stores durable representations of already-approved, generalized
 * knowledge, per 18_MEMORY/SEMANTIC-MEMORY.md.
 *
 * "Semantic Memory stores; it does not validate, classify, or promote
 * candidate knowledge itself" is upheld literally: storeApprovedKnowledge()
 * requires `category`, `source_reference`, and `validation_reference` to
 * already be present on the incoming record and stores them verbatim --
 * this class never calls EngineValidation or KnowledgeManager itself to
 * decide them. The one real structural check it does perform is that
 * `category` is one of the seven values 25_KNOWLEDGE/KNOWLEDGE-MANAGER.md
 * already defines (Standards, Patterns, Architecture, Workflows,
 * Templates, Best Practices, Solutions) -- rejecting an unrecognized
 * category is confirming the record was actually classified upstream,
 * not performing classification here.
 *
 * "Index the record through Memory Index" and "make it available for
 * retrieval through Memory Retrieval" have no real implementations to
 * call, so this class delegates storage and lookup to the already-real,
 * generic MemoryStoreInterface -- the same substitution EpisodicMemory
 * and WorkingMemory already use for the same unbuilt dependency.
 *
 * markReviewed() is the one genuinely mutable operation this class
 * owns: "Update Last Reviewed when the record is periodically
 * reviewed" is real content Semantic Memory itself authors, per the
 * spec's own Record table, so it is the only field this class ever
 * writes to an existing record after it's stored.
 */
final class SemanticMemory
{
    private const CATEGORIES = ['Standards', 'Patterns', 'Architecture', 'Workflows', 'Templates', 'Best Practices', 'Solutions'];
    private const REQUIRED_FIELDS = ['category', 'title', 'description', 'source_reference', 'validation_reference'];

    public function __construct(private readonly ?MemoryStoreInterface $store = null)
    {
    }

    /**
     * @param array{category: string, title: string, description: string, source_reference: string, validation_reference: string, related_workflows?: array<int, string>, last_reviewed?: ?string} $record
     * @return array{record_id: ?string, outcome: string, error: ?string}
     */
    public function storeApprovedKnowledge(array $record): array
    {
        if ($this->store === null) {
            return ['record_id' => null, 'outcome' => 'not_configured', 'error' => 'Memory Store is not configured.'];
        }

        foreach (self::REQUIRED_FIELDS as $field) {
            if (($record[$field] ?? '') === '') {
                return ['record_id' => null, 'outcome' => 'invalid_record', 'error' => sprintf('"%s" is required.', $field)];
            }
        }

        if (!in_array($record['category'], self::CATEGORIES, true)) {
            return ['record_id' => null, 'outcome' => 'invalid_category', 'error' => sprintf('Unknown knowledge category "%s".', $record['category'])];
        }

        $stored = [
            'type' => 'semantic',
            'category' => $record['category'],
            'title' => $record['title'],
            'description' => $record['description'],
            'source_reference' => $record['source_reference'],
            'validation_reference' => $record['validation_reference'],
            'related_workflows' => $record['related_workflows'] ?? [],
            'last_reviewed' => $record['last_reviewed'] ?? gmdate(DATE_RFC3339),
        ];

        return ['record_id' => $this->store->store($stored), 'outcome' => 'stored', 'error' => null];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function retrieve(string $recordId): ?array
    {
        return $this->store?->retrieve($recordId);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return array<int, array<string, mixed>>
     */
    public function search(array $criteria = []): array
    {
        return $this->store?->search(['type' => 'semantic', ...$criteria]) ?? [];
    }

    /**
     * @return array{outcome: string, error: ?string}
     */
    public function markReviewed(string $recordId, ?string $reviewedAt = null): array
    {
        if ($this->store === null) {
            return ['outcome' => 'not_configured', 'error' => 'Memory Store is not configured.'];
        }

        if ($this->store->retrieve($recordId) === null) {
            return ['outcome' => 'not_found', 'error' => sprintf('Unknown record "%s".', $recordId)];
        }

        $updated = $this->store->update($recordId, ['last_reviewed' => $reviewedAt ?? gmdate(DATE_RFC3339)]);

        return $updated
            ? ['outcome' => 'reviewed', 'error' => null]
            : ['outcome' => 'update_failed', 'error' => sprintf('Failed to update record "%s".', $recordId)];
    }
}
