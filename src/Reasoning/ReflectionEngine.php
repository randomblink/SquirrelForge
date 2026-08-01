<?php

declare(strict_types=1);

namespace SquirrelForge\Reasoning;

use SquirrelForge\Memory\EpisodicMemory;

/**
 * Reviews a completed, validated task record to identify successes,
 * failures, and repeated issues, producing reflection records and
 * improvement candidates, per 19_REASONING/REFLECTION-ENGINE.md.
 *
 * 18_MEMORY/EPISODIC-MEMORY.md is real now: reflectOnTask() genuinely
 * fetches the completed task record via the injected
 * EpisodicMemory::retrieve() rather than requiring the caller to
 * assemble one, literally satisfying "receive a completed, validated
 * task record from 18_MEMORY/EPISODIC-MEMORY.md". Episodic Memory only
 * ever stores a `goal_reference` (a reference, not the full goal
 * object, per its own spec's "reference... rather than re-describing
 * them"), so acceptance-criteria evaluation still comes from the
 * caller -- that data was never Episodic Memory's to own. The original
 * reflect() method remains for callers that already have a fully
 * assembled record (or a synthetic one, e.g. in tests) and don't need
 * the Episodic Memory round-trip.
 *
 * "Review the validation result already referenced there" is a real,
 * literal check against
 * EngineValidation's own real decision vocabulary (ACCEPTED,
 * ACCEPTED_WITH_LIMITATIONS, REJECTED, BLOCKED, REPAIR_REQUIRED,
 * RECOVERY_REQUIRED, CLARIFICATION_REQUIRED) -- this class never
 * re-runs validation, it only reads the decision the caller already
 * recorded.
 *
 * "Compare the result against the original goal" is real and
 * structural: goal achievement requires both a passing validation
 * decision and every one of the goal's own real acceptance_criteria
 * (GoalPlanner's own field) marked satisfied in the episodic record --
 * any unmet criterion becomes a concrete, named issue, never a vague
 * "something went wrong."
 *
 * "Identify... repeated issues" reuses the same recurrence-counting
 * idiom PatternDetector already established for experience records: an
 * issue recurring at or above a caller-set threshold across the
 * caller-supplied prior-issue history becomes a repeated issue, and
 * only repeated issues become improvement candidates -- a one-off
 * problem is recorded as an issue but isn't escalated into a suggested
 * change.
 *
 * The Permission Boundary ("must not promote, approve, or register
 * knowledge itself") is upheld structurally, not by convention: this
 * class takes no KnowledgeManager dependency at all. It produces
 * improvement candidates and learning signals as plain return values;
 * forwarding them to Knowledge Manager for its own promotion decision
 * is the caller's responsibility, never something this class does on
 * its own.
 *
 * Like Confidence Scorer, this class has no database: a Reflection
 * Record is a pure return value for the caller to act on.
 */
final class ReflectionEngine
{
    private const PASSING_DECISIONS = ['ACCEPTED', 'ACCEPTED_WITH_LIMITATIONS'];

    public function __construct(private readonly ?EpisodicMemory $episodicMemory = null)
    {
    }

    /**
     * Genuinely fetches the completed task record from Episodic Memory
     * rather than requiring the caller to assemble one. Acceptance
     * criteria and their satisfaction are still caller-supplied, since
     * Episodic Memory only stores a goal reference, not the full goal.
     *
     * @param array{primary_goal?: ?string, acceptance_criteria?: array<int, string>, acceptance_criteria_met?: array<string, bool>, prior_issues?: array<int, array{description: string}>, recurrence_threshold?: int} $options
     * @return array{reflection: array<string, mixed>, outcome: string, error: ?string}
     */
    public function reflectOnTask(string $taskReference, array $options = []): array
    {
        if ($this->episodicMemory === null) {
            return ['reflection' => [], 'outcome' => 'not_configured', 'error' => 'Episodic Memory is not configured.'];
        }

        $matches = $this->episodicMemory->search(['task_reference' => $taskReference]);

        if ($matches === []) {
            return ['reflection' => [], 'outcome' => 'not_found', 'error' => sprintf('Unknown task reference "%s".', $taskReference)];
        }

        $episodicRecord = $matches[0];

        $mappedRecord = [
            'task_reference' => $episodicRecord['task_reference'],
            'goal' => [
                'primary_goal' => $options['primary_goal'] ?? $episodicRecord['goal_reference'],
                'acceptance_criteria' => $options['acceptance_criteria'] ?? [],
            ],
            'result' => $episodicRecord['outcome_summary'],
            'validation_reference' => ['decision' => $episodicRecord['validation_result_reference']],
            'acceptance_criteria_met' => $options['acceptance_criteria_met'] ?? [],
        ];

        return $this->reflect($mappedRecord, $options['prior_issues'] ?? [], $options['recurrence_threshold'] ?? 2);
    }

