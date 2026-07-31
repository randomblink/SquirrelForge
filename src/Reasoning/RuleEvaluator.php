<?php

declare(strict_types=1);

namespace SquirrelForge\Reasoning;

use SquirrelForge\Automation\RuleEngine;
use SquirrelForge\Governance\SqlitePolicyEngine;

/**
 * Evaluates a proposed decision or action against applicable rules,
 * detects violations and conflicts, applies existing precedence, and
 * produces a traceable Rule Evaluation Record, per
 * 19_REASONING/RULE-EVALUATOR.md. This is the cleanest root in
 * 19_REASONING: unlike every other component in this category, it has
 * no dependency on another not-yet-built Reasoning component -- only
 * 01_RULES (consumed as caller-declared rule definitions, since prose
 * rule documents have no runtime shape to parse), the already-real
 * Policy Engine, and 24_SECURITY (real via the Security namespace,
 * consumed the same opaque way Quality Gates is: caller-supplied
 * evidence, since no src/ implementation exists for it yet).
 *
 * The actual mechanical evaluation -- condition matching, and
 * structural conflict detection (duplicate rule IDs, contradictory
 * boolean claims) -- is not reimplemented here: it genuinely delegates
 * to the existing SquirrelForge\Automation\RuleEngine, the same engine
 * SqlitePolicyEngine, TriggerManager, and Scheduler already reuse
 * across categories. This class's own contribution is domain-specific:
 * mapping RuleEngine's five-state result vocabulary onto the spec's
 * three-state Passed/Warning/Failed outcome, and applying the
 * precedence 01_RULES/README.md's Rule Loading Order and Conflict Rule
 * actually define, rather than inventing a new one.
 *
 * Precedence is applied literally, not invented: 01_RULES/README.md
 * orders General Agent Rules < Project-Specific Rules < Domain Rules <
 * Workflow Rules < Security/Governance Constraints, and states
 * "Mandatory rules override recommendations." When RuleEngine flags a
 * structural conflict between two applicable rules, the rule from the
 * higher-precedence source tier wins; a mandatory rule's Failed result
 * always overrides a non-mandatory rule's conflicting result regardless
 * of tier. When neither rule differs in tier or mandatory-ness, there
 * is no existing authority to resolve it, so per the spec's own
 * Conflict Rule ("surface the conflict... resolve it using the active
 * authority order... must not hide conflicts"), this class reports
 * `Escalate` rather than guessing -- a fourth, architecture-approved
 * outcome state the spec's own Evaluation Process step 6 requires.
 *
 * When a SqlitePolicyEngine is injected and the caller supplies
 * `policy_context`, its real decision is folded in as one additional
 * mandatory `security_governance`-tier rule -- genuine composition of
 * an already-real governance authority, not a fabricated security
 * check.
 *
 * Like RuleEngine/PatternDetector, this class has no database: findings
 * are pure return values, matching the spec's own stance that the
 * Evaluator "returns its result to whichever component requested the
 * evaluation" rather than owning a persisted record store itself.
 */
final class RuleEvaluator
{
    private const PRECEDENCE_ORDER = [
        'general_agent' => 0,
        'project_specific' => 1,
        'domain' => 2,
        'workflow' => 3,
        'security_governance' => 4,
    ];

    public function __construct(
        private readonly RuleEngine $ruleEngine = new RuleEngine(),
        private readonly ?SqlitePolicyEngine $policyEngine = null
    ) {
    }

