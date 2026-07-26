# SquirrelForge Execution Engine

Version: 1.1.0
Status: Stable
Owner: Execution Maintainers
Depends On: `19_REASONING/STRATEGY-PLANNER.md`, `14_ENGINE/EXECUTION-PLANNER.md`, `14_ENGINE/TASK-ROUTER.md`, `14_ENGINE/DEPENDENCY-ANALYZER.md`, `14_ENGINE/VALIDATION.md`, `14_ENGINE/STATE-MANAGER.md`, `20_EXECUTION/WORKFLOW-EXECUTOR.md`, `20_EXECUTION/CHECKPOINT-MANAGER.md`, `20_EXECUTION/EXECUTION-MONITOR.md`, `20_EXECUTION/FAILURE-HANDLER.md`, `20_EXECUTION/RESULT-COLLECTOR.md`
Used By: `20_EXECUTION/EXECUTION-REPORTER.md`, Coordination
Last Updated: 2026-07-26

## Purpose

The Execution Engine is the Execution layer's entry point and top-level coordinator. It receives an approved strategy and execution plan, verifies preconditions, and hands off actual workflow execution to `20_EXECUTION/WORKFLOW-EXECUTOR.md`, monitoring overall progress and routing failures until the workflow reaches a terminal state.

The Execution Engine coordinates; it does not perform the detailed work itself. Carrying out workflow steps is owned by `20_EXECUTION/WORKFLOW-EXECUTOR.md`; dispatching individual actions is owned by `20_EXECUTION/ACTION-DISPATCHER.md`; creating and verifying checkpoints is owned by `20_EXECUTION/CHECKPOINT-MANAGER.md`; granular status tracking is owned by `20_EXECUTION/EXECUTION-MONITOR.md`; failure normalization and recovery routing is owned by `20_EXECUTION/FAILURE-HANDLER.md`; and assembling results and reports is owned by `20_EXECUTION/RESULT-COLLECTOR.md` and `20_EXECUTION/EXECUTION-REPORTER.md`. The Execution Engine does not select the strategy (`19_REASONING/STRATEGY-PLANNER.md`), build the execution plan (`14_ENGINE/EXECUTION-PLANNER.md`), assign the agent (`14_ENGINE/TASK-ROUTER.md`), or resolve dependencies (`14_ENGINE/DEPENDENCY-ANALYZER.md`) — it receives these as already-approved inputs.

---

## Responsibilities

The Execution Engine must:

- receive the approved strategy from `19_REASONING/STRATEGY-PLANNER.md` and the execution plan from `14_ENGINE/EXECUTION-PLANNER.md`,
- verify that required dependencies are satisfied, per `14_ENGINE/DEPENDENCY-ANALYZER.md`,
- request the resume point from `20_EXECUTION/CHECKPOINT-MANAGER.md` rather than independently determining the correct checkpoint,
- hand off workflow execution to `20_EXECUTION/WORKFLOW-EXECUTOR.md`,
- monitor overall execution progress through `20_EXECUTION/EXECUTION-MONITOR.md`'s reported health,
- route any reported failure to `20_EXECUTION/FAILURE-HANDLER.md`,
- continue until execution reaches a terminal state or produces a result set ready for validation,
- submit the assembled result-set reference and validation subject/version references to `14_ENGINE/VALIDATION.md`,
- apply the returned validation decision through `14_ENGINE/STATE-MANAGER.md` without reinterpreting it,
- route `REPAIR_REQUIRED`, `BLOCKED`, and `RECOVERY_REQUIRED` decisions to their recorded owners,
- and ensure `20_EXECUTION/EXECUTION-REPORTER.md` receives the result set, standardized validation-record reference, and authoritative state reference.

---

## Execution Inputs

