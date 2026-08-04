<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Knowledge\SqliteCitationManager;
use SquirrelForge\Knowledge\SqliteKnowledgeRegistry;

final class SqliteCitationManagerTest extends TestCase
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

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-citation-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function manager(?SqliteKnowledgeRegistry $registry = null): SqliteCitationManager
    {
        return new SqliteCitationManager($this->tempPath('citations'), $registry);
    }

    public function testRegisterCitationRejectsAnUnrecognizedType(): void
    {
        $manager = $this->manager();

        $result = $manager->registerCitation('knowledge_1', 'https://example.com', 'astrology');

        $this->assertSame('invalid_type', $result['outcome']);
    }

    public function testRegisterCitationRejectsUnregisteredKnowledgeWhenRegistryIsConfigured(): void
    {
        $registry = new SqliteKnowledgeRegistry($this->tempPath('registry'));
        $manager = $this->manager($registry);

        $result = $manager->registerCitation('knowledge_missing', 'https://example.com', 'primary_source', ['url' => 'https://example.com']);

        $this->assertSame('unregistered_knowledge', $result['outcome']);
    }

    public function testRegisterCitationSucceedsForUnregisteredKnowledgeWhenNoRegistryIsConfigured(): void
    {
        $manager = $this->manager();

        $result = $manager->registerCitation('knowledge_missing', 'https://example.com', 'primary_source', ['url' => 'https://example.com']);

        $this->assertSame('registered', $result['outcome']);
    }

    public function testCitationWithSourceAndLocatorIsActive(): void
    {
        $manager = $this->manager();

        $result = $manager->registerCitation('knowledge_1', 'https://example.com', 'primary_source', ['url' => 'https://example.com']);

        $this->assertSame('Active', $result['citation_status']);
    }

    public function testCitationWithoutALocatorIsUnresolved(): void
    {
        $manager = $this->manager();

        $result = $manager->registerCitation('knowledge_1', 'https://example.com', 'primary_source', []);

        $this->assertSame('Unresolved', $result['citation_status']);
    }

    public function testCitationWithoutASourceReferenceIsUnresolved(): void
    {
        $manager = $this->manager();

        $result = $manager->registerCitation('knowledge_1', '', 'primary_source', ['url' => 'https://example.com']);

        $this->assertSame('Unresolved', $result['citation_status']);
    }

    public function testMarkSupersededTransitionsAnExistingCitation(): void
    {
        $manager = $this->manager();
        $registered = $manager->registerCitation('knowledge_1', 'https://example.com', 'primary_source', ['url' => 'https://example.com']);

        $result = $manager->markSuperseded($registered['citation_id']);

        $this->assertSame('transitioned', $result['outcome']);
        $this->assertSame('Superseded', $manager->get($registered['citation_id'])['citation_status']);
    }

    public function testMarkStaleOfAnUnknownCitationIsNotFound(): void
    {
        $manager = $this->manager();

        $result = $manager->markStale('citation_missing');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testFindByKnowledgeIdReturnsOnlyMatchingCitations(): void
    {
        $manager = $this->manager();
        $manager->registerCitation('knowledge_1', 'https://a.example.com', 'primary_source', ['url' => 'a']);
        $manager->registerCitation('knowledge_1', 'https://b.example.com', 'secondary_source', ['url' => 'b']);
        $manager->registerCitation('knowledge_2', 'https://c.example.com', 'primary_source', ['url' => 'c']);

        $this->assertCount(2, $manager->findByKnowledgeId('knowledge_1'));
    }

    public function testHistoryTracksEveryTransition(): void
    {
        $manager = $this->manager();
        $registered = $manager->registerCitation('knowledge_1', 'https://example.com', 'primary_source', ['url' => 'https://example.com']);
        $manager->markStale($registered['citation_id']);
        $manager->markSuperseded($registered['citation_id']);

        $history = $manager->history($registered['citation_id']);

        $this->assertCount(3, $history);
        $this->assertSame('Active', $history[0]['to_status']);
        $this->assertSame('Stale', $history[1]['to_status']);
        $this->assertSame('Superseded', $history[2]['to_status']);
    }
}
