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
use SquirrelForge\Contracts\CommandRunnerInterface;
use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\FileSystemInterface;
use SquirrelForge\Contracts\LlmClientInterface;
use SquirrelForge\Llm\LlmClientResolver;
use SquirrelForge\Modules\AbstractModule;
use SquirrelForge\Tools\LocalFileSystem;
use SquirrelForge\Tools\ReleaseActionsPolicy;
use SquirrelForge\Tools\ShellCommandRunner;

/**
 * Registers the Architect -> Planner -> Developer -> Reviewer -> Security ->
 * Performance -> Documentation -> Release role agent pipeline, plus the
 * orchestrator, into `AgentRegistry`.
 *
 * This is loaded through `ModuleLoader`, discovered by `ModuleDiscovery`
 * scanning `src/` (see `Kernel::loadModules()`) rather than being hardcoded
 * inside `AgentServiceProvider::boot()`, per `12_AGENT/BOOTSTRAP.md`:
 * capability registration is project initialization, not core service
 * wiring. `AgentServiceProvider` only registers the
 * `AgentRegistry`/`AgentOrchestrator` infrastructure those agents get
 * registered into.
 *
 * Also wires the real tool-use dependencies for Developer (file writes)
 * and Release (git actions): a `LocalFileSystem` rooted at the project
 * directory and a `ShellCommandRunner` restricted to an allowlist of
 * binaries. Whether Release is actually allowed to run those actions is a
 * separate opt-in (`ReleaseActionsPolicy`) from having an LLM configured.
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
        $fileSystem = new LocalFileSystem($this->projectRoot());
        $commandRunner = new ShellCommandRunner($this->projectRoot());
        $actionsEnabled = ReleaseActionsPolicy::isEnabled($container);

        foreach ($this->pipelineAgents($llm, $fileSystem, $commandRunner, $actionsEnabled) as $agent) {
            $agent->boot();
            $registry->register($agent);
        }

        /** @var AgentOrchestrator $orchestrator */
        $orchestrator = $container->make(AgentOrchestrator::class);
        $orchestrator->boot();
        $registry->register($orchestrator);
    }

    /**
     * @return array<int, AgentInterface>
     */
    private function pipelineAgents(
        ?LlmClientInterface $llm,
        FileSystemInterface $fileSystem,
        CommandRunnerInterface $commandRunner,
        bool $actionsEnabled
    ): array {
        return [
            new ArchitectAgent($llm),
            new PlannerAgent($llm),
            new DeveloperAgent($llm, $fileSystem),
            new ReviewerAgent($llm),
            new SecurityAgent($llm),
            new PerformanceAgent($llm),
            new DocumentationAgent($llm),
            new ReleaseAgent($llm, $fileSystem, $commandRunner, $actionsEnabled),
        ];
    }

    /**
     * Absolute path to the project root (one level above `src/`), used as
     * the containment root for file writes and the working directory for
     * shell commands.
     */
    private function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
