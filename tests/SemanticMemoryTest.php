<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Memory\InMemoryStore;
use SquirrelForge\Memory\SemanticMemory;

final class SemanticMemoryTest extends TestCase
{
    private function validRecord(array $overrides = []): array
    {
        return array_merge([
            'category' => 'Patterns',
            'title' => 'Consume, don\'t compute',
            'description' => 'Accept caller-supplied evaluations for judgments no component can honestly automate.',
            'source_reference' => 'knowledge_ref_1',
            'validation_reference' => 'ACCEPTED',
        ], $overrides);
    }

    public function testStoreApprovedKnowledgeWithoutAStoreIsNotConfigured(): void
    {
        $memory = new SemanticMemory();

        $result = $memory->storeApprovedKnowledge($this->validRecord());

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testStoreApprovedKnowledgeRejectsAMissingRequiredField(): void
    {
        $memory = new SemanticMemory(new InMemoryStore());
        $record = $this->validRecord();
        unset($record['description']);

        $result = $memory->storeApprovedKnowledge($record);

        $this->assertSame('invalid_record', $result['outcome']);
        $this->assertStringContainsString('description', $result['error']);
    }

    public function testStoreApprovedKnowledgeRejectsAnUnrecognizedCategory(): void
    {
        $memory = new SemanticMemory(new InMemoryStore());

        $result = $memory->storeApprovedKnowledge($this->validRecord(['category' => 'Miscellaneous']));

        $this->assertSame('invalid_category', $result['outcome']);
    }

    public function testStoreApprovedKnowledgeSucceedsAndAutoFillsLastReviewed(): void
    {
        $memory = new SemanticMemory(new InMemoryStore());

        $result = $memory->storeApprovedKnowledge($this->validRecord());

        $this->assertSame('stored', $result['outcome']);
        $stored = $memory->retrieve($result['record_id']);
        $this->assertSame('semantic', $stored['type']);
        $this->assertSame('Patterns', $stored['category']);
        $this->assertNotNull($stored['last_reviewed']);
    }

    public function testStoreApprovedKnowledgePreservesAnExplicitLastReviewedDate(): void
    {
        $memory = new SemanticMemory(new InMemoryStore());

        $result = $memory->storeApprovedKnowledge($this->validRecord(['last_reviewed' => '2026-01-01T00:00:00+00:00']));

        $this->assertSame('2026-01-01T00:00:00+00:00', $memory->retrieve($result['record_id'])['last_reviewed']);
    }

    public function testRetrieveReturnsNullForAnUnknownRecord(): void
    {
        $memory = new SemanticMemory(new InMemoryStore());

        $this->assertNull($memory->retrieve('record_ghost'));
    }

    public function testRetrieveWithoutAStoreReturnsNull(): void
    {
        $memory = new SemanticMemory();

        $this->assertNull($memory->retrieve('record_1'));
    }

    public function testSearchFiltersByTypeAndAdditionalCriteria(): void
    {
        $store = new InMemoryStore();
        $memory = new SemanticMemory($store);
        $memory->storeApprovedKnowledge($this->validRecord(['category' => 'Patterns']));
        $memory->storeApprovedKnowledge($this->validRecord(['category' => 'Solutions']));
        $store->store(['type' => 'episodic', 'task_reference' => 'unrelated']);

        $result = $memory->search(['category' => 'Solutions']);

        $this->assertCount(1, $result);
        $this->assertSame('Solutions', $result[0]['category']);
    }

    public function testMarkReviewedWithoutAStoreIsNotConfigured(): void
    {
        $memory = new SemanticMemory();

        $this->assertSame('not_configured', $memory->markReviewed('record_1')['outcome']);
    }

    public function testMarkReviewedRejectsAnUnknownRecord(): void
    {
        $memory = new SemanticMemory(new InMemoryStore());

        $this->assertSame('not_found', $memory->markReviewed('record_ghost')['outcome']);
    }

    public function testMarkReviewedUpdatesTheLastReviewedDate(): void
    {
        $memory = new SemanticMemory(new InMemoryStore());
        $result = $memory->storeApprovedKnowledge($this->validRecord(['last_reviewed' => '2026-01-01T00:00:00+00:00']));

        $reviewResult = $memory->markReviewed($result['record_id'], '2026-06-01T00:00:00+00:00');

        $this->assertSame('reviewed', $reviewResult['outcome']);
        $this->assertSame('2026-06-01T00:00:00+00:00', $memory->retrieve($result['record_id'])['last_reviewed']);
    }
}
