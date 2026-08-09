<?php

declare(strict_types=1);

namespace SquirrelForge\Tests\Agent;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\AgentRegistry;
use SquirrelForge\Agent\CallbackAgent;
use SquirrelForge\Agent\SqliteAgentDelegation;
use SquirrelForge\Agent\SqliteAgentLifecycle;
use SquirrelForge\Agent\SqliteAgentManager;
use SquirrelForge\Agent\SqliteAgentMonitor;
use SquirrelForge\Agent\SqliteAgentSpecialization;
use SquirrelForge\Engine\TaskRouter;
use SquirrelForge\Observability\HealthReporter;
use SquirrelForge\Observability\SqliteAlertManager;

final class SqliteAgentManagerTest extends TestCase
{
    /** @var array<int, string> */
    private array $databasePaths = [];

    /** @var array<int, string> */
    private array $specDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->databasePaths as $path) {
            foreach ([$path, $path . '-shm', $path . '-wal'] as $candidate) {
                if (is_file($candidate)) {
                    unlink($candidate);
                }
            }
        }

        foreach ($this->specDirectories as $directory) {
            foreach (glob($directory . '/*.md') ?: [] as $file) {
                unlink($file);
            }

            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }

    private function tempPath(string $label): string
    {
        $path = sys_get_temp_dir() . "/squirrelforge-agent-manager-{$label}-" . bin2hex(random_bytes(8)) . '.sqlite';
        $this->databasePaths[] = $path;

        return $path;
    }

    private function specDirectoryWith(array $roles): string
    {
        $directory = sys_get_temp_dir() . '/squirrelforge-agent-manager-specs-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $this->specDirectories[] = $directory;

        foreach ($roles as $role) {
            file_put_contents($directory . '/AGENT-' . $role . '.md', "# {$role}\n");
        }

        return $directory;
    }

    private function agent(string $id): CallbackAgent
    {
        return new CallbackAgent($id, $id, 'A test agent', static fn(): bool => true, static fn(array $c): array => $c);
    }

    private function registryWith(string ...$agentIds): AgentRegistry
    {
        $registry = new AgentRegistry();

        foreach ($agentIds as $id) {
            $registry->register($this->agent($id));
        }

        return $registry;
    }

    private function specialization(): SqliteAgentSpecialization
    {
        return new SqliteAgentSpecialization($this->tempPath('spec'), $this->specDirectoryWith(['DEVELOPER']));
    }

    private function lifecycleActive(string $agentId): SqliteAgentLifecycle
    {
        $lifecycle = new SqliteAgentLifecycle($this->tempPath('lifecycle'));

        foreach (['DRAFT', 'REGISTERED', 'INITIALIZED', 'ACTIVE'] as $state) {
            $lifecycle->transition($agentId, $state, 'system');
        }

        return $lifecycle;
    }

    private function monitorWithStatus(string $agentId, ?string $status): SqliteAgentMonitor
    {
        $alerts = new SqliteAlertManager($this->tempPath('alerts'));
        $monitor = new SqliteAgentMonitor($this->tempPath('monitor'), new HealthReporter($alerts));

        if ($status === 'DEGRADED') {
            $alerts->create($agentId, 'workload', 'warning', ['note' => 'elevated load']);
        } elseif ($status === 'CRITICAL') {
            $alerts->create($agentId, 'workload', 'critical', ['note' => 'repeated failures']);
        }

        if ($status !== null) {
            $monitor->monitor(['agent_id' => $agentId]);
        }

        return $monitor;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestFor(array $overrides = []): array
    {
        return array_replace([
            'agent_id' => 'agent_1',
            'work_reference' => 'task_1',
            'specialization' => ['required_domain' => 'backend work', 'candidate_roles' => ['DEVELOPER'], 'boundary_verified' => true],
        ], $overrides);
    }

    // --- shape validation ---

    public function testMissingAgentIdIsInvalid(): void
    {
        $manager = new SqliteAgentManager($this->tempPath('db'));

        $result = $manager->assign($this->requestFor(['agent_id' => '']));

        $this->assertSame('invalid', $result['outcome']);
    }

    public function testMissingWorkReferenceIsInvalid(): void
    {
        $manager = new SqliteAgentManager($this->tempPath('db'));

        $result = $manager->assign($this->requestFor(['work_reference' => null]));

        $this->assertSame('invalid', $result['outcome']);
    }

    // --- fail-closed on every unconfigured authority ---

    public function testUnconfiguredRegistryRejects(): void
    {
        $manager = new SqliteAgentManager($this->tempPath('db'));

        $result = $manager->assign($this->requestFor());

        $this->assertSame('reject', $result['outcome']);
        $this->assertSame('registry', $result['failing_check']);
    }

    public function testAgentNotInRegistryRejects(): void
    {
        $manager = new SqliteAgentManager($this->tempPath('db'), $this->registryWith('agent_other'));

        $result = $manager->assign($this->requestFor());

        $this->assertSame('reject', $result['outcome']);
        $this->assertSame('registry', $result['failing_check']);
    }

    public function testUnconfiguredSpecializationRejects(): void
    {
        $manager = new SqliteAgentManager($this->tempPath('db'), $this->registryWith('agent_1'));

        $result = $manager->assign($this->requestFor());

        $this->assertSame('reject', $result['outcome']);
        $this->assertSame('specialization', $result['failing_check']);
    }

    // --- specialization outcomes map onto Manager's own Assignment Outcome ---

    public function testSpecializationInvalidInputRejects(): void
    {
        $manager = new SqliteAgentManager($this->tempPath('db'), $this->registryWith('agent_1'), $this->specialization());

        $result = $manager->assign($this->requestFor(['specialization' => ['required_domain' => 'backend work', 'candidate_roles' => [], 'boundary_verified' => true]]));

        $this->assertSame('reject', $result['outcome']);
        $this->assertSame('specialization', $result['failing_check']);
    }

    public function testSpecializationEscalationIsReferredNotRejected(): void
    {
        $manager = new SqliteAgentManager($this->tempPath('db'), $this->registryWith('agent_1'), $this->specialization());

        $result = $manager->assign($this->requestFor(['specialization' => ['required_domain' => 'backend work', 'candidate_roles' => ['QUANTUM_WIZARD'], 'boundary_verified' => true]]));

        $this->assertSame('refer', $result['outcome']);
        $this->assertSame('specialization', $result['failing_check']);
    }

    public function testSpecializationCollaborationRequiredIsReferred(): void
    {
        $manager = new SqliteAgentManager($this->tempPath('db'), $this->registryWith('agent_1'), new SqliteAgentSpecialization($this->tempPath('spec'), $this->specDirectoryWith(['DEVELOPER', 'REVIEWER'])));

        $result = $manager->assign($this->requestFor(['specialization' => ['required_domain' => 'backend work', 'candidate_roles' => ['DEVELOPER', 'REVIEWER'], 'boundary_verified' => true]]));

        $this->assertSame('refer', $result['outcome']);
        $this->assertSame('specialization', $result['failing_check']);
    }

    // --- lifecycle ---

    public function testUnconfiguredLifecycleRejects(): void
    {
        $manager = new SqliteAgentManager($this->tempPath('db'), $this->registryWith('agent_1'), $this->specialization());

        $result = $manager->assign($this->requestFor());

        $this->assertSame('reject', $result['outcome']);
        $this->assertSame('lifecycle', $result['failing_check']);
    }

    public function testAgentNotInActiveLifecycleStateRejects(): void
    {
        // Never driven past DRAFT -- not ACTIVE.
        $lifecycle = new SqliteAgentLifecycle($this->tempPath('lifecycle'));
        $lifecycle->transition('agent_1', 'DRAFT', 'system');

        $manager = new SqliteAgentManager($this->tempPath('db'), $this->registryWith('agent_1'), $this->specialization(), $lifecycle);

        $result = $manager->assign($this->requestFor());

        $this->assertSame('reject', $result['outcome']);
        $this->assertSame('lifecycle', $result['failing_check']);
    }

    // --- health ---

    public function testUnconfiguredMonitorRejects(): void
    {
        $manager = new SqliteAgentManager($this->tempPath('db'), $this->registryWith('agent_1'), $this->specialization(), $this->lifecycleActive('agent_1'));

        $result = $manager->assign($this->requestFor());

        $this->assertSame('reject', $result['outcome']);
        $this->assertSame('monitor', $result['failing_check']);
    }

    public function testNeverMonitoredAgentIsUnknownAndRejects(): void
    {
        $manager = new SqliteAgentManager(
            $this->tempPath('db'),
            $this->registryWith('agent_1'),
            $this->specialization(),
            $this->lifecycleActive('agent_1'),
            $this->monitorWithStatus('agent_1', null)
        );

        $result = $manager->assign($this->requestFor());

        $this->assertSame('reject', $result['outcome']);
        $this->assertSame('monitor', $result['failing_check']);
    }

    public function testCriticalHealthRejects(): void
    {
        $manager = new SqliteAgentManager(
            $this->tempPath('db'),
            $this->registryWith('agent_1'),
            $this->specialization(),
            $this->lifecycleActive('agent_1'),
            $this->monitorWithStatus('agent_1', 'CRITICAL')
        );

        $result = $manager->assign($this->requestFor());

        $this->assertSame('reject', $result['outcome']);
        $this->assertSame('monitor', $result['failing_check']);
    }

    public function testDegradedHealthProceedsButIsFlagged(): void
    {
        $registry = $this->registryWith('agent_1');
        $manager = new SqliteAgentManager(
            $this->tempPath('db'),
            $registry,
            $this->specialization(),
            $this->lifecycleActive('agent_1'),
            $this->monitorWithStatus('agent_1', 'DEGRADED'),
            null,
            new TaskRouter($registry)
        );

        $result = $manager->assign($this->requestFor());

        $this->assertSame('proceed', $result['outcome']);
        $this->assertTrue($result['health_flagged']);
    }

    // --- delegation: only checked when a delegation_id is supplied ---

    public function testDirectAssignmentSkipsDelegationCheckEntirely(): void
    {
        $registry = $this->registryWith('agent_1');
        $manager = new SqliteAgentManager(
            $this->tempPath('db'),
            $registry,
            $this->specialization(),
            $this->lifecycleActive('agent_1'),
            $this->monitorWithStatus('agent_1', 'NORMAL'),
            null,
            new TaskRouter($registry)
        );

        $result = $manager->assign($this->requestFor());

        $this->assertSame('proceed', $result['outcome']);
    }

    public function testDelegatedAssignmentWithUnconfiguredDelegationRejects(): void
    {
        $registry = $this->registryWith('agent_1');
        $manager = new SqliteAgentManager(
            $this->tempPath('db'),
            $registry,
            $this->specialization(),
            $this->lifecycleActive('agent_1'),
            $this->monitorWithStatus('agent_1', 'NORMAL'),
            null,
            new TaskRouter($registry)
        );

        $result = $manager->assign($this->requestFor(['delegation_id' => 'delegation_ghost']));

        $this->assertSame('reject', $result['outcome']);
        $this->assertSame('delegation', $result['failing_check']);
    }

    public function testDelegatedAssignmentWithUnknownDelegationIdRejects(): void
    {
        $registry = $this->registryWith('agent_1');
        $delegation = new SqliteAgentDelegation($this->tempPath('delegation'));
        $manager = new SqliteAgentManager(
            $this->tempPath('db'),
            $registry,
            $this->specialization(),
            $this->lifecycleActive('agent_1'),
            $this->monitorWithStatus('agent_1', 'NORMAL'),
            $delegation,
            new TaskRouter($registry)
        );

        $result = $manager->assign($this->requestFor(['delegation_id' => 'delegation_ghost']));

        $this->assertSame('reject', $result['outcome']);
        $this->assertSame('delegation', $result['failing_check']);
    }

    public function testDelegatedAssignmentWithApprovedDelegationProceeds(): void
    {
        $registry = $this->registryWith('agent_1', 'agent_delegator');
        $taskRouter = new TaskRouter($registry);
        $delegationLifecycle = $this->lifecycleActive('agent_1');
        $delegation = new SqliteAgentDelegation($this->tempPath('delegation'), $taskRouter, null, $delegationLifecycle);

        $delegateResult = $delegation->delegate([
            'task_ref' => 'task_1',
            'delegating_agent' => 'agent_delegator',
            'delegation_type' => 'Direct',
            'authorized_delegation_types' => ['Direct'],
            'required_capability' => '',
        ]);

        $this->assertSame('recorded', $delegateResult['outcome']);
        $this->assertSame('Approved', $delegateResult['authorization']);

        $manager = new SqliteAgentManager(
            $this->tempPath('db'),
            $registry,
            $this->specialization(),
            $delegationLifecycle,
            $this->monitorWithStatus('agent_1', 'NORMAL'),
            $delegation,
            $taskRouter
        );

        $result = $manager->assign($this->requestFor(['delegation_id' => $delegateResult['delegation_id']]));

        $this->assertSame('proceed', $result['outcome']);
    }

    // --- task router ---

    public function testUnconfiguredTaskRouterRejects(): void
    {
        $manager = new SqliteAgentManager(
            $this->tempPath('db'),
            $this->registryWith('agent_1'),
            $this->specialization(),
            $this->lifecycleActive('agent_1'),
            $this->monitorWithStatus('agent_1', 'NORMAL')
        );

        $result = $manager->assign($this->requestFor());

        $this->assertSame('reject', $result['outcome']);
        $this->assertSame('task_router', $result['failing_check']);
    }

    public function testTaskRouterBlockedRejects(): void
    {
        $registry = $this->registryWith('agent_1');
        $manager = new SqliteAgentManager(
            $this->tempPath('db'),
            $registry,
            $this->specialization(),
            $this->lifecycleActive('agent_1'),
            $this->monitorWithStatus('agent_1', 'NORMAL'),
            null,
            new TaskRouter($registry)
        );

        // No agent in the registry supports this required_capability path -- forced BLOCKED via availability.
        $result = $manager->assign($this->requestFor(['context' => ['agent_availability' => ['agent_1' => false]]]));

        $this->assertSame('reject', $result['outcome']);
        $this->assertSame('task_router', $result['failing_check']);
    }

    public function testFullyEligibleAssignmentProceedsWithOwner(): void
    {
        $registry = $this->registryWith('agent_1');
        $manager = new SqliteAgentManager(
            $this->tempPath('db'),
            $registry,
            $this->specialization(),
            $this->lifecycleActive('agent_1'),
            $this->monitorWithStatus('agent_1', 'NORMAL'),
            null,
            new TaskRouter($registry)
        );

        $result = $manager->assign($this->requestFor());

        $this->assertSame('proceed', $result['outcome']);
        $this->assertSame('agent_1', $result['owner']);
        $this->assertFalse($result['health_flagged']);
        $this->assertNotNull($result['assignment_id']);
    }

    // --- get() / history() ---

    public function testGetUnknownAssignmentReturnsNull(): void
    {
        $manager = new SqliteAgentManager($this->tempPath('db'));

        $this->assertNull($manager->get('ghost'));
    }

    public function testHistoryPreservesEveryDecisionForAnAgent(): void
    {
        $manager = new SqliteAgentManager($this->tempPath('db'), $this->registryWith('agent_other'));

        $manager->assign($this->requestFor());
        $manager->assign($this->requestFor(['work_reference' => 'task_2']));

        $history = $manager->history('agent_1');

        $this->assertCount(2, $history);
        $this->assertSame('reject', $history[0]['outcome']);
        $this->assertSame('registry', $history[0]['failing_check']);
    }
}
