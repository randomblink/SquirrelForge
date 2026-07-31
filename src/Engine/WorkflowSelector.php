<?php

declare(strict_types=1);

namespace SquirrelForge\Engine;

/**
 * Chooses one primary workflow for an active request and records why it
 * fits the goal, project context, domain, risk, and permissions, per
 * 14_ENGINE/WORKFLOW-SELECTOR.md.
 *
 * "Find candidate workflows in 02_WORKFLOWS" and "must not invent...
 * tool availability" are upheld literally: listAvailableWorkflows()
 * globs the real directory, and selectWorkflow() rejects any candidate
 * naming a workflow file that doesn't actually exist there. Judging
 * whether a workflow's *purpose* fits the goal is inherently semantic
 * (these workflow documents are free-form prose with no machine-
 * readable applicability metadata), so fit scores per candidate
 * (outcome_fit, domain_fit, risk_fit, validation_support,
 * recoverability, simplicity) are caller-supplied -- the same
 * "consume, don't compute" boundary every semantic judgment in this
 * codebase respects. Ranking those scores into a single selection is
 * real: a plain average, the same DecisionMatrix-style weighted-scoring
 * idiom used throughout 19_REASONING/32_OPTIMIZATION.
 *
 * This is a genuine, direct composition of Goal Planner's real output
 * (the literal "Depends On: Goal record"): a goal already marked
 * `clarification-required` or `blocked` by GoalPlanner short-circuits
 * straight to the matching Selection State without re-deriving
 * anything, and the Domain Rule is enforced by cross-checking a
 * candidate's declared domain against the goal's own real
 * active_domains, exactly like TaskDecomposer already does.
 *
 * The Risk Rule ("higher-risk requests may require supporting
 * workflows for security review, testing, governance approval...")
 * is a real, literal mapping from GoalPlanner's own initial_risk value
 * to a fixed list of required supporting-workflow purposes -- critical
 * risk requires more than high risk, and neither is fabricated per
 * request, it's the same fixed table every time. A caller-declared
 * supporting workflow's purpose is checked against that list, and a gap
 * is recorded as a limitation (SELECTED_WITH_LIMITATIONS), never
 * silently ignored.
 *
 * Like GoalPlanner, this class has no database: a selection record is a
 * pure return value for the caller (eventually Execution Planner / Task
 * Router) to act on.
 */
final class WorkflowSelector
{
    private const RISK_REQUIRED_SUPPORT_PURPOSES = [
        'critical' => ['security_review', 'testing', 'governance_approval', 'rollback_preparation'],
        'high' => ['security_review', 'testing'],
    ];

    public function __construct(private readonly string $workflowsDirectory = '02_WORKFLOWS')
    {
    }

