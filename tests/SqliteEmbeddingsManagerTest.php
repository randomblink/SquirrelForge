<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Knowledge\SqliteDocumentRepository;
use SquirrelForge\Knowledge\SqliteEmbeddingsManager;
use SquirrelForge\Storage\SqliteVectorStorage;

final class SqliteEmbeddingsManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-embeddings-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{0: SqliteVectorStorage, 1: SqliteEmbeddingsManager}
     */
    private function makeManager(): array
    {
        $vectors = new SqliteVectorStorage($this->tempPath('vectors'));
        $embeddings = new SqliteEmbeddingsManager($this->tempPath('embeddings'), $vectors);

        return [$vectors, $embeddings];
    }

    public function testGenerateProducesAFixedDimensionVectorAndPersistsIt(): void
    {
        [, $embeddings] = $this->makeManager();
        $result = $embeddings->generate('doc_1', 'the quick brown fox jumps over the lazy dog', ['dimension' => 64]);

        $this->assertTrue($result['created']);
        $this->assertSame('active', $result['status']);
        $this->assertCount(64, $result['vector']);
        $this->assertNotNull($result['vector_storage_reference']);
    }

    public function testIdenticalTextForTheSameVectorProducesTheSameEmbedding(): void
    {
        $text = 'consistent input text';
        [, $embeddingsA] = $this->makeManager();
        [, $embeddingsB] = $this->makeManager();

        $vectorA = $embeddingsA->generate('doc_1', $text)['vector'];
        $vectorB = $embeddingsB->generate('doc_1', $text)['vector'];

        $this->assertSame($vectorA, $vectorB);
    }

    public function testDifferentTextProducesADifferentVector(): void
    {
        [, $embeddings] = $this->makeManager();

        $a = $embeddings->generate('doc_1', 'apples and oranges')['vector'];
        $b = $embeddings->generate('doc_2', 'quantum mechanics textbook')['vector'];

        $this->assertNotSame($a, $b);
    }

    public function testGeneratingIdenticalContentAgainIsPreventedAsADuplicate(): void
    {
        [, $embeddings] = $this->makeManager();
        $first = $embeddings->generate('doc_1', 'same content every time');

        $second = $embeddings->generate('doc_1', 'same content every time');

        $this->assertFalse($second['created']);
        $this->assertSame($first['embedding_id'], $second['embedding_id']);
    }

    public function testGeneratingChangedContentDeprecatesThePriorActiveEmbedding(): void
    {
        [, $embeddings] = $this->makeManager();
        $first = $embeddings->generate('doc_1', 'the original content');

        $second = $embeddings->generate('doc_1', 'the revised content');

        $this->assertTrue($second['created']);
        $this->assertNotSame($first['embedding_id'], $second['embedding_id']);
        $this->assertSame('deprecated', $embeddings->get($first['embedding_id'])['status']);
        $this->assertSame('active', $embeddings->get($second['embedding_id'])['status']);
        $this->assertSame($second['embedding_id'], $embeddings->activeFor('doc_1')['embedding_id']);
    }

    public function testGenerateRejectsAnUnregisteredKnowledgeAssetWhenDocumentRepositoryIsConfigured(): void
    {
        [$vectors] = $this->makeManager();
        $documents = new SqliteDocumentRepository($this->tempPath('documents'));
        $embeddings = new SqliteEmbeddingsManager($this->tempPath('embeddings2'), $vectors, $documents);

        $result = $embeddings->generate('knowledge_document_ghost', 'some text');

        $this->assertFalse($result['created']);
        $this->assertStringContainsString('not a registered document reference', (string) $result['error']);
    }

    public function testGenerateSucceedsForARegisteredKnowledgeAsset(): void
    {
        [$vectors] = $this->makeManager();
        $documents = new SqliteDocumentRepository($this->tempPath('documents'));
        $registered = $documents->registerReference('Policy', 'policy');
        $embeddings = new SqliteEmbeddingsManager($this->tempPath('embeddings2'), $vectors, $documents);

        $result = $embeddings->generate($registered['document_id'], 'policy text');

        $this->assertTrue($result['created']);
    }

    public function testCheckFreshnessFlagsAnActiveEmbeddingWhenContentHasChanged(): void
    {
        [, $embeddings] = $this->makeManager();
        $generated = $embeddings->generate('doc_1', 'original content');

        $fresh = $embeddings->checkFreshness('doc_1', 'original content');
        $stale = $embeddings->checkFreshness('doc_1', 'content has drifted');

        $this->assertTrue($fresh['fresh']);
        $this->assertFalse($stale['fresh']);
        $this->assertSame('refresh_required', $embeddings->get($generated['embedding_id'])['status']);
    }

    public function testCheckFreshnessWithNoActiveEmbeddingReportsUnchecked(): void
    {
        [, $embeddings] = $this->makeManager();

        $result = $embeddings->checkFreshness('doc_never_embedded', 'text');

        $this->assertFalse($result['checked']);
    }

    public function testSimilarToDelegatesToVectorStorageUsingThePersistedVector(): void
    {
        [, $embeddings] = $this->makeManager();
        $embeddings->generate('doc_1', 'cats and dogs are common pets');
        $target = $embeddings->generate('doc_2', 'cats and dogs are common pets plus fish');

        $result = $embeddings->similarTo($target['embedding_id']);

        $this->assertNull($result['error']);
        $this->assertNotEmpty($result['results']);
    }

    public function testSimilarToFailsWhenTheEmbeddingHasNoPersistedVector(): void
    {
        $embeddings = new SqliteEmbeddingsManager($this->tempPath('embeddings-bare'));
        $generated = $embeddings->generate('doc_1', 'text', ['persist' => false]);

        $result = $embeddings->similarTo($generated['embedding_id']);

        $this->assertNotNull($result['error']);
    }

    public function testArchiveTransitionsStatus(): void
    {
        [, $embeddings] = $this->makeManager();
        $generated = $embeddings->generate('doc_1', 'text');

        $archived = $embeddings->archive($generated['embedding_id']);

        $this->assertTrue($archived['found']);
        $this->assertSame('archived', $embeddings->get($generated['embedding_id'])['status']);
    }

    public function testArchiveReturnsNotFoundForUnknownEmbedding(): void
    {
        [, $embeddings] = $this->makeManager();

        $result = $embeddings->archive('knowledge_embedding_does_not_exist');

        $this->assertFalse($result['found']);
    }

    public function testGeneratedEmbeddingDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('knowledge_embedding.generated', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $vectors = new SqliteVectorStorage($this->tempPath('vectors'));
        $embeddings = new SqliteEmbeddingsManager($this->tempPath('embeddings'), $vectors, events: $events);
        $embeddings->generate('doc_1', 'text');

        $this->assertCount(1, $captured);
    }
}
