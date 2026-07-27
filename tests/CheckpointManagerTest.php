<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Workflow\CheckpointManager;

final class CheckpointManagerTest extends TestCase
{
    public function testHistoryPreservesRecordOrder(): void
    {
        $manager = new CheckpointManager();

        $manager->record('step-one', 'complete', ['a' => 1]);
        $manager->record('step-two', 'complete', ['a' => 1, 'b' => 2]);

        $history = $manager->history();

        $this->assertCount(2, $history);
        $this->assertSame('step-one', $history[0]->stepId);
        $this->assertSame('step-two', $history[1]->stepId);
    }

    public function testLastCompleteSkipsFailedEntries(): void
    {
        $manager = new CheckpointManager();

        $manager->record('step-one', 'complete', ['a' => 1]);
        $manager->record('step-two', 'failed', ['a' => 1]);

        $last = $manager->lastComplete();

        $this->assertNotNull($last);
        $this->assertSame('step-one', $last->stepId);
    }

    public function testLastCompleteReturnsNullWhenNothingCompleted(): void
    {
        $manager = new CheckpointManager();

        $manager->record('step-one', 'failed', []);

        $this->assertNull($manager->lastComplete());
    }

    public function testRecordedCheckpointIdsAreUnique(): void
    {
        $manager = new CheckpointManager();

        $first = $manager->record('step-one', 'complete', []);
        $second = $manager->record('step-two', 'complete', []);

        $this->assertNotSame($first->id, $second->id);
    }
}
