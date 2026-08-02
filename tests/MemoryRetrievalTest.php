<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Memory\EpisodicMemory;
use SquirrelForge\Memory\InMemoryStore;
use SquirrelForge\Memory\MemoryRetrieval;
use SquirrelForge\Memory\ProjectMemory;
use SquirrelForge\Memory\SemanticMemory;
use SquirrelForge\Memory\SqliteMemoryIndex;
use SquirrelForge\Memory\WorkingMemory;

final class MemoryRetrievalTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }
    }

    private function index(): SqliteMemoryIndex
    {
        $path = sys_get_temp_dir() . '/squirrelforge-memory-retrieval-' . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return new SqliteMemoryIndex($path);
    }

    public function testSearchWithoutAnIndexIsNotConfigured(): void
    {
        $retrieval = new MemoryRetrieval();

        $result = $retrieval->search(['checkout']);

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testSearchWithNoQueryTermsIsRejected(): void
    {
        $retrieval = new MemoryRetrieval($this->index());

        $result = $retrieval->search([]);

        $this->assertSame('no_query', $result['outcome']);
    }

    public function testSearchRejectsAnUnknownMemoryType(): void
    {
        $retrieval = new MemoryRetrieval($this->index());

        $result = $retrieval->search(['checkout'], ['memory_types' => ['knowledge']]);

        $this->assertSame('invalid_memory_type', $result['outcome']);
    }

    public function testSearchWithNoMatchesReturnsEmptyResults(): void
    {
        $index = $this->index();
        $index->indexRecord('episodic', 'record_1', ['title' => 'Unrelated topic']);
        $retrieval = new MemoryRetrieval($index);

        $result = $retrieval->search(['checkout']);

        $this->assertSame('searched', $result['outcome']);
        $this->assertSame([], $result['results']);
    }

    public function testSearchRanksHigherKeywordOverlapFirstAndRetrievesTheRealRecord(): void
    {
        $index = $this->index();
        $episodicStore = new InMemoryStore();
        $episodicMemory = new EpisodicMemory($episodicStore);
        $semanticMemory = new SemanticMemory(new InMemoryStore());

        $weakMatch = $episodicMemory->recordCompletedTask([
            'task_reference' => 'task_1', 'goal_reference' => 'goal_1',
            'outcome_summary' => 'Fixed the checkout bug.', 'validation_result_reference' => 'ACCEPTED',
        ]);
        $strongMatch = $semanticMemory->storeApprovedKnowledge([
            'category' => 'Solutions', 'title' => 'Checkout discount checkout validation pattern',
            'description' => 'Reusable checkout validation approach.', 'source_reference' => 'src_1', 'validation_reference' => 'ACCEPTED',
        ]);
        $index->indexRecord('episodic', $weakMatch['memory_id'], ['title' => 'Checkout bug fix', 'keywords' => ['bugfix']]);
        $index->indexRecord('semantic', $strongMatch['record_id'], ['title' => 'Checkout discount checkout validation pattern', 'keywords' => ['checkout', 'discount']]);

        $retrieval = new MemoryRetrieval($index, episodicMemory: $episodicMemory, semanticMemory: $semanticMemory);
        $result = $retrieval->search(['checkout']);

        $this->assertSame('searched', $result['outcome']);
        $this->assertCount(2, $result['results']);
        $this->assertSame('semantic', $result['results'][0]['memory_type']);
        $this->assertGreaterThan($result['results'][1]['score'], $result['results'][0]['score']);
        $this->assertSame('Fixed the checkout bug.', $result['results'][1]['record']['outcome_summary']);
        $this->assertSame('Checkout discount checkout validation pattern', $result['results'][0]['record']['title']);
    }

    public function testSearchFiltersByMemoryTypesOption(): void
    {
        $index = $this->index();
        $index->indexRecord('episodic', 'record_1', ['title' => 'Checkout episodic entry']);
        $index->indexRecord('semantic', 'record_2', ['title' => 'Checkout semantic entry']);
        $retrieval = new MemoryRetrieval($index);

        $result = $retrieval->search(['checkout'], ['memory_types' => ['semantic']]);

        $this->assertCount(1, $result['results']);
        $this->assertSame('semantic', $result['results'][0]['memory_type']);
    }

    public function testSearchRespectsTheLimitOption(): void
    {
        $index = $this->index();
        $index->indexRecord('episodic', 'record_1', ['title' => 'Checkout one']);
        $index->indexRecord('episodic', 'record_2', ['title' => 'Checkout two']);
        $index->indexRecord('episodic', 'record_3', ['title' => 'Checkout three']);
        $retrieval = new MemoryRetrieval($index);

        $result = $retrieval->search(['checkout'], ['limit' => 2]);

        $this->assertCount(2, $result['results']);
    }

    public function testSearchRetrievesWorkingMemoryAndProjectMemoryByTheirOwnNaturalKeys(): void
    {
        $index = $this->index();
        $workingMemory = new WorkingMemory();
        $workingMemory->initialize('task_1', ['active_goal' => 'checkout_goal']);
        $projectMemory = new ProjectMemory(new InMemoryStore());
        $projectMemory->initializeProject('CheckoutProject');

        $index->indexRecord('working', 'task_1', ['title' => 'Checkout active context']);
        $index->indexRecord('project', 'CheckoutProject', ['title' => 'Checkout project record']);

        $retrieval = new MemoryRetrieval($index, workingMemory: $workingMemory, projectMemory: $projectMemory);
        $result = $retrieval->search(['checkout']);

        $working = current(array_filter($result['results'], static fn(array $r): bool => $r['memory_type'] === 'working'));
        $project = current(array_filter($result['results'], static fn(array $r): bool => $r['memory_type'] === 'project'));
        $this->assertSame('checkout_goal', $working['record']['active_goal']);
        $this->assertSame('CheckoutProject', $project['record']['project_name']);
    }

    public function testGetByReferenceIsPubliclyCallableForAnExactLookup(): void
    {
        $index = $this->index();
        $semanticMemory = new SemanticMemory(new InMemoryStore());
        $stored = $semanticMemory->storeApprovedKnowledge([
            'category' => 'Solutions', 'title' => 'Checkout validation pattern',
            'description' => 'desc', 'source_reference' => 'src_1', 'validation_reference' => 'ACCEPTED',
        ]);
        $retrieval = new MemoryRetrieval($index, semanticMemory: $semanticMemory);

        $record = $retrieval->getByReference('semantic', $stored['record_id']);

        $this->assertSame('Checkout validation pattern', $record['title']);
    }
}
