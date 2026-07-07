# SquirrelForge Result Reviewer

Version: 1.0.0
Status: Stable
Owner: AI Driver Maintainers
Depends On: `20_EXECUTION/RESULT-COLLECTOR.md`, `14_ENGINE/VALIDATION.md`
Used By: `19_REASONING/AI-DRIVER.md`, `19_REASONING/REFLECTION-ENGINE.md`
Last Updated: 2026-07-07

## Purpose

The Result Reviewer closes the AI reasoning loop after execution. It reads `20_EXECUTION/RESULT-COLLECTOR.md`'s assembled Execution Result Set together with `14_ENGINE/VALIDATION.md`'s findings, compares them against the original structured goal, and recommends the AI Driver's next step: continue, retry, revise the plan, or conclude the task.

The Result Reviewer does not collect execution results (owned by `20_EXECUTION/RESULT-COLLECTOR.md`, which explicitly does not judge correctness), validate output correctness (owned by `14_ENGINE/VALIDATION.md`), decide whether a workflow stage is complete or execution may advance (owned by `20_EXECUTION/WORKFLOW-EXECUTOR.md` and `20_EXECUTION/EXECUTION-ENGINE.md`), score pre-execution confidence (owned by `19_REASONING/CONFIDENCE-SCORER.md`), or extract retrospective lessons from completed work (owned by `19_REASONING/REFLECTION-ENGINE.md`). It is the real-time bridge between a finished action and the next iteration of `19_REASONING/DECISION-ENGINE.md`, distinct from all of those.

---

## Responsibilities

- Read the Execution Result Set from `20_EXECUTION/RESULT-COLLECTOR.md`.
- Read validation findings from `14_ENGINE/VALIDATION.md`.
- Compare expected outcome (from the structured goal) against actual results.
- Determine goal status.
- Detect deviations or failures.
- Recommend the AI Driver's next step.
- Record review activity.

---

## Inputs

The Result Reviewer receives:

- The Execution Result Set (from `20_EXECUTION/RESULT-COLLECTOR.md`)
- Validation findings (from `14_ENGINE/VALIDATION.md`)
- The original structured goal (from `14_ENGINE/GOAL-PLANNER.md`)
- The selected action and strategy (from `19_REASONING/DECISION-ENGINE.md` and `19_REASONING/STRATEGY-PLANNER.md`)

---

## Outputs

The Result Reviewer produces:

- Goal status assessments
- Recovery recommendations
- Retry recommendations
- Plan revision requests (routed back to `19_REASONING/DECISION-ENGINE.md`)
- Completion confirmations
- Result review audit records

---

## Result Review Workflow

1. Receive the Execution Result Set from `20_EXECUTION/RESULT-COLLECTOR.md`.
2. Retrieve the expected outcome from the structured goal.
3. Compare expected and actual results, incorporating `14_ENGINE/VALIDATION.md`'s findings.
4. Detect deviations or failures.
5. Determine goal status.
6. Recommend the next step to `19_REASONING/AI-DRIVER.md`.
7. Record audit information.

---

## Goal Status

Goal outcomes include:

- Completed
- Partially completed
- Requires retry
- Requires replanning
- Blocked
- Failed

---

## Recovery Recommendations

The Result Reviewer may recommend:

- Retry the current action
- Retry with a different tool (via `34_AIDRIVER/TOOL-SELECTOR.md`)
- Revise the plan (routed to `19_REASONING/DECISION-ENGINE.md`)
- Request clarification
- Escalate the issue
- Continue the workflow
- Mark the goal complete

The Result Reviewer recommends; it does not execute recovery actions itself. Rollback and generic failure recovery remain owned by `20_EXECUTION/ROLLBACK-MANAGER.md` and `17_COORDINATION/FAILURE-RECOVERY.md`.

---

## Safety Rules

The Result Reviewer must never:

- Fabricate evaluation results.
- Ignore execution failures or validation findings.
- Bypass `14_ENGINE/VALIDATION.md`'s conclusions.
- Alter historical execution records.
- Execute recovery actions directly.
- Delete audit records.

---

## Failure Handling

If result review fails:

- Preserve execution evidence.
- Record the review failure.
- Notify `19_REASONING/AI-DRIVER.md`.
- Escalate persistent issues.
- Maintain audit continuity.

---

## Audit Requirements

Every result review records:

- Result review ID
- Timestamp
- Goal ID
- Action ID
- Expected outcome
- Actual outcome (from `20_EXECUTION/RESULT-COLLECTOR.md` and `14_ENGINE/VALIDATION.md`)
- Goal status
- Recommended next step
- Final outcome

---

## Success Criteria

The Result Reviewer succeeds when:

- Execution outcomes are accurately evaluated against the original goal.
- Goal status is correctly determined.
- Recovery recommendations are evidence-based.
- Recommendations reach `19_REASONING/AI-DRIVER.md` promptly.
- Audit records remain complete.

---

## Permission Boundary

The Result Reviewer may read assembled results and validation findings, compare them to the original goal, and recommend the AI Driver's next step.

It must not collect results, validate correctness, decide workflow-stage completion, score pre-execution confidence, or extract retrospective lessons — those remain owned by `20_EXECUTION/RESULT-COLLECTOR.md`, `14_ENGINE/VALIDATION.md`, `20_EXECUTION/WORKFLOW-EXECUTOR.md`/`20_EXECUTION/EXECUTION-ENGINE.md`, `19_REASONING/CONFIDENCE-SCORER.md`, and `19_REASONING/REFLECTION-ENGINE.md` respectively.

---

## Domain Rule

Result review applies identically regardless of domain; domain-specific success criteria are carried in the goal and validation evidence it reads, not interpreted by the Result Reviewer itself.

---

## Rule

No AI-driven task may be marked complete, retried, or replanned without the Result Reviewer comparing actual results against the original goal and recording a goal status.
