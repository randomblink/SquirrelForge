<?php

declare(strict_types=1);

namespace SquirrelForge\Agent;

use RuntimeException;
use SquirrelForge\Agent\Support\BootableHealthCheck;
use SquirrelForge\Contracts\AgentInterface;

/**
 * Implements the Agent Orchestrator role from `16_AGENTS/AGENT-ORCHESTRATOR.md`.
 *
 * Coordinates the registered pipeline agents through the documented handoff
 * sequence (Architect -> Planner -> Developer -> Reviewer -> Security ->
 * Performance -> Documentation -> Release), one owner per stage, stopping
 * the moment a stage does not declare a `next_stage` to hand off to.
 */
final class AgentOrchestrator implements AgentInterface
{
    use BootableHealthCheck;

    public function __construct(
        private readonly AgentRegistry $registry,
        private readonly string $startStage = 'architect'
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    protected function healthDetails(): array
    {
        return [
            'id' => $this->getId(),
            'managed_agents' => count($this->registry->all()),
        ];
    }

    public function getId(): string
    {
        return 'orchestrator';
    }

    public function getName(): string
    {
        return 'Agent Orchestrator';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'Coordinates all SquirrelForge agents through the correct handoff sequence.';
    }

    public function supports(array $context): bool
    {
        return ($context['stage'] ?? null) === 'orchestrate';
    }

    public function execute(array $context): array
    {
        return $this->run($context);
    }

    public function metadata(): array
    {
        return [
            'start_stage' => $this->startStage,
        ];
    }

    /**
     * Run the full pipeline starting from `$this->startStage`, feeding each
     * stage's result into `history` for the following stages, and stopping
     * as soon as a stage fails to produce a `next_stage`.
     *
     * @param array<string, mixed> $initialContext
     * @return array<string, mixed> {trace, history, status, complete}
     */
    public function run(array $initialContext): array
    {
        $context = $initialContext;
        $context['history'] = $context['history'] ?? [];

        $stage = $this->startStage;
        $trace = [];

        while ($stage !== null) {
            $context['stage'] = $stage;

            $agent = $this->registry->findFor($context);

            if ($agent === null) {
                $trace[] = [
                    'stage' => $stage,
                    'status' => 'Blocked',
                    'reason' => sprintf('No agent registered for stage "%s".', $stage),
                ];
                $stage = null;
                break;
            }

            $result = $agent->execute($context);
            $context['history'][$stage] = $result;
            $trace[] = $result;

            $stage = $result['next_stage'] ?? null;
        }

        $last = $trace[count($trace) - 1] ?? null;
        $lastStatus = $last['status'] ?? 'Blocked';
        $complete = ($last['stage'] ?? null) === 'release' && $lastStatus === 'Ready';

        return [
            'trace' => $trace,
            'history' => $context['history'],
            'status' => $lastStatus,
            'complete' => $complete,
        ];
    }

    /**
     * Convenience accessor used by callers that already hold the registry
     * and want to confirm the orchestrator has visibility into every
     * expected pipeline stage before running it.
     */
    public function assertPipelineComplete(): void
    {
        foreach (PipelineStages::ALL as $stage) {
            if ($this->registry->findFor(['stage' => $stage]) === null) {
                throw new RuntimeException(sprintf('No agent registered for required stage "%s".', $stage));
            }
        }
    }
}
