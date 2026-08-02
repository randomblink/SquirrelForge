<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Memory\EpisodicMemory;
use SquirrelForge\Memory\InMemoryStore;
use SquirrelForge\Memory\MemoryManager;
use SquirrelForge\Memory\MemoryRetrieval;
use SquirrelForge\Memory\ProjectMemory;
use SquirrelForge\Memory\SemanticMemory;
use SquirrelForge\Memory\SqliteMemoryIndex;
use SquirrelForge\Memory\SqliteMemoryRetention;
use SquirrelForge\Memory\WorkingMemory;
use SquirrelForge\Observability\AuditTrail;
use SquirrelForge\Storage\SqliteDocumentStorage;

final class MemoryManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-memory-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    /**
     * @return array{0: SqliteMemoryIndex, 1: WorkingMemory, 2: EpisodicMemory, 3: ProjectMemory, 4: MemoryManager, 5: AuditTrail}
     */
    private function fullyWiredManager(): array
    {
        $index = new SqliteMemoryIndex($this->tempPath('index'));
        $workingMemory = new WorkingMemory();
        $episodicMemory = new EpisodicMemory(new InMemoryStore());
        $semanticMemory = new SemanticMemory(new InMemoryStore());
        $projectMemory = new ProjectMemory(new InMemoryStore());
        $retrieval = new MemoryRetrieval($index, $workingMemory, $episodicMemory, $semanticMemory, $projectMemory);
        $retention = new SqliteMemoryRetention($this->tempPath('retention'), $index);
        $auditTrail = new AuditTrail(new SqliteDocumentStorage($this->tempPath('audit_documents')));

        $manager = new MemoryManager($workingMemory, $episodicMemory, $semanticMemory, $projectMemory, $index, $retrieval, $retention, $auditTrail);

        return [$index, $workingMemory, $episodicMemory, $projectMemory, $manager, $auditTrail];
    }

    public function testUnknownOperationIsRejected(): void
    {
        $manager = new MemoryManager();

        $result = $manager->coordinate('teleport', []);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('none', $result['target_component']);
    }

    public function testMissingRequiredFieldIsRejectedBeforeRouting(): void
    {
        $manager = new MemoryManager();

        $result = $manager->coordinate('store_episodic', ['task_reference' => 'task_1']);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('goal_reference', $result['error']);
    }

    public function testStoreWorkingInitializesThenRefreshesOnSubsequentCalls(): void
    {
        [$index, $workingMemory, , , $manager] = $this->fullyWiredManager();

        $first = $manager->coordinate('store_working', ['task_id' => 'task_1', 'references' => ['active_goal' => 'goal_1']]);
        $second = $manager->coordinate('store_working', ['task_id' => 'task_1', 'references' => ['current_task' => 'task_1']]);

        $this->assertSame('initialized', $first['outcome']);
        $this->assertSame('refreshed', $second['outcome']);
        $snapshot = $workingMemory->snapshot('task_1');
        $this->assertSame('goal_1', $snapshot['active_goal']);
        $this->assertSame('task_1', $snapshot['current_task']);
        $this->assertNotNull($index->get('working', 'task_1'));
    }

    public function testStoreEpisodicIndexesTheRecordOnSuccess(): void
    {
        [$index, , , , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('store_episodic', [
            'task_reference' => 'task_1', 'goal_reference' => 'goal_1',
            'outcome_summary' => 'Checkout discount shipped.', 'validation_result_reference' => 'ACCEPTED',
        ]);

        $this->assertSame('recorded', $result['outcome']);
        $indexed = $index->get('episodic', $result['result']['memory_id']);
        $this->assertSame('Checkout discount shipped.', $indexed['title']);
    }

    public function testStoreSemanticIndexesTheRecordOnSuccess(): void
    {
        [$index, , , , $manager] = $this->fullyWiredManager();

        $result = $manager->coordinate('store_semantic', [
            'category' => 'Patterns', 'title' => 'Consume, don\'t compute', 'description' => 'desc',
            'source_reference' => 'ref_1', 'validation_reference' => 'ACCEPTED',
        ]);

        $this->assertSame('stored', $result['outcome']);
        $this->assertNotNull($index->get('semantic', $result['result']['record_id']));
    }

    public function testInitializeProjectAndReferenceOperationsComposeProjectMemory(): void
    {
        [, , , $projectMemory, $manager] = $this->fullyWiredManager();

        $manager->coordinate('initialize_project', ['project_name' => 'SquirrelForge']);
        $manager->coordinate('reference_project_decision', ['project_name' => 'SquirrelForge', 'reference' => 'decision_1']);
        $manager->coordinate('add_project_note', ['project_name' => 'SquirrelForge', 'note' => 'Note 1']);
        $manager->coordinate('set_project_conventions', ['project_name' => 'SquirrelForge', 'conventions' => ['PSR-12']]);
        $manager->coordinate('update_project_architecture', ['project_name' => 'SquirrelForge', 'reference' => 'arch_1']);

        $record = $projectMemory->get('SquirrelForge');
        $this->assertSame(['decision_1'], $record['decisions']);
        $this->assertSame(['Note 1'], $record['notes']);
        $this->assertSame(['PSR-12'], $record['conventions']);
        $this->assertSame('arch_1', $record['architecture']);
    }

    public function testRetrieveRoutesToMemoryRetrieval(): void
    {
        [$index, , , , $manager] = $this->fullyWiredManager();
        $index->indexRecord('semantic', 'record_1', ['title' => 'Checkout pattern']);

        $result = $manager->coordinate('retrieve', ['query_terms' => ['checkout']]);

        $this->assertSame('memory_retrieval', $result['target_component']);
        $this->assertSame('searched', $result['outcome']);
        $this->assertCount(1, $result['result']['results']);
    }

    public function testApplyRetentionRoutesToMemoryRetention(): void
    {
        [, , , , $manager] = $this->fullyWiredManager();
        $manager->coordinate('apply_retention', ['memory_type' => 'working', 'record_id' => 'task_1']);
        // Not registered yet -- confirms real routing without fabricating success.
        $result = $manager->coordinate('apply_retention', ['memory_type' => 'working', 'record_id' => 'task_1']);

        $this->assertSame('memory_retention', $result['target_component']);
        $this->assertSame('not_registered', $result['outcome']);
    }

    public function testCoordinateRecordsARealAuditEvent(): void
    {
        [, , , , $manager, $auditTrail] = $this->fullyWiredManager();

        $result = $manager->coordinate('store_semantic', [
            'category' => 'Patterns', 'title' => 'Audited pattern', 'description' => 'desc',
            'source_reference' => 'ref_1', 'validation_reference' => 'ACCEPTED',
            'requesting_component' => 'reflection_engine',
        ]);

        $this->assertSame('stored', $result['outcome']);
        $auditRecords = $auditTrail->search(['actor_ref' => 'reflection_engine']);
        $this->assertCount(1, $auditRecords);
        $this->assertSame('store_semantic', $auditRecords[0]['action']);
        $this->assertSame('stored', $auditRecords[0]['outcome']);
    }
}
