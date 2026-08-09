<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Coordination;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Coordination\SqlitePriorityManager;
use SquirrelForge\Engine\DependencyAnalyzer;

final class SqlitePriorityManagerTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-priority-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function manager(): SqlitePriorityManager
    {
        return new SqlitePriorityManager($this->tempPath('db'), new DependencyAnalyzer());
    }

    private function missingDependency(): array
    {
        return ['id' => 'd1', 'name' => 'build tool', 'type' => 'tool', 'required' => true, 'status' => 'MISSING'];
    }

    // --- required field ---

    public function testMissingTaskIdIsInvalid(): void
    {
        $manager = $this->manager();

        $result = $manager->assign([]);

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- scoring: default / all-medium factors ---

    public function testDefaultFactorsWithNoBlockersIsMedium(): void
    {
        $manager = $this->manager();

        $result = $manager->assign(['task_id' => 't1']);

        $this->assertSame('Medium', $result['priority']);
    }

    public function testHighUrgencyFactorsAcrossTheBoardIsCritical(): void
    {
        $manager = $this->manager();

        $result = $manager->assign([
            'task_id' => 't1',
            'factors' => [
                'urgency' => 'critical',
                'security_implications' => true,
                'release_readiness' => true,
                'technical_risk' => 'high',
                'business_value' => 'high',
                'estimated_effort' => 'low',
            ],
        ]);

        $this->assertSame('Critical', $result['priority']);
    }

    public function testLowUrgencyFactorsAcrossTheBoardIsLow(): void
    {
        $manager = $this->manager();

        $result = $manager->assign([
            'task_id' => 't1',
            'factors' => ['urgency' => 'none', 'technical_risk' => 'medium', 'business_value' => 'low', 'estimated_effort' => 'high'],
        ]);

        $this->assertSame('Low', $result['priority']);
    }

    // --- dependency-blocking impact ---

    public function testUnresolvedBlockerIsSurfacedInResult(): void
    {
        $manager = $this->manager();

        $result = $manager->assign(['task_id' => 't1', 'dependencies' => [$this->missingDependency()]]);

        $this->assertCount(1, $result['blockers']);
    }

    public function testBlockerCapsAHighComputedPriorityToMedium(): void
    {
        $manager = $this->manager();

        $result = $manager->assign([
            'task_id' => 't1',
            'factors' => ['urgency' => 'high', 'security_implications' => true],
            'dependencies' => [$this->missingDependency()],
        ]);

        $this->assertSame('Medium', $result['priority']);
        $this->assertStringContainsString('capped', $result['reason']);
    }

    public function testAuthorizedBypassPreventsTheCap(): void
    {
        $manager = $this->manager();

        $capped = $manager->assign([
            'task_id' => 't1',
            'factors' => ['urgency' => 'high', 'security_implications' => true],
            'dependencies' => [$this->missingDependency()],
        ]);
        $bypassed = $manager->assign([
            'task_id' => 't1',
            'factors' => ['urgency' => 'high', 'security_implications' => true],
            'dependencies' => [$this->missingDependency()],
            'authorized_bypass' => true,
        ]);

        $this->assertSame('Medium', $capped['priority']);
        $this->assertNotSame('Medium', $bypassed['priority']);
        $this->assertStringNotContainsString('capped', $bypassed['reason']);
    }

    public function testBlockerAtAnAlreadyLowLevelIsNotFlaggedAsCapped(): void
    {
        $manager = $this->manager();

        $result = $manager->assign([
            'task_id' => 't1',
            'factors' => ['urgency' => 'none', 'business_value' => 'low'],
            'dependencies' => [$this->missingDependency()],
        ]);

        $this->assertStringNotContainsString('capped', $result['reason']);
    }

    // --- recalculate(): trigger vocabulary ---

    public function testRecalculateRejectsAnUnrecognizedTrigger(): void
    {
        $manager = $this->manager();

        $result = $manager->recalculate(['task_id' => 't1'], 'Someone asked nicely');

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('Reprioritization Triggers', $result['error']);
    }

    public function testRecalculateWithARealTriggerSucceeds(): void
    {
        $manager = $this->manager();

        $result = $manager->recalculate(['task_id' => 't1'], 'Security issue');

        $this->assertSame('assigned', $result['outcome']);
        $this->assertStringContainsString('Security issue', $result['reason']);
    }

    // --- persistence: current() / allCurrent() / history() ---

    public function testCurrentReturnsTheMostRecentAssignment(): void
    {
        $manager = $this->manager();
        $manager->assign(['task_id' => 't1', 'factors' => ['urgency' => 'low']]);
        $manager->recalculate(['task_id' => 't1', 'factors' => ['urgency' => 'critical', 'security_implications' => true, 'release_readiness' => true, 'technical_risk' => 'high', 'business_value' => 'high']], 'New critical task');

        $current = $manager->current('t1');

        $this->assertSame('Critical', $current['priority']);
    }

    public function testCurrentForAnUnknownTaskIsNull(): void
    {
        $manager = $this->manager();

        $this->assertNull($manager->current('ghost'));
    }

    public function testAllCurrentReturnsOneEntryPerTaskOrderedByPriority(): void
    {
        $manager = $this->manager();
        $manager->assign(['task_id' => 't_low', 'factors' => ['urgency' => 'none', 'business_value' => 'low']]);
        $manager->assign(['task_id' => 't_critical', 'factors' => ['urgency' => 'critical', 'security_implications' => true, 'release_readiness' => true, 'technical_risk' => 'high', 'business_value' => 'high']]);
        $manager->assign(['task_id' => 't_low', 'factors' => ['urgency' => 'none', 'business_value' => 'low']]);

        $all = $manager->allCurrent();

        $this->assertCount(2, $all);
        $this->assertSame('t_critical', $all[0]['task_id']);
        $this->assertSame('t_low', $all[1]['task_id']);
    }

    public function testHistoryReturnsEveryDecisionInOrder(): void
    {
        $manager = $this->manager();
        $manager->assign(['task_id' => 't1']);
        $manager->recalculate(['task_id' => 't1'], 'Blocked dependency');

        $history = $manager->history('t1');

        $this->assertCount(2, $history);
        $this->assertNull($history[0]['trigger_name']);
        $this->assertSame('Blocked dependency', $history[1]['trigger_name']);
    }

    // --- works without a DependencyAnalyzer ---

    public function testWorksWithoutADependencyAnalyzer(): void
    {
        $manager = new SqlitePriorityManager($this->tempPath('db2'), null);

        $result = $manager->assign(['task_id' => 't1']);

        $this->assertSame('assigned', $result['outcome']);
        $this->assertSame([], $result['blockers']);
    }
}
