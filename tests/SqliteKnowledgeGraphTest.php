<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Knowledge\SqliteKnowledgeGraph;

final class SqliteKnowledgeGraphTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-knowledge-graph-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function graph(): SqliteKnowledgeGraph
    {
        return new SqliteKnowledgeGraph($this->tempPath('graph'));
    }

    public function testRegisterEntityRejectsAnUnrecognizedType(): void
    {
        $graph = $this->graph();

        $result = $graph->registerEntity('entity_1', 'astrology');

        $this->assertSame('invalid_type', $result['outcome']);
    }

    public function testRegisterEntityIsIdempotent(): void
    {
        $graph = $this->graph();
        $graph->registerEntity('entity_1', 'document');

        $result = $graph->registerEntity('entity_1', 'document');

        $this->assertSame('already_registered', $result['outcome']);
    }

    public function testDefineRelationshipRejectsAnUnrecognizedRelationshipType(): void
    {
        $graph = $this->graph();
        $graph->registerEntity('a', 'document');
        $graph->registerEntity('b', 'document');

        $result = $graph->defineRelationship('a', 'astrology', 'b');

        $this->assertSame('invalid_relationship', $result['outcome']);
    }

    public function testDefineRelationshipRequiresBothEntitiesRegistered(): void
    {
        $graph = $this->graph();
        $graph->registerEntity('a', 'document');

        $result = $graph->defineRelationship('a', 'references', 'b_unregistered');

        $this->assertSame('unregistered_entity', $result['outcome']);
    }

    public function testDefineRelationshipSucceedsBetweenTwoRegisteredEntities(): void
    {
        $graph = $this->graph();
        $graph->registerEntity('a', 'document');
        $graph->registerEntity('b', 'document');

        $result = $graph->defineRelationship('a', 'references', 'b', 0.9, 'citation_1');

        $this->assertSame('defined', $result['outcome']);
        $record = $graph->get($result['graph_id']);
        $this->assertSame('Active', $record['status']);
        $this->assertSame('citation_1', $record['evidence_reference']);
    }

    public function testDeprecateRelationshipExcludesItFromTraversal(): void
    {
        $graph = $this->graph();
        $graph->registerEntity('a', 'document');
        $graph->registerEntity('b', 'document');
        $defined = $graph->defineRelationship('a', 'references', 'b');

        $graph->deprecateRelationship($defined['graph_id']);
        $result = $graph->traverse('a');

        $this->assertSame(['a'], $result['entities']);
        $this->assertSame([], $result['edges']);
    }

    public function testTraverseFindsDirectNeighborsAtDepthOne(): void
    {
        $graph = $this->graph();
        $graph->registerEntity('a', 'document');
        $graph->registerEntity('b', 'document');
        $graph->registerEntity('c', 'document');
        $graph->defineRelationship('a', 'references', 'b');
        $graph->defineRelationship('b', 'references', 'c');

        $result = $graph->traverse('a', 'outgoing', null, 1);

        $this->assertSame(['a', 'b'], $result['entities']);
    }

    public function testTraverseFollowsMultipleHopsUpToDepth(): void
    {
        $graph = $this->graph();
        $graph->registerEntity('a', 'document');
        $graph->registerEntity('b', 'document');
        $graph->registerEntity('c', 'document');
        $graph->defineRelationship('a', 'references', 'b');
        $graph->defineRelationship('b', 'references', 'c');

        $result = $graph->traverse('a', 'outgoing', null, 2);

        $this->assertSame(['a', 'b', 'c'], $result['entities']);
        $this->assertCount(2, $result['edges']);
    }

    public function testTraverseRespectsDirection(): void
    {
        $graph = $this->graph();
        $graph->registerEntity('a', 'document');
        $graph->registerEntity('b', 'document');
        $graph->defineRelationship('a', 'references', 'b');

        $outgoingFromB = $graph->traverse('b', 'outgoing', null, 1);
        $incomingToB = $graph->traverse('b', 'incoming', null, 1);

        $this->assertSame(['b'], $outgoingFromB['entities']);
        $this->assertSame(['b', 'a'], $incomingToB['entities']);
    }

    public function testFindIndirectRelationshipsFindsAMultiHopPathWithinDepthBound(): void
    {
        $graph = $this->graph();
        $graph->registerEntity('a', 'document');
        $graph->registerEntity('b', 'document');
        $graph->registerEntity('c', 'document');
        $graph->defineRelationship('a', 'depends_on', 'b');
        $graph->defineRelationship('b', 'depends_on', 'c');

        $result = $graph->findIndirectRelationships('a', 'c', 3);

        $this->assertTrue($result['reachable']);
        $this->assertCount(2, $result['path']);
    }

    public function testFindIndirectRelationshipsReportsUnreachableBeyondTheDepthBound(): void
    {
        $graph = $this->graph();
        $graph->registerEntity('a', 'document');
        $graph->registerEntity('b', 'document');
        $graph->registerEntity('c', 'document');
        $graph->defineRelationship('a', 'depends_on', 'b');
        $graph->defineRelationship('b', 'depends_on', 'c');

        $result = $graph->findIndirectRelationships('a', 'c', 1);

        $this->assertFalse($result['reachable']);
    }

    public function testRelationshipsForReturnsBothDirections(): void
    {
        $graph = $this->graph();
        $graph->registerEntity('a', 'document');
        $graph->registerEntity('b', 'document');
        $graph->registerEntity('c', 'document');
        $graph->defineRelationship('a', 'references', 'b');
        $graph->defineRelationship('c', 'references', 'a');

        $this->assertCount(2, $graph->relationshipsFor('a'));
    }
}
