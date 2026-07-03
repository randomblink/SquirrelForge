# SquirrelForge Checkpoint Manager

## Purpose

The Checkpoint Manager records the completion of major execution milestones, verifies that required conditions have been satisfied, and determines whether a workflow may safely continue.

---

## Responsibilities

- Create checkpoints during execution.
- Verify checkpoint completion.
- Validate required outputs.
- Detect incomplete work.
- Support workflow resume after interruption.
- Prevent skipped workflow stages.
- Record checkpoint history.
- Report checkpoint status.

---

## Checkpoint Lifecycle

1. Create checkpoint.
2. Record current workflow state.
3. Validate required deliverables.
4. Verify rule compliance.
5. Mark checkpoint as Complete or Failed.
6. Allow workflow continuation only if complete.

---

## Checkpoint Status

| Status | Meaning |
|---|---|
| Pending | Waiting for validation |
| Active | Currently being evaluated |
| Complete | Successfully validated |
| Failed | Validation failed |
| Blocked | Waiting on prerequisite |
| Skipped | Not permitted unless explicitly authorized |

---

## Checkpoint Record

| Field | Description |
|---|---|
| Checkpoint ID | Unique identifier |
| Workflow | Parent workflow |
| Stage | Current execution stage |
| Validation Result | Pass / Fail |
| Timestamp | Completion time |
| Notes | Optional observations |

---

## Resume Procedure

When execution resumes after interruption:

1. Load the last completed checkpoint.
2. Verify output integrity.
3. Reconstruct workflow state.
4. Resume from the next incomplete step.
5. Prevent duplicate execution.

---

## Rule

A workflow may never advance beyond a checkpoint that has not successfully passed validation.
