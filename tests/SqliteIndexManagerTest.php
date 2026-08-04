<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Storage\SqliteIndexManager;

final class SqliteIndexManagerTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-index-manager-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function manager(): SqliteIndexManager
    {
        return new SqliteIndexManager($this->databasePath);
    }

    public function testIndexRecordRejectsAnUnrecognizedSourceType(): void
    {
        $manager = $this->manager();

        $result = $manager->indexRecord('astrology', 'record_1');

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testIndexRecordCreatesThenUpdatesTheSameEntry(): void
    {
        $manager = $this->manager();

        $first = $manager->indexRecord('documents', 'record_1', ['title' => 'Original title']);
        $second = $manager->indexRecord('documents', 'record_1', ['title' => 'Updated title']);

        $this->assertSame('indexed', $first['outcome']);
        $this->assertSame('updated', $second['outcome']);
        $this->assertSame($first['index_entry_id'], $second['index_entry_id']);
        $this->assertSame('Updated title', $manager->get('documents', 'record_1')['title']);
    }

    public function testRemoveIndexEntryDeletesTheRecord(): void
    {
        $manager = $this->manager();
        $manager->indexRecord('documents', 'record_1', ['title' => 'Doc']);

        $result = $manager->removeIndexEntry('documents', 'record_1');

        $this->assertSame('removed', $result['outcome']);
        $this->assertNull($manager->get('documents', 'record_1'));
    }

    public function testRemoveIndexEntryOnAnUnknownRecordIsRejected(): void
    {
        $manager = $this->manager();

        $result = $manager->removeIndexEntry('documents', 'unknown');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testVerifyIntegritySucceedsForAnUntamperedEntry(): void
    {
        $manager = $this->manager();
        $manager->indexRecord('documents', 'record_1', ['title' => 'Doc']);

        $result = $manager->verifyIntegrity('documents', 'record_1');

        $this->assertTrue($result['verified']);
        $this->assertSame('verified', $result['outcome']);
    }

    public function testVerifyIntegrityDetectsATamperedChecksum(): void
    {
        $manager = $this->manager();
        $manager->indexRecord('documents', 'record_1', ['title' => 'Doc']);

        $pdo = new \PDO('sqlite:' . $this->databasePath);
        $pdo->exec("UPDATE index_entries SET attributes_json = '{\"title\":\"Tampered\"}' WHERE record_id = 'record_1'");

        $result = $manager->verifyIntegrity('documents', 'record_1');

        $this->assertFalse($result['verified']);
        $this->assertSame('corrupted', $result['outcome']);
    }

    public function testOptimizeReportsTheEntryCountForASourceType(): void
    {
        $manager = $this->manager();
        $manager->indexRecord('documents', 'record_1');
        $manager->indexRecord('documents', 'record_2');
        $manager->indexRecord('system_logs', 'record_3');

        $result = $manager->optimize('documents');

        $this->assertSame(2, $result['entry_count']);
    }

    public function testSearchRanksHigherKeywordOverlapFirst(): void
    {
        $manager = $this->manager();
        $manager->indexRecord('documents', 'record_1', ['title' => 'Checkout bug fix', 'keywords' => ['bugfix']]);
        $manager->indexRecord('knowledge_artifacts', 'record_2', ['title' => 'Checkout discount checkout pattern', 'keywords' => ['checkout', 'discount']]);

        $result = $manager->search(['checkout']);

        $this->assertSame('searched', $result['outcome']);
        $this->assertCount(2, $result['results']);
        $this->assertSame('record_2', $result['results'][0]['record_id']);
        $this->assertGreaterThan($result['results'][1]['score'], $result['results'][0]['score']);
    }

    public function testSearchFiltersBySourceType(): void
    {
        $manager = $this->manager();
        $manager->indexRecord('documents', 'record_1', ['title' => 'Checkout doc']);
        $manager->indexRecord('system_logs', 'record_2', ['title' => 'Checkout log']);

        $result = $manager->search(['checkout'], ['source_types' => ['documents']]);

        $this->assertCount(1, $result['results']);
        $this->assertSame('documents', $result['results'][0]['source_type']);
    }

    public function testSearchFiltersByCategory(): void
    {
        $manager = $this->manager();
        $manager->indexRecord('documents', 'record_1', ['title' => 'Checkout doc', 'categories' => ['billing']]);
        $manager->indexRecord('documents', 'record_2', ['title' => 'Checkout doc', 'categories' => ['shipping']]);

        $result = $manager->search(['checkout'], ['category' => 'billing']);

        $this->assertCount(1, $result['results']);
        $this->assertSame('record_1', $result['results'][0]['record_id']);
    }

    public function testSearchFiltersByRelationship(): void
    {
        $manager = $this->manager();
        $manager->indexRecord('documents', 'record_1', ['title' => 'Checkout doc', 'relationships' => ['workflow_1']]);
        $manager->indexRecord('documents', 'record_2', ['title' => 'Checkout doc', 'relationships' => ['workflow_2']]);

        $result = $manager->search(['checkout'], ['related_to' => 'workflow_1']);

        $this->assertCount(1, $result['results']);
        $this->assertSame('record_1', $result['results'][0]['record_id']);
    }

    public function testSearchWithNoQueryTermsIsRejected(): void
    {
        $manager = $this->manager();

        $result = $manager->search([]);

        $this->assertSame('no_query', $result['outcome']);
    }

    public function testSearchRejectsAnUnknownSourceType(): void
    {
        $manager = $this->manager();

        $result = $manager->search(['checkout'], ['source_types' => ['astrology']]);

        $this->assertSame('invalid_source_type', $result['outcome']);
    }

    public function testFindBySourceTypeReturnsOnlyMatchingEntries(): void
    {
        $manager = $this->manager();
        $manager->indexRecord('documents', 'record_1');
        $manager->indexRecord('system_logs', 'record_2');

        $this->assertCount(1, $manager->findBySourceType('documents'));
    }
}
