# SquirrelForge Action Dispatcher

Version: 1.0.0
Status: Stable
Owner: Execution Maintainers
Depends On: `20_EXECUTION/WORKFLOW-EXECUTOR.md`, `14_ENGINE/TASK-ROUTER.md`, `20_EXECUTION/FAILURE-HANDLER.md`
Used By: `20_EXECUTION/WORKFLOW-EXECUTOR.md`
Last Updated: 2026-07-06

## Purpose

The Action Dispatcher translates an approved workflow step into an executable action and performs the technical dispatch to the target `14_ENGINE/TASK-ROUTER.md` has already selected, confirming receipt and recording the result.

The Action Dispatcher dispatches; it does not select the target. Which agent, tool, or execution component receives the action is already decided by `14_ENGINE/TASK-ROUTER.md`'s routing record before the action reaches this component. The Action Dispatcher does not decide task ownership or reroute a task itself (owned by `14_ENGINE/TASK-ROUTER.md`), and it does not decide how a dispatch failure should be recovered (owned by `17_COORDINATION/FAILURE-RECOVERY.md` via `20_EXECUTION/FAILURE-HANDLER.md`) — it reports the failure and acts on the authorized instruction it receives back.

---

## Responsibilities

The Action Dispatcher must:

- receive a workflow action from `20_EXECUTION/WORKFLOW-EXECUTOR.md`,
- identify the action type,
- read the already-selected execution target from `14_ENGINE/TASK-ROUTER.md`'s routing record, rather than independently selecting one,
- verify execution prerequisites,
- dispatch the action to that target,
- confirm receipt,
- record the dispatch,
- report a dispatch failure to `20_EXECUTION/FAILURE-HANDLER.md` rather than handling it independently,
- and return the execution status to `20_EXECUTION/WORKFLOW-EXECUTOR.md`.

---

## Dispatch Process

1. Receive the workflow action from `20_EXECUTION/WORKFLOW-EXECUTOR.md`.
2. Identify the action type.
3. Read the already-selected execution target from `14_ENGINE/TASK-ROUTER.md`'s routing record.
4. Verify prerequisites.
5. Dispatch the action to the target.
6. Confirm receipt.
7. Record the dispatch.
8. If dispatch fails, report to `20_EXECUTION/FAILURE-HANDLER.md` and act only on the authorized instruction it returns.
9. Return execution status to `20_EXECUTION/WORKFLOW-EXECUTOR.md`.

---

## Action Types

| Action | Typical Target |
|---|---|
| Architecture | Agent Architect |
| Planning | Agent Planner |
| Development | Agent Developer |
| Review | Agent Reviewer |
| Security | Agent Security |
| Performance | Agent Performance |
| Documentation | Agent Documentation |
| Release | Agent Release |
| Validation | Validation Engine |

These are typical mappings shown for reference. The actual target for a given action is whatever `14_ENGINE/TASK-ROUTER.md` has already selected and recorded, which may differ based on availability, capability, and routing rules.

---

## Dispatch Record

| Field | Description |
|---|---|
| Action ID | Unique identifier |
| Workflow | Originating workflow |
| Target Reference | Reference to the execution target `14_ENGINE/TASK-ROUTER.md` selected |
| Status | Pending / Dispatched / Running / Complete / Failed |
| Timestamp | Dispatch time |
| Result | Execution outcome |

---

## Permission Boundary

The Action Dispatcher may identify the action type, read the already-selected target, verify prerequisites, dispatch, confirm receipt, and record the dispatch.

It must not select or reroute the execution target itself (owned by `14_ENGINE/TASK-ROUTER.md`), or decide how a dispatch failure should be recovered (owned by `17_COORDINATION/FAILURE-RECOVERY.md` via `20_EXECUTION/FAILURE-HANDLER.md`).

---

## Domain Rule

Dispatch mechanics apply identically regardless of domain; domain-specific content is carried in the action, not interpreted by the Action Dispatcher itself.

---

## Rule

> Every dispatched action must have exactly one execution target, already selected by the Task Router, and a recorded execution result before the next workflow step begins. The Action Dispatcher performs the dispatch; it does not select the target.
