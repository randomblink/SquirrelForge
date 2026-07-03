<?php

declare(strict_types=1);

namespace SquirrelForge\Tests;

use PHPUnit\Framework\TestCase;
use SquirrelForge\Agent\AgentOrchestrator;
use SquirrelForge\Agent\AgentRegistry;
use SquirrelForge\Core\Kernel;

final class RolePipelineTest extends TestCase
{
    private AgentOrchestrator $orchestrator;
    private AgentRegistry $registry;

    protected function setUp(): void
    {
        $kernel = new Kernel();
        $app = $kernel->boot();

        $this->registry = $app->container()->make(AgentRegistry::class);
        $this->orchestrator = $app->container()->make(AgentOrchestrator::class);
    }

    public function testAllEightPipelineStagesAreRegistered(): void
    {
        foreach (['architect', 'planner', 'developer', 'reviewer', 'security', 'performance', 'documentation', 'release'] as $stage) {
            $agent = $this->registry->findFor(['stage' => $stage]);
            $this->assertNotNull($agent, "No agent registered for stage \"{$stage}\".");
            $this->assertSame($stage, $agent->getId());
        }
    }

    public function testOrchestratorIsRegisteredForOrchestrateStage(): void
    {
        $agent = $this->registry->findFor(['stage' => 'orchestrate']);

        $this->assertNotNull($agent);
        $this->assertSame('orchestrator', $agent->getId());
    }

    public function testHappyPathReachesReadyRelease(): void
    {
        $result = $this->orchestrator->run($this->happyPathContext());

        $this->assertSame('Ready', $result['status']);
        $this->assertTrue($result['complete']);
        $this->assertCount(8, $result['trace']);
        $this->assertSame('architect', $result['trace'][0]['stage']);
        $this->assertSame('release', $result['trace'][7]['stage']);
        $this->assertSame([], $result['history']['release']['release']['outstanding']);
    }

    public function testBlockedDeveloperStageStopsThePipeline(): void
    {
        $context = $this->happyPathContext();
        $context['tasks_completed'] = [
            ['task' => 'Implement cache manager', 'output' => null, 'status' => 'Blocked'],
        ];

        $result = $this->orchestrator->run($context);

        $this->assertSame('Blocked', $result['status']);
        $this->assertFalse($result['complete']);
        // architect, planner, developer ran; nothing past developer.
        $this->assertCount(3, $result['trace']);
        $this->assertSame('developer', $result['trace'][2]['stage']);
    }

    public function testReviewerIssuesHaltBeforeSecurity(): void
    {
        $context = $this->happyPathContext();
        $context['issues'] = ['Missing input validation on settings form.'];

        $result = $this->orchestrator->run($context);

        $this->assertSame('Revision Required', $result['status']);
        $this->assertFalse($result['complete']);
        $this->assertCount(4, $result['trace']);
        $this->assertSame('reviewer', $result['trace'][3]['stage']);
    }

    public function testCriticalSecurityFindingStopsThePipeline(): void
    {
        $context = $this->happyPathContext();
        $context['security_findings'] = [
            ['severity' => 'critical', 'summary' => 'Missing nonce verification.'],
        ];

        $result = $this->orchestrator->run($context);

        // architect, planner, developer, reviewer all pass before security fails.
        $this->assertSame('Failed', $result['status']);
        $this->assertFalse($result['complete']);
        $this->assertCount(5, $result['trace']);
        $this->assertSame('security', $result['trace'][4]['stage']);
    }

    public function testNonCriticalFindingsWarnButStillReachRelease(): void
    {
        $context = $this->happyPathContext();
        $context['security_findings'] = [
            ['severity' => 'low', 'summary' => 'Missing rate limiting on a low-risk endpoint.'],
        ];

        $result = $this->orchestrator->run($context);

        $this->assertSame('Ready', $result['status']);
        $this->assertTrue($result['complete']);
        $this->assertSame('Warning', $result['history']['security']['status']);
        $this->assertTrue($result['history']['release']['release']['gates']['security']);
    }

    public function testArchitectRequiresAGoal(): void
    {
        $context = $this->happyPathContext();
        unset($context['goal']);

        $this->expectException(\InvalidArgumentException::class);

        $this->orchestrator->run($context);
    }

    /**
     * @return array<string, mixed>
     */
    private function happyPathContext(): array
    {
        return [
            'goal' => 'Build a transient-backed caching plugin.',
            'project_type' => 'Plugin',
            'components' => ['Cache Manager', 'Admin Settings'],
            'dependencies' => ['WordPress Transients API'],
            'risks' => [],
            'primary_workflow' => 'PLUGIN-DEVELOPMENT-WORKFLOW',
            'supporting_workflows' => [],
            'expected_output' => 'Installable WordPress plugin.',
            'phases' => [
                [
                    'phase' => 'Core',
                    'task' => 'Implement cache manager',
                    'agent' => 'developer',
                    'dependencies' => [],
                    'validation' => 'Unit tests pass',
                    'status' => 'Pending',
                ],
            ],
            'tasks_completed' => [
                ['task' => 'Implement cache manager', 'output' => 'CacheManager.php', 'status' => 'Complete'],
            ],
            'issues' => [],
            'security_findings' => [],
            'performance_findings' => [],
            'documentation_updates' => ['README.md'],
            'documentation_checklist' => [
                'readme' => true,
                'changelog' => true,
                'release_notes' => true,
            ],
        ];
    }
}
