<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Security\StaticAuthorizationManager;
use SquirrelForge\Storage\SqliteVectorStorage;

final class SqliteVectorStorageTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir() . '/squirrelforge-vectors-' . bin2hex(random_bytes(8)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->databasePath, $this->databasePath . '-shm', $this->databasePath . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testStoreAndRetrieveRoundTripsTheVector(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);
        $stored = $storage->store('docs', [1.0, 2.0, 3.0], ['source_content_id' => 'document_1']);

        $this->assertSame('stored', $stored['outcome']);
        $this->assertSame(3, $stored['dimension']);

        $retrieved = $storage->retrieve($stored['vector_ref']);
        $this->assertTrue($retrieved['found']);
        $this->assertEquals([1.0, 2.0, 3.0], $retrieved['vector']);
    }

    public function testStoreRejectsAnEmptyOrNonNumericVector(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);

        $empty = $storage->store('docs', []);
        $nonNumeric = $storage->store('docs', [1.0, 'two', 3.0]);

        $this->assertSame('rejected', $empty['outcome']);
        $this->assertSame('rejected', $nonNumeric['outcome']);
    }

    public function testStoreRejectsAnUnknownDistanceMetric(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);

        $result = $storage->store('docs', [1.0, 2.0], ['distance_metric' => 'manhattan']);

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testStoreRejectsAVectorWhoseDimensionDoesNotMatchTheCollection(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);
        $storage->store('docs', [1.0, 2.0, 3.0]);

        $result = $storage->store('docs', [1.0, 2.0]);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('incompatible', (string) $result['error']);
    }

    public function testADifferentCollectionMayUseADifferentDimension(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);
        $storage->store('docs', [1.0, 2.0, 3.0]);

        $result = $storage->store('images', [1.0, 2.0]);

        $this->assertSame('stored', $result['outcome']);
    }

    public function testCosineSimilarityRanksByDirectionNotMagnitude(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);
        $identical = $storage->store('docs', [2.0, 0.0]);
        $orthogonal = $storage->store('docs', [0.0, 1.0]);
        $opposite = $storage->store('docs', [-1.0, 0.0]);

        $result = $storage->search('docs', [1.0, 0.0], ['distance_metric' => 'cosine']);

        $this->assertSame($identical['vector_ref'], $result['results'][0]['vector_ref']);
        $this->assertEqualsWithDelta(1.0, $result['results'][0]['score'], 0.0001);
        $this->assertSame($orthogonal['vector_ref'], $result['results'][1]['vector_ref']);
        $this->assertEqualsWithDelta(0.0, $result['results'][1]['score'], 0.0001);
        $this->assertSame($opposite['vector_ref'], $result['results'][2]['vector_ref']);
        $this->assertEqualsWithDelta(-1.0, $result['results'][2]['score'], 0.0001);
    }

    public function testDotProductRanksByMagnitudeUnlikeCosine(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);
        $small = $storage->store('docs', [1.0, 0.0]);
        $large = $storage->store('docs', [3.0, 0.0]);

        $result = $storage->search('docs', [2.0, 0.0], ['distance_metric' => 'dot']);

        $this->assertSame($large['vector_ref'], $result['results'][0]['vector_ref']);
        $this->assertEqualsWithDelta(6.0, $result['results'][0]['score'], 0.0001);
        $this->assertSame($small['vector_ref'], $result['results'][1]['vector_ref']);
        $this->assertEqualsWithDelta(2.0, $result['results'][1]['score'], 0.0001);
    }

    public function testEuclideanRanksByClosenessViaNegativeDistance(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);
        $near = $storage->store('docs', [1.0, 0.0]);
        $far = $storage->store('docs', [5.0, 0.0]);

        $result = $storage->search('docs', [0.0, 0.0], ['distance_metric' => 'euclidean']);

        $this->assertSame($near['vector_ref'], $result['results'][0]['vector_ref']);
        $this->assertEqualsWithDelta(-1.0, $result['results'][0]['score'], 0.0001);
        $this->assertSame($far['vector_ref'], $result['results'][1]['vector_ref']);
        $this->assertEqualsWithDelta(-5.0, $result['results'][1]['score'], 0.0001);
    }

    public function testSearchRespectsTopKAndScoreThreshold(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);
        $storage->store('docs', [1.0, 0.0]);
        $storage->store('docs', [0.0, 1.0]);
        $storage->store('docs', [-1.0, 0.0]);

        $limited = $storage->search('docs', [1.0, 0.0], ['top_k' => 1]);
        $thresholded = $storage->search('docs', [1.0, 0.0], ['score_threshold' => 0.5]);

        $this->assertCount(1, $limited['results']);
        $this->assertCount(1, $thresholded['results']);
    }

    public function testSearchAppliesMetadataFilters(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);
        $storage->store('docs', [1.0, 0.0], ['metadata' => ['lang' => 'en']]);
        $frenchDoc = $storage->store('docs', [1.0, 0.1], ['metadata' => ['lang' => 'fr']]);

        $result = $storage->search('docs', [1.0, 0.0], ['metadata_filters' => ['lang' => 'fr']]);

        $this->assertCount(1, $result['results']);
        $this->assertSame($frenchDoc['vector_ref'], $result['results'][0]['vector_ref']);
    }

    public function testSearchIsScopedToASingleCollectionAndNeverLeaksAcrossCollections(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);
        $storage->store('docs', [1.0, 0.0]);
        $storage->store('images', [1.0, 0.0]);

        $result = $storage->search('docs', [1.0, 0.0]);

        $this->assertCount(1, $result['results']);
    }

    public function testSearchRejectsAQueryVectorWithAnIncompatibleDimension(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);
        $storage->store('docs', [1.0, 2.0, 3.0]);

        $result = $storage->search('docs', [1.0, 2.0]);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('incompatible', (string) $result['error']);
    }

    public function testDeletedVectorIsExcludedFromSearchAndRetrieval(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);
        $stored = $storage->store('docs', [1.0, 0.0]);
        $storage->delete($stored['vector_ref']);

        $retrieved = $storage->retrieve($stored['vector_ref']);
        $result = $storage->search('docs', [1.0, 0.0]);

        $this->assertFalse($retrieved['found']);
        $this->assertCount(0, $result['results']);
    }

    public function testVectorUnderLegalHoldCannotBeDeleted(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);
        $stored = $storage->store('docs', [1.0, 0.0]);

        $storage->setLegalHold($stored['vector_ref'], true);
        $result = $storage->delete($stored['vector_ref']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('legal hold', (string) $result['error']);
    }

    public function testDeniedAuthorizationBlocksStorageAndSearch(): void
    {
        $authorization = new StaticAuthorizationManager('permission:allowed');
        $storage = new SqliteVectorStorage($this->databasePath, $authorization);

        $stored = $storage->store('docs', [1.0, 0.0], ['identity_ref' => 'user_1', 'permission_ref' => 'permission:denied']);
        $result = $storage->search('docs', [1.0, 0.0], ['identity_ref' => 'user_1', 'permission_ref' => 'permission:denied']);

        $this->assertSame('rejected', $stored['outcome']);
        $this->assertSame('rejected', $result['outcome']);
    }

    public function testListCollectionReturnsOnlyActiveVectorsForThatCollection(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);
        $a = $storage->store('docs', [1.0, 0.0]);
        $storage->store('images', [1.0, 0.0]);
        $deleted = $storage->store('docs', [0.0, 1.0]);
        $storage->delete($deleted['vector_ref']);

        $listed = $storage->listCollection('docs');

        $this->assertCount(1, $listed);
        $this->assertSame($a['vector_ref'], $listed[0]['vector_ref']);
    }

    public function testOperationDispatchesEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('vector.stored', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $storage = new SqliteVectorStorage($this->databasePath, events: $events);
        $storage->store('docs', [1.0, 0.0]);

        $this->assertCount(1, $captured);
    }

    public function testOperationRetrievesAPersistedAuditRecordByRef(): void
    {
        $storage = new SqliteVectorStorage($this->databasePath);
        $storage->store('docs', [1.0, 0.0], ['requesting_component' => 'embeddings-manager']);

        $recent = $storage->recent(1);

        $this->assertSame('embeddings-manager', $recent[0]['requesting_component']);
        $fetched = $storage->operation($recent[0]['operation_ref']);
        $this->assertNotNull($fetched);
        $this->assertSame('stored', $fetched['outcome']);
    }
}