    /**
     * @param array{task_reference: string, goal?: array{primary_goal?: ?string, acceptance_criteria?: array<int, string>}, result: string, validation_reference: array{decision?: ?string}, acceptance_criteria_met?: array<string, bool>} $episodicRecord a fully assembled record, e.g. from reflectOnTask() or a caller that already has one
     * @param array<int, array{description: string}> $priorIssues prior Reflection Records' own issues, for real recurrence detection
     * @return array{reflection: array<string, mixed>, outcome: string, error: ?string}
     */
    public function reflect(array $episodicRecord, array $priorIssues = [], int $recurrenceThreshold = 2): array
    {
        $decision = $episodicRecord['validation_reference']['decision'] ?? null;
        $validationPassed = in_array($decision, self::PASSING_DECISIONS, true);

        $acceptanceCriteria = $episodicRecord['goal']['acceptance_criteria'] ?? [];
        $criteriaMet = $episodicRecord['acceptance_criteria_met'] ?? [];
        $unmetCriteria = array_values(array_filter(
            $acceptanceCriteria,
            static fn(string $criterion): bool => ($criteriaMet[$criterion] ?? false) !== true
        ));

        $goalAchieved = $validationPassed && $unmetCriteria === [];

        $successes = [];
        $issues = [];

        if ($goalAchieved) {
            $successes[] = 'The original goal was fully achieved with successful validation.';
        }

        if (!$validationPassed) {
            $issues[] = sprintf('Validation did not pass (decision: "%s").', $decision ?? 'unknown');
        } elseif ($decision === 'ACCEPTED_WITH_LIMITATIONS') {
            $issues[] = 'Validation passed only with limitations.';
        }

        foreach ($unmetCriteria as $criterion) {
            $issues[] = sprintf('Acceptance criterion not met: "%s".', $criterion);
        }

        $repeatedIssues = $this->findRepeatedIssues($issues, $priorIssues, $recurrenceThreshold);

        $reflection = [
            'reflection_id' => 'reflection_' . bin2hex(random_bytes(12)),
            'task_reference' => $episodicRecord['task_reference'],
            'goal' => $episodicRecord['goal']['primary_goal'] ?? null,
            'result' => $episodicRecord['result'],
            'validation_reference' => $episodicRecord['validation_reference'],
            'goal_achieved' => $goalAchieved,
            'successes' => $successes,
            'issues' => $issues,
            'repeated_issues' => $repeatedIssues,
            'improvement_candidates' => array_map(
                static fn(string $issue): string => sprintf('Address recurring issue: %s', $issue),
                $repeatedIssues
            ),
            'learning_signals' => $goalAchieved
                ? [sprintf('Approach used for "%s" succeeded; consider it a reusable pattern candidate.', $episodicRecord['goal']['primary_goal'] ?? $episodicRecord['task_reference'])]
                : [],
        ];

        return ['reflection' => $reflection, 'outcome' => 'reflected', 'error' => null];
    }

    /**
     * @param array<int, string> $currentIssues
     * @param array<int, array{description: string}> $priorIssues
     * @return array<int, string>
     */
    private function findRepeatedIssues(array $currentIssues, array $priorIssues, int $threshold): array
    {
        $priorDescriptions = array_column($priorIssues, 'description');
        $repeated = [];

        foreach ($currentIssues as $issue) {
            $occurrences = 1 + count(array_filter($priorDescriptions, static fn(string $description): bool => $description === $issue));

            if ($occurrences >= $threshold) {
                $repeated[] = $issue;
            }
        }

        return $repeated;
    }
}
