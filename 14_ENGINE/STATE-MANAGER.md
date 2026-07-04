# SquirrelForge State Manager

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `14_ENGINE/VALIDATION.md`
Used By: `14_ENGINE/ENGINE-OVERVIEW.md`
Last Updated: 2026-07-04

## Purpose

The State Manager is the authoritative source for a task's lifecycle state. It tracks progress, manages state transitions, and coordinates with other engine components like the `Validation` coordinator to move a task from creation to completion.

## Responsibilities

-   Maintain the definitive status for every task (`Not Started`, `In Progress`, etc.).
-   Transition a task's status based on events from other engine components.
-   Invoke the `Validation` component when a task is ready for review.
-   Update a task's status based on the `Passed` or `Failed` outcome from validation.
-   Ensure that a task cannot be marked `Complete` until all required gates have passed.
-   Produce a traceable record of all state transitions.

## State Model

| Field | Description |
|---|---|
| Workflow | Active workflow being executed |
| Task | Current task |
| Status | `Not Started` / `In Progress` / `Pending Validation` / `Blocked` / `Complete` |
| ValidationResult | `Not Run` / `Passed` / `Failed` |
| Next Step | Recommended action after completion |

## State Transitions

The State Manager orchestrates the following lifecycle for a task:

1.  **Creation:** A new task is created with the status `Not Started`.
2.  **Execution:** The `Task Router` assigns the task, and its status becomes `In Progress`.
3.  **Validation Handoff:** Once the agent completes its work, it notifies the State Manager. The status transitions to `Pending Validation`, and the State Manager invokes the `14_ENGINE/VALIDATION.md` component.
4.  **Validation Success:** If the `Validation` component returns a `Passed` result, the State Manager sets `ValidationResult` to `Passed` and transitions the task status to `Complete`.
5.  **Validation Failure:** If the `Validation` component returns a `Failed` result, the State Manager sets `ValidationResult` to `Failed` and transitions the task status back to `In Progress` for remediation.
6.  **Blocking:** If an external dependency prevents progress at any point, the status can be set to `Blocked`.

```text
               ┌──────────────────┐
               │ In Progress      ├<───────────┐
               └───────┬──────────┘            │
                       │ (Work Complete)       │ (Failed)
                       ▼                       │
┌──────────────────────┴───────────────┐       │
│ Pending Validation (Invoke VALIDATION) │───────┘
└──────────────────────┬───────────────┘
                       │ (Passed)
                       ▼
               ┌─────────┴────────┐
               │ Complete         │
               └──────────────────┘
```

## Rule

A task **must not** be marked `Complete` until its `ValidationResult` is `Passed`. The State Manager is the sole authority for changing a task's status.