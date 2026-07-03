<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use SquirrelForge\Agent\Roles\ArchitectAgent;
use SquirrelForge\Agent\Roles\DeveloperAgent;
use SquirrelForge\Agent\Roles\DocumentationAgent;
use SquirrelForge\Agent\Roles\PerformanceAgent;
use SquirrelForge\Agent\Roles\PlannerAgent;
use SquirrelForge\Agent\Roles\ReleaseAgent;
use SquirrelForge\Agent\Roles\ReviewerAgent;
use SquirrelForge\Agent\Roles\SecurityAgent;
use SquirrelForge\Contracts\AgentInterface;
use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\LlmClientInterface;
use SquirrelForge\Llm\LlmClientResolver;
use SquirrelForge\Modules\AbstractModule;

/**
 * Registers the Architect -> Planner -> Developer -> Reviewer -> Security ->
 * Performance -> Documentation -> Release role agent pipeline, plus the
 * orchestrator, into `AgentRegistry`.
 *
 * This is loaded through `ModuleLoader` (see `Kernel::boot()`) rather than
 * being hardcoded inside `AgentServiceProvider::boot()`, per
 * `12_AGENT/BOOTSTRAP.md`: capability registration is project
 * initialization, not core service wiring. `AgentServiceProvider` only
 * registers the `AgentRegistry`/`AgentOrchestrator` infrastructure those
 * agents get registered into.
 *
 * Note: this is module-based registration, not filesystem auto-discovery.
 * The module list is still an explicit array in `Kernel::boot()`; nothing
 * in SquirrelForge yet scans a directory for modules to load.
 */
final class AgentPipelineModule extends AbstractModule
{
    public function getId(): string
    {
        return 'agent-pipeline';
    }

    public function getName(): string
    {
        return 'Agent Pipeline Module';
    }

    public function getDescription(): string
    {
        return 'Registers the Architect..Release role agent pipeline and orchestrator into AgentRegistry.';
    }

    public function boot(ContainerInterface $container): void
    {
        /** @var AgentRegistry $registry */
        $registry = $container->make(AgentRegistry::class);

        $llm = LlmClientResolver::resolve($container);

        foreach ($this->pipelineAgents($llm) as $agent) {
            $agent->boot();
            $registry->register($agent);
        }

        /** @var AgentOrchestrator $orchestrator */
        $orchestrator = $container->make(AgentOrchestrator::class);
        $orchestrator->boot();
        $registry->register($orchestrator);
    }

    /**
     * Each agent receives the same (possibly null) LLM client. Agents that
     * don't need to reason (Developer, Release) simply never call it.
     *
     * @return array<int, AgentInterface>
     */
    private function pipelineAgents(?LlmClientInterface $llm): array
    {
        return [
            new ArchitectAgent($llm),
            new PlannerAgent($llm),
            new DeveloperAgent($llm),
            new ReviewerAgent($llm),
            new SecurityAgent($llm),
            new PerformanceAgent($llm),
            new DocumentationAgent($llm),
            new ReleaseAgent($llm),
        ];
    }
}
