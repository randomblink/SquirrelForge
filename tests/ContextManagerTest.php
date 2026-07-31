<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Engine\ContextManager;
use SquirrelForge\Engine\InMemoryEngineRuntime;
use SquirrelForge\Engine\ProjectLoader;

final class ContextManagerTest extends TestCase
{
    public function testLoadWithAnUnknownTierIsRejected(): void
    {
        $manager = new ContextManager();

        $result = $manager->load('goal', 'made_up_tier', 'ship the feature');

        $this->assertSame('invalid_tier', $result['outcome']);
    }

    public function testLoadedEntriesAppearInTheRecordSortedByLoadingOrder(): void
    {
        $manager = new ContextManager();
        $manager->load('goal', 'user_request', 'ship the feature');
        $manager->load('rules', 'mandatory_rules', ['README.md' => '...']);

        $record = $manager->record();

        $this->assertSame(['goal', 'rules'], array_keys($record));
        $this->assertSame('ship the feature', $record['goal']['value']);
    }

    public function testLoadProjectContextWithoutProjectLoaderIsNotConfigured(): void
    {
        $manager = new ContextManager();

        $result = $manager->loadProjectContext('SquirrelForge', sys_get_temp_dir());

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testLoadProjectContextDelegatesToProjectLoaderAndProtectsTheEntry(): void
    {
        $loader = new ProjectLoader(fn(string $command): string => str_contains($command, 'rev-parse --show-toplevel') ? "/repos/SquirrelForge\n" : '');
        $manager = new ContextManager($loader);

        $result = $manager->loadProjectContext('SquirrelForge', '/repos/SquirrelForge');

        $this->assertSame('loaded', $result['outcome']);
        $this->assertSame('matched', $result['context']['repository_identity']['status']);

        // Protected entries survive every pruning trigger.
        $removed = $manager->prune('project_complete');
        $this->assertNotContains('project_context', $removed);
        $this->assertArrayHasKey('project_context', $manager->record());
    }

    public function testLoadActiveStateWithoutEngineRuntimeIsNotConfigured(): void
    {
        $manager = new ContextManager();

        $result = $manager->loadActiveState('exec_1');

        $this->assertSame('not_configured', $result['outcome']);
    }

    public function testLoadActiveStateDelegatesToEngineRuntimeAndProtectsTheEntry(): void
    {
        $runtime = new InMemoryEngineRuntime();
        $submitted = $runtime->submit(['goal' => 'ship it'], 'project_1', 'identity_1', 'permission:allowed', 'idem_1', 'corr_1');
        $manager = new ContextManager(engineRuntime: $runtime);

        $result = $manager->loadActiveState($submitted['execution_ref']);

        $this->assertSame('loaded', $result['outcome']);
        $this->assertNotNull($result['state']);

        $removed = $manager->prune('task_finished');
        $this->assertNotContains('active_state', $removed);
    }

    public function testResolveEvidenceWithNoCandidatesIsRejected(): void
    {
        $manager = new ContextManager();

        $result = $manager->resolveEvidence([]);

        $this->assertSame('no_candidates', $result['outcome']);
    }

    public function testResolveEvidenceWithAnUnknownTierIsRejected(): void
    {
        $manager = new ContextManager();

        $result = $manager->resolveEvidence([['tier' => 'made_up_tier', 'value' => 'x']]);

        $this->assertSame('invalid_tier', $result['outcome']);
    }

    public function testResolveEvidencePrefersTheHighestPriorityTier(): void
    {
        $manager = new ContextManager();

        $result = $manager->resolveEvidence([
            ['tier' => 'general_knowledge', 'value' => 'stale memory says X'],
            ['tier' => 'repository_runtime_evidence', 'value' => 'the repo actually shows Y'],
            ['tier' => 'assumptions', 'value' => 'maybe Z'],
        ]);

        $this->assertSame('the repo actually shows Y', $result['value']);
        $this->assertSame('repository_runtime_evidence', $result['tier']);
        $this->assertFalse($result['is_assumption']);
    }

    public function testResolveEvidenceFlagsAWinningAssumption(): void
    {
        $manager = new ContextManager();

        $result = $manager->resolveEvidence([['tier' => 'assumptions', 'value' => 'probably fine']]);

        $this->assertTrue($result['is_assumption']);
    }

    public function testFlagStalenessReturnsNullWhenValuesMatch(): void
    {
        $manager = new ContextManager();

        $this->assertNull($manager->flagStaleness('branch', 'main', 'main'));
        $this->assertSame([], $manager->stalenessConflicts());
    }

    public function testFlagStalenessRecordsARealConflictWhenValuesDiffer(): void
    {
        $manager = new ContextManager();

        $conflict = $manager->flagStaleness('branch', 'main', 'feature/old');

        $this->assertNotNull($conflict);
        $this->assertSame('main', $conflict['current']);
        $this->assertSame('feature/old', $conflict['remembered']);
        $this->assertCount(1, $manager->stalenessConflicts());
    }

    public function testPruneOnlyRemovesEntriesMatchingTheTriggerScope(): void
    {
        $manager = new ContextManager();
        $manager->load('workflow_ref', 'workflow_checklists', 'wf_1', 'workflow');
        $manager->load('task_note', 'task_supporting_context', 'note', 'task');

        $removed = $manager->prune('workflow_route_changed');

        $this->assertSame(['workflow_ref'], $removed);
        $this->assertArrayNotHasKey('workflow_ref', $manager->record());
        $this->assertArrayHasKey('task_note', $manager->record());
    }
}
