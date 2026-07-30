<?php

declare(strict_types=1);

namespace SquirrelForge\Optimization;

use SquirrelForge\Reasoning\SqliteRiskAssessor;

/**
 * Assesses an optimization proposal's readiness for Optimization
 * Governance review, per 32_OPTIMIZATION/OPTIMIZATION-VALIDATOR.md.
 *
 * "Check proposal evidence completeness and traceability" is a real
 * structural check against the actual shape OptimizationEngine::
 * createProposal() produces: a proposal must carry a non-empty
 * `opportunity` with its own `evidence` -- the real trace back to
 * PatternDetector's findings -- or it's rejected outright.
 * "Consume authoritative risk assessments" is real: validate() calls
 * SqliteRiskAssessor::canProceed() for the proposal's own id, the same
 * check every governance component in this codebase performs.
 *
 * Test evidence, platform-validation references, policy-evaluation
 * results, and Security decisions are consumed as caller-supplied
 * true/false/"pending" values per category, the same pattern
 * AutomationValidator uses for its own required references -- this
 * class never decides whether a test passed or a security review
 * cleared, only whether one was recorded.
 *
 * All five findings are real and reachable through a deterministic
 * priority ladder: `not_ready` (incomplete evidence, an unmitigated
 * critical risk, or an explicitly denied reference) beats
 * `requires_revision` (missing measurable objectives, baseline
 * reference, comparison method, rollback plan, or a reference category
 * never addressed at all) beats `deferred` (a reference explicitly
 * "pending") beats `ready_with_conditions` (a reference explicitly
 * false) beats `ready`. Like OptimizationEngine, this class has no
 * database -- the spec forbids owning "audit-trail infrastructure" --
 * it's a pure function from a proposal to a finding.
 */
final class OptimizationValidator
{
    private const REFERENCE_CATEGORIES = ['test_evidence', 'platform_validation', 'policy_result', 'security_decision'];

    public function __construct(private readonly ?SqliteRiskAssessor $riskAssessor = null)
    {
    }

    /**
     * @param array{proposal_id?: string, opportunity?: array<string, mixed>, success_criteria?: array<int, string>} $proposal
     * @param array{baseline_reference?: string, comparison_method?: string, rollback_plan?: string, references?: array<string, bool|string>} $options
     * @return array{outcome: string, issues: array<int, string>, missing_references: array<int, string>, pending_references: array<int, string>, unsatisfied_references: array<int, string>, denied_references: array<int, string>}
     */
    public function validate(array $proposal, array $options = []): array
    {
        if (($proposal['opportunity']['evidence'] ?? null) === null) {
            return $this->finish('not_ready', ['Proposal has no traceable opportunity evidence.'], [], [], [], []);
        }

        if ($this->riskAssessor !== null && isset($proposal['proposal_id'])) {
            $risk = $this->riskAssessor->canProceed($proposal['proposal_id']);

            if (!$risk['can_proceed']) {
                return $this->finish('not_ready', ['Unmitigated critical risk(s) block this proposal.'], [], [], [], []);
            }
        }

        $incomplete = [];

        if (($proposal['success_criteria'] ?? []) === []) {
            $incomplete[] = 'Measurable success criteria are missing.';
        }

        foreach (['baseline_reference', 'comparison_method', 'rollback_plan'] as $field) {
            if (($options[$field] ?? '') === '') {
                $incomplete[] = sprintf('"%s" is missing.', $field);
            }
        }

        $references = $options['references'] ?? [];
        $missing = [];
        $pending = [];
        $unsatisfied = [];
        $denied = [];

        foreach (self::REFERENCE_CATEGORIES as $category) {
            if (!array_key_exists($category, $references)) {
                $missing[] = $category;
            } elseif ($references[$category] === 'pending') {
                $pending[] = $category;
            } elseif ($references[$category] === false) {
                $unsatisfied[] = $category;
            } elseif ($references[$category] === 'denied') {
                $denied[] = $category;
            }
        }

        if ($denied !== []) {
            return $this->finish('not_ready', array_map(static fn(string $c): string => sprintf('"%s" was denied.', $c), $denied), $missing, $pending, $unsatisfied, $denied);
        }

        if ($incomplete !== [] || $missing !== []) {
            return $this->finish('requires_revision', $incomplete, $missing, $pending, $unsatisfied, $denied);
        }

        if ($pending !== []) {
            return $this->finish('deferred', [], $missing, $pending, $unsatisfied, $denied);
        }

        if ($unsatisfied !== []) {
            return $this->finish('ready_with_conditions', [], $missing, $pending, $unsatisfied, $denied);
        }

        return $this->finish('ready', [], $missing, $pending, $unsatisfied, $denied);
    }

    /**
     * @param array<int, string> $issues
     * @param array<int, string> $missing
     * @param array<int, string> $pending
     * @param array<int, string> $unsatisfied
     * @param array<int, string> $denied
     * @return array{outcome: string, issues: array<int, string>, missing_references: array<int, string>, pending_references: array<int, string>, unsatisfied_references: array<int, string>, denied_references: array<int, string>}
     */
    private function finish(string $outcome, array $issues, array $missing, array $pending, array $unsatisfied, array $denied): array
    {
        return [
            'outcome' => $outcome,
            'issues' => $issues,
            'missing_references' => $missing,
            'pending_references' => $pending,
            'unsatisfied_references' => $unsatisfied,
            'denied_references' => $denied,
        ];
    }
}
