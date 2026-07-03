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
use SquirrelForge\Contracts\ConfigurationInterface;
use SquirrelForge\Contracts\ContainerInterface;
use SquirrelForge\Contracts\LlmClientInterface;
use SquirrelForge\Contracts\ServiceProviderInterface;
use SquirrelForge\Llm\AnthropicClient;

final class AgentServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(AgentRegistry::class, AgentRegistry::class);

        $container->singleton(
            AgentOrchestrator::class,
            static fn (ContainerInterface $container): AgentOrchestrator => new AgentOrchestrator(
                $container->make(AgentRegistry::class)
            )
        );
    }

    public function boot(ContainerInterface $container): void
    {
        /** @var AgentRegistry $registry */
        $registry = $container->make(AgentRegistry::class);

        $llm = $this->resolveLlmClient($container);

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
     * The Architect -> Planner -> Developer -> Reviewer -> Security ->
     * Performance -> Documentation -> Release handoff sequence defined in
     * `16_AGENTS/README.md`.
     *
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

    /**
     * Builds an Anthropic-backed LLM client when an API key is configured,
     * either via `ConfigurationInterface` (key: `llm.anthropic.api_key`) or
     * the `ANTHROPIC_API_KEY` environment variable. Returns null when no
     * key is available, in which case every role agent falls back to its
     * deterministic behavior.
     */
    private function resolveLlmClient(ContainerInterface $container): ?LlmClientInterface
    {
        $apiKey = null;

        if ($container->has(ConfigurationInterface::class)) {
            /** @var ConfigurationInterface $config */
            $config = $container->make(ConfigurationInterface::class);
            $apiKey = $config->get('llm.anthropic.api_key');
        }

        if ($apiKey === null || trim((string) $apiKey) === '') {
            $envKey = getenv('ANTHROPIC_API_KEY');
            $apiKey = $envKey !== false ? $envKey : null;
        }

        if ($apiKey === null || trim((string) $apiKey) === '') {
            return null;
        }

        $model = getenv('ANTHROPIC_MODEL');

        return new AnthropicClient(
            (string) $apiKey,
            $model !== false && $model !== '' ? $model : 'claude-sonnet-5'
        );
    }
}