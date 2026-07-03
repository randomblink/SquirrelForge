# SquirrelForge Rollback Manager

## Purpose

The Rollback Manager restores the workflow to the last known valid checkpoint after an unrecoverable failure or invalid execution result, ensuring system consistency and preventing partial or corrupt execution states.

---

## Responsibilities

- Identify the last valid checkpoint.
- Determine rollback scope.
- Reverse completed actions when possible.
- Restore workflow state.
- Verify rollback integrity.
- Record rollback operations.
- Notify dependent components.
- Return the workflow to a recoverable state.

---

## Rollback Process

1. Receive rollback request.
2. Locate the most recent valid checkpoint.
3. Identify affected actions.
4. Reverse reversible actions.
5. Restore workflow state.
6. Validate restored state.
7. Record rollback results.
8. Return control to the Failure Handler or Workflow Executor.

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
| Checkpoint | Recovery point |
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
- Checkpoint integrity confirmed.
- Execution history updated.

---

## Rule

Every rollback must restore the workflow to a verified checkpoint before execution may resume.
