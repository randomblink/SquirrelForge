<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Memory\EpisodicMemory;
use SquirrelForge\Memory\InMemoryStore;

final class EpisodicMemoryTest extends TestCase
{
    private function validEntry(array $overrides = []): array
    {
        return array_merge([
            'task_reference' => 'task_1',
            'goal_reference' => 'goal_1',
            'outcome_summary' => 'Checkout discount field shipped and validated.',
            'validation_result_reference' => 'ACCEPTED',
        ], $overrides);
    }

    public function testRecordCompletedTaskWithoutAStoreIsNotConfigured(): void
    {
        $memory = new EpisodicMemory();

        $result = $memory->recordCompletedTask($this->validEntry());

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testRecordCompletedTaskRejectsAMissingRequiredField(): void
    {
        $memory = new EpisodicMemory(new InMemoryStore());
        $entry = $this->validEntry();
        unset($entry['outcome_summary']);

        $result = $memory->recordCompletedTask($entry);

        $this->assertSame('invalid_entry', $result['outcome']);
        $this->assertStringContainsString('outcome_summary', $result['error']);
    }

    public function testRecordCompletedTaskDelegatesRealPersistenceToTheMemoryStore(): void
    {
        $store = new InMemoryStore();
        $memory = new EpisodicMemory($store);

        $result = $memory->recordCompletedTask($this->validEntry());

        $this->assertSame('recorded', $result['outcome']);
        $this->assertNotNull($result['memory_id']);
        $stored = $memory->retrieve($result['memory_id']);
        $this->assertSame('task_1', $stored['task_reference']);
        $this->assertSame('episodic', $stored['type']);
        $this->assertNotNull($stored['completed_at']);
    }

    public function testRecordCompletedTaskPreservesAnExplicitCompletionTimestamp(): void
    {
        $memory = new EpisodicMemory(new InMemoryStore());

        $result = $memory->recordCompletedTask($this->validEntry(['completed_at' => '2026-01-01T00:00:00+00:00']));

        $stored = $memory->retrieve($result['memory_id']);
        $this->assertSame('2026-01-01T00:00:00+00:00', $stored['completed_at']);
    }

    public function testAutoTagsIncludeTheValidationOutcomeAndDecisionPresence(): void
    {
        $memory = new EpisodicMemory(new InMemoryStore());

        $result = $memory->recordCompletedTask($this->validEntry([
            'decision_references' => ['decision_1'],
            'retrieval_tags' => ['checkout'],
        ]));

        $stored = $memory->retrieve($result['memory_id']);
        $this->assertContains('validation:accepted', $stored['retrieval_tags']);
        $this->assertContains('has_decision_reference', $stored['retrieval_tags']);
        $this->assertContains('checkout', $stored['retrieval_tags']);
    }

    public function testAutoTagsOmitTheDecisionTagWhenNoDecisionReferenceIsSupplied(): void
    {
        $memory = new EpisodicMemory(new InMemoryStore());

        $result = $memory->recordCompletedTask($this->validEntry());

        $stored = $memory->retrieve($result['memory_id']);
        $this->assertNotContains('has_decision_reference', $stored['retrieval_tags']);
    }

    public function testRetrieveReturnsNullForAnUnknownMemoryId(): void
    {
        $memory = new EpisodicMemory(new InMemoryStore());

        $this->assertNull($memory->retrieve('mem_ghost'));
    }

    public function testRetrieveWithoutAStoreReturnsNull(): void
    {
        $memory = new EpisodicMemory();

        $this->assertNull($memory->retrieve('mem_1'));
    }

    public function testSearchFiltersByTypeAndAdditionalCriteria(): void
    {
        $store = new InMemoryStore();
        $memory = new EpisodicMemory($store);
        $memory->recordCompletedTask($this->validEntry(['task_reference' => 'task_1']));
        $memory->recordCompletedTask($this->validEntry(['task_reference' => 'task_2']));
        $store->store(['type' => 'semantic', 'content' => 'unrelated']);

        $result = $memory->search(['task_reference' => 'task_1']);

        $this->assertCount(1, $result);
        $this->assertSame('task_1', $result[0]['task_reference']);
    }

    public function testSearchWithoutAStoreReturnsEmpty(): void
    {
        $memory = new EpisodicMemory();

        $this->assertSame([], $memory->search());
    }
}
