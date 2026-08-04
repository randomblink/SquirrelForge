<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Knowledge\KnowledgeManager;
use SquirrelForge\Knowledge\SemanticSearchManager;
use SquirrelForge\Knowledge\SqliteCitationManager;
use SquirrelForge\Knowledge\SqliteDocumentRepository;
use SquirrelForge\Knowledge\SqliteEmbeddingsManager;
use SquirrelForge\Knowledge\SqliteKnowledgeGraph;
use SquirrelForge\Knowledge\SqliteKnowledgeRegistry;
use SquirrelForge\Knowledge\SqliteKnowledgeValidator;
use SquirrelForge\Knowledge\SqliteKnowledgeVersioning;
use SquirrelForge\Storage\SqliteVectorStorage;

final class KnowledgeManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-knowledge-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function makeManager(?EventBus $events = null): KnowledgeManager
    {
        $documents = new SqliteDocumentRepository($this->tempPath('documents'));
        $vectors = new SqliteVectorStorage($this->tempPath('vectors'));
        $embeddings = new SqliteEmbeddingsManager($this->tempPath('embeddings'), $vectors, $documents);
        $search = new SemanticSearchManager($embeddings, $documents);

        return new KnowledgeManager($documents, $embeddings, $search, events: $events);
    }

    private function makeKnowledgeGraphManager(): KnowledgeManager
    {
        $registry = new SqliteKnowledgeRegistry($this->tempPath('registry'));
        $citations = new SqliteCitationManager($this->tempPath('citations'), $registry);
        $validator = new SqliteKnowledgeValidator($this->tempPath('validator'), $registry, $citations);
        $versioning = new SqliteKnowledgeVersioning($this->tempPath('versioning'));
        $graph = new SqliteKnowledgeGraph($this->tempPath('graph'));

        return new KnowledgeManager(registry: $registry, validator: $validator, citations: $citations, versioning: $versioning, graph: $graph);
    }

    public function testRegisterRoutesToDocumentRepository(): void
    {
        $manager = $this->makeManager();

        $result = $manager->coordinate('register', ['title' => 'Deploy Runbook', 'type' => 'workflow']);

        $this->assertSame('registered', $result['outcome']);
        $this->assertSame('document_repository', $result['target_component']);
        $this->assertTrue($result['result']['found']);
    }

    public function testReadUpdateArchiveRestoreRoundTripThroughDocumentRepository(): void
    {
        $manager = $this->makeManager();
        $registered = $manager->coordinate('register', ['title' => 'Policy', 'type' => 'policy']);
        $documentId = $registered['result']['document_id'];

        $read = $manager->coordinate('read', ['document_id' => $documentId]);
        $this->assertSame('retrieved', $read['outcome']);

        $updated = $manager->coordinate('update', ['document_id' => $documentId, 'changes' => ['title' => 'New Title']]);
        $this->assertSame('updated', $updated['outcome']);

        $archived = $manager->coordinate('archive', ['document_id' => $documentId]);
        $this->assertSame('archived', $archived['outcome']);

        $restored = $manager->coordinate('restore', ['document_id' => $documentId]);
        $this->assertSame('restored', $restored['outcome']);
    }

    public function testEmbedRoutesToEmbeddingsManager(): void
    {
        $manager = $this->makeManager();
        $registered = $manager->coordinate('register', ['title' => 'Doc', 'type' => 'knowledge']);

        $result = $manager->coordinate('embed', ['document_id' => $registered['result']['document_id'], 'text' => 'some content']);

        $this->assertSame('embedded', $result['outcome']);
        $this->assertSame('embeddings_manager', $result['target_component']);
        $this->assertTrue($result['result']['created']);
    }

    public function testSearchRoutesToSemanticSearch(): void
    {
        $manager = $this->makeManager();
        $registered = $manager->coordinate('register', ['title' => 'Cats', 'type' => 'knowledge']);
        $manager->coordinate('embed', ['document_id' => $registered['result']['document_id'], 'text' => 'cats and dogs are pets']);

        $result = $manager->coordinate('search', ['query' => 'cats and dogs']);

        $this->assertSame('searched', $result['outcome']);
        $this->assertSame('semantic_search', $result['target_component']);
        $this->assertNotEmpty($result['result']['results']);
    }

    public function testCatalogIsRejectedWithoutAConfiguredRegistry(): void
    {
        $manager = $this->makeManager();

        $result = $manager->coordinate('catalog', ['name' => 'Deploy Runbook', 'type' => 'document', 'source' => 'wiki', 'owner' => 'platform_team']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('knowledge_registry', $result['target_component']);
    }

    public function testCatalogRoutesToTheRealKnowledgeRegistry(): void
    {
        $manager = $this->makeKnowledgeGraphManager();

        $result = $manager->coordinate('catalog', ['name' => 'Deploy Runbook', 'type' => 'document', 'source' => 'wiki', 'owner' => 'platform_team']);

        $this->assertSame('registered', $result['outcome']);
        $this->assertNotNull($result['result']['knowledge_id']);
    }

    public function testValidateRoutesToTheRealKnowledgeValidator(): void
    {
        $manager = $this->makeKnowledgeGraphManager();
        $catalogued = $manager->coordinate('catalog', ['name' => 'Deploy Runbook', 'type' => 'document', 'source' => 'wiki', 'owner' => 'platform_team']);

        $result = $manager->coordinate('validate', ['knowledge_id' => $catalogued['result']['knowledge_id']]);

        $this->assertSame('knowledge_validator', $result['target_component']);
        $this->assertContains($result['outcome'], ['Valid', 'Warning', 'Failed', 'Rejected']);
    }

    public function testCiteRoutesToTheRealCitationManager(): void
    {
        $manager = $this->makeKnowledgeGraphManager();
        $catalogued = $manager->coordinate('catalog', ['name' => 'Deploy Runbook', 'type' => 'document', 'source' => 'wiki', 'owner' => 'platform_team']);

        $result = $manager->coordinate('cite', [
            'knowledge_id' => $catalogued['result']['knowledge_id'],
            'source_reference' => 'https://example.com',
            'citation_type' => 'primary_source',
        ]);

        $this->assertSame('registered', $result['outcome']);
        $this->assertSame('citation_manager', $result['target_component']);
    }

    public function testVersionRoutesToTheRealKnowledgeVersioning(): void
    {
        $manager = $this->makeKnowledgeGraphManager();
        $catalogued = $manager->coordinate('catalog', ['name' => 'Deploy Runbook', 'type' => 'document', 'source' => 'wiki', 'owner' => 'platform_team']);

        $result = $manager->coordinate('version', ['knowledge_id' => $catalogued['result']['knowledge_id'], 'content' => ['body' => 'v1']]);

        $this->assertSame('created', $result['outcome']);
        $this->assertSame('knowledge_versioning', $result['target_component']);
    }

    public function testRelateRoutesToTheRealKnowledgeGraph(): void
    {
        $manager = $this->makeKnowledgeGraphManager();

        $result = $manager->coordinate('relate', ['source_entity' => 'a', 'relationship' => 'depends_on', 'target_entity' => 'b']);

        $this->assertSame('unregistered_entity', $result['outcome'], 'Neither entity was registered in the Knowledge Graph directly, so this must fail the real registration gate rather than fabricate success.');
        $this->assertSame('knowledge_graph', $result['target_component']);
    }

    public function testUnknownOperationIsRejected(): void
    {
        $manager = $this->makeManager();

        $result = $manager->coordinate('teleport', []);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testMissingRequiredFieldIsRejectedBeforeRoutingIsAttempted(): void
    {
        $manager = $this->makeManager();

        $result = $manager->coordinate('register', ['title' => 'Doc']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('type', (string) $result['error']);
    }

    public function testMissingDependencyIsRejectedNotFatal(): void
    {
        $manager = new KnowledgeManager();

        $result = $manager->coordinate('register', ['title' => 'Doc', 'type' => 'knowledge']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('not configured', (string) $result['error']);
    }

    public function testCoordinationDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('knowledge.finished', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $manager = $this->makeManager($events);
        $manager->coordinate('register', ['title' => 'Doc', 'type' => 'knowledge']);

        $this->assertCount(1, $captured);
        $this->assertSame('register', $captured[0]->getPayload()['operation']);
    }
}
