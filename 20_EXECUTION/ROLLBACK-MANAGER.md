# SquirrelForge Rollback Manager

Version: 1.0.0
Status: Stable
Owner: Execution Maintainers
Depends On: `20_EXECUTION/FAILURE-HANDLER.md`, `17_COORDINATION/FAILURE-RECOVERY.md`, `20_EXECUTION/CHECKPOINT-MANAGER.md`, `20_EXECUTION/WORKFLOW-EXECUTOR.md`
Used By: `20_EXECUTION/FAILURE-HANDLER.md`
Last Updated: 2026-07-06

## Purpose

The Rollback Manager executes an authorized rollback by restoring the workflow to a target checkpoint, reversing completed actions within the authorized scope, and confirming the restored state's integrity — ensuring system consistency and preventing partial or corrupt execution states.

The Rollback Manager executes an already-authorized rollback; it does not decide that rollback is the correct recovery strategy. That decision belongs to `17_COORDINATION/FAILURE-RECOVERY.md`, routed here through `20_EXECUTION/FAILURE-HANDLER.md`. The Rollback Manager does determine the technical scope and target checkpoint for an authorized rollback, and confirms restored-state integrity through `20_EXECUTION/CHECKPOINT-MANAGER.md` rather than independently validating it.

---

## Responsibilities

The Rollback Manager must:

- receive an authorized rollback request through `20_EXECUTION/FAILURE-HANDLER.md`,
- identify the last valid checkpoint to restore to,
- determine the technical rollback scope (Action, Stage, Workflow, or Session) needed to satisfy the authorization,
- reverse completed actions within that scope when possible,
- restore workflow state,
- confirm restored-state integrity through `20_EXECUTION/CHECKPOINT-MANAGER.md`,
- record rollback operations,
- notify dependent components of the restored state,
- and return control to `20_EXECUTION/FAILURE-HANDLER.md`.

---

## Rollback Process

1. Receive an authorized rollback request through `20_EXECUTION/FAILURE-HANDLER.md`.
2. Locate the most recent valid checkpoint.
3. Identify affected actions and the technical scope required.
4. Reverse reversible actions.
5. Restore workflow state.
6. Confirm restored-state integrity through `20_EXECUTION/CHECKPOINT-MANAGER.md`.
7. Record rollback results.
8. Return control to `20_EXECUTION/FAILURE-HANDLER.md`.

---

## Rollback Levels

| Level | Description |
|---|---|
| Action | Undo a single failed action |
| Stage | Restore the current workflow stage |
| Workflow | Restore the entire workflow |
| Session | Restore the complete execution session |

---

## Rollback Record

| Field | Description |
|---|---|
| Rollback ID | Unique identifier |
| Workflow | Parent workflow |
| Authorization Reference | Reference to the recovery decision authorizing this rollback (`17_COORDINATION/FAILURE-RECOVERY.md`) |
| Checkpoint Reference | Recovery point restored to |
| Scope | Action / Stage / Workflow / Session |
| Status | Successful / Partial / Failed |
| Timestamp | Rollback completion time |
| Notes | Additional details |

---

## Verification Checklist

- Last valid checkpoint located.
- Workflow state restored.
- Invalid outputs removed or isolated.
- Dependencies revalidated.
- Checkpoint integrity confirmed through `20_EXECUTION/CHECKPOINT-MANAGER.md`.
- Execution history updated.

---

## Permission Boundary

The Rollback Manager may receive an authorized rollback request, locate the target checkpoint, determine technical scope, reverse actions, restore state, and record the outcome.

It must not decide that rollback is the correct recovery strategy itself (owned by `17_COORDINATION/FAILURE-RECOVERY.md`), or independently validate restored-state correctness (confirmed through `20_EXECUTION/CHECKPOINT-MANAGER.md`).

---

## Domain Rule

Rollback mechanics apply identically regardless of domain; domain-specific content is carried in the restored artifacts, not interpreted by the Rollback Manager itself.

---

## Rule

> Every rollback must be authorized before it begins, must restore the workflow to a checkpoint whose integrity is confirmed through the Checkpoint Manager, and must be recorded before execution may resume. The Rollback Manager executes the rollback; it does not decide whether one is required.
