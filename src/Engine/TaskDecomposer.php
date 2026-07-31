<?php

declare(strict_types=1);

namespace SquirrelForge\Engine;

/**
 * Converts a structured goal into bounded, ordered, independently
 * understandable tasks, per 14_ENGINE/TASK-DECOMPOSER.md.
 *
 * "Convert the primary goal into concrete task units" is inherently a
 * semantic judgment -- deciding what the actual sub-steps of a goal are
 * requires understanding the goal's meaning, which no deterministic
 * class can honestly do without fabricating an NLP model. Task
 * definitions are therefore accepted as caller-supplied input, the same
 * boundary GoalPlanner already draws around primary_goal and expected_
 * output. What this class actually computes is real, structural
 * validation of that caller-supplied task graph:
 *
 * Graph integrity (duplicate task IDs, unknown dependency references,
 * circular dependency) reuses the same Kahn's-algorithm leveling and
 * completeness check WorkflowOptimizer and TaskOrchestrator already
 * implement -- a third, deliberate re-implementation of a small,
 * well-understood algorithm rather than a premature shared abstraction,
 * consistent with this codebase's established precedent.
 *
 * The Task Size Rule is checked structurally, not inferred: a task
 * missing a description, expected_output, or completion_condition is a
 * real violation ("one clear outcome... a verifiable output... a
 * defined completion condition"), never a subjective judgment about
 * whether a task is "too small."
 *
 * The Completion Boundary Rule ("must not pre-claim successful
 * execution or validation") is a real, literal check: any task
 * declaring a `completed` or `validated` status at decomposition time
 * is flagged outright.
 *
 * The Domain Rule is upheld the same way GoalPlanner upholds it: a
 * task's declared active_domain is only valid when it appears in the
 * goal's own real active_domains (GoalPlanner::planGoal()'s output,
 * itself sourced from ProjectLoader's real file-presence evidence) --
 * never assumed because the repository merely supports that domain.
 *
 * The Parallel Work Rule's own explicit warning ("must not be inferred
 * solely because tasks appear different") is honored literally:
 * sharing a dependency-graph level is necessary but not sufficient for
 * parallel eligibility here -- every task at that level must also
 * declare `mutates_shared_state: false` and `ownership_defined: true`,
 * real caller-supplied signals for the two conditions this class cannot
 * infer from graph shape alone.
 *
 * Like GoalPlanner, this class has no database: a decomposition result
 * is a pure return value for the caller (eventually Dependency Analyzer
 * / Execution Planner) to act on.
 */
final class TaskDecomposer
{
    private const REQUIRED_TASK_FIELDS = ['description', 'expected_output', 'completion_condition'];
    private const PRE_CLAIMED_STATUSES = ['completed', 'validated'];

    /**
     * @param array{acceptance_criteria?: array<int, string>, active_domains?: array<int, string>} $goal GoalPlanner::planGoal()'s `goal` output
     * @param array<int, array{id: string, description?: string, required?: bool, inputs?: array<int, string>, expected_output?: string, dependencies?: array<int, string>, active_domain?: ?string, status?: string, mutates_shared_state?: bool, ownership_defined?: bool}> $tasks caller-supplied task definitions
     * @return array{tasks: array<int, array<string, mixed>>, levels: array<int, array<int, string>>|null, parallel_eligible_levels: array<int, array<int, string>>, violations: array<int, array{task_id: string, rule: string, reason: string}>, outcome: string, error: ?string}
     */
    public function decompose(array $goal, array $tasks): array
    {
        if ($tasks === []) {
            return ['tasks' => [], 'levels' => null, 'parallel_eligible_levels' => [], 'violations' => [], 'outcome' => 'no_tasks', 'error' => 'At least one task is required.'];
        }

        $structuralError = $this->checkGraphIntegrity($tasks);

        if ($structuralError !== null) {
            return ['tasks' => $tasks, 'levels' => null, 'parallel_eligible_levels' => [], 'violations' => [], 'outcome' => 'invalid_graph', 'error' => $structuralError];
        }

        $levels = $this->computeLevels($tasks);

        if ($levels === null) {
            return ['tasks' => $tasks, 'levels' => null, 'parallel_eligible_levels' => [], 'violations' => [], 'outcome' => 'invalid_graph', 'error' => 'Task dependency graph contains a cycle.'];
        }

        $activeDomains = $goal['active_domains'] ?? [];
        $violations = [];

        foreach ($tasks as $task) {
            $violations = [...$violations, ...$this->checkTaskModel($task, $activeDomains)];
        }

        $tasksById = $this->indexById($tasks);
        $parallelEligibleLevels = [];

        foreach ($levels as $level) {
            if (count($level) > 1 && $this->allSafeForParallelWork($level, $tasksById)) {
                $parallelEligibleLevels[] = $level;
            }
        }

        return [
            'tasks' => $tasks,
            'levels' => $levels,
            'parallel_eligible_levels' => $parallelEligibleLevels,
            'violations' => $violations,
            'outcome' => $violations === [] ? 'decomposed' : 'requires_revision',
            'error' => null,
        ];
    }

