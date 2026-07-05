# SquirrelForge Progress Tracker

Version: 1.0.0
Status: Stable
Owner: Coordination Maintainers
Depends On: `14_ENGINE/EXECUTION-PLANNER.md`, `14_ENGINE/STATE-MANAGER.md`
Used By: Coordination, Reporting
Last Updated: 2026-07-05

## Purpose

The Progress Tracker aggregates and reports the real-time completion status of an active execution plan across in-flight and parallel tasks.

The Tracker reads and rolls up task status; it does not own or redefine it. Each task's actual status is recorded and owned by `14_ENGINE/STATE-MANAGER.md`. The Tracker consumes that status to compute completion percentage, surface active blockers, and answer progress requests — it does not maintain a separate, competing status vocabulary of its own.

---

## Responsibilities

The Progress Tracker must:

- receive the execution plan from `14_ENGINE/EXECUTION-PLANNER.md`,
- read each task's current status from `14_ENGINE/STATE-MANAGER.md` rather than maintaining an independent status,
- aggregate task status into an overall completion percentage,
- surface active blockers and their responsible phase as recorded by the State Manager,
- record a reference to each completed task's output for the final report,
- and report progress at key intervals or upon request.

---

## Inputs

The Tracker should receive:

- the execution plan from `14_ENGINE/EXECUTION-PLANNER.md`,
- and current task status, blocker, and validation state from `14_ENGINE/STATE-MANAGER.md`.

A task's status must not be inferred or independently tracked when the State Manager has already recorded it.

---

## Outputs

The Tracker should produce:

- an overall completion percentage,
- a current list of blocked tasks and their reasons,
- recorded references to completed task output,
- and a progress report on request.

---

## Tracking Process

1. **Initialize:** receive the execution plan from the Execution Planner.
2. **Read:** read each task's current status from the State Manager as work proceeds.
3. **Aggregate:** recompute completion percentage and the blocked-task list from current State Manager status.
4. **Log:** record a reference to each completed task's output for the final report.
5. **Report:** provide status updates at key intervals or upon request.

---

## Progress Model

| Field | Description |
|---|---|
| Total Tasks | The total number of tasks in the execution plan. |
| Completed Tasks | Count of tasks in the State Manager's `COMPLETED` state. |
| Pending Tasks | Count of tasks in any non-terminal, non-blocked State Manager state (`NOT_STARTED`, `READY`, `ROUTED`, `IN_PROGRESS`, `WAITING`, `VALIDATION_PENDING`). |
| Blocked Tasks | Tasks in the State Manager's `BLOCKED` or `VALIDATION_FAILED` state, with the recorded blocker reason. |
| Completion % | Completed Tasks ÷ Total Tasks. |

---

## Permission Boundary

The Progress Tracker may read task status, aggregate it, and report it.

It must not set or change a task's status itself (owned by `14_ENGINE/STATE-MANAGER.md`), decide execution order (owned by `14_ENGINE/EXECUTION-PLANNER.md`, `17_COORDINATION/TASK-QUEUE.md`, and `17_COORDINATION/PRIORITY-MANAGER.md`), or resolve a blocker itself (owned by `17_COORDINATION/CONFLICT-RESOLUTION.md` or `17_COORDINATION/FAILURE-RECOVERY.md` as applicable).

---

## Domain Rule

Progress aggregation applies identically regardless of domain; domain-specific task content is carried in the execution plan, not interpreted by the Tracker itself.

---

## Rule

> Progress reporting must reflect verified task status as recorded by the State Manager. The Progress Tracker aggregates and reports; it does not independently define or alter a task's status.
