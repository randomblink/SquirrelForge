<?php

declare(strict_types=1);

namespace SquirrelForge\Engine;

/**
 * Converts a user request into a clear, bounded, evidence-aware goal
 * record, per 14_ENGINE/GOAL-PLANNER.md.
 *
 * Most of the Goal Model's fields -- primary goal, expected output,
 * acceptance criteria, supporting tasks, dependencies, assumptions,
 * missing information -- are inherently semantic judgments about a
 * natural-language request. No deterministic class can honestly derive
 * "the primary requested outcome" from prose without fabricating an NLP
 * model this codebase doesn't have, so those are accepted as
 * caller-supplied input (the same "consume, don't compute" boundary
 * OptimizationEngine draws around expected benefit and effort). What
 * this class actually computes is real:
 *
 * classifyRequestType() is a real, deterministic keyword match against
 * the request text (e.g. "delete"/"drop" classifies as `destructive`),
 * the same substring-based honesty AgentOptimizer already applies to
 * its own dimension classification. estimateInitialRisk() is a real,
 * simple, explainable mapping from that classification to a risk
 * level -- not a fabricated risk model.
 *
 * The Clarification Rule and Scope Rule are both real, structural
 * checks, not invented judgment: evaluateClarificationNeed() flags
 * clarification as required only when a caller-tagged missing-info item
 * carries one of the spec's own seven named impact categories (output,
 * target project, permission boundary, destructive impact, security
 * posture, production impact, user-data risk) -- never a generic
 * "something is unknown" trigger. evaluateScopeExpansions() flags a
 * scope expansion as unjustified only when its caller-supplied reason
 * isn't one of the spec's own six allowed reasons (safety, validation,
 * architecture consistency, dependency resolution, recovery, explicit
 * user instruction).
 *
 * The Domain Rule ("must not assume a domain merely because
 * SquirrelForge supports it") is upheld by composing the already-real
 * ProjectLoader::detectProjectType() for active_domains rather than
 * guessing from the request text -- a domain is only ever active when
 * Project Loader's own file-presence evidence found it.
 *
 * Status is derived deterministically from these real checks alone:
 * `clarification-required` when the Clarification Rule triggers,
 * `blocked` when the Scope Rule finds an unjustified expansion,
 * otherwise `planned`. Like ProjectLoader, this class has no database:
 * a goal record is a pure return value for the caller (eventually Task
 * Decomposer / Workflow Selector) to act on.
 */
final class GoalPlanner
{
    private const REQUEST_TYPE_KEYWORDS = [
        'destructive' => ['delete', 'remove', 'drop', 'truncate', 'wipe', 'purge'],
        'deployment' => ['deploy', 'release', 'publish', 'ship to production'],
        'automation' => ['automate', 'schedule', 'cron job', 'trigger'],
        'recovery' => ['rollback', 'restore', 'recover', 'revert'],
        'documentation' => ['document', 'write docs', 'explain how', 'describe the'],
        'read_only' => ['show me', 'read the', 'list the', 'view the', 'what is', 'inspect'],
        'external' => ['external api', 'external service', 'webhook', 'third-party'],
    ];

    private const RISK_BY_REQUEST_TYPE = [
        'destructive' => 'critical',
        'deployment' => 'high',
        'recovery' => 'high',
        'automation' => 'moderate',
        'external' => 'moderate',
        'implementation' => 'moderate',
        'documentation' => 'low',
        'read_only' => 'low',
    ];

    private const CLARIFICATION_IMPACT_CATEGORIES = [
        'output', 'target_project', 'permission_boundary', 'destructive_impact',
        'security_posture', 'production_impact', 'user_data_risk',
    ];

    private const VALID_SCOPE_EXPANSION_REASONS = [
        'safety', 'validation', 'architecture_consistency', 'dependency_resolution', 'recovery', 'explicit_user_instruction',
    ];

    public function __construct(private readonly ?ProjectLoader $projectLoader = null)
    {
    }

