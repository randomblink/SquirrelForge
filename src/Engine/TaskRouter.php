<?php

declare(strict_types=1);

namespace SquirrelForge\Engine;

use SquirrelForge\Agent\AgentRegistry;

/**
 * Assigns each ready task to the agent and execution path able to
 * satisfy its requirements, per 14_ENGINE/TASK-ROUTER.md.
 *
 * Does not take 17_COORDINATION or 22_INTERFACES as dependencies: neither
 * has a src/ implementation, so coordination constraints and interface
 * access are accepted as caller-supplied context, the same boundary
 * DependencyAnalyzer already draws around 22_INTERFACES/21_CONFIGURATION.
 * 16_AGENTS, by contrast, is real: "find candidate agents" and "reject
 * candidates that lack required capability" are genuine compositions of
 * the already-real AgentRegistry::all() and every AgentInterface's own
 * real supports() check (`($context['stage'] ?? null) ===
 * $this->stage()`), never a fabricated capability model.
 *
 * "Confirm that required predecessor dependencies are satisfied" is
 * upheld by consuming ExecutionPlanner's own step `status` directly: a
 * step ExecutionPlanner already marked `blocked` (because of a
 * DependencyAnalyzer blocker or an unmet Recovery Planning Rule) is
 * never routed -- this class does not re-derive readiness, it trusts
 * the real upstream result.
 *
 * Permission and domain gating reuse the same literal cross-check
 * pattern established throughout this session (GoalPlanner,
 * TaskDecomposer, WorkflowSelector): an agent is rejected unless the
 * caller's own `granted_permissions`/`agent_domains` context actually
 * covers what the step requires -- never assumed available.
 *
 * Among agents that pass every real check, selection is a plain,
 * explainable tie-break: the candidate with the lowest caller-supplied
 * current load, the same "simple formula over real inputs" idiom this
 * codebase uses throughout rather than a fabricated scheduling
 * algorithm.
 *
 * The Rerouting Rule ("must preserve task history and must not erase
 * the failed or superseded route") is upheld literally: reroute()
 * nests the previous route record rather than discarding it.
 *
 * Like ExecutionPlanner, this class has no database: routing decisions
 * are pure return values for the caller (eventually Coordination) to
 * act on.
 */
final class TaskRouter
{
    public function __construct(private readonly ?AgentRegistry $agentRegistry = null)
    {
    }

    /**
     * @param array{task_id: string, status?: string, domain_context?: ?string, permissions?: ?string} $step ExecutionPlanner's own step shape
     * @param array{required_capability: string} $requirements
     * @param array{granted_permissions?: array<int, string>, agent_domains?: array<string, array<int, string>>, agent_availability?: array<string, bool>, agent_load?: array<string, int>} $context
     * @return array{task_id: string, status: string, owner: ?string, rejected_candidates: array<int, array{agent_id: string, reason: string}>, rationale: ?string}
     */
    public function route(array $step, array $requirements, array $context = []): array
    {
        if (($step['status'] ?? null) === 'blocked') {
            return $this->result('BLOCKED', $step['task_id'], null, [], 'The execution plan already marked this step blocked.');
        }

        if ($this->agentRegistry === null) {
            return $this->result('BLOCKED', $step['task_id'], null, [], 'No agent registry is configured.');
        }

        $rejected = [];
        $eligible = [];

        foreach ($this->agentRegistry->all() as $agent) {
            $reason = $this->rejectionReason($agent->getId(), $agent->supports(['stage' => $requirements['required_capability']]), $step, $requirements, $context);

            if ($reason !== null) {
                $rejected[] = ['agent_id' => $agent->getId(), 'reason' => $reason];

                continue;
            }

            $eligible[] = $agent;
        }

        if ($eligible === []) {
            return $this->result('BLOCKED', $step['task_id'], null, $rejected, 'No eligible agent was found for this step.');
        }

        usort($eligible, static fn($a, $b): int => ($context['agent_load'][$a->getId()] ?? 0) <=> ($context['agent_load'][$b->getId()] ?? 0));
        $selected = $eligible[0];

        $rationale = sprintf(
            'Agent "%s" supports capability "%s" and has the lowest current load among %d eligible candidate(s).',
            $selected->getId(),
            $requirements['required_capability'],
            count($eligible)
        );

        return $this->result('ROUTED', $step['task_id'], $selected->getId(), $rejected, $rationale);
    }

    /**
     * The Rerouting Rule: preserves the superseded route rather than
     * erasing it.
     *
     * @param array{task_id: string} $previousRoute
     * @return array{task_id: string, status: string, previous_route: array<string, mixed>, reroute_reason: string}
     */
    public function reroute(array $previousRoute, string $reason): array
    {
        return [
            'task_id' => $previousRoute['task_id'],
            'status' => 'REROUTE_REQUIRED',
            'previous_route' => $previousRoute,
            'reroute_reason' => $reason,
        ];
    }

    /**
     * @param array{domain_context?: ?string, permissions?: ?string} $step
     * @param array{required_capability: string} $requirements
     * @param array{granted_permissions?: array<int, string>, agent_domains?: array<string, array<int, string>>, agent_availability?: array<string, bool>} $context
     */
    private function rejectionReason(string $agentId, bool $supportsCapability, array $step, array $requirements, array $context): ?string
    {
        if (!$supportsCapability) {
            return sprintf('Agent "%s" does not support capability "%s".', $agentId, $requirements['required_capability']);
        }

        if (($context['agent_availability'][$agentId] ?? true) !== true) {
            return sprintf('Agent "%s" is not currently available.', $agentId);
        }

        if (($step['permissions'] ?? null) !== null && !in_array($step['permissions'], $context['granted_permissions'] ?? [], true)) {
            return sprintf('Agent "%s" lacks the required permission "%s".', $agentId, $step['permissions']);
        }

        if (($step['domain_context'] ?? null) !== null && !in_array($step['domain_context'], $context['agent_domains'][$agentId] ?? [], true)) {
            return sprintf('Agent "%s" is not approved for domain "%s".', $agentId, $step['domain_context']);
        }

        return null;
    }

    /**
     * @param array<int, array{agent_id: string, reason: string}> $rejected
     * @return array{task_id: string, status: string, owner: ?string, rejected_candidates: array<int, array{agent_id: string, reason: string}>, rationale: ?string}
     */
    private function result(string $status, string $taskId, ?string $owner, array $rejected, ?string $rationale): array
    {
        return ['task_id' => $taskId, 'status' => $status, 'owner' => $owner, 'rejected_candidates' => $rejected, 'rationale' => $rationale];
    }
}