    /**
     * @param array<int, array{id: string, source: string, mandatory?: bool, condition: array<string, mixed>}> $rules
     * @param array<string, mixed> $context
     * @param array{policy_request_id?: string, policy_context?: array<string, mixed>, policy_category?: ?string} $options
     * @return array{outcome: string, records: array<int, array<string, mixed>>, escalations: array<int, array<string, mixed>>, error: ?string}
     */
    public function evaluate(array $rules, array $context, array $options = []): array
    {
        foreach ($rules as $rule) {
            if (!isset(self::PRECEDENCE_ORDER[$rule['source'] ?? ''])) {
                return ['outcome' => 'Escalate', 'records' => [], 'escalations' => [], 'error' => sprintf('Rule "%s" has an unknown source tier "%s".', $rule['id'] ?? '?', $rule['source'] ?? '')];
            }
        }

        $policyReason = null;

        if ($this->policyEngine !== null && isset($options['policy_context'])) {
            [$policyRule, $passes, $policyReason] = $this->policyRule($options);
            $rules[] = $policyRule;
            $context = [...$context, 'policy_engine_decision_passes' => $passes];
        }

        $evaluation = $this->ruleEngine->evaluateAll($rules, $context);
        [$duplicateIds, $peersByRuleId] = $this->classifyConflicts($evaluation['conflicts']);
        $rulesById = $this->indexById($rules);

        $records = [];
        $escalations = [];
        $contributingResults = [];

        foreach ($evaluation['results'] as $ruleId => $result) {
            $rule = $rulesById[$ruleId];
            $mappedResult = $this->mapResult($result['result']);
            $peers = $peersByRuleId[$ruleId] ?? [];
            $inConflict = in_array($ruleId, $duplicateIds, true) || $peers !== [];
            $requiredAction = $mappedResult === 'Passed' ? 'None' : 'Revise';
            $overridden = false;

            if (in_array($ruleId, $duplicateIds, true)) {
                $requiredAction = 'Escalate';
                $escalations[] = ['rule_id' => $ruleId, 'source' => $rule['source'], 'reason' => 'Duplicate rule identifier; no existing authority resolves malformed input.'];
            } elseif ($peers !== []) {
                $resolution = $this->resolveConflict($rule, $peers, $rulesById);

                if ($resolution === 'unresolved') {
                    $requiredAction = 'Escalate';
                    $escalations[] = ['rule_id' => $ruleId, 'source' => $rule['source'], 'reason' => 'Conflicting rules share the same precedence tier and mandatory status; no existing authority resolves this.'];
                } elseif ($resolution === 'overridden') {
                    $requiredAction = 'Revise';
                    $overridden = true;
                }
            }

            $reason = $ruleId === 'policy_engine_decision' && $policyReason !== null
                ? $policyReason
                : ($result['error'] ?? ($result['evidence'] !== [] ? json_encode($result['evidence'], JSON_THROW_ON_ERROR) : 'No evidence recorded.'));

            $records[] = [
                'rule_id' => $ruleId,
                'source' => $rule['source'],
                'result' => $mappedResult,
                'reason' => $reason,
                'conflict_detected' => $inConflict,
                'required_action' => $requiredAction,
            ];

            if (!$overridden && $requiredAction !== 'Escalate') {
                $contributingResults[] = $mappedResult;
            }
        }

        $outcome = $escalations !== [] ? 'Escalate' : $this->aggregateOutcome($contributingResults);

        return ['outcome' => $outcome, 'records' => $records, 'escalations' => $escalations, 'error' => null];
    }

    /**
     * @param array{policy_request_id?: string, policy_context?: array<string, mixed>, policy_category?: ?string} $options
     * @return array{0: array{id: string, source: string, mandatory: bool, condition: array<string, mixed>}, 1: bool, 2: string}
     */
    private function policyRule(array $options): array
    {
        $decision = $this->policyEngine->evaluate(
            $options['policy_request_id'] ?? ('policy_' . bin2hex(random_bytes(8))),
            $options['policy_context'],
            $options['policy_category'] ?? null
        );

        $passes = $decision['decision'] === 'allowed';

        $rule = [
            'id' => 'policy_engine_decision',
            'source' => 'security_governance',
            'mandatory' => true,
            'condition' => ['type' => 'boolean', 'field' => 'policy_engine_decision_passes', 'equals' => true],
        ];

        return [$rule, $passes, sprintf('Policy Engine decision: %s. %s', $decision['decision'], $decision['rationale'] ?? '')];
    }

