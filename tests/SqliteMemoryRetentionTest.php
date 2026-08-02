<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Contracts\EventInterface;
use SquirrelForge\Events\CallbackEventListener;
use SquirrelForge\Events\EventBus;
use SquirrelForge\Memory\SqliteMemoryIndex;
use SquirrelForge\Memory\SqliteMemoryRetention;

final class SqliteMemoryRetentionTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-memory-retention-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function retention(): SqliteMemoryRetention
    {
        return new SqliteMemoryRetention($this->tempPath('retention'));
    }

    public function testRegisterRejectsAnUnknownMemoryType(): void
    {
        $retention = $this->retention();

        $result = $retention->register('knowledge', 'record_1');

        $this->assertSame('invalid_memory_type', $result['outcome']);
    }

    public function testRegisterIsIdempotent(): void
    {
        $retention = $this->retention();

        $first = $retention->register('episodic', 'record_1');
        $second = $retention->register('episodic', 'record_1');

        $this->assertSame('registered', $first['outcome']);
        $this->assertSame('already_registered', $second['outcome']);
        $this->assertSame($first['retention_id'], $second['retention_id']);
    }

    public function testEvaluateOnAnUnregisteredRecordIsRejected(): void
    {
        $retention = $this->retention();

        $result = $retention->evaluate('episodic', 'record_1');

        $this->assertSame('not_registered', $result['outcome']);
    }

    public function testWorkingMemoryPrunesImmediatelyOnTaskCompletionWithNoArchivalStep(): void
    {
        $retention = $this->retention();
        $retention->register('working', 'task_1');

        $result = $retention->evaluate('working', 'task_1', ['task_completed' => true]);

        $this->assertSame('removed', $result['outcome']);
        $this->assertSame('Removed', $result['state']);
    }

    public function testWorkingMemoryIsRetainedWhileTheTaskIsStillActive(): void
    {
        $retention = $this->retention();
        $retention->register('working', 'task_1');

        $result = $retention->evaluate('working', 'task_1');

        $this->assertSame('evaluated', $result['outcome']);
        $this->assertSame('retain', $result['action']);
    }

    public function testEpisodicMemoryIsRetainedByDefault(): void
    {
        $retention = $this->retention();
        $retention->register('episodic', 'record_1');

        $result = $retention->evaluate('episodic', 'record_1');

        $this->assertSame('retain', $result['action']);
    }

    public function testEpisodicMemoryCannotBePrunedBeforeArchivalEvenWithGovernanceApproval(): void
    {
        $retention = $this->retention();
        $retention->register('episodic', 'record_1');

        $result = $retention->evaluate('episodic', 'record_1', ['governance_approved' => true, 'requested_action' => 'prune']);

        $this->assertSame('blocked', $result['outcome']);
        $this->assertSame('Active', $result['state']);
    }

    public function testEpisodicMemoryArchivesThenPrunesWithGovernanceApproval(): void
    {
        $retention = $this->retention();
        $retention->register('episodic', 'record_1');
        $retention->evaluate('episodic', 'record_1', ['governance_approved' => true, 'requested_action' => 'archive']);

        $result = $retention->evaluate('episodic', 'record_1', ['governance_approved' => true, 'requested_action' => 'prune']);

        $this->assertSame('removed', $result['outcome']);
    }

    public function testSemanticMemoryArchivesWhenSuperseded(): void
    {
        $retention = $this->retention();
        $retention->register('semantic', 'record_1');

        $result = $retention->evaluate('semantic', 'record_1', ['superseded' => true]);

        $this->assertSame('archived', $result['outcome']);
        $this->assertStringContainsString('Superseded', $result['reason']);
    }

    public function testSemanticMemoryArchivesWhenNoLongerReferenced(): void
    {
        $retention = $this->retention();
        $retention->register('semantic', 'record_1');

        $result = $retention->evaluate('semantic', 'record_1', ['no_longer_referenced' => true]);

        $this->assertSame('archived', $result['outcome']);
    }

    public function testSemanticMemoryPrunesAfterArchivalOnceTheInactivityWindowIsExceeded(): void
    {
        $retention = $this->retention();
        $retention->register('semantic', 'record_1');
        $retention->evaluate('semantic', 'record_1', ['superseded' => true]);

        $result = $retention->evaluate('semantic', 'record_1', ['days_since_last_reference' => 100, 'inactivity_window_days' => 90]);

        $this->assertSame('removed', $result['outcome']);
    }

    public function testSemanticMemoryStaysArchivedWithinTheInactivityWindow(): void
    {
        $retention = $this->retention();
        $retention->register('semantic', 'record_1');
        $retention->evaluate('semantic', 'record_1', ['superseded' => true]);

        $result = $retention->evaluate('semantic', 'record_1', ['days_since_last_reference' => 10, 'inactivity_window_days' => 90]);

        $this->assertSame('evaluated', $result['outcome']);
        $this->assertSame('Archived', $result['state']);
    }

    public function testProjectMemoryRecommendsWithoutGovernanceApprovalAtRetentionPhase(): void
    {
        $retention = $this->retention();
        $retention->register('project', 'ProjectA');

        $result = $retention->evaluate('project', 'ProjectA', ['project_lifecycle_phase' => 'RETENTION']);

        $this->assertSame('recommended', $result['outcome']);
    }

    public function testProjectMemoryArchivesWithGovernanceApprovalAtRetentionPhase(): void
    {
        $retention = $this->retention();
        $retention->register('project', 'ProjectA');

        $result = $retention->evaluate('project', 'ProjectA', ['project_lifecycle_phase' => 'RETENTION', 'governance_approved' => true]);

        $this->assertSame('archived', $result['outcome']);
    }

    public function testProjectMemoryPrunesOnlyAfterCloseoutApproval(): void
    {
        $retention = $this->retention();
        $retention->register('project', 'ProjectA');
        $retention->evaluate('project', 'ProjectA', ['project_lifecycle_phase' => 'RETENTION', 'governance_approved' => true]);

        $notApproved = $retention->evaluate('project', 'ProjectA');
        $approved = $retention->evaluate('project', 'ProjectA', ['closeout_approved' => true]);

        $this->assertSame('evaluated', $notApproved['outcome']);
        $this->assertSame('removed', $approved['outcome']);
    }

    public function testEvaluateOnAnAlreadyRemovedRecordIsANoOp(): void
    {
        $retention = $this->retention();
        $retention->register('working', 'task_1');
        $retention->evaluate('working', 'task_1', ['task_completed' => true]);

        $result = $retention->evaluate('working', 'task_1');

        $this->assertSame('already_removed', $result['outcome']);
    }

    public function testArchivingUpdatesTheMemoryIndexEntry(): void
    {
        $indexPath = $this->tempPath('index');
        $index = new SqliteMemoryIndex($indexPath);
        $index->indexRecord('semantic', 'record_1', ['title' => 'Original title']);
        $retention = new SqliteMemoryRetention($this->tempPath('retention'), $index);
        $retention->register('semantic', 'record_1');

        $retention->evaluate('semantic', 'record_1', ['superseded' => true]);

        $this->assertNotNull($index->get('semantic', 'record_1'));
    }

    public function testPruningRemovesTheMemoryIndexEntry(): void
    {
        $index = new SqliteMemoryIndex($this->tempPath('index'));
        $index->indexRecord('working', 'task_1', ['title' => 'Active context']);
        $retention = new SqliteMemoryRetention($this->tempPath('retention'), $index);
        $retention->register('working', 'task_1');

        $retention->evaluate('working', 'task_1', ['task_completed' => true]);

        $this->assertNull($index->get('working', 'task_1'));
    }

    public function testEvaluateDispatchesAnEvent(): void
    {
        $events = new EventBus();
        /** @var array<int, EventInterface> $captured */
        $captured = [];
        $events->listen('memory_retention.removed', new CallbackEventListener(
            function (EventInterface $event) use (&$captured): void {
                $captured[] = $event;
            }
        ));

        $retention = new SqliteMemoryRetention($this->tempPath('retention'), events: $events);
        $retention->register('working', 'task_1');
        $retention->evaluate('working', 'task_1', ['task_completed' => true]);

        $this->assertCount(1, $captured);
    }
}
