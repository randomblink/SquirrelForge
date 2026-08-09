<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Agent;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\SqliteAgentLifecycle;

final class SqliteAgentLifecycleTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-agent-lifecycle-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function lifecycle(): SqliteAgentLifecycle
    {
        return new SqliteAgentLifecycle($this->tempPath('db'));
    }

    // --- shape validation ---

    public function testEmptyAgentIdIsInvalid(): void
    {
        $lifecycle = $this->lifecycle();

        $result = $lifecycle->transition('', 'DRAFT', 'system');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testEmptyRequestedByIsInvalid(): void
    {
        $lifecycle = $this->lifecycle();

        $result = $lifecycle->transition('agent_1', 'DRAFT', '');

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testUnrecognizedTargetStateIsRejected(): void
    {
        $lifecycle = $this->lifecycle();

        $result = $lifecycle->transition('agent_1', 'MADE_UP_STATE', 'system');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('Lifecycle States', $result['error']);
    }

    // --- unknown agent: only DRAFT is a valid first transition ---

    public function testUnknownAgentCanOnlyTransitionToDraft(): void
    {
        $lifecycle = $this->lifecycle();

        $result = $lifecycle->transition('agent_1', 'ACTIVE', 'system');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('only DRAFT', $result['error']);
    }

    public function testUnknownAgentCreatedViaDraftSucceeds(): void
    {
        $lifecycle = $this->lifecycle();

        $result = $lifecycle->transition('agent_1', 'DRAFT', 'system');

        $this->assertSame('transitioned', $result['outcome']);
        $this->assertNull($result['previous_state']);
        $this->assertSame('DRAFT', $result['new_state']);
        $this->assertSame('DRAFT', $lifecycle->currentState('agent_1'));
    }

    // --- the real transition table ---

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function validTransitionProvider(): array
    {
        return [
            ['DRAFT', 'REGISTERED'],
            ['REGISTERED', 'INITIALIZED'],
            ['INITIALIZED', 'ACTIVE'],
            ['ACTIVE', 'BUSY'],
            ['BUSY', 'ACTIVE'],
            ['ACTIVE', 'SUSPENDED'],
            ['SUSPENDED', 'ACTIVE'],
            ['ACTIVE', 'MAINTENANCE'],
            ['MAINTENANCE', 'ACTIVE'],
            ['ACTIVE', 'RETIRED'],
            ['RETIRED', 'ARCHIVED'],
        ];
    }

    #[DataProvider('validTransitionProvider')]
    public function testEachValidTransitionSucceeds(string $from, string $to): void
    {
        $lifecycle = $this->lifecycle();
        $this->driveToState($lifecycle, 'agent_1', $from);

        $result = $lifecycle->transition('agent_1', $to, 'system');

        $this->assertSame('transitioned', $result['outcome']);
        $this->assertSame($to, $lifecycle->currentState('agent_1'));
    }

    public function testInvalidTransitionIsRejectedNotCoerced(): void
    {
        $lifecycle = $this->lifecycle();
        $this->driveToState($lifecycle, 'agent_1', 'DRAFT');

        $result = $lifecycle->transition('agent_1', 'ACTIVE', 'system');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('DRAFT', $lifecycle->currentState('agent_1'));
    }

    // --- terminal states enforced structurally by the table's own absence of rows ---

    public function testArchivedIsImmutableNoOutboundTransition(): void
    {
        $lifecycle = $this->lifecycle();
        $this->driveToState($lifecycle, 'agent_1', 'ARCHIVED');

        $result = $lifecycle->transition('agent_1', 'ACTIVE', 'system');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('ARCHIVED', $lifecycle->currentState('agent_1'));
    }

    public function testRetiredAgentCannotBeReactivated(): void
    {
        $lifecycle = $this->lifecycle();
        $this->driveToState($lifecycle, 'agent_1', 'RETIRED');

        $result = $lifecycle->transition('agent_1', 'ACTIVE', 'system');

        $this->assertSame('rejected', $result['outcome']);
    }

    // --- governance/suspension override ---

    public function testGovernanceBlockRejectsAnOtherwiseValidTransition(): void
    {
        $lifecycle = $this->lifecycle();
        $this->driveToState($lifecycle, 'agent_1', 'INITIALIZED');

        $result = $lifecycle->transition('agent_1', 'ACTIVE', 'system', [], 'pending compliance review');

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('pending compliance review', $result['error']);
        $this->assertSame('INITIALIZED', $lifecycle->currentState('agent_1'));
    }

    public function testEmptyGovernanceBlockReasonNeverBlocks(): void
    {
        $lifecycle = $this->lifecycle();
        $this->driveToState($lifecycle, 'agent_1', 'INITIALIZED');

        $result = $lifecycle->transition('agent_1', 'ACTIVE', 'system', [], '');

        $this->assertSame('transitioned', $result['outcome']);
    }

    // --- evidence: recorded, not interpreted ---

    public function testEvidenceIsRecordedVerbatim(): void
    {
        $lifecycle = $this->lifecycle();
        $result = $lifecycle->transition('agent_1', 'DRAFT', 'system', ['note' => 'first registration']);

        $record = $lifecycle->get($result['event_id']);

        $this->assertSame(['note' => 'first registration'], $record['evidence']);
    }

    // --- currentState() ---

    public function testCurrentStateIsNullForAnUnknownAgent(): void
    {
        $lifecycle = $this->lifecycle();

        $this->assertNull($lifecycle->currentState('ghost'));
    }

    public function testCurrentStateNeverAdvancesOnARejectedTransition(): void
    {
        $lifecycle = $this->lifecycle();
        $this->driveToState($lifecycle, 'agent_1', 'DRAFT');

        $lifecycle->transition('agent_1', 'ACTIVE', 'system');

        $this->assertSame('DRAFT', $lifecycle->currentState('agent_1'));
    }

    // --- get() / history() ---

    public function testGetUnknownEventReturnsNull(): void
    {
        $lifecycle = $this->lifecycle();

        $this->assertNull($lifecycle->get('ghost'));
    }

    public function testHistoryIncludesBothPassedAndRejectedAttempts(): void
    {
        $lifecycle = $this->lifecycle();
        $this->driveToState($lifecycle, 'agent_1', 'DRAFT');
        $lifecycle->transition('agent_1', 'ACTIVE', 'system');

        $history = $lifecycle->history('agent_1');

        $this->assertCount(2, $history);
        $this->assertSame('Passed', $history[0]['validation']);
        $this->assertSame('Rejected', $history[1]['validation']);
    }

    public function testHistoryRecordsTheRequester(): void
    {
        $lifecycle = $this->lifecycle();
        $lifecycle->transition('agent_1', 'DRAFT', 'admin_console');

        $history = $lifecycle->history('agent_1');

        $this->assertSame('admin_console', $history[0]['requested_by']);
    }

    /**
     * Drives an agent from unknown through the real transition table to
     * the target state, one valid hop at a time -- never fabricating a
     * shortcut this class itself would reject.
     */
    private function driveToState(SqliteAgentLifecycle $lifecycle, string $agentId, string $targetState): void
    {
        $base = ['DRAFT', 'REGISTERED', 'INITIALIZED', 'ACTIVE'];
        $baseIndex = array_search($targetState, $base, true);

        if ($baseIndex !== false) {
            foreach (array_slice($base, 0, $baseIndex + 1) as $state) {
                $lifecycle->transition($agentId, $state, 'system');
            }

            return;
        }

        foreach ($base as $state) {
            $lifecycle->transition($agentId, $state, 'system');
        }

        if ($targetState === 'ARCHIVED') {
            $lifecycle->transition($agentId, 'RETIRED', 'system');
            $lifecycle->transition($agentId, 'ARCHIVED', 'system');

            return;
        }

        $lifecycle->transition($agentId, $targetState, 'system');
    }
}
