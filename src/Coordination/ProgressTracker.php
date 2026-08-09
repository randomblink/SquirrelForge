<?php

declare(strict_types=1);

namespace SquirrelForge\Coordination;

/**
 * Aggregates and reports the real-time completion status of an active
 * execution plan across in-flight and parallel tasks, per
 * 17_COORDINATION/PROGRESS-TRACKER.md -- the second real component in
 * 17_COORDINATION.
 *
 * "The Tracker reads and rolls up task status; it does not own or
 * redefine it. Each task's actual status is recorded and owned by
 * `14_ENGINE/STATE-MANAGER.md`" (Purpose) is the whole shape of this
 * class: `STATE-MANAGER.md` has no code, so `$taskStatuses` arrives as
 * caller-supplied evidence (task_id => status), the same "reference,
 * don't recompute" boundary this codebase applies to every uncoded
 * authority. A task absent from `$taskStatuses` is treated as
 * `NOT_STARTED` -- the honest "no status has been recorded yet"
 * default the Progress Model's own state list already names, never a
 * fabricated `COMPLETED` or `BLOCKED` claim.
 *
 * The Progress Model's own status table is a real, closed nine-value
 * set (`NOT_STARTED`/`READY`/`ROUTED`/`IN_PROGRESS`/`WAITING`/
 * `VALIDATION_PENDING`/`COMPLETED`/`BLOCKED`/`VALIDATION_FAILED`) --
 * `aggregate()` validates every supplied status against it rather than
 * trusting an arbitrary string; an unrecognized value is reported back
 * in its own `unrecognized_statuses` list (visible, not silently
 * dropped) and counted as pending rather than fabricating a specific
 * bucket for a status this class cannot actually verify.
 *
 * This class owns no database and re-derives every number from
 * scratch on each call -- "recompute completion percentage... from
 * current State Manager status" (Tracking Process step 3) reads as a
 * pure recomputation, not an incrementally maintained cache that could
 * drift from what the State Manager actually holds. "Record a
 * reference to each completed task's output for the final report" is
 * satisfied by reading the output reference straight out of the
 * caller-supplied status entry for a `COMPLETED` task, never inventing
 * or fetching one itself (the actual artifact remains
 * `37_STORAGE`/`RESULT-COLLECTOR.md`'s concern).
 */
final class ProgressTracker
{
    private const COMPLETED_STATE = 'COMPLETED';

    private const BLOCKED_STATES = ['BLOCKED', 'VALIDATION_FAILED'];

    private const PENDING_STATES = ['NOT_STARTED', 'READY', 'ROUTED', 'IN_PROGRESS', 'WAITING', 'VALIDATION_PENDING'];

    private const ALL_STATES = [...self::PENDING_STATES, self::COMPLETED_STATE, ...self::BLOCKED_STATES];

    /**
     * Tracking Process steps 2-4: reads current status for every task
     * in the plan and aggregates it into the Progress Model.
     *
     * @param array<int, array{task_id?: ?string, step_id?: ?string}> $executionPlan ExecutionPlanner::plan()'s own `steps`.
     * @param array<string, array{status?: ?string, blocker_reason?: ?string, output_ref?: ?string}> $taskStatuses task_id => State Manager's own recorded status. A task absent here is treated as NOT_STARTED.
     * @return array{
     *     total_tasks: int,
     *     completed_tasks: int,
     *     pending_tasks: int,
     *     blocked_tasks: array<int, array{task_id: string, status: string, reason: ?string}>,
     *     completion_percentage: float,
     *     completed_task_outputs: array<string, string>,
     *     unrecognized_statuses: array<int, array{task_id: string, status: string}>
     * }
     */
    public function aggregate(array $executionPlan, array $taskStatuses = []): array
    {
        $completed = 0;
        $pending = 0;
        $blocked = [];
        $completedOutputs = [];
        $unrecognized = [];
        $total = 0;

        foreach ($executionPlan as $step) {
            $taskId = $step['task_id'] ?? $step['step_id'] ?? null;

            if (!is_string($taskId) || $taskId === '') {
                continue;
            }

            $total++;
            $entry = $taskStatuses[$taskId] ?? [];
            $status = $entry['status'] ?? 'NOT_STARTED';

            if (!in_array($status, self::ALL_STATES, true)) {
                $unrecognized[] = ['task_id' => $taskId, 'status' => (string) $status];
                $pending++;

                continue;
            }

            if ($status === self::COMPLETED_STATE) {
                $completed++;

                if (isset($entry['output_ref'])) {
                    $completedOutputs[$taskId] = $entry['output_ref'];
                }
            } elseif (in_array($status, self::BLOCKED_STATES, true)) {
                $blocked[] = ['task_id' => $taskId, 'status' => $status, 'reason' => $entry['blocker_reason'] ?? null];
            } else {
                $pending++;
            }
        }

        return [
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'pending_tasks' => $pending,
            'blocked_tasks' => $blocked,
            'completion_percentage' => $total > 0 ? round(($completed / $total) * 100, 2) : 0.0,
            'completed_task_outputs' => $completedOutputs,
            'unrecognized_statuses' => $unrecognized,
        ];
    }
}
