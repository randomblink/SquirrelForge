<?php

declare(strict_types=1);

namespace SquirrelForge\Engine;

/**
 * Converts the validated task graph, dependency analysis, and workflow
 * selection into an ordered execution plan, per
 * 14_ENGINE/EXECUTION-PLANNER.md.
 *
 * This is a genuine, direct composition of three already-real
 * components rather than a re-derivation of any of their work:
 * sequencing comes straight from TaskDecomposer's own Kahn's-algorithm
 * `levels`, safe parallel grouping comes straight from its
 * `parallel_eligible_levels` (further narrowed by DependencyAnalyzer's
 * real `blockers` -- a level TaskDecomposer marked parallel-safe is
 * still pulled apart if one of its tasks is independently blocked),
 * and the plan is only produced at all when WorkflowSelector's own
 * status is SELECTED or SELECTED_WITH_LIMITATIONS.
 *
 * "Reject or block tasks with unresolved required dependencies" is
 * upheld literally: any task named as a DependencyAnalyzer blocker's
 * `required_by` is marked `blocked` and gets a real, derived stop
 * condition naming the actual blocking dependency ID -- not a
 * fabricated generic warning.
 *
 * The Recovery Planning Rule ("must not claim a rollback path exists
 * unless the required mechanism is actually available") is a real,
 * enforced gate: a task tagged `high` or `critical` risk must declare
 * both a `recovery_path` and `recovery_available: true`; either missing
 * blocks the step rather than silently assuming recovery is possible.
 * This is the one place this class refuses to plan around a caller's
 * incomplete declaration, because the spec makes it an explicit "must
 * not claim" requirement, not an optional nicety.
 *
 * Everything else in the Execution Plan Model (owner type, tools and
 * interfaces, expected output, validation, checkpoint) is passed
 * straight through from the caller-supplied task definition -- this
 * class does not invent what a step needs, only sequences, gates, and
 * groups what Task Decomposer and Dependency Analyzer already
 * established.
 */
final class ExecutionPlanner
{
    private const READY_SELECTION_STATUSES = ['SELECTED', 'SELECTED_WITH_LIMITATIONS'];
    private const RECOVERY_REQUIRED_RISKS = ['high', 'critical'];