| Input | Description | Source |
|---|---|---|
| Approved Strategy | Strategy selected upstream. | `19_REASONING/STRATEGY-PLANNER.md` |
| Execution Plan | Ordered work plan. | `14_ENGINE/EXECUTION-PLANNER.md` |
| Active Workflow | Workflow responsible for the task. | `14_ENGINE/WORKFLOW-SELECTOR.md` |
| Assigned Agent | Agent currently performing the task. | `14_ENGINE/TASK-ROUTER.md` |
| Dependencies | Required files, tools, or context. | `14_ENGINE/DEPENDENCY-ANALYZER.md` |
| Resume Checkpoint | Most recent valid checkpoint, when resuming. | `20_EXECUTION/CHECKPOINT-MANAGER.md` |

---

## Execution Process

1. Receive the approved strategy and execution plan.
2. Verify dependencies are satisfied.
3. Request the resume checkpoint from `20_EXECUTION/CHECKPOINT-MANAGER.md`, if resuming.
4. Hand off workflow execution to `20_EXECUTION/WORKFLOW-EXECUTOR.md`.
5. Monitor overall progress through `20_EXECUTION/EXECUTION-MONITOR.md`.
6. Route any reported failure to `20_EXECUTION/FAILURE-HANDLER.md`.
7. When execution produces its expected outputs, have `20_EXECUTION/RESULT-COLLECTOR.md` assemble the Execution Result Set.
8. Submit the result-set reference, validation subject and version references, and required evidence references to `14_ENGINE/VALIDATION.md`.
9. Receive the standardized validation-record reference and apply its decision through `14_ENGINE/STATE-MANAGER.md`.
10. If the decision is `REPAIR_REQUIRED`, resume only from the recorded responsible phase and invalidate the identified dependent evidence.
11. If the decision is `BLOCKED` or `RECOVERY_REQUIRED`, stop dependent progression and route to the recorded blocker or recovery owner.
12. If the decision is `REJECTED`, terminate without reporting successful completion.
13. Provide the result set, validation-record reference, and authoritative state reference to `20_EXECUTION/EXECUTION-REPORTER.md`.

---

## Execution Status

| Status | Meaning |
|---|---|
| Pending | Waiting to begin |
| Running | Workflow execution is active |
| Paused | Temporarily stopped at a checkpoint |
| Validation Pending | Expected outputs exist and the validation decision is pending |
| Repair Required | Validation returned repair work within an approved attempt boundary |
| Blocked | Cannot continue due to an unresolved failure or missing dependency |
| Complete | Execution finished and validation returned `ACCEPTED` or policy-permitted `ACCEPTED_WITH_LIMITATIONS` |
| Recovery Required | Validation or execution identified a required recovery action |
| Rejected | Validation returned `REJECTED`; successful completion is prohibited |
| Failed | Workflow execution ended unsuccessfully |

This reflects overall execution state only. Per-action status is owned by `20_EXECUTION/EXECUTION-MONITOR.md`'s own Execution Status table.

---

## Permission Boundary

The Execution Engine may receive approved inputs, verify preconditions, hand off to the Workflow Executor, monitor overall progress, submit assembled results for validation, apply the returned decision through the State Manager, and route failures or repair.

It must not carry out workflow steps itself (owned by `20_EXECUTION/WORKFLOW-EXECUTOR.md`), dispatch individual actions (owned by `20_EXECUTION/ACTION-DISPATCHER.md`), create or verify checkpoints (owned by `20_EXECUTION/CHECKPOINT-MANAGER.md`), perform granular status tracking (owned by `20_EXECUTION/EXECUTION-MONITOR.md`), decide validation outcomes (owned by `14_ENGINE/VALIDATION.md`), decide recovery strategy (owned by `17_COORDINATION/FAILURE-RECOVERY.md` via `20_EXECUTION/FAILURE-HANDLER.md`), or assemble results and reports itself (owned by `20_EXECUTION/RESULT-COLLECTOR.md` and `20_EXECUTION/EXECUTION-REPORTER.md`).

---

## Domain Rule

Execution coordination applies identically regardless of domain; domain-specific content is carried in the strategy and plan, not interpreted by the Execution Engine itself.

---

## Rule

> Execution may only begin after the strategy, plan, dependencies, and required checkpoints have been approved, and it may be marked complete only after an accepted validation decision. The Execution Engine coordinates the run and applies authoritative decisions; it does not produce those decisions itself.
