# SquirrelForge Checkpoint Manager

Version: 1.0.0
Status: Stable
Owner: Execution Maintainers
Depends On: `20_EXECUTION/WORKFLOW-EXECUTOR.md`, `14_ENGINE/VALIDATION.md`, `19_REASONING/RULE-EVALUATOR.md`, `14_ENGINE/EXECUTION-PLANNER.md`
Used By: `20_EXECUTION/WORKFLOW-EXECUTOR.md`, `20_EXECUTION/EXECUTION-ENGINE.md`
Last Updated: 2026-07-06

## Purpose

The Checkpoint Manager records the completion of major execution milestones, confirms that the validation and rule-compliance evidence the execution plan requires actually exists and passed, and gates whether a workflow may safely continue past that point.

The Checkpoint Manager gates continuation; it does not perform the underlying validation or rule-compliance assessment itself. Output validation is owned by `14_ENGINE/VALIDATION.md`; rule compliance is owned by `19_REASONING/RULE-EVALUATOR.md`; which points in the plan require a checkpoint is already scheduled by `14_ENGINE/EXECUTION-PLANNER.md`. The Checkpoint Manager reads these results and confirms their presence; it does not independently decide that an output is correct or that a rule was satisfied.

---

## Responsibilities

The Checkpoint Manager must:

- create a checkpoint at the points `14_ENGINE/EXECUTION-PLANNER.md` has already scheduled,
- record the current workflow state at each checkpoint,
- confirm the validation result from `14_ENGINE/VALIDATION.md` exists and passed, rather than independently validating outputs,
- confirm the rule-compliance result from `19_REASONING/RULE-EVALUATOR.md` exists and passed, rather than independently verifying compliance,
- detect incomplete work,
- gate whether the workflow may continue based on the confirmed results,
- support workflow resume after interruption,
- prevent a workflow stage from being skipped without explicit authorization,
- record checkpoint history,
- and report checkpoint status to `20_EXECUTION/WORKFLOW-EXECUTOR.md`.

---

## Checkpoint Lifecycle

1. Create the checkpoint at a point the execution plan already schedules.
2. Record the current workflow state.
3. Confirm the validation result from `14_ENGINE/VALIDATION.md`.
4. Confirm the rule-compliance result from `19_REASONING/RULE-EVALUATOR.md`.
5. Mark the checkpoint Complete only if both are confirmed and passed; otherwise mark it Failed.
6. Allow workflow continuation only if the checkpoint is Complete.

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
| Validation Result Reference | Reference to `14_ENGINE/VALIDATION.md`'s result |
| Rule Compliance Reference | Reference to `19_REASONING/RULE-EVALUATOR.md`'s result |
| Timestamp | Completion time |
| Notes | Optional observations |

---

## Resume Procedure

When execution resumes after interruption:

1. Load the last completed checkpoint.
2. Confirm output integrity via `14_ENGINE/VALIDATION.md`'s recorded result.
3. Reconstruct workflow state.
4. Resume from the next incomplete step.
5. Prevent duplicate execution.

---

## Permission Boundary

The Checkpoint Manager may create checkpoints, record workflow state, confirm validation and rule-compliance results, and gate workflow continuation.

It must not independently validate outputs (owned by `14_ENGINE/VALIDATION.md`) or independently verify rule compliance (owned by `19_REASONING/RULE-EVALUATOR.md`), and it must not schedule where checkpoints belong in the plan (owned by `14_ENGINE/EXECUTION-PLANNER.md`).

---

## Domain Rule

Checkpoint gating applies identically regardless of domain; domain-specific content is carried in the referenced validation and rule results, not interpreted by the Checkpoint Manager itself.

---

## Rule

> A workflow may never advance beyond a checkpoint whose required validation and rule-compliance results have not been confirmed as passed. The Checkpoint Manager gates continuation on that confirmation; it does not perform the validation or rule evaluation itself.
