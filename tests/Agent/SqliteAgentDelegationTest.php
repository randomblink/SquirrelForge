<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Agent;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\AgentRegistry;
use SquirrelForge\Agent\CallbackAgent;
use SquirrelForge\Agent\SqliteAgentDelegation;
use SquirrelForge\Agent\SqliteAgentLifecycle;
use SquirrelForge\Coordination\SqliteHandoffProtocol;
use SquirrelForge\Engine\TaskRouter;

final class SqliteAgentDelegationTest extends TestCase
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
        $path = sys_get_temp_dir() . "/squirrelforge-agent-delegation-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function taskRouterWithAgent(string $agentId = 'agent_dev', string $capability = 'developer'): TaskRouter
    {
        $registry = new AgentRegistry();
        $registry->register(new CallbackAgent(
            $agentId,
            'Test Developer',
            'test agent',
            static fn(array $context): bool => ($context['stage'] ?? null) === $capability,
            static fn(array $context): array => []
        ));

        return new TaskRouter($registry);
    }

    private function agentLifecycle(): SqliteAgentLifecycle
    {
        return new SqliteAgentLifecycle($this->tempPath('lifecycle'));
    }

    private function activateAgent(SqliteAgentLifecycle $lifecycle, string $agentId): void
    {
        foreach (['DRAFT', 'REGISTERED', 'INITIALIZED', 'ACTIVE'] as $state) {
            $lifecycle->transition($agentId, $state, 'system');
        }
    }

    private function handoffProtocol(): SqliteHandoffProtocol
    {
        return new SqliteHandoffProtocol($this->tempPath('handoff'));
    }

    private function delegation(
        ?TaskRouter $taskRouter = null,
        ?SqliteHandoffProtocol $handoffProtocol = null,
        ?SqliteAgentLifecycle $agentLifecycle = null
    ): SqliteAgentDelegation {
        return new SqliteAgentDelegation($this->tempPath('db'), $taskRouter, $handoffProtocol, $agentLifecycle);
    }

    /**
     * @return array{task_ref: string, delegating_agent: string, delegation_type: string, authorized_delegation_types: array<int, string>, required_capability: string}
     */
    private function minimalRequest(array $overrides = []): array
    {
        return array_replace([
            'task_ref' => 'task_1',
            'delegating_agent' => 'agent_lead',
            'delegation_type' => 'Direct',
            'authorized_delegation_types' => ['Direct'],
            'required_capability' => 'developer',
        ], $overrides);
    }

    // --- required fields ---

    public function testMissingTaskRefIsRejected(): void
    {
        $delegation = $this->delegation();
        $request = $this->minimalRequest();
        unset($request['task_ref']);

        $result = $delegation->delegate($request);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('Rejected', $result['status']);
    }

    public function testMissingDelegatingAgentIsRejected(): void
    {
        $delegation = $this->delegation();
        $request = $this->minimalRequest();
        unset($request['delegating_agent']);

        $result = $delegation->delegate($request);

        $this->assertSame('rejected', $result['outcome']);
    }

    // --- delegation type vocabulary ---

    public function testUnrecognizedDelegationTypeIsRejected(): void
    {
        $delegation = $this->delegation();

        $result = $delegation->delegate($this->minimalRequest(['delegation_type' => 'Made Up Type']));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('Delegation Types', $result['error']);
    }

    // --- authorization: fails closed ---

    public function testUnauthorizedDelegationTypeIsDenied(): void
    {
        $delegation = $this->delegation();

        $result = $delegation->delegate($this->minimalRequest(['authorized_delegation_types' => ['Escalation']]));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('Denied', $result['authorization']);
        $this->assertStringContainsString('not confirmed authorized', $result['error']);
    }

    public function testOmittedAuthorizedTypesFailsClosed(): void
    {
        $delegation = $this->delegation();
        $request = $this->minimalRequest();
        unset($request['authorized_delegation_types']);

        $result = $delegation->delegate($request);

        $this->assertSame('rejected', $result['outcome']);
        $this->assertSame('Denied', $result['authorization']);
    }

    // --- Task Router composition ---

    public function testWithoutATaskRouterIsRejected(): void
    {
        $delegation = $this->delegation();

        $result = $delegation->delegate($this->minimalRequest());

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('No Task Router', $result['error']);
    }

    public function testNoEligibleCandidateIsRejected(): void
    {
        $taskRouter = $this->taskRouterWithAgent('agent_dev', 'developer');
        $delegation = $this->delegation($taskRouter);

        $result = $delegation->delegate($this->minimalRequest(['required_capability' => 'nothing_matches']));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertNull($result['receiving_agent']);
    }

    public function testTaskRouterSelectionBecomesTheReceivingAgent(): void
    {
        $taskRouter = $this->taskRouterWithAgent('agent_dev', 'developer');
        $delegation = $this->delegation($taskRouter);

        $result = $delegation->delegate($this->minimalRequest());

        $this->assertSame('agent_dev', $result['receiving_agent']);
    }

    // --- Agent Lifecycle composition ---

    public function testIneligibleReceivingAgentIsRejected(): void
    {
        $taskRouter = $this->taskRouterWithAgent('agent_dev', 'developer');
        $lifecycle = $this->agentLifecycle();
        // agent_dev is left unregistered in the lifecycle -> currentState() is null -> ineligible.
        $delegation = $this->delegation($taskRouter, null, $lifecycle);

        $result = $delegation->delegate($this->minimalRequest());

        $this->assertSame('rejected', $result['outcome']);
        $this->assertStringContainsString('not eligible', $result['error']);
    }

    public function testActiveReceivingAgentIsEligible(): void
    {
        $taskRouter = $this->taskRouterWithAgent('agent_dev', 'developer');
        $lifecycle = $this->agentLifecycle();
        $this->activateAgent($lifecycle, 'agent_dev');
        $delegation = $this->delegation($taskRouter, null, $lifecycle);

        $result = $delegation->delegate($this->minimalRequest());

        $this->assertNotSame('rejected', $result['outcome']);
    }

    public function testSuspendedAgentIsNotEligible(): void
    {
        $taskRouter = $this->taskRouterWithAgent('agent_dev', 'developer');
        $lifecycle = $this->agentLifecycle();
        $this->activateAgent($lifecycle, 'agent_dev');
        $lifecycle->transition('agent_dev', 'SUSPENDED', 'monitor');
        $delegation = $this->delegation($taskRouter, null, $lifecycle);

        $result = $delegation->delegate($this->minimalRequest());

        $this->assertSame('rejected', $result['outcome']);
    }

    public function testIneligibleReceivingAgentHintIsRejectedBeforeConsultingTaskRouter(): void
    {
        $taskRouter = $this->taskRouterWithAgent('agent_dev', 'developer');
        $lifecycle = $this->agentLifecycle();
        // "agent_other" is unknown to the lifecycle manager -> ineligible hint.
        $delegation = $this->delegation($taskRouter, null, $lifecycle);

        $result = $delegation->delegate($this->minimalRequest(['receiving_agent_hint' => 'agent_other']));

        $this->assertSame('rejected', $result['outcome']);
        $this->assertNull($result['receiving_agent']);
    }

    // --- Handoff Protocol composition ---

    public function testWithoutAHandoffProtocolIsRecordedAsAuthorized(): void
    {
        $taskRouter = $this->taskRouterWithAgent('agent_dev', 'developer');
        $delegation = $this->delegation($taskRouter);

        $result = $delegation->delegate($this->minimalRequest());

        $this->assertSame('Authorized', $result['status']);
        $this->assertSame('Approved', $result['authorization']);
    }

    public function testHandoffAcceptedIsRecordedAsAccepted(): void
    {
        $taskRouter = $this->taskRouterWithAgent('agent_dev', 'developer');
        $handoffProtocol = $this->handoffProtocol();
        $delegation = $this->delegation($taskRouter, $handoffProtocol);

        $result = $delegation->delegate(
            $this->minimalRequest(),
            requestAcceptance: static fn(array $h): array => ['accepted' => true]
        );

        $this->assertSame('Accepted', $result['status']);
        $this->assertNotNull($result['handoff_ref']);
    }

    public function testHandoffSentWithoutAcceptanceIsHandedOff(): void
    {
        $taskRouter = $this->taskRouterWithAgent('agent_dev', 'developer');
        $handoffProtocol = $this->handoffProtocol();
        $delegation = $this->delegation($taskRouter, $handoffProtocol);

        $result = $delegation->delegate($this->minimalRequest());

        $this->assertSame('Handed Off', $result['status']);
    }

    public function testHandoffRejectedIsRecordedAsRejected(): void
    {
        $taskRouter = $this->taskRouterWithAgent('agent_dev', 'developer');
        $handoffProtocol = $this->handoffProtocol();
        $delegation = $this->delegation($taskRouter, $handoffProtocol);

        $result = $delegation->delegate(
            $this->minimalRequest(),
            requestAcceptance: static fn(array $h): array => ['accepted' => false, 'reason' => 'busy right now']
        );

        // outcome reflects that this call was processed and recorded successfully; status
        // carries the Delegation Record's own Rejected outcome per the spec's vocabulary.
        $this->assertSame('recorded', $result['outcome']);
        $this->assertSame('Rejected', $result['status']);
    }

    // --- onRejection: real "alternative handling" hook ---

    public function testOnRejectionIsInvokedForAnAuthorizationDenial(): void
    {
        $delegation = $this->delegation();
        $seen = null;

        $delegation->delegate(
            $this->minimalRequest(['authorized_delegation_types' => []]),
            onRejection: function (array $context) use (&$seen): void {
                $seen = $context;
            }
        );

        $this->assertSame('task_1', $seen['task_ref']);
    }

    public function testOnRejectionIsInvokedWhenHandoffIsRejected(): void
    {
        $taskRouter = $this->taskRouterWithAgent('agent_dev', 'developer');
        $handoffProtocol = $this->handoffProtocol();
        $delegation = $this->delegation($taskRouter, $handoffProtocol);
        $invoked = false;

        $delegation->delegate(
            $this->minimalRequest(),
            requestAcceptance: static fn(array $h): array => ['accepted' => false],
            onRejection: function () use (&$invoked): void {
                $invoked = true;
            }
        );

        $this->assertTrue($invoked);
    }

    public function testOnRejectionIsNeverInvokedOnSuccess(): void
    {
        $taskRouter = $this->taskRouterWithAgent('agent_dev', 'developer');
        $handoffProtocol = $this->handoffProtocol();
        $delegation = $this->delegation($taskRouter, $handoffProtocol);
        $invoked = false;

        $delegation->delegate(
            $this->minimalRequest(),
            requestAcceptance: static fn(array $h): array => ['accepted' => true],
            onRejection: function () use (&$invoked): void {
                $invoked = true;
            }
        );

        $this->assertFalse($invoked);
    }

    // --- get() / history() ---

    public function testGetUnknownDelegationReturnsNull(): void
    {
        $delegation = $this->delegation();

        $this->assertNull($delegation->get('ghost'));
    }

    public function testHistoryReturnsEveryAttemptForATask(): void
    {
        $delegation = $this->delegation();
        $delegation->delegate($this->minimalRequest(['authorized_delegation_types' => []]));
        $delegation->delegate($this->minimalRequest(['delegation_type' => 'Made Up']));

        $history = $delegation->history('task_1');

        $this->assertCount(2, $history);
        $this->assertSame('Rejected', $history[0]['status']);
        $this->assertSame('Rejected', $history[1]['status']);
    }
}