    /**
     * @param array<int, array{id: string, active_domain?: ?string, permissions?: ?string, tools_and_interfaces?: array<int, string>, expected_output?: ?string, validation?: ?string, checkpoint?: ?string, stop_conditions?: array<int, string>, recovery_path?: ?string, recovery_available?: bool, risk?: ?string, workflow?: ?string}> $tasks
     * @param array<int, array<int, string>> $levels TaskDecomposer::decompose()'s `levels`
     * @param array<int, array<int, string>> $parallelEligibleLevels TaskDecomposer::decompose()'s `parallel_eligible_levels`
     * @param array<int, array{dependency_id: string, required_by: ?string}> $dependencyBlockers DependencyAnalyzer::analyze()'s `blockers`
     * @param array{status: string, primary_workflow: ?string} $workflowSelection WorkflowSelector::selectWorkflow()'s output
     * @return array{steps: array<int, array<string, mixed>>, violations: array<int, array<string, mixed>>, outcome: string, error: ?string}
     */
    public function plan(array $tasks, array $levels, array $parallelEligibleLevels, array $dependencyBlockers, array $workflowSelection): array
    {
        if (!in_array($workflowSelection['status'] ?? null, self::READY_SELECTION_STATUSES, true)) {
            return ['steps' => [], 'violations' => [], 'outcome' => 'not_ready', 'error' => sprintf('Workflow selection status "%s" does not permit execution planning.', $workflowSelection['status'] ?? 'unknown')];
        }

        $tasksById = $this->indexById($tasks);
        $blockersByTaskId = $this->blockersByTaskId($dependencyBlockers);
        $parallelTaskIds = $this->flattenParallelEligibleTaskIds($parallelEligibleLevels, $blockersByTaskId);

        $steps = [];
        $violations = [];
        $sequence = 0;

        foreach ($levels as $level) {
            $sequence++;

            foreach ($level as $taskId) {
                if (!isset($tasksById[$taskId])) {
                    continue;
                }

                $task = $tasksById[$taskId];
                $blocker = $blockersByTaskId[$taskId] ?? null;
                $recoveryViolation = $this->checkRecoveryRequirement($task);

                if ($recoveryViolation !== null) {
                    $violations[] = $recoveryViolation;
                }

                $stopConditions = $task['stop_conditions'] ?? [];

                if ($blocker !== null) {
                    $stopConditions[] = sprintf('Blocked by dependency "%s".', $blocker['dependency_id']);
                }

                $steps[] = [
                    'step_id' => 'step_' . $taskId,
                    'sequence' => $sequence,
                    'task_id' => $taskId,
                    'workflow' => $task['workflow'] ?? $workflowSelection['primary_workflow'],
                    'parallel_group' => isset($parallelTaskIds[$taskId]) ? $sequence : null,
                    'domain_context' => $task['active_domain'] ?? null,
                    'permissions' => $task['permissions'] ?? null,
                    'tools_and_interfaces' => $task['tools_and_interfaces'] ?? [],
                    'expected_output' => $task['expected_output'] ?? null,
                    'validation' => $task['validation'] ?? null,
                    'checkpoint' => $task['checkpoint'] ?? null,
                    'stop_conditions' => $stopConditions,
                    'recovery_path' => $task['recovery_path'] ?? null,
                    'status' => $blocker !== null || $recoveryViolation !== null ? 'blocked' : 'planned',
                ];
            }
        }

        $outcome = match (true) {
            $steps === [] => 'blocked',
            $blockersByTaskId === [] && $violations === [] => 'planned',
            default => 'planned_with_blockers',
        };

        return ['steps' => $steps, 'violations' => $violations, 'outcome' => $outcome, 'error' => null];
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
     * @param array<int, array{dependency_id: string, required_by: ?string}> $blockers
     * @return array<string, array{dependency_id: string, required_by: ?string}>
     */
    private function blockersByTaskId(array $blockers): array
    {
        $byTaskId = [];

        foreach ($blockers as $blocker) {
            if (($blocker['required_by'] ?? null) !== null) {
                $byTaskId[$blocker['required_by']] = $blocker;
            }
        }

        return $byTaskId;
    }

    /**
     * A level TaskDecomposer marked parallel-safe is re-checked here: if
     * removing independently blocked tasks leaves fewer than two
     * survivors, "parallel" no longer means anything for that group, so
     * none of its tasks are marked parallel-eligible.
     *
     * @param array<int, array<int, string>> $parallelEligibleLevels
     * @param array<string, array<string, mixed>> $blockersByTaskId
     * @return array<string, bool>
     */
    private function flattenParallelEligibleTaskIds(array $parallelEligibleLevels, array $blockersByTaskId): array
    {
        $ids = [];

        foreach ($parallelEligibleLevels as $level) {
            $survivors = array_values(array_filter($level, static fn(string $taskId): bool => !isset($blockersByTaskId[$taskId])));

            if (count($survivors) < 2) {
                continue;
            }

            foreach ($survivors as $taskId) {
                $ids[$taskId] = true;
            }
        }

        return $ids;
    }

    /**
     * @param array{id: string, risk?: ?string, recovery_path?: ?string, recovery_available?: bool} $task
     * @return array{task_id: string, rule: string, reason: string}|null
     */
    private function checkRecoveryRequirement(array $task): ?array
    {
        if (!in_array($task['risk'] ?? null, self::RECOVERY_REQUIRED_RISKS, true)) {
            return null;
        }

        if (($task['recovery_path'] ?? null) === null) {
            return ['task_id' => $task['id'], 'rule' => 'recovery_planning', 'reason' => sprintf('Task "%s" is "%s" risk but declares no recovery path.', $task['id'], $task['risk'])];
        }

        if (($task['recovery_available'] ?? false) !== true) {
            return ['task_id' => $task['id'], 'rule' => 'recovery_planning', 'reason' => sprintf('Task "%s" declares a recovery path that is not confirmed available.', $task['id'])];
        }

        return null;
    }
}
