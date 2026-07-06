# SquirrelForge Execution Engine

Version: 1.0.0
Status: Stable
Owner: Execution Maintainers
Depends On: `19_REASONING/STRATEGY-PLANNER.md`, `14_ENGINE/EXECUTION-PLANNER.md`, `14_ENGINE/TASK-ROUTER.md`, `14_ENGINE/DEPENDENCY-ANALYZER.md`, `20_EXECUTION/WORKFLOW-EXECUTOR.md`, `20_EXECUTION/CHECKPOINT-MANAGER.md`, `20_EXECUTION/EXECUTION-MONITOR.md`, `20_EXECUTION/FAILURE-HANDLER.md`, `20_EXECUTION/RESULT-COLLECTOR.md`
Used By: `20_EXECUTION/EXECUTION-REPORTER.md`, Coordination
Last Updated: 2026-07-06

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
- continue until the workflow reaches a terminal state (Complete, Failed, or Blocked),
- and ensure `20_EXECUTION/RESULT-COLLECTOR.md` and `20_EXECUTION/EXECUTION-REPORTER.md` receive the completed execution for assembly and reporting.

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
7. Continue until the workflow reaches a terminal state.
8. Ensure `20_EXECUTION/RESULT-COLLECTOR.md` and `20_EXECUTION/EXECUTION-REPORTER.md` receive the completed execution.

---

## Execution Status

| Status | Meaning |
|---|---|
| Pending | Waiting to begin |
| Running | Workflow execution is active |
| Paused | Temporarily stopped at a checkpoint |
| Blocked | Cannot continue due to an unresolved failure or missing dependency |
| Complete | Workflow execution finished successfully |
| Failed | Workflow execution ended unsuccessfully |

This reflects overall execution state only. Per-action status is owned by `20_EXECUTION/EXECUTION-MONITOR.md`'s own Execution Status table.

---

## Permission Boundary

The Execution Engine may receive approved inputs, verify preconditions, hand off to the Workflow Executor, monitor overall progress, and route failures.

It must not carry out workflow steps itself (owned by `20_EXECUTION/WORKFLOW-EXECUTOR.md`), dispatch individual actions (owned by `20_EXECUTION/ACTION-DISPATCHER.md`), create or verify checkpoints (owned by `20_EXECUTION/CHECKPOINT-MANAGER.md`), perform granular status tracking (owned by `20_EXECUTION/EXECUTION-MONITOR.md`), decide recovery strategy (owned by `17_COORDINATION/FAILURE-RECOVERY.md` via `20_EXECUTION/FAILURE-HANDLER.md`), or assemble results and reports itself (owned by `20_EXECUTION/RESULT-COLLECTOR.md` and `20_EXECUTION/EXECUTION-REPORTER.md`).

---

## Domain Rule

Execution coordination applies identically regardless of domain; domain-specific content is carried in the strategy and plan, not interpreted by the Execution Engine itself.

---

## Rule

> Execution may only begin after the strategy, plan, dependencies, and required checkpoints have been approved. The Execution Engine coordinates the overall run; it does not perform the detailed work its specialized components own.
