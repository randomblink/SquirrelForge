<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Knowledge\SqliteKnowledgeRegistry;

final class SqliteKnowledgeRegistryTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-knowledge-registry-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function registry(): SqliteKnowledgeRegistry
    {
        return new SqliteKnowledgeRegistry($this->tempPath('registry'));
    }

    public function testRegisterRejectsAnUnrecognizedType(): void
    {
        $registry = $this->registry();

        $result = $registry->register('Deploy Runbook', 'astrology', 'wiki', 'platform_team');

        $this->assertSame('invalid_type', $result['outcome']);
    }

    public function testRegisterRejectsAnEmptySourceOrOwner(): void
    {
        $registry = $this->registry();

        $result = $registry->register('Deploy Runbook', 'document', '', 'platform_team');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testRegisterCreatesADraftEntry(): void
    {
        $registry = $this->registry();

        $result = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');
        $record = $registry->get($result['knowledge_id']);

        $this->assertSame('registered', $result['outcome']);
        $this->assertSame('Draft', $record['lifecycle_status']);
        $this->assertSame('document', $record['type']);
        $this->assertNull($record['trust_level']);
        $this->assertSame([], $record['relationship_references']);
    }

    public function testRecordTrustLevelRejectsAnUnrecognizedLevel(): void
    {
        $registry = $this->registry();
        $registered = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');

        $result = $registry->recordTrustLevel($registered['knowledge_id'], 'astrology', 'validation_1');

        $this->assertSame('invalid_trust_level', $result['outcome']);
    }

    public function testRecordTrustLevelRejectsAnUnknownKnowledgeId(): void
    {
        $registry = $this->registry();

        $result = $registry->recordTrustLevel('knowledge_missing', 'High', 'validation_1');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testRecordTrustLevelStoresTheLevelAndReference(): void
    {
        $registry = $this->registry();
        $registered = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');

        $registry->recordTrustLevel($registered['knowledge_id'], 'High', 'validation_1');
        $record = $registry->get($registered['knowledge_id']);

        $this->assertSame('High', $record['trust_level']);
        $this->assertSame('validation_1', $record['trust_level_reference']);
    }

    public function testRecordLifecycleStatusRejectsAnUnrecognizedStatus(): void
    {
        $registry = $this->registry();
        $registered = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');

        $result = $registry->recordLifecycleStatus($registered['knowledge_id'], 'astrology');

        $this->assertSame('invalid_status', $result['outcome']);
    }

    public function testRecordLifecycleStatusUpdatesTheStatus(): void
    {
        $registry = $this->registry();
        $registered = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');

        $registry->recordLifecycleStatus($registered['knowledge_id'], 'Active');

        $this->assertSame('Active', $registry->get($registered['knowledge_id'])['lifecycle_status']);
    }

    public function testAttachVersionAndCitationReferencesOverwriteTheSingularField(): void
    {
        $registry = $this->registry();
        $registered = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');
        $id = $registered['knowledge_id'];

        $registry->attachVersionReference($id, 'version_1');
        $registry->attachCitationReference($id, 'citation_1');
        $registry->attachVersionReference($id, 'version_2');

        $record = $registry->get($id);
        $this->assertSame('version_2', $record['version_reference']);
        $this->assertSame('citation_1', $record['citation_reference']);
    }

    public function testAttachRelationshipReferenceAccumulatesIntoAList(): void
    {
        $registry = $this->registry();
        $registered = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');
        $id = $registered['knowledge_id'];

        $registry->attachRelationshipReference($id, 'graph_1');
        $registry->attachRelationshipReference($id, 'graph_2');

        $this->assertSame(['graph_1', 'graph_2'], $registry->get($id)['relationship_references']);
    }

    public function testFindByTypeAndStatusFilterCorrectly(): void
    {
        $registry = $this->registry();
        $a = $registry->register('Deploy Runbook', 'document', 'wiki', 'platform_team');
        $registry->register('Deploy Script', 'artifact', 'ci', 'platform_team');
        $registry->recordLifecycleStatus($a['knowledge_id'], 'Active');

        $this->assertCount(1, $registry->findByType('artifact'));
        $this->assertCount(1, $registry->findByStatus('Active'));
    }

    public function testGetOfAnUnknownKnowledgeIdReturnsNull(): void
    {
        $registry = $this->registry();

        $this->assertNull($registry->get('knowledge_missing'));
    }
}
