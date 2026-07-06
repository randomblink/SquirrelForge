# SquirrelForge Workflow Executor

Version: 1.0.0
Status: Stable
Owner: Execution Maintainers
Depends On: `20_EXECUTION/EXECUTION-ENGINE.md`, `14_ENGINE/EXECUTION-PLANNER.md`, `20_EXECUTION/ACTION-DISPATCHER.md`, `20_EXECUTION/CHECKPOINT-MANAGER.md`, `20_EXECUTION/FAILURE-HANDLER.md`, `20_EXECUTION/EXECUTION-MONITOR.md`
Used By: `20_EXECUTION/EXECUTION-ENGINE.md`
Last Updated: 2026-07-06

## Purpose

The Workflow Executor carries out the active workflow's approved steps, in the order and with the dependencies the execution plan already defines, handing each step to `20_EXECUTION/ACTION-DISPATCHER.md` for actual execution and to `20_EXECUTION/CHECKPOINT-MANAGER.md` for required checkpoint validation.

The Workflow Executor sequences steps; it does not perform the underlying action itself (owned by `20_EXECUTION/ACTION-DISPATCHER.md`), validate a checkpoint itself (owned by `20_EXECUTION/CHECKPOINT-MANAGER.md`), decide dependency order (already fixed by `14_ENGINE/EXECUTION-PLANNER.md`), decide a recovery strategy (owned by `17_COORDINATION/FAILURE-RECOVERY.md` via `20_EXECUTION/FAILURE-HANDLER.md`), or perform rollback itself (owned by `20_EXECUTION/ROLLBACK-MANAGER.md`). It reports its progress and completion to `20_EXECUTION/EXECUTION-ENGINE.md`, and its step-level activity is observed in detail by `20_EXECUTION/EXECUTION-MONITOR.md`.

---

## Responsibilities

The Workflow Executor must:

- receive the execution plan from `20_EXECUTION/EXECUTION-ENGINE.md`,
- load the active workflow and its ordered steps,
- hand off each step, in the order and with the dependencies the plan already defines, to `20_EXECUTION/ACTION-DISPATCHER.md`,
- request checkpoint validation from `20_EXECUTION/CHECKPOINT-MANAGER.md` at the points the plan requires,
- update step status as execution proceeds,
- report any failure to `20_EXECUTION/FAILURE-HANDLER.md` and act only on the authorized instruction it returns,
- report workflow completion or termination to `20_EXECUTION/EXECUTION-ENGINE.md`,
- and maintain the step-level record needed for traceability.

---

## Workflow Execution Process

1. Receive the execution plan from `20_EXECUTION/EXECUTION-ENGINE.md`.
2. Load the active workflow.
3. Verify prerequisites from the plan's already-defined dependencies.
4. Hand off the current step to `20_EXECUTION/ACTION-DISPATCHER.md`.
5. Update step status.
6. Request checkpoint validation from `20_EXECUTION/CHECKPOINT-MANAGER.md` where the plan requires it.
7. Continue until the workflow completes, or report a failure to `20_EXECUTION/FAILURE-HANDLER.md`.
8. Return execution status to `20_EXECUTION/EXECUTION-ENGINE.md`.

---

## Workflow Step Model

| Field | Description |
|---|---|
| Step ID | Unique workflow step identifier |
| Name | Step name |
| Description | Action to perform |
| Dependencies | Required previous steps, as fixed by the execution plan |
| Validation Reference | Reference to the checkpoint `20_EXECUTION/CHECKPOINT-MANAGER.md` requires after this step, if any |
| Status | Pending / Running / Passed / Failed / Skipped |

---

## Execution Principles

- Execute one validated step at a time.
- Never skip a mandatory workflow step without an approved justification.
- Stop and report immediately on a critical failure.
- Resume from the checkpoint `20_EXECUTION/CHECKPOINT-MANAGER.md` confirms is last valid, when recovering.
- Maintain a complete step-level execution record.

---

## Permission Boundary

The Workflow Executor may sequence steps, hand off actions, request checkpoint validation, update step status, and report to the Execution Engine and Failure Handler.

It must not perform the underlying action itself (owned by `20_EXECUTION/ACTION-DISPATCHER.md`), validate a checkpoint itself (owned by `20_EXECUTION/CHECKPOINT-MANAGER.md`), decide dependency order (owned by `14_ENGINE/EXECUTION-PLANNER.md`), decide a recovery strategy or perform rollback (owned by `17_COORDINATION/FAILURE-RECOVERY.md` and `20_EXECUTION/ROLLBACK-MANAGER.md`), or replace `20_EXECUTION/EXECUTION-MONITOR.md`'s detailed progress tracking.

---

## Domain Rule

Workflow sequencing applies identically regardless of domain; domain-specific content is carried in the step description, not interpreted by the Workflow Executor itself.

---

## Rule

> Every workflow step must either complete successfully, fail with a documented reason routed to the Failure Handler, or be explicitly skipped with an approved justification. No step may be silently ignored, and no step may be executed out of the order the execution plan already defines.
