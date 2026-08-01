<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\AgentRegistry;
use SquirrelForge\Agent\CallbackAgent;
use SquirrelForge\Agent\Roles\DeveloperAgent;
use SquirrelForge\Agent\Roles\SecurityAgent;
use SquirrelForge\Engine\TaskRouter;

final class TaskRouterTest extends TestCase
{
    private function registry(): AgentRegistry
    {
        $registry = new AgentRegistry();
        $registry->register(new DeveloperAgent());
        $registry->register(new SecurityAgent());

        return $registry;
    }

    public function testRouteWithAnAlreadyBlockedStepIsBlockedWithoutConsultingAgents(): void
    {
        $router = new TaskRouter($this->registry());

        $result = $router->route(['task_id' => 'task_a', 'status' => 'blocked'], ['required_capability' => 'developer']);

        $this->assertSame('BLOCKED', $result['status']);
        $this->assertSame([], $result['rejected_candidates']);
    }

    public function testRouteWithoutAnAgentRegistryIsBlocked(): void
    {
        $router = new TaskRouter();

        $result = $router->route(['task_id' => 'task_a'], ['required_capability' => 'developer']);

        $this->assertSame('BLOCKED', $result['status']);
        $this->assertStringContainsString('registry', $result['rationale']);
    }

    public function testRouteSelectsTheRealAgentThatSupportsTheCapability(): void
    {
        $router = new TaskRouter($this->registry());

        $result = $router->route(['task_id' => 'task_a'], ['required_capability' => 'developer']);

        $this->assertSame('ROUTED', $result['status']);
        $this->assertSame('developer', $result['owner']);
        $this->assertCount(1, $result['rejected_candidates']);
        $this->assertSame('security', $result['rejected_candidates'][0]['agent_id']);
        $this->assertStringContainsString('does not support', $result['rejected_candidates'][0]['reason']);
    }

    public function testRouteWithNoCapabilityMatchIsBlocked(): void
    {
        $router = new TaskRouter($this->registry());

        $result = $router->route(['task_id' => 'task_a'], ['required_capability' => 'made_up_capability']);

        $this->assertSame('BLOCKED', $result['status']);
        $this->assertCount(2, $result['rejected_candidates']);
    }

    public function testRouteRejectsAnUnavailableAgent(): void
    {
        $router = new TaskRouter($this->registry());

        $result = $router->route(
            ['task_id' => 'task_a'],
            ['required_capability' => 'developer'],
            ['agent_availability' => ['developer' => false]]
        );

        $this->assertSame('BLOCKED', $result['status']);
        $this->assertStringContainsString('not currently available', $result['rejected_candidates'][0]['reason']);
    }

    public function testRouteRejectsAnAgentMissingTheRequiredPermission(): void
    {
        $router = new TaskRouter($this->registry());

        $result = $router->route(
            ['task_id' => 'task_a', 'permissions' => 'permission:deploy'],
            ['required_capability' => 'developer'],
            ['granted_permissions' => ['permission:read']]
        );

        $this->assertSame('BLOCKED', $result['status']);
        $this->assertStringContainsString('permission:deploy', $result['rejected_candidates'][0]['reason']);
    }

    public function testRouteAcceptsAnAgentWithTheGrantedPermission(): void
    {
        $router = new TaskRouter($this->registry());

        $result = $router->route(
            ['task_id' => 'task_a', 'permissions' => 'permission:deploy'],
            ['required_capability' => 'developer'],
            ['granted_permissions' => ['permission:deploy']]
        );

        $this->assertSame('ROUTED', $result['status']);
    }

    public function testRouteRejectsAnAgentNotApprovedForTheRequiredDomain(): void
    {
        $router = new TaskRouter($this->registry());

        $result = $router->route(
            ['task_id' => 'task_a', 'domain_context' => 'wordpress_plugin'],
            ['required_capability' => 'developer'],
            ['agent_domains' => ['developer' => ['general']]]
        );

        $this->assertSame('BLOCKED', $result['status']);
        $this->assertStringContainsString('wordpress_plugin', $result['rejected_candidates'][0]['reason']);
    }

    public function testRouteAcceptsAnAgentApprovedForTheRequiredDomain(): void
    {
        $router = new TaskRouter($this->registry());

        $result = $router->route(
            ['task_id' => 'task_a', 'domain_context' => 'wordpress_plugin'],
            ['required_capability' => 'developer'],
            ['agent_domains' => ['developer' => ['wordpress_plugin']]]
        );

        $this->assertSame('ROUTED', $result['status']);
    }

    public function testRouteBreaksATieBetweenEqualCandidatesByLowestLoad(): void
    {
        $registry = new AgentRegistry();
        $registry->register(new CallbackAgent('agent_busy', 'Busy', 'desc', fn(array $c): bool => ($c['stage'] ?? null) === 'writer', fn(array $c): array => []));
        $registry->register(new CallbackAgent('agent_idle', 'Idle', 'desc', fn(array $c): bool => ($c['stage'] ?? null) === 'writer', fn(array $c): array => []));
        $router = new TaskRouter($registry);

        $result = $router->route(
            ['task_id' => 'task_a'],
            ['required_capability' => 'writer'],
            ['agent_load' => ['agent_busy' => 5, 'agent_idle' => 0]]
        );

        $this->assertSame('ROUTED', $result['status']);
        $this->assertSame('agent_idle', $result['owner']);
    }

    public function testRerouteNeverErasesThePreviousRoute(): void
    {
        $router = new TaskRouter($this->registry());
        $original = $router->route(['task_id' => 'task_a'], ['required_capability' => 'developer']);

        $rerouted = $router->reroute($original, 'Agent "developer" became unavailable mid-execution.');

        $this->assertSame('REROUTE_REQUIRED', $rerouted['status']);
        $this->assertSame($original, $rerouted['previous_route']);
        $this->assertSame('Agent "developer" became unavailable mid-execution.', $rerouted['reroute_reason']);
    }
}
