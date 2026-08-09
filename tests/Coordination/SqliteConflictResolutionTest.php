<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Coordination;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Coordination\SqliteConflictResolution;
use SquirrelForge\Knowledge\KnowledgeManager;
use SquirrelForge\Knowledge\SqliteDocumentRepository;

final class SqliteConflictResolutionTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-conflict-resolution-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function resolution(?KnowledgeManager $knowledgeManager = null): SqliteConflictResolution
    {
        return new SqliteConflictResolution($this->tempPath('db'), $knowledgeManager);
    }

    /**
     * @return array{task_id: string, agents_involved: array<int, string>, conflict_type: string}
     */
    private function minimalConflict(array $overrides = []): array
    {
        return array_replace([
            'task_id' => 'task_1',
            'agents_involved' => ['agent_security', 'agent_developer'],
            'conflict_type' => 'Technical',
        ], $overrides);
    }

    // --- required fields ---

    public function testMissingTaskIdIsInvalid(): void
    {
        $resolution = $this->resolution();
        $conflict = $this->minimalConflict();
        unset($conflict['task_id']);

        $result = $resolution->resolve($conflict);

        $this->assertSame('Invalid', $result['outcome']);
    }

    public function testEmptyAgentsInvolvedIsInvalid(): void
    {
        $resolution = $this->resolution();

        $result = $resolution->resolve($this->minimalConflict(['agents_involved' => []]));

        $this->assertSame('Invalid', $result['outcome']);
    }

    public function testUnrecognizedConflictTypeIsInvalid(): void
    {
        $resolution = $this->resolution();

        $result = $resolution->resolve($this->minimalConflict(['conflict_type' => 'Made Up Type']));

        $this->assertSame('Invalid', $result['outcome']);
        $this->assertStringContainsString('Conflict Types', $result['error']);
    }

    // --- escalation criteria applied, not invented ---

    public function testExplicitEscalationRequiredEscalates(): void
    {
        $resolution = $this->resolution();

        $result = $resolution->resolve($this->minimalConflict(['escalation_required' => true]));

        $this->assertSame('Escalated', $result['outcome']);
        $this->assertNull($result['resolution']);
    }

    public function testNoApplicableRuleSuppliedEscalates(): void
    {
        $resolution = $this->resolution();

        $result = $resolution->resolve($this->minimalConflict());

        $this->assertSame('Escalated', $result['outcome']);
        $this->assertStringContainsString('No applicable rule', $result['error']);
    }

    // --- Resolution Priority selection ---

    public function testHighestPriorityRuleWins(): void
    {
        $resolution = $this->resolution();

        $result = $resolution->resolve($this->minimalConflict([
            'applicable_rules' => [
                ['source' => 'Coding Standards', 'recommendation' => 'use snake_case'],
                ['source' => 'Security Requirements', 'recommendation' => 'sanitize all inputs'],
                ['source' => 'Documentation Standards', 'recommendation' => 'add a docblock'],
            ],
        ]));

        $this->assertSame('Resolved', $result['outcome']);
        $this->assertSame('Security Requirements', $result['decision_source']);
        $this->assertSame('sanitize all inputs', $result['resolution']);
    }

    public function testProjectRulesOutranksEverythingElse(): void
    {
        $resolution = $this->resolution();

        $result = $resolution->resolve($this->minimalConflict([
            'applicable_rules' => [
                ['source' => 'Security Requirements', 'recommendation' => 'a'],
                ['source' => 'Project Rules', 'recommendation' => 'b'],
            ],
        ]));

        $this->assertSame('Project Rules', $result['decision_source']);
    }

    public function testUnrecognizedRuleSourceIsIgnoredNotFabricated(): void
    {
        $resolution = $this->resolution();

        $result = $resolution->resolve($this->minimalConflict([
            'applicable_rules' => [['source' => 'Made Up Authority', 'recommendation' => 'x']],
        ]));

        $this->assertSame('Escalated', $result['outcome']);
    }

    // --- recurrence ---

    public function testFirstConflictOfATypeNeverAutoEscalates(): void
    {
        $resolution = $this->resolution();

        $result = $resolution->resolve($this->minimalConflict([
            'applicable_rules' => [['source' => 'Coding Standards', 'recommendation' => 'x']],
        ]));

        $this->assertSame('Resolved', $result['outcome']);
    }

    public function testRecurringConflictOfTheSameTypeEscalatesAutomatically(): void
    {
        $resolution = $this->resolution();
        $conflict = $this->minimalConflict(['applicable_rules' => [['source' => 'Coding Standards', 'recommendation' => 'x']]]);

        $resolution->resolve($conflict);
        $result = $resolution->resolve($conflict);

        $this->assertSame('Escalated', $result['outcome']);
        $this->assertStringContainsString('occurrence', $result['error']);
    }

    public function testDifferentConflictTypesHaveIndependentRecurrence(): void
    {
        $resolution = $this->resolution();
        $rules = ['applicable_rules' => [['source' => 'Coding Standards', 'recommendation' => 'x']]];
        $resolution->resolve($this->minimalConflict($rules + ['conflict_type' => 'Technical']));

        $result = $resolution->resolve($this->minimalConflict($rules + ['conflict_type' => 'Performance']));

        $this->assertSame('Resolved', $result['outcome']);
    }

    // --- clearBlock() ---

    public function testClearBlockIsCalledOnlyOnResolved(): void
    {
        $resolution = $this->resolution();
        $seen = null;

        $resolution->resolve(
            $this->minimalConflict(['applicable_rules' => [['source' => 'Coding Standards', 'recommendation' => 'use snake_case']]]),
            function (string $taskId, string $decision) use (&$seen): void {
                $seen = [$taskId, $decision];
            }
        );

        $this->assertSame(['task_1', 'use snake_case'], $seen);
    }

    public function testClearBlockIsNeverCalledOnEscalated(): void
    {
        $resolution = $this->resolution();
        $invoked = false;

        $resolution->resolve(
            $this->minimalConflict(['escalation_required' => true]),
            function () use (&$invoked): void {
                $invoked = true;
            }
        );

        $this->assertFalse($invoked);
    }

    // --- KnowledgeManager composition ---

    public function testReusableGuidanceIsForwardedToKnowledgeManager(): void
    {
        $documents = new SqliteDocumentRepository($this->tempPath('docs'));
        $knowledgeManager = new KnowledgeManager($documents);
        $resolution = $this->resolution($knowledgeManager);

        $result = $resolution->resolve($this->minimalConflict([
            'applicable_rules' => [['source' => 'Security Requirements', 'recommendation' => 'sanitize inputs']],
            'reusable_guidance' => 'Always sanitize user-controlled input before rendering.',
        ]));

        $this->assertSame('Resolved', $result['outcome']);
    }

    public function testNoReusableGuidanceNeverCallsKnowledgeManager(): void
    {
        $resolution = $this->resolution();

        $result = $resolution->resolve($this->minimalConflict([
            'applicable_rules' => [['source' => 'Security Requirements', 'recommendation' => 'x']],
        ]));

        $this->assertSame('Resolved', $result['outcome']);
    }

    // --- get() / history() ---

    public function testGetUnknownConflictReturnsNull(): void
    {
        $resolution = $this->resolution();

        $this->assertNull($resolution->get('ghost'));
    }

    public function testHistoryReturnsAllConflictsForATaskInOrder(): void
    {
        $resolution = $this->resolution();
        $resolution->resolve($this->minimalConflict(['applicable_rules' => [['source' => 'Coding Standards', 'recommendation' => 'a']]]));
        $resolution->resolve($this->minimalConflict(['conflict_type' => 'Security', 'applicable_rules' => [['source' => 'Security Requirements', 'recommendation' => 'b']]]));

        $history = $resolution->history('task_1');

        $this->assertCount(2, $history);
        $this->assertSame('Technical', $history[0]['conflict_type']);
        $this->assertSame('Security', $history[1]['conflict_type']);
    }

    public function testWorksWithoutAKnowledgeManager(): void
    {
        $resolution = $this->resolution(null);

        $result = $resolution->resolve($this->minimalConflict([
            'applicable_rules' => [['source' => 'Coding Standards', 'recommendation' => 'x']],
            'reusable_guidance' => 'reuse me',
        ]));

        $this->assertSame('Resolved', $result['outcome']);
    }
}
