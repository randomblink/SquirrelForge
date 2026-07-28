<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Knowledge\SqliteDocumentRepository;
use SquirrelForge\Knowledge\SqliteEmbeddingsManager;
use SquirrelForge\Knowledge\SemanticSearchManager;
use SquirrelForge\Storage\SqliteVectorStorage;

final class SemanticSearchManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-semantic-search-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{0: SqliteDocumentRepository, 1: SqliteEmbeddingsManager, 2: SemanticSearchManager}
     */
    private function makeManagers(?EventBus $events = null): array
    {
        $documents = new SqliteDocumentRepository($this->tempPath('documents'));
        $vectors = new SqliteVectorStorage($this->tempPath('vectors'));
        $embeddings = new SqliteEmbeddingsManager($this->tempPath('embeddings'), $vectors, $documents);
        $search = new SemanticSearchManager($embeddings, $documents, $events);

        return [$documents, $embeddings, $search];
    }

    private function registerAndEmbed(SqliteDocumentRepository $documents, SqliteEmbeddingsManager $embeddings, string $title, string $type, string $text, array $options = []): string
    {
        $registered = $documents->registerReference($title, $type, $options);
        $embeddings->generate($registered['document_id'], $text);

        return $registered['document_id'];
    }

    public function testSearchRanksMostSimilarDocumentFirst(): void
    {
        [$documents, $embeddings, $search] = $this->makeManagers();
        $close = $this->registerAndEmbed($documents, $embeddings, 'Cats and Dogs', 'knowledge', 'cats and dogs are common household pets');
        $far = $this->registerAndEmbed($documents, $embeddings, 'Quantum Mechanics', 'knowledge', 'quantum mechanics describes subatomic particle behavior');

        $result = $search->search('cats and dogs as pets');

        $this->assertNull($result['error']);
        $this->assertSame($close, $result['results'][0]['document_id']);
        $this->assertGreaterThan($result['results'][1]['score'], $result['results'][0]['score']);
    }

    public function testSearchExcludesArchivedReferences(): void
    {
        [$documents, $embeddings, $search] = $this->makeManagers();
        $documentId = $this->registerAndEmbed($documents, $embeddings, 'Old Runbook', 'workflow', 'deploy the service to production');
        $documents->archiveReference($documentId);

        $result = $search->search('deploy the service');

        $this->assertSame([], $result['results']);
    }

    public function testSearchExcludesDocumentsWithNoEmbeddingYet(): void
    {
        [$documents, , $search] = $this->makeManagers();
        $documents->registerReference('No Embedding Yet', 'workflow');

        $result = $search->search('anything');

        $this->assertSame([], $result['results']);
    }

    public function testSearchExcludesProtectedReferencesWithoutAuthorization(): void
    {
        [$documents, $embeddings, $search] = $this->makeManagers();
        $this->registerAndEmbed($documents, $embeddings, 'Secret Policy', 'policy', 'confidential compensation details', ['protected' => true]);

        $withoutAuth = $search->search('confidential compensation details');
        $withAuth = $search->search('confidential compensation details', ['authorization_result' => ['authorized' => true]]);

        $this->assertSame([], $withoutAuth['results']);
        $this->assertCount(1, $withAuth['results']);
    }

    public function testSearchFiltersByType(): void
    {
        [$documents, $embeddings, $search] = $this->makeManagers();
        $this->registerAndEmbed($documents, $embeddings, 'Policy Doc', 'policy', 'shared vocabulary about pets and animals');
        $this->registerAndEmbed($documents, $embeddings, 'Workflow Doc', 'workflow', 'shared vocabulary about pets and animals');

        $result = $search->search('pets and animals', ['type' => 'policy']);

        $this->assertCount(1, $result['results']);
        $this->assertSame('policy', $result['results'][0]['type']);
    }

    public function testSearchRespectsTopKAndScoreThreshold(): void
    {
        [$documents, $embeddings, $search] = $this->makeManagers();
        $this->registerAndEmbed($documents, $embeddings, 'A', 'knowledge', 'apples and oranges are fruit');
        $this->registerAndEmbed($documents, $embeddings, 'B', 'knowledge', 'bananas and grapes are fruit');
        $this->registerAndEmbed($documents, $embeddings, 'C', 'knowledge', 'quantum entanglement in physics');

        $limited = $search->search('fruit', ['top_k' => 1]);
        $thresholded = $search->search('fruit', ['score_threshold' => 0.9]);

        $this->assertCount(1, $limited['results']);
        $this->assertLessThanOrEqual(2, count($thresholded['results']));
    }

    public function testResultsIncludeCitationReferencesForExplainability(): void
    {
        [$documents, $embeddings, $search] = $this->makeManagers();
        $documentId = $this->registerAndEmbed($documents, $embeddings, 'Cited Doc', 'knowledge', 'traceable knowledge content');
        $documents->attachCitation($documentId, 'citation_1');

        $result = $search->search('traceable knowledge content');

        $this->assertSame(['citation_1'], $result['results'][0]['citation_references']);
    }

    public function testTiedScoresBreakDeterministicallyByDocumentId(): void
    {
        [$documents, $embeddings, $search] = $this->makeManagers();
        $a = $this->registerAndEmbed($documents, $embeddings, 'A', 'knowledge', 'identical shared text');
        $b = $this->registerAndEmbed($documents, $embeddings, 'B', 'knowledge', 'identical shared text');

        $result = $search->search('identical shared text');

        $expectedFirst = min($a, $b);
        $this->assertSame($expectedFirst, $result['results'][0]['document_id']);
    }

    public function testSearchWithoutEmbeddingsConfiguredReturnsAnError(): void
    {
        $documents = new SqliteDocumentRepository($this->tempPath('documents'));
        $search = new SemanticSearchManager(documents: $documents);

        $result = $search->search('anything');

        $this->assertStringContainsString('Embeddings Manager', (string) $result['error']);
    }

    public function testSearchWithoutDocumentRepositoryConfiguredReturnsAnError(): void
    {
        $vectors = new SqliteVectorStorage($this->tempPath('vectors'));
        $embeddings = new SqliteEmbeddingsManager($this->tempPath('embeddings'), $vectors);
        $search = new SemanticSearchManager($embeddings);

        $result = $search->search('anything');

        $this->assertStringContainsString('Document Repository', (string) $result['error']);
    }

    public function testCompletedSearchDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('semantic_search.completed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        [, , $search] = $this->makeManagers($events);
        $search->search('anything');

        $this->assertCount(1, $captured);
        $this->assertSame('anything', $captured[0]->getPayload()['query']);
    }
}
