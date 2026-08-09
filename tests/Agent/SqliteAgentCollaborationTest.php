<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Agent;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\SqliteAgentCollaboration;

final class SqliteAgentCollaborationTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-agent-collaboration-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function collaboration(): SqliteAgentCollaboration
    {
        return new SqliteAgentCollaboration($this->tempPath('db'));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestFor(array $overrides = []): array
    {
        return array_replace([
            'objective' => 'Ship the new onboarding flow',
            'collaboration_model' => 'Sequential',
            'participants' => [
                ['agent' => 'agent_planner', 'role' => 'Planner', 'ownership_boundary' => 'plan'],
                ['agent' => 'agent_developer', 'role' => 'Developer', 'ownership_boundary' => 'implementation'],
            ],
            'escalation_criteria' => ['unresolved_dependency'],
        ], $overrides);
    }

    // --- shape validation ---

    public function testMissingObjectiveIsInvalid(): void
    {
        $result = $this->collaboration()->define($this->requestFor(['objective' => '']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testEmptyParticipantsIsInvalid(): void
    {
        $result = $this->collaboration()->define($this->requestFor(['participants' => []]));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('at least one participating agent', $result['error']);
    }

    public function testParticipantMissingRoleIsInvalid(): void
    {
        $result = $this->collaboration()->define($this->requestFor([
            'participants' => [['agent' => 'agent_planner', 'role' => '']],
        ]));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUnrecognizedCollaborationModelIsInvalid(): void
    {
        $result = $this->collaboration()->define($this->requestFor(['collaboration_model' => 'Freelance']));

        $this->assertSame('invalid', $result['outcome']);
        $this->assertStringContainsString('Collaboration Models', $result['error']);
    }

    // --- collaboration models ---

    /**
     * @return array<int, array{0: string}>
     */
    public static function nonHierarchicalModelProvider(): array
    {
        return [['Sequential'], ['Parallel'], ['Consensus'], ['Specialist Team']];
    }

    #[DataProvider('nonHierarchicalModelProvider')]
    public function testNonHierarchicalModelsDoNotRequireALeadAgent(string $model): void
    {
        $result = $this->collaboration()->define($this->requestFor(['collaboration_model' => $model]));

        $this->assertSame('defined', $result['outcome']);
    }

    public function testHierarchicalModelWithoutLeadAgentIsRejected(): void
    {
        $result = $this->collaboration()->define($this->requestFor(['collaboration_model' => 'Hierarchical']));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('Hierarchical model requires an identified lead agent', $result['error']);
    }

    public function testHierarchicalModelWithLeadAgentSucceeds(): void
    {
        $result = $this->collaboration()->define($this->requestFor(['collaboration_model' => 'Hierarchical', 'lead_agent' => 'agent_planner']));

        $this->assertSame('defined', $result['outcome']);
    }

    public function testLeadAgentNotAmongParticipantsIsRejected(): void
    {
        $result = $this->collaboration()->define($this->requestFor(['collaboration_model' => 'Hierarchical', 'lead_agent' => 'agent_ghost']));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('not one of this structure\'s own participants', $result['error']);
    }

    // --- one-owner-at-a-time ---

    public function testDuplicateOwnershipBoundaryIsRejected(): void
    {
        $result = $this->collaboration()->define($this->requestFor([
            'participants' => [
                ['agent' => 'agent_planner', 'role' => 'Planner', 'ownership_boundary' => 'design'],
                ['agent' => 'agent_developer', 'role' => 'Developer', 'ownership_boundary' => 'design'],
            ],
        ]));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('one-owner-at-a-time rule', $result['error']);
    }

    public function testParticipantsWithoutAnOwnershipBoundaryDoNotConflict(): void
    {
        $result = $this->collaboration()->define($this->requestFor([
            'participants' => [
                ['agent' => 'agent_planner', 'role' => 'Planner'],
                ['agent' => 'agent_developer', 'role' => 'Developer'],
            ],
        ]));

        $this->assertSame('defined', $result['outcome']);
    }

    // --- escalation criteria ---

    public function testEmptyEscalationCriteriaIsRejected(): void
    {
        $result = $this->collaboration()->define($this->requestFor(['escalation_criteria' => []]));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('not improvised during a conflict', $result['error']);
    }

    public function testUnrecognizedEscalationCriterionIsRejected(): void
    {
        $result = $this->collaboration()->define($this->requestFor(['escalation_criteria' => ['someone_felt_left_out']]));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('Unrecognized escalation criteria', $result['error']);
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function escalationCriterionProvider(): array
    {
        return [
            ['conflicting_outputs'],
            ['disputed_ownership'],
            ['incompatible_technical_approaches'],
            ['cross_participant_concern'],
            ['unresolved_dependency'],
        ];
    }

    #[DataProvider('escalationCriterionProvider')]
    public function testEachRealEscalationCriterionIsAccepted(string $criterion): void
    {
        $result = $this->collaboration()->define($this->requestFor(['escalation_criteria' => [$criterion]]));

        $this->assertSame('defined', $result['outcome']);
    }

    // --- successful definition ---

    public function testSuccessfulDefinitionReturnsCollaborationIdAndStructure(): void
    {
        $result = $this->collaboration()->define($this->requestFor());

        $this->assertSame('defined', $result['outcome']);
        $this->assertNotNull($result['collaboration_id']);
        $this->assertCount(2, $result['participants']);
        $this->assertSame(['unresolved_dependency'], $result['escalation_criteria']);
    }

    public function testPlannerPlanIsRecordedVerbatimNotCrossChecked(): void
    {
        $plan = [['phase' => 1, 'task' => 'design', 'agent' => 'agent_planner']];
        $collaboration = $this->collaboration();

        $result = $collaboration->define($this->requestFor(['planner_plan' => $plan]));
        $record = $collaboration->get($result['collaboration_id']);

        $this->assertSame($plan, $record['planner_plan']);
    }

    // --- get() / history() ---

    public function testGetUnknownCollaborationReturnsNull(): void
    {
        $this->assertNull($this->collaboration()->get('ghost'));
    }

    public function testHistoryPreservesEveryDefinedCollaboration(): void
    {
        $collaboration = $this->collaboration();
        $collaboration->define($this->requestFor(['objective' => 'first']));
        $collaboration->define($this->requestFor(['objective' => 'second']));
        $collaboration->define($this->requestFor(['objective' => 'invalid attempt', 'participants' => []]));

        $history = $collaboration->history();

        $this->assertCount(2, $history);
        $this->assertSame('first', $history[0]['objective']);
        $this->assertSame('second', $history[1]['objective']);
    }
}
