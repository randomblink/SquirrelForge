<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Memory\SqliteMemoryIndex;

final class SqliteMemoryIndexTest extends TestCase
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
        $path = sys_get_temp_dir() . '/squirrelforge-memory-index-' . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return new SqliteMemoryIndex($path);
    }

    public function testIndexRecordRejectsAnUnindexableMemoryType(): void
    {
        $index = $this->index();

        $result = $index->indexRecord('knowledge', 'record_1');

        $this->assertSame('invalid_memory_type', $result['outcome']);
    }

    public function testIndexRecordCreatesANewEntry(): void
    {
        $index = $this->index();

        $result = $index->indexRecord('episodic', 'record_1', ['title' => 'Checkout fix', 'keywords' => ['checkout']]);

        $this->assertSame('created', $result['outcome']);
        $entry = $index->get('episodic', 'record_1');
        $this->assertSame('Checkout fix', $entry['title']);
        $this->assertSame(['checkout'], $entry['keywords']);
        $this->assertSame([], $entry['related_records']);
    }

    public function testIndexRecordUpdatesTheSameEntryInsteadOfDuplicating(): void
    {
        $index = $this->index();
        $first = $index->indexRecord('episodic', 'record_1', ['title' => 'Original title']);

        $second = $index->indexRecord('episodic', 'record_1', ['title' => 'Updated title', 'keywords' => ['discount']]);

        $this->assertSame('updated', $second['outcome']);
        $this->assertSame($first['index_id'], $second['index_id']);
        $entry = $index->get('episodic', 'record_1');
        $this->assertSame('Updated title', $entry['title']);
        $this->assertSame(['discount'], $entry['keywords']);
    }

    public function testGetReturnsNullForAnUnknownEntry(): void
    {
        $index = $this->index();

        $this->assertNull($index->get('episodic', 'record_ghost'));
    }

    public function testLinkRelatedRequiresAnExistingBaseEntry(): void
    {
        $index = $this->index();

        $result = $index->linkRelated('episodic', 'record_1', 'semantic', 'record_2');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testLinkRelatedTracksTheRelationship(): void
    {
        $index = $this->index();
        $index->indexRecord('episodic', 'record_1');

        $result = $index->linkRelated('episodic', 'record_1', 'semantic', 'record_2');

        $this->assertSame('linked', $result['outcome']);
        $this->assertSame([['memory_type' => 'semantic', 'record_id' => 'record_2']], $index->findRelated('episodic', 'record_1'));
    }

    public function testLinkRelatedDeduplicatesTheSameRelationship(): void
    {
        $index = $this->index();
        $index->indexRecord('episodic', 'record_1');

        $index->linkRelated('episodic', 'record_1', 'semantic', 'record_2');
        $index->linkRelated('episodic', 'record_1', 'semantic', 'record_2');

        $this->assertCount(1, $index->findRelated('episodic', 'record_1'));
    }

    public function testFindRelatedForAnUnknownEntryIsEmpty(): void
    {
        $index = $this->index();

        $this->assertSame([], $index->findRelated('episodic', 'record_ghost'));
    }

    public function testRemoveIndexEntryRemovesOnlyTheIndexNeverTheUnderlyingRecord(): void
    {
        $index = $this->index();
        $index->indexRecord('episodic', 'record_1');

        $result = $index->removeIndexEntry('episodic', 'record_1');

        $this->assertSame('removed', $result['outcome']);
        $this->assertNull($index->get('episodic', 'record_1'));
    }

    public function testRemoveIndexEntryOnAnUnknownEntryIsNotFound(): void
    {
        $index = $this->index();

        $this->assertSame('not_found', $index->removeIndexEntry('episodic', 'record_ghost')['outcome']);
    }

    public function testFindByTypeReturnsOnlyMatchingEntries(): void
    {
        $index = $this->index();
        $index->indexRecord('episodic', 'record_1');
        $index->indexRecord('semantic', 'record_2');

        $result = $index->findByType('episodic');

        $this->assertCount(1, $result);
        $this->assertSame('record_1', $result[0]['record_id']);
    }
}
