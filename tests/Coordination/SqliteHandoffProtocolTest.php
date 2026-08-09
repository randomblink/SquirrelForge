<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Coordination;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Communication\SqliteMessageBroker;
use SquirrelForge\Communication\SqliteMessageValidator;
use SquirrelForge\Coordination\SqliteFailureRecovery;
use SquirrelForge\Coordination\SqliteHandoffProtocol;
use SquirrelForge\Coordination\SqliteMessageBus;

final class SqliteHandoffProtocolTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-handoff-protocol-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function messageBus(): SqliteMessageBus
    {
        $validator = new SqliteMessageValidator($this->tempPath('validator'));
        $broker = new SqliteMessageBroker($this->tempPath('broker'), $validator);
        $broker->registerRoute('agent_developer', static fn(array $m): array => ['received' => true]);

        return new SqliteMessageBus($this->tempPath('bus'), $broker);
    }

    private function protocol(?SqliteMessageBus $bus = null, ?SqliteFailureRecovery $failureRecovery = null): SqliteHandoffProtocol
    {
        return new SqliteHandoffProtocol($this->tempPath('db'), $bus, $failureRecovery);
    }

    /**
     * @return array{task_id: string, current_agent: string, next_agent: string}
     */
    private function minimalHandoff(array $overrides = []): array
    {
        return array_replace([
            'task_id' => 'task_1',
            'current_agent' => 'agent_planner',
            'next_agent' => 'agent_developer',
        ], $overrides);
    }

    // --- required fields ---

    public function testMissingNextAgentIsInvalid(): void
    {
        $protocol = $this->protocol();
        $handoff = $this->minimalHandoff();
        unset($handoff['next_agent']);

        $result = $protocol->initiate($handoff);

        $this->assertSame('Invalid', $result['outcome']);
        $this->assertStringContainsString('next_agent', $result['error']);
    }

    // --- validation status guard ---

    public function testUnrecognizedValidationStatusIsInvalid(): void
    {
        $protocol = $this->protocol();

        $result = $protocol->initiate($this->minimalHandoff(['validation_status' => 'MOSTLY_FINE']));

        $this->assertSame('Invalid', $result['outcome']);
        $this->assertStringContainsString('MOSTLY_FINE', $result['error']);
    }

    public function testRealValidationStatusIsAccepted(): void
    {
        $protocol = $this->protocol();

        $result = $protocol->initiate($this->minimalHandoff(['validation_status' => 'ACCEPTED']));

        $this->assertNotSame('Invalid', $result['outcome']);
    }

    // --- ownership: prevent duplicate work ---

    public function testFirstHandoffForATaskHasNoOwnershipConflict(): void
    {
        $protocol = $this->protocol();

        $result = $protocol->initiate($this->minimalHandoff());

        $this->assertNotSame('Blocked', $result['outcome']);
    }

    public function testWrongDeclaredCurrentAgentIsBlocked(): void
    {
        $protocol = $this->protocol();
        $protocol->initiate($this->minimalHandoff(), requestAcceptance: static fn(array $h): array => ['accepted' => true]);

        $result = $protocol->initiate($this->minimalHandoff(['current_agent' => 'agent_planner']));

        $this->assertSame('Blocked', $result['outcome']);
        $this->assertStringContainsString('agent_developer', $result['error']);
    }

    public function testActualOwnerCanInitiateTheNextHandoff(): void
    {
        $protocol = $this->protocol();
        $protocol->initiate($this->minimalHandoff(), requestAcceptance: static fn(array $h): array => ['accepted' => true]);

        $result = $protocol->initiate($this->minimalHandoff(['current_agent' => 'agent_developer', 'next_agent' => 'agent_reviewer']));

        $this->assertNotSame('Blocked', $result['outcome']);
    }

    // --- dry run ---

    public function testWithoutARequestAcceptanceClosureIsADryRunSent(): void
    {
        $protocol = $this->protocol();

        $result = $protocol->initiate($this->minimalHandoff());

        $this->assertSame('Sent', $result['outcome']);
        $this->assertNull($protocol->currentOwner('task_1'));
    }

    // --- acceptance ---

    public function testAcceptedHandoffTransfersOwnership(): void
    {
        $protocol = $this->protocol();

        $result = $protocol->initiate($this->minimalHandoff(), requestAcceptance: static fn(array $h): array => ['accepted' => true]);

        $this->assertSame('Accepted', $result['outcome']);
        $this->assertSame('agent_developer', $protocol->currentOwner('task_1'));
    }

    public function testAcceptanceClosureReceivesTheFullHandoffPayload(): void
    {
        $protocol = $this->protocol();
        $seen = null;

        $protocol->initiate(
            $this->minimalHandoff(['task_status' => 'IN_PROGRESS', 'validation_status' => 'ACCEPTED', 'notes' => 'see PR #4']),
            requestAcceptance: function (array $payload) use (&$seen): array {
                $seen = $payload;

                return ['accepted' => true];
            }
        );

        $this->assertSame('IN_PROGRESS', $seen['task_status']);
        $this->assertSame('ACCEPTED', $seen['validation_status']);
        $this->assertSame('see PR #4', $seen['notes']);
    }

    // --- rejection ---

    public function testRejectedHandoffReturnsOwnershipToCurrentAgent(): void
    {
        $protocol = $this->protocol();

        $result = $protocol->initiate($this->minimalHandoff(), requestAcceptance: static fn(array $h): array => ['accepted' => false, 'reason' => 'missing tests']);

        $this->assertSame('Rejected', $result['outcome']);
        $this->assertSame('missing tests', $result['rejection_reason']);
        $this->assertSame('agent_planner', $protocol->currentOwner('task_1'));
    }

    public function testSingleRejectionNeverEscalates(): void
    {
        $failureRecovery = new SqliteFailureRecovery($this->tempPath('recovery'));
        $protocol = $this->protocol(null, $failureRecovery);

        $result = $protocol->initiate($this->minimalHandoff(), requestAcceptance: static fn(array $h): array => ['accepted' => false]);

        $this->assertNull($result['recovery']);
    }

    public function testRecurringRejectionEscalatesToFailureRecovery(): void
    {
        $failureRecovery = new SqliteFailureRecovery($this->tempPath('recovery'));
        $protocol = $this->protocol(null, $failureRecovery);
        $handoff = $this->minimalHandoff();

        $protocol->initiate($handoff, requestAcceptance: static fn(array $h): array => ['accepted' => false]);
        $result = $protocol->initiate($handoff, requestAcceptance: static fn(array $h): array => ['accepted' => false]);

        $this->assertSame('Workflow Failure', $result['recovery']['failure_type']);
    }

    public function testRejectionCounterResetsAfterAnAcceptance(): void
    {
        $failureRecovery = new SqliteFailureRecovery($this->tempPath('recovery'));
        $protocol = $this->protocol(null, $failureRecovery);
        $handoff = $this->minimalHandoff();

        $protocol->initiate($handoff, requestAcceptance: static fn(array $h): array => ['accepted' => false]);
        $protocol->initiate($handoff, requestAcceptance: static fn(array $h): array => ['accepted' => true]);

        $second = $protocol->initiate(
            $this->minimalHandoff(['current_agent' => 'agent_developer', 'next_agent' => 'agent_reviewer']),
            requestAcceptance: static fn(array $h): array => ['accepted' => false]
        );

        $this->assertNull($second['recovery']);
    }

    public function testRejectionWithoutAFailureRecoveryNeverCrashes(): void
    {
        $protocol = $this->protocol();
        $handoff = $this->minimalHandoff();

        $protocol->initiate($handoff, requestAcceptance: static fn(array $h): array => ['accepted' => false]);
        $result = $protocol->initiate($handoff, requestAcceptance: static fn(array $h): array => ['accepted' => false]);

        $this->assertNull($result['recovery']);
    }

    // --- MessageBus composition ---

    public function testHandoffIsSentAsATaskAssignmentThroughTheMessageBus(): void
    {
        $bus = $this->messageBus();
        $protocol = $this->protocol($bus);

        $protocol->initiate($this->minimalHandoff(), requestAcceptance: static fn(array $h): array => ['accepted' => true]);

        $history = $bus->history('task_1');
        $this->assertCount(1, $history);
        $this->assertSame('Task Assignment', $history[0]['message_type']);
        $this->assertSame('agent_planner', $history[0]['sender']);
        $this->assertSame('agent_developer', $history[0]['recipient']);
    }

    // --- history / get() ---

    public function testHistoryReturnsAllHandoffsForATaskInOrder(): void
    {
        $protocol = $this->protocol();
        $protocol->initiate($this->minimalHandoff(), requestAcceptance: static fn(array $h): array => ['accepted' => false]);
        $protocol->initiate($this->minimalHandoff(), requestAcceptance: static fn(array $h): array => ['accepted' => true]);

        $history = $protocol->history('task_1');

        $this->assertCount(2, $history);
        $this->assertSame('Rejected', $history[0]['outcome']);
        $this->assertSame('Accepted', $history[1]['outcome']);
    }

    public function testGetUnknownHandoffReturnsNull(): void
    {
        $protocol = $this->protocol();

        $this->assertNull($protocol->get('ghost'));
    }

    public function testCurrentOwnerIsNullForAnUnknownTask(): void
    {
        $protocol = $this->protocol();

        $this->assertNull($protocol->currentOwner('ghost_task'));
    }

    public function testWorksWithNoComposedComponentsAtAll(): void
    {
        $protocol = new SqliteHandoffProtocol($this->tempPath('db'), null, null);

        $result = $protocol->initiate($this->minimalHandoff());

        $this->assertSame('Sent', $result['outcome']);
    }
}
