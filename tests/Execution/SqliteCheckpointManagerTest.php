<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Execution;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Execution\SqliteCheckpointManager;

final class SqliteCheckpointManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-checkpoint-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function manager(): SqliteCheckpointManager
    {
        return new SqliteCheckpointManager($this->tempPath('db'));
    }

    /**
     * @return array{item_id: string, stage: string, required: bool, status: string}
     */
    private function passingValidationItem(): array
    {
        return ['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'PASSED'];
    }

    /**
     * @return array{item_id: string, stage: string, required: bool, status: string}
     */
    private function failingValidationItem(): array
    {
        return ['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'FAILED', 'waivable' => false];
    }

    // --- create() ---

    public function testCreateRequiresWorkflowRefAndStage(): void
    {
        $manager = $this->manager();

        $result = $manager->create(['workflow_ref' => 'wf_1']);

        $this->assertSame('invalid', $result['outcome']);
        $this->assertNull($result['checkpoint_id']);
    }

    public function testCreateSucceedsAndLandsAtPending(): void
    {
        $manager = $this->manager();

        $result = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 'draft_complete', 'workflow_state' => ['step' => 3]]);

        $this->assertSame('created', $result['outcome']);
        $this->assertNotNull($result['checkpoint_id']);
        $this->assertSame('Pending', $result['record']['status']);
        $this->assertSame(['step' => 3], $result['record']['workflow_state']);
    }

    // --- confirm(): not found / already resolved ---

    public function testConfirmUnknownCheckpointIsNotFound(): void
    {
        $manager = $this->manager();

        $result = $manager->confirm('ghost');

        $this->assertSame('not_found', $result['outcome']);
    }

    public function testConfirmAlreadyCompleteRefusesToReRun(): void
    {
        $manager = $this->manager();
        $checkpointId = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];
        $manager->confirm($checkpointId, [$this->passingValidationItem()]);

        $result = $manager->confirm($checkpointId, [$this->failingValidationItem()]);

        $this->assertSame('already_resolved', $result['outcome']);
        $this->assertSame('Complete', $result['record']['status']);
    }

    // --- confirm(): passing / failing combinations ---

    public function testConfirmWithPassingValidationAndNoRulesIsComplete(): void
    {
        $manager = $this->manager();
        $checkpointId = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];

        $result = $manager->confirm($checkpointId, [$this->passingValidationItem()]);

        $this->assertSame('complete', $result['outcome']);
        $this->assertSame('Complete', $result['record']['status']);
        $this->assertSame('ACCEPTED', $result['record']['validation_decision']);
        $this->assertSame('Passed', $result['record']['rule_outcome']);
    }

    public function testConfirmWithFailingValidationIsFailed(): void
    {
        $manager = $this->manager();
        $checkpointId = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];

        $result = $manager->confirm($checkpointId, [$this->failingValidationItem()]);

        $this->assertSame('failed', $result['outcome']);
        $this->assertSame('Failed', $result['record']['status']);
        $this->assertSame('REJECTED', $result['record']['validation_decision']);
    }

    public function testConfirmWithFailingRuleIsFailedEvenWhenValidationPasses(): void
    {
        $manager = $this->manager();
        $checkpointId = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];

        $result = $manager->confirm(
            $checkpointId,
            [$this->passingValidationItem()],
            [],
            [['id' => 'r1', 'source' => 'domain', 'condition' => ['type' => 'boolean', 'field' => 'ok', 'equals' => true]]],
            ['ok' => false]
        );

        $this->assertSame('failed', $result['outcome']);
        $this->assertSame('Failed', $result['record']['status']);
    }

    public function testConfirmWithAcceptedWithLimitationsCountsAsPassed(): void
    {
        $manager = $this->manager();
        $checkpointId = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];

        $result = $manager->confirm($checkpointId, [
            ['item_id' => 'v1', 'stage' => 'OUTPUT', 'required' => true, 'status' => 'WAIVED', 'waivable' => true],
        ]);

        $this->assertSame('complete', $result['outcome']);
        $this->assertSame('ACCEPTED_WITH_LIMITATIONS', $result['record']['validation_decision']);
    }

    // --- Blocked: prerequisite ordering ---

    public function testSecondCheckpointIsBlockedWhileFirstIsUnresolved(): void
    {
        $manager = $this->manager();
        $first = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];
        $second = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's2'])['checkpoint_id'];

        $result = $manager->confirm($second, [$this->passingValidationItem()]);

        $this->assertSame('blocked', $result['outcome']);
        $this->assertSame('Blocked', $result['record']['status']);
        $this->assertStringContainsString($first, $result['error']);
    }

    public function testSecondCheckpointProceedsOnceFirstIsComplete(): void
    {
        $manager = $this->manager();
        $first = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];
        $second = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's2'])['checkpoint_id'];
        $manager->confirm($first, [$this->passingValidationItem()]);

        $result = $manager->confirm($second, [$this->passingValidationItem()]);

        $this->assertSame('complete', $result['outcome']);
    }

    public function testSecondCheckpointProceedsOnceFirstIsSkipped(): void
    {
        $manager = $this->manager();
        $first = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];
        $second = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's2'])['checkpoint_id'];
        $manager->skip($first, 'ops_lead_1');

        $result = $manager->confirm($second, [$this->passingValidationItem()]);

        $this->assertSame('complete', $result['outcome']);
    }

    public function testUnrelatedWorkflowsDoNotBlockEachOther(): void
    {
        $manager = $this->manager();
        $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1']);
        $other = $manager->create(['workflow_ref' => 'wf_2', 'stage' => 's1'])['checkpoint_id'];

        $result = $manager->confirm($other, [$this->passingValidationItem()]);

        $this->assertSame('complete', $result['outcome']);
    }

    // --- skip() ---

    public function testSkipRequiresNonEmptyAuthorization(): void
    {
        $manager = $this->manager();
        $checkpointId = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];

        $result = $manager->skip($checkpointId, '');

        $this->assertSame('unauthorized', $result['outcome']);
    }

    public function testSkipSucceedsWithAuthorization(): void
    {
        $manager = $this->manager();
        $checkpointId = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];

        $result = $manager->skip($checkpointId, 'ops_lead_1', 'manual override, provider outage');

        $this->assertSame('skipped', $result['outcome']);
        $this->assertSame('Skipped', $result['record']['status']);
        $this->assertSame('ops_lead_1', $result['record']['authorized_by']);
    }

    public function testSkipAlreadyCompleteIsRefused(): void
    {
        $manager = $this->manager();
        $checkpointId = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];
        $manager->confirm($checkpointId, [$this->passingValidationItem()]);

        $result = $manager->skip($checkpointId, 'ops_lead_1');

        $this->assertSame('already_resolved', $result['outcome']);
    }

    // --- history() / latestComplete() / mayContinue() ---

    public function testHistoryReturnsAllCheckpointsInOrder(): void
    {
        $manager = $this->manager();
        $first = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];
        $second = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's2'])['checkpoint_id'];

        $history = $manager->history('wf_1');

        $this->assertCount(2, $history);
        $this->assertSame($first, $history[0]['checkpoint_id']);
        $this->assertSame($second, $history[1]['checkpoint_id']);
    }

    public function testLatestCompleteIgnoresSkippedAndFailed(): void
    {
        $manager = $this->manager();
        $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1']);
        $skipped = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's2'])['checkpoint_id'];

        $this->assertNull($manager->latestComplete('wf_1'));
    }

    public function testLatestCompleteReturnsTheMostRecentCompleteCheckpoint(): void
    {
        $manager = $this->manager();
        $first = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];
        $manager->confirm($first, [$this->passingValidationItem()]);
        $second = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's2'])['checkpoint_id'];
        $manager->confirm($second, [$this->passingValidationItem()]);

        $latest = $manager->latestComplete('wf_1');

        $this->assertSame($second, $latest['checkpoint_id']);
    }

    public function testMayContinueIsTrueForCompleteAndSkippedOnly(): void
    {
        $manager = $this->manager();
        $completeId = $manager->create(['workflow_ref' => 'wf_1', 'stage' => 's1'])['checkpoint_id'];
        $manager->confirm($completeId, [$this->passingValidationItem()]);
        $skippedId = $manager->create(['workflow_ref' => 'wf_2', 'stage' => 's1'])['checkpoint_id'];
        $manager->skip($skippedId, 'ops_lead_1');
        $pendingId = $manager->create(['workflow_ref' => 'wf_3', 'stage' => 's1'])['checkpoint_id'];

        $this->assertTrue($manager->mayContinue($completeId));
        $this->assertTrue($manager->mayContinue($skippedId));
        $this->assertFalse($manager->mayContinue($pendingId));
        $this->assertFalse($manager->mayContinue('ghost'));
    }

    // --- get() ---

    public function testGetUnknownCheckpointReturnsNull(): void
    {
        $manager = $this->manager();

        $this->assertNull($manager->get('ghost'));
    }
}