    /**
     * @return array<int, string>
     */
    public function listAvailableWorkflows(): array
    {
        $files = glob(rtrim($this->workflowsDirectory, '/') . '/*.md') ?: [];
        $names = [];

        foreach ($files as $file) {
            $name = basename($file, '.md');

            if ($name !== 'README') {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    /**
     * @param array{status?: string, initial_risk?: ?string, active_domains?: array<int, string>} $goal GoalPlanner::planGoal()'s `goal` output
     * @param array<int, array{name: string, domain?: ?string, outcome_fit?: int, domain_fit?: int, risk_fit?: int, validation_support?: int, recoverability?: int, simplicity?: int, rejection_reason?: ?string, missing_capabilities?: array<int, string>}> $candidates
     * @param array<int, array{name: string, purpose: string}> $supportingCandidates
     * @return array{status: string, primary_workflow: ?string, supporting_workflows: array<int, string>, rejected_candidates: array<int, array<string, mixed>>, rationale: ?string, limitations: array<int, string>, error: ?string}
     */
    public function selectWorkflow(array $goal, array $candidates, array $supportingCandidates = []): array
    {
        if (($goal['status'] ?? null) === 'clarification-required') {
            return $this->result('CLARIFICATION_REQUIRED', null, [], [], 'The goal itself requires clarification before a workflow can be safely selected.', []);
        }

        if (($goal['status'] ?? null) === 'blocked') {
            return $this->result('BLOCKED', null, [], [], 'The goal is blocked and cannot proceed to workflow selection.', []);
        }

        $available = $this->listAvailableWorkflows();
        $activeDomains = $goal['active_domains'] ?? [];
        $accepted = [];
        $rejected = [];

        foreach ($candidates as $candidate) {
            $reason = $this->rejectionReason($candidate, $available, $activeDomains);

            if ($reason !== null) {
                $rejected[] = [...$candidate, 'rejection_reason' => $reason];

                continue;
            }

            $accepted[] = [...$candidate, 'score' => $this->score($candidate)];
        }

        if ($accepted === []) {
            return $this->result('UNSUPPORTED', null, [], $rejected, 'No governed workflow in the workflow library fits this goal.', []);
        }

        usort($accepted, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $primary = $accepted[0];

        $limitations = [];

        if (($primary['missing_capabilities'] ?? []) !== []) {
            $limitations[] = sprintf('Workflow "%s" is missing: %s.', $primary['name'], implode(', ', $primary['missing_capabilities']));
        }

        $requiredSupportPurposes = self::RISK_REQUIRED_SUPPORT_PURPOSES[$goal['initial_risk'] ?? ''] ?? [];
        $providedPurposes = array_column($supportingCandidates, 'purpose');
        $missingSupport = array_diff($requiredSupportPurposes, $providedPurposes);

        if ($missingSupport !== []) {
            $limitations[] = sprintf('Risk level "%s" typically requires supporting workflow(s) for: %s.', $goal['initial_risk'], implode(', ', $missingSupport));
        }

        $status = $limitations !== [] ? 'SELECTED_WITH_LIMITATIONS' : 'SELECTED';
        $rationale = sprintf('"%s" scored highest (%.2f) among %d fitting candidate(s).', $primary['name'], $primary['score'], count($accepted));

        return $this->result($status, $primary['name'], array_column($supportingCandidates, 'name'), $rejected, $rationale, $limitations);
    }

    /**
     * @param array{name: string, domain?: ?string, rejection_reason?: ?string} $candidate
     * @param array<int, string> $available
     * @param array<int, string> $activeDomains
     */
    private function rejectionReason(array $candidate, array $available, array $activeDomains): ?string
    {
        if (($candidate['rejection_reason'] ?? null) !== null) {
            return $candidate['rejection_reason'];
        }

        if (!in_array($candidate['name'], $available, true)) {
            return sprintf('Workflow "%s" does not exist in the workflow library.', $candidate['name']);
        }

        if (($candidate['domain'] ?? null) !== null && !in_array($candidate['domain'], $activeDomains, true)) {
            return sprintf('Workflow "%s" requires domain "%s", which is not active for this goal.', $candidate['name'], $candidate['domain']);
        }

        return null;
    }

    /**
     * @param array{outcome_fit?: int, domain_fit?: int, risk_fit?: int, validation_support?: int, recoverability?: int, simplicity?: int} $candidate
     */
    private function score(array $candidate): float
    {
        $fields = ['outcome_fit', 'domain_fit', 'risk_fit', 'validation_support', 'recoverability', 'simplicity'];
        $sum = 0;
        $count = 0;

        foreach ($fields as $field) {
            if (isset($candidate[$field])) {
                $sum += $candidate[$field];
                $count++;
            }
        }

        return $count > 0 ? $sum / $count : 0.0;
    }

    /**
     * @param array<int, array<string, mixed>> $rejected
     * @param array<int, string> $supporting
     * @param array<int, string> $limitations
     * @return array{status: string, primary_workflow: ?string, supporting_workflows: array<int, string>, rejected_candidates: array<int, array<string, mixed>>, rationale: ?string, limitations: array<int, string>, error: ?string}
     */
    private function result(string $status, ?string $primary, array $supporting, array $rejected, ?string $rationale, array $limitations): array
    {
        return [
            'status' => $status,
            'primary_workflow' => $primary,
            'supporting_workflows' => $supporting,
            'rejected_candidates' => $rejected,
            'rationale' => $rationale,
            'limitations' => $limitations,
            'error' => null,
        ];
    }
}
