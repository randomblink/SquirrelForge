<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Integration\Http\MemoryApiServer;
use SquirrelForge\Memory\EpisodicMemory;
use SquirrelForge\Memory\InMemoryStore;
use SquirrelForge\Memory\MemoryManager;
use SquirrelForge\Memory\MemoryRetrieval;
use SquirrelForge\Memory\ProjectMemory;
use SquirrelForge\Memory\SemanticMemory;
use SquirrelForge\Memory\SqliteMemoryIndex;
use SquirrelForge\Memory\SqliteMemoryRetention;
use SquirrelForge\Memory\WorkingMemory;

final class MemoryApiServerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-memory-api-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{0: MemoryApiServer, 1: SqliteMemoryIndex, 2: SqliteMemoryRetention}
     */
    private function server(): array
    {
        $index = new SqliteMemoryIndex($this->tempPath('index'));
        $workingMemory = new WorkingMemory();
        $episodicMemory = new EpisodicMemory(new InMemoryStore());
        $semanticMemory = new SemanticMemory(new InMemoryStore());
        $projectMemory = new ProjectMemory(new InMemoryStore());
        $retrieval = new MemoryRetrieval($index, $workingMemory, $episodicMemory, $semanticMemory, $projectMemory);
        $retention = new SqliteMemoryRetention($this->tempPath('retention'), $index);
        $manager = new MemoryManager($workingMemory, $episodicMemory, $semanticMemory, $projectMemory, $index, $retrieval, $retention);

        $server = new MemoryApiServer($manager, $retrieval, $retention);

        return [$server, $index, $retention];
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $permissionRef = 'permission:allowed'): array
    {
        return [
            'x-squirrelforge-identity-ref' => 'identity_1',
            'x-squirrelforge-permission-ref' => $permissionRef,
            'x-correlation-id' => 'correlation_1',
        ];
    }

    public function testMissingHeadersAreRejected(): void
    {
        [$server] = $this->server();

        $response = $server->handle('POST', '/v1/memory/records', [], null);

        $this->assertSame(401, $response->status);
    }

    public function testPutStoresAWorkingMemoryRecordAndReturnsARealMemoryRef(): void
    {
        [$server] = $this->server();

        $response = $server->handle('POST', '/v1/memory/records', $this->headers(), json_encode([
            'record' => ['task_id' => 'task_1', 'references' => ['active_goal' => 'goal_1']],
            'classification' => ['memory_type' => 'working'],
            'provenance' => ['source' => 'task_router'],
        ]));
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(201, $response->status);
        $this->assertSame('working:task_1', $decoded['result']['memory_ref']);
        $this->assertSame('goal_1', $decoded['result']['record']['active_goal']);
    }

    public function testPutStoresAnEpisodicMemoryRecordAndReturnsARealMemoryRef(): void
    {
        [$server] = $this->server();

        $response = $server->handle('POST', '/v1/memory/records', $this->headers(), json_encode([
            'record' => [
                'task_reference' => 'task_1', 'goal_reference' => 'goal_1',
                'outcome_summary' => 'Checkout discount shipped.', 'validation_result_reference' => 'ACCEPTED',
            ],
            'classification' => ['memory_type' => 'episodic'],
            'provenance' => ['source' => 'reflection_engine'],
        ]));
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(201, $response->status);
        $this->assertStringStartsWith('episodic:', $decoded['result']['memory_ref']);
    }

    public function testPutStoresASemanticMemoryRecordAndReturnsARealMemoryRef(): void
    {
        [$server] = $this->server();

        $response = $server->handle('POST', '/v1/memory/records', $this->headers(), json_encode([
            'record' => [
                'category' => 'Patterns', 'title' => 'Consume, don\'t compute', 'description' => 'desc',
                'source_reference' => 'ref_1', 'validation_reference' => 'ACCEPTED',
            ],
            'classification' => ['memory_type' => 'semantic'],
            'provenance' => ['source' => 'knowledge_manager'],
        ]));
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(201, $response->status);
        $this->assertStringStartsWith('semantic:', $decoded['result']['memory_ref']);
    }

    public function testPutRejectsAnUnsupportedMemoryType(): void
    {
        [$server] = $this->server();

        $response = $server->handle('POST', '/v1/memory/records', $this->headers(), json_encode([
            'record' => ['project_name' => 'SquirrelForge'],
            'classification' => ['memory_type' => 'project'],
            'provenance' => ['source' => 'x'],
        ]));
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(422, $response->status);
        $this->assertSame('INVALID_MEMORY_REQUEST', $decoded['error']['type']);
    }

    public function testPutRejectsAMissingRequiredFieldAsInvalidSchema(): void
    {
        [$server] = $this->server();

        $response = $server->handle('POST', '/v1/memory/records', $this->headers(), json_encode([
            'record' => ['task_reference' => 'task_1'],
            'classification' => ['memory_type' => 'episodic'],
            'provenance' => ['source' => 'x'],
        ]));
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(422, $response->status);
        $this->assertSame('INVALID_MEMORY_REQUEST', $decoded['error']['type']);
    }

    public function testGetRetrievesAStoredRecordByReference(): void
    {
        [$server] = $this->server();
        $put = $server->handle('POST', '/v1/memory/records', $this->headers(), json_encode([
            'record' => ['task_id' => 'task_1', 'references' => ['active_goal' => 'goal_1']],
            'classification' => ['memory_type' => 'working'],
            'provenance' => ['source' => 'task_router'],
        ]));
        $memoryRef = json_decode($put->body, true, 512, JSON_THROW_ON_ERROR)['result']['memory_ref'];

        $response = $server->handle('GET', '/v1/memory/records/' . $memoryRef, $this->headers(), null);
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->status);
        $this->assertSame('goal_1', $decoded['result']['record']['active_goal']);
    }

    public function testGetReturnsUnknownReferenceForAMissingRecord(): void
    {
        [$server] = $this->server();

        $response = $server->handle('GET', '/v1/memory/records/episodic:unknown_id', $this->headers(), null);
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(404, $response->status);
        $this->assertSame('UNKNOWN_MEMORY_REFERENCE', $decoded['error']['type']);
    }

    public function testGetRejectsAMalformedMemoryReference(): void
    {
        [$server] = $this->server();

        $response = $server->handle('GET', '/v1/memory/records/not-a-valid-ref', $this->headers(), null);
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(422, $response->status);
        $this->assertSame('INVALID_MEMORY_REQUEST', $decoded['error']['type']);
    }

    public function testSearchReturnsRankedMatches(): void
    {
        [$server, $index] = $this->server();
        $index->indexRecord('semantic', 'record_1', ['title' => 'Checkout validation pattern']);

        $response = $server->handle('POST', '/v1/memory/search', $this->headers(), json_encode([
            'query_terms' => ['checkout'],
        ]));
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->status);
        $this->assertCount(1, $decoded['result']['matches']);
        $this->assertSame('semantic', $decoded['result']['matches'][0]['memory_type']);
    }

    public function testSearchRejectsEmptyQueryTerms(): void
    {
        [$server] = $this->server();

        $response = $server->handle('POST', '/v1/memory/search', $this->headers(), json_encode(['query_terms' => []]));
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(422, $response->status);
        $this->assertSame('INVALID_MEMORY_REQUEST', $decoded['error']['type']);
    }

    public function testPromoteReturnsAcknowledgmentWithoutDecidingPromotion(): void
    {
        [$server] = $this->server();

        $response = $server->handle(
            'POST',
            '/v1/memory/records/semantic:record_1/promotion',
            $this->headers(),
            json_encode(['evidence' => ['reused_count' => 3]])
        );
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(202, $response->status);
        $this->assertSame('candidate_received', $decoded['result']['status']);
        $this->assertTrue($decoded['result']['evidence_received']);
        $this->assertArrayNotHasKey('promoted', $decoded['result']);
    }

    public function testArchiveReturnsRealRetentionDecisionRatherThanAssumingArchival(): void
    {
        [$server, $index, $retention] = $this->server();
        $retention->register('episodic', 'record_1');

        $response = $server->handle(
            'POST',
            '/v1/memory/records/episodic:record_1/archival',
            $this->headers(),
            json_encode(['reason' => 'No longer needed.'])
        );
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->status);
        $this->assertSame('retain', $decoded['result']['action']);
        $this->assertSame('evaluated', $decoded['result']['outcome']);
    }

    public function testArchiveReturnsUnknownReferenceWhenNotRegisteredWithRetention(): void
    {
        [$server] = $this->server();

        $response = $server->handle(
            'POST',
            '/v1/memory/records/episodic:never_registered/archival',
            $this->headers(),
            json_encode(['reason' => 'No longer needed.'])
        );
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(404, $response->status);
        $this->assertSame('UNKNOWN_MEMORY_REFERENCE', $decoded['error']['type']);
    }

    public function testAuthorizationDenialFailsClosed(): void
    {
        [$server] = $this->server();

        $response = $server->handle(
            'GET',
            '/v1/memory/records/episodic:record_1',
            $this->headers('permission:denied'),
            null
        );

        $this->assertSame(403, $response->status);
    }

    public function testUnknownRouteReturnsNotFound(): void
    {
        [$server] = $this->server();

        $response = $server->handle('GET', '/v1/memory/unknown', $this->headers(), null);

        $this->assertSame(404, $response->status);
    }

    public function testWrongMethodIsNotAllowed(): void
    {
        [$server] = $this->server();

        $response = $server->handle('DELETE', '/v1/memory/records/episodic:record_1', $this->headers(), null);

        $this->assertSame(405, $response->status);
    }

    public function testMalformedJsonBodyIsRejected(): void
    {
        [$server] = $this->server();

        $response = $server->handle('POST', '/v1/memory/records', $this->headers(), '{not-json');
        $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(400, $response->status);
        $this->assertSame('INVALID_MEMORY_REQUEST', $decoded['error']['type']);
    }
}
