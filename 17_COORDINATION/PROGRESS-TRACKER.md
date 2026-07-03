# SquirrelForge Progress Tracker

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Progress Tracker monitors the real-time status of an active execution plan, providing visibility into task completion and overall progress.

## Responsibilities

- Receive the final `Execution Plan` from the `EXECUTION-PLANNER`.
- Update the status of each task as it moves through the `AGENT-LOOP` (Pending → Running → Complete).
- Log the output or result of each completed task.
- Record any errors or blockages encountered during execution.
- Calculate and maintain the overall completion percentage for the plan.

## Tracking Process

1.  **Initialize:** Load the `Execution Plan`.
2.  **Update on Execution:** As the `AGENT-LOOP` begins a task, update its status to `Running`.
3.  **Update on Validation:** After a task is validated, update its status to `Complete` or `Failed`.
4.  **Log Results:** Store the output of each task for the final report.
5.  **Report Progress:** Provide status updates to the user at key intervals or upon request.

## Progress Model

| Field | Description |
|---|---|
| Total Tasks | The total number of tasks in the execution plan. |
| Completed Tasks | The number of tasks with a `Complete` status. |
| Pending Tasks | The number of tasks with a `Pending` status. |
| Blocked Task | The ID of any task with a `Blocked` status, if applicable. |
| Completion % | The percentage of tasks that are complete. |

## Rule

The progress tracker must be updated after every task status change to provide an accurate, real-time view of the agent's work.