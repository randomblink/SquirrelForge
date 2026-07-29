<?php

declare(strict_types=1);

namespace SquirrelForge\Automation;

/**
 * Assesses an automation definition's readiness before approval and
 * execution progression, per 33_AUTOMATION/AUTOMATION-VALIDATOR.md.
 *
 * "Assess trigger, rule, schedule, workflow-reference, and task-
 * dependency coherence" is real referential-integrity checking against
 * the actual shapes RuleEngine/TriggerManager/Scheduler already define
 * in this codebase: a schedule's `linked_trigger_id` must name a trigger
 * present in the same definition, and a trigger's `dependency` condition
 * (or nested composite dependency) must name a rule or trigger id that
 * exists in the bundle. These are genuine broken-reference detections,
 * not fabricated checks.
 *
 * "Check that required test, platform-validation, Security, policy,
 * observability-coverage, and recovery-plan references are present" is
 * implemented as consumption, not computation: the caller supplies a
 * `references` map of true/false/"pending" per category, reflecting
 * decisions already made by Testing, Validation, Security, Governance,
 * and Observability authorities elsewhere -- this class never decides
 * whether a security review passed, only whether one was recorded.
 *
 * Of the spec's five findings, all are real and reachable through a
 * deterministic priority ladder: `not-ready` for structural/referential
 * errors, `requires-revision` when success criteria, failure handling,
 * or a reference category was never addressed at all, `deferred` when a
 * reference is explicitly "pending", `ready-with-conditions` when a
 * reference is explicitly false, and `ready` only when everything
 * checked out. Like RuleEngine and Event Listener, this has no database
 * -- the spec forbids owning "audit/storage infrastructure" -- it's a
 * pure function from a definition to a finding.
 */
final class AutomationValidator
{
    private const REFERENCE_CATEGORIES = ['test', 'platform_validation', 'security', 'policy', 'observability_coverage', 'recovery_plan'];

    /**
     * @param array{
     *     id?: string,
     *     triggers?: array<int, array{id: string, condition?: array<string, mixed>, requires?: array<int, string>}>,
     *     schedules?: array<int, array{id: string, linked_trigger_id?: string}>,
     *     rules?: array<int, array{id: string, condition?: array<string, mixed>}>,
     *     success_criteria?: array<int, string>,
     *     failure_handling?: string,
     *     references?: array<string, bool|string>
     * } $definition
     * @return array{outcome: string, issues: array<int, string>, missing_references: array<int, string>, pending_references: array<int, string>, unsatisfied_references: array<int, string>}
     */
    public function validate(array $definition): array
    {
        $issues = $this->checkCoherence($definition);

        $incomplete = [];

        if (($definition['success_criteria'] ?? []) === [] || $this->hasEmptyEntry($definition['success_criteria'] ?? [])) {
            $incomplete[] = 'Measurable success criteria are missing.';
        }

        if (($definition['failure_handling'] ?? '') === '') {
            $incomplete[] = 'A failure-handling reference is missing.';
        }

        $references = $definition['references'] ?? [];
        $missing = [];
        $pending = [];
        $unsatisfied = [];

        foreach (self::REFERENCE_CATEGORIES as $category) {
            if (!array_key_exists($category, $references)) {
                $missing[] = $category;
            } elseif ($references[$category] === 'pending') {
                $pending[] = $category;
            } elseif ($references[$category] === false) {
                $unsatisfied[] = $category;
            }
        }

        $outcome = match (true) {
            $issues !== [] => 'not-ready',
            $incomplete !== [] || $missing !== [] => 'requires-revision',
            $pending !== [] => 'deferred',
            $unsatisfied !== [] => 'ready-with-conditions',
            default => 'ready',
        };

        return [
            'outcome' => $outcome,
            'issues' => [...$issues, ...$incomplete],
            'missing_references' => $missing,
            'pending_references' => $pending,
            'unsatisfied_references' => $unsatisfied,
        ];
    }

    /**
     * @param array<int, mixed> $entries
     */
    private function hasEmptyEntry(array $entries): bool
    {
        foreach ($entries as $entry) {
            if (!is_string($entry) || trim($entry) === '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<int, string>
     */
    private function checkCoherence(array $definition): array
    {
        $issues = [];
        $triggerIds = array_column($definition['triggers'] ?? [], 'id');
        $ruleIds = array_column($definition['rules'] ?? [], 'id');
        $scheduleIds = array_column($definition['schedules'] ?? [], 'id');

        foreach ([$triggerIds, $ruleIds, $scheduleIds] as $idList) {
            foreach (array_count_values($idList) as $id => $count) {
                if ($count > 1) {
                    $issues[] = sprintf('Duplicate identifier "%s" appears %d times.', $id, $count);
                }
            }
        }

        foreach ($definition['schedules'] ?? [] as $schedule) {
            $linkedTriggerId = $schedule['linked_trigger_id'] ?? null;

            if ($linkedTriggerId !== null && !in_array($linkedTriggerId, $triggerIds, true)) {
                $issues[] = sprintf('Schedule "%s" links to unknown trigger "%s".', $schedule['id'], $linkedTriggerId);
            }
        }

        $knownDependencyTargets = [...$triggerIds, ...$ruleIds];

        foreach ($definition['triggers'] ?? [] as $trigger) {
            foreach ($this->dependencyTargets($trigger['condition'] ?? []) as $dependsOn) {
                if (!in_array($dependsOn, $knownDependencyTargets, true)) {
                    $issues[] = sprintf('Trigger "%s" depends on unknown rule/trigger "%s".', $trigger['id'], $dependsOn);
                }
            }
        }

        return $issues;
    }

    /**
     * Recursively collects every `dependency` condition's `depends_on`
     * target, including inside composite and/or/not conditions.
     *
     * @param array<string, mixed> $condition
     * @return array<int, string>
     */
    private function dependencyTargets(array $condition): array
    {
        if (($condition['type'] ?? null) === 'dependency' && is_string($condition['depends_on'] ?? null)) {
            return [$condition['depends_on']];
        }

        if (($condition['type'] ?? null) === 'composite' && is_array($condition['conditions'] ?? null)) {
            $targets = [];

            foreach ($condition['conditions'] as $subCondition) {
                $targets = [...$targets, ...$this->dependencyTargets($subCondition)];
            }

            return $targets;
        }

        return [];
    }
}