    private function mapResult(string $ruleEngineResult): string
    {
        return match ($ruleEngineResult) {
            'pass' => 'Passed',
            'fail' => 'Failed',
            default => 'Warning',
        };
    }

    /**
     * RuleEngine's own detectConflicts() reports two distinct shapes:
     * `duplicate_id` (a single `rule_id`, a data-integrity problem no
     * precedence rule addresses) and `contradictory_boolean` (a
     * `rule_ids` pair that precedence can genuinely resolve). This
     * separates them and, for the latter, builds each rule's own direct
     * conflict peers rather than a single flat set -- resolving rule A
     * against rule B's tier must never be influenced by an unrelated
     * conflict between rule C and rule D in the same batch.
     *
     * @param array<int, array{type: string, rule_id?: string, rule_ids?: array<int, string>}> $conflicts
     * @return array{0: array<int, string>, 1: array<string, array<int, string>>}
     */
    private function classifyConflicts(array $conflicts): array
    {
        $duplicateIds = [];
        $peersByRuleId = [];

        foreach ($conflicts as $conflict) {
            if ($conflict['type'] === 'duplicate_id') {
                $duplicateIds[] = $conflict['rule_id'];

                continue;
            }

            foreach ($conflict['rule_ids'] as $ruleId) {
                foreach ($conflict['rule_ids'] as $peerId) {
                    if ($peerId !== $ruleId) {
                        $peersByRuleId[$ruleId][] = $peerId;
                    }
                }
            }
        }

        return [array_unique($duplicateIds), $peersByRuleId];
    }

    /**
     * @param array<int, array{id: string, source: string, mandatory?: bool}> $rules
     * @return array<string, array{id: string, source: string, mandatory?: bool}>
     */
    private function indexById(array $rules): array
    {
        $indexed = [];

        foreach ($rules as $rule) {
            $indexed[$rule['id']] = $rule;
        }

        return $indexed;
    }

    /**
     * Applies 01_RULES/README.md's precedence literally: a higher source
     * tier wins, and a mandatory rule always overrides a non-mandatory
     * one regardless of tier. Returns 'winner' if this rule outranks its
     * conflicting peers, 'overridden' if a peer outranks it, or
     * 'unresolved' if no existing precedence distinguishes them.
     *
     * @param array{id: string, source: string, mandatory?: bool} $self
     * @param array<int, string> $peerIds this rule's own direct conflict peers only
     * @param array<string, array{id: string, source: string, mandatory?: bool}> $rulesById
     */
    private function resolveConflict(array $self, array $peerIds, array $rulesById): string
    {
        $selfTier = self::PRECEDENCE_ORDER[$self['source']];
        $selfMandatory = $self['mandatory'] ?? true;

        $outranked = false;
        $distinguishable = false;

        foreach ($peerIds as $peerId) {
            $peer = $rulesById[$peerId];
            $peerTier = self::PRECEDENCE_ORDER[$peer['source']];
            $peerMandatory = $peer['mandatory'] ?? true;

            if ($peerMandatory && !$selfMandatory) {
                $distinguishable = true;
                $outranked = true;
            } elseif ($selfMandatory && !$peerMandatory) {
                $distinguishable = true;
            } elseif ($peerTier !== $selfTier) {
                $distinguishable = true;

                if ($peerTier > $selfTier) {
                    $outranked = true;
                }
            }
        }

        if (!$distinguishable) {
            return 'unresolved';
        }

        return $outranked ? 'overridden' : 'winner';
    }

    /**
     * @param array<int, string> $results
     */
    private function aggregateOutcome(array $results): string
    {
        if (in_array('Failed', $results, true)) {
            return 'Failed';
        }

        if (in_array('Warning', $results, true)) {
            return 'Warning';
        }

        return 'Passed';
    }
}