    /**
     * @param array<int, array{id: string, dependencies?: array<int, string>}> $tasks
     */
    private function checkGraphIntegrity(array $tasks): ?string
    {
        $ids = array_column($tasks, 'id');

        foreach (array_count_values($ids) as $id => $count) {
            if ($count > 1) {
                return sprintf('Duplicate task identifier "%s".', $id);
            }
        }

        foreach ($tasks as $task) {
            foreach ($task['dependencies'] ?? [] as $dependency) {
                if (!in_array($dependency, $ids, true)) {
                    return sprintf('Task "%s" depends on unknown task "%s".', $task['id'], $dependency);
                }
            }
        }

        return null;
    }

    /**
     * Kahn's algorithm, matching TaskOrchestrator's and
     * WorkflowOptimizer's own real leveling.
     *
     * @param array<int, array{id: string, dependencies?: array<int, string>}> $tasks
     * @return array<int, array<int, string>>|null
     */
    private function computeLevels(array $tasks): ?array
    {
        $remainingDependencies = [];

        foreach ($tasks as $task) {
            $remainingDependencies[$task['id']] = $task['dependencies'] ?? [];
        }

        $levels = [];

        while ($remainingDependencies !== []) {
            $ready = array_keys(array_filter($remainingDependencies, static fn(array $deps): bool => $deps === []));

            if ($ready === []) {
                return null;
            }

            $levels[] = $ready;

            foreach ($ready as $id) {
                unset($remainingDependencies[$id]);
            }

            foreach ($remainingDependencies as $id => $deps) {
                $remainingDependencies[$id] = array_values(array_diff($deps, $ready));
            }
        }

        return $levels;
    }

    /**
     * @param array{id: string, description?: string, expected_output?: string, completion_condition?: string, active_domain?: ?string, status?: string} $task
     * @param array<int, string> $activeDomains
     * @return array<int, array{task_id: string, rule: string, reason: string}>
     */
    private function checkTaskModel(array $task, array $activeDomains): array
    {
        $violations = [];
        $id = $task['id'];

        foreach (self::REQUIRED_TASK_FIELDS as $field) {
            if (($task[$field] ?? '') === '') {
                $violations[] = ['task_id' => $id, 'rule' => 'task_size', 'reason' => sprintf('Task "%s" is missing a "%s".', $id, $field)];
            }
        }

        if (in_array($task['status'] ?? null, self::PRE_CLAIMED_STATUSES, true)) {
            $violations[] = ['task_id' => $id, 'rule' => 'completion_boundary', 'reason' => sprintf('Task "%s" must not pre-claim a "%s" status at decomposition time.', $id, $task['status'])];
        }

        if (($task['active_domain'] ?? null) !== null && !in_array($task['active_domain'], $activeDomains, true)) {
            $violations[] = ['task_id' => $id, 'rule' => 'domain', 'reason' => sprintf('Task "%s" declares active domain "%s", which is not among the goal\'s active domains.', $id, $task['active_domain'])];
        }

        return $violations;
    }

    /**
     * @param array<int, array{id: string}> $tasks
     * @return array<string, array<string, mixed>>
     */
    private function indexById(array $tasks): array
    {
        $indexed = [];

        foreach ($tasks as $task) {
            $indexed[$task['id']] = $task;
        }

        return $indexed;
    }

    /**
     * @param array<int, string> $level
     * @param array<string, array{mutates_shared_state?: bool, ownership_defined?: bool}> $tasksById
     */
    private function allSafeForParallelWork(array $level, array $tasksById): bool
    {
        foreach ($level as $taskId) {
            $task = $tasksById[$taskId];

            if (($task['mutates_shared_state'] ?? true) !== false || ($task['ownership_defined'] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }
}