    public function classifyRequestType(string $requestText): string
    {
        $lowered = strtolower($requestText);

        foreach (self::REQUEST_TYPE_KEYWORDS as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lowered, $keyword)) {
                    return $type;
                }
            }
        }

        return 'implementation';
    }

    public function estimateInitialRisk(string $requestType): string
    {
        return self::RISK_BY_REQUEST_TYPE[$requestType] ?? 'moderate';
    }

    /**
     * @param array<int, array{item: string, impact_category?: ?string}> $missingInformation
     * @return array{requires_clarification: bool, triggering_items: array<int, array<string, mixed>>}
     */
    public function evaluateClarificationNeed(array $missingInformation): array
    {
        $triggering = array_values(array_filter(
            $missingInformation,
            static fn(array $item): bool => in_array($item['impact_category'] ?? null, self::CLARIFICATION_IMPACT_CATEGORIES, true)
        ));

        return ['requires_clarification' => $triggering !== [], 'triggering_items' => $triggering];
    }

    /**
     * @param array<int, array{description: string, reason?: ?string}> $scopeExpansions
     * @return array{valid: bool, unjustified_expansions: array<int, array<string, mixed>>}
     */
    public function evaluateScopeExpansions(array $scopeExpansions): array
    {
        $unjustified = array_values(array_filter(
            $scopeExpansions,
            static fn(array $expansion): bool => !in_array($expansion['reason'] ?? null, self::VALID_SCOPE_EXPANSION_REASONS, true)
        ));

        return ['valid' => $unjustified === [], 'unjustified_expansions' => $unjustified];
    }

    /**
     * @param array{primary_goal: string, expected_output: string, request_text: string, acceptance_criteria?: array<int, string>, supporting_tasks?: array<int, string>, dependencies?: array<int, string>, assumptions?: array<int, string>, missing_information?: array<int, array{item: string, impact_category?: ?string}>, scope_expansions?: array<int, array{description: string, reason?: ?string}>, validation_needs?: array<int, string>, project_root?: string} $input
     * @return array{goal: array<string, mixed>, outcome: string, error: ?string}
     */
    public function planGoal(array $input): array
    {
        foreach (['primary_goal', 'expected_output', 'request_text'] as $field) {
            if (($input[$field] ?? '') === '') {
                return ['goal' => [], 'outcome' => 'invalid_request', 'error' => sprintf('"%s" is required.', $field)];
            }
        }

        $requestType = $this->classifyRequestType($input['request_text']);
        $risk = $this->estimateInitialRisk($requestType);
        $clarification = $this->evaluateClarificationNeed($input['missing_information'] ?? []);
        $scope = $this->evaluateScopeExpansions($input['scope_expansions'] ?? []);

        $activeDomains = [];

        if ($this->projectLoader !== null && isset($input['project_root'])) {
            $activeDomains = $this->projectLoader->detectProjectType($input['project_root'])['active_domains'];
        }

        $status = match (true) {
            $clarification['requires_clarification'] => 'clarification-required',
            !$scope['valid'] => 'blocked',
            default => 'planned',
        };

        $goal = [
            'request' => $input['request_text'],
            'primary_goal' => $input['primary_goal'],
            'expected_output' => $input['expected_output'],
            'acceptance_criteria' => $input['acceptance_criteria'] ?? [],
            'scope' => ['expansions' => $input['scope_expansions'] ?? [], 'unjustified_expansions' => $scope['unjustified_expansions']],
            'request_type' => $requestType,
            'active_domains' => $activeDomains,
            'supporting_tasks' => $input['supporting_tasks'] ?? [],
            'dependencies' => $input['dependencies'] ?? [],
            'assumptions' => $input['assumptions'] ?? [],
            'missing_information' => $input['missing_information'] ?? [],
            'initial_risk' => $risk,
            'validation_needs' => $input['validation_needs'] ?? [],
            'status' => $status,
        ];

        return ['goal' => $goal, 'outcome' => $status, 'error' => null];
    }
}
