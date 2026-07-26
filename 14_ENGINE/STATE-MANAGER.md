# SquirrelForge State Manager

Version: 1.1.0
Status: Stable
Owner: Engine Maintainers
Depends On: `11_OVERVIEW/LIFECYCLE.md`, `14_ENGINE/PROJECT-LOADER.md`, `14_ENGINE/WORKFLOW-SELECTOR.md`, `14_ENGINE/TASK-ROUTER.md`, `14_ENGINE/VALIDATION.md`
Used By: Engine, Coordination, Execution, Validation, Reporting
Last Updated: 2026-07-26

## Purpose

The State Manager tracks the current planning and routing state for an active SquirrelForge request.

It preserves enough lifecycle, workflow, task, routing, validation, blocker, and recovery context for work to resume, report accurately, or return to the earliest responsible phase after a failed gate.

The State Manager records state. It does not execute actions, validate tests, or rewrite history.

---

## Responsibilities

The State Manager must:

- record the active request and goal,
- record the active lifecycle phase,
- record project loading state,
- record selected workflow state,
- track milestones and tasks,
- track dependency status,
- track routing status,
- track owner handoffs,
- track blockers and blocked reasons,
- track validation requirements and evidence status,
- track recovery requirements,
- preserve context between workflow stages,
- prevent false completion state,
- and expose a concise current-state summary for reporting.

---

## State Record

A useful state record should include:

| Field | Description |
|---|---|
| Request ID | Active request identifier. |
| Goal ID | Structured goal identifier. |
| Lifecycle Phase | Current lifecycle phase from intake through retention. |
| Project State | Project loading and readiness state. |
| Workflow | Selected primary workflow and supporting workflows. |
| Milestone | Current milestone or milestone group. |
| Task | Current task or active task set. |
| Dependencies | Required predecessor tasks and their status. |
| Routing | Routing state and current owner. |
| Permissions | Permission status for planned actions. |
| Validation | Validation ID, subject and version references, overall decision, stage and item states, evidence and report references, attempt history, invalidated items, limitations, residual risks, and next action from the standardized validation object. |
| Blockers | Active blockers, reasons, and responsible phase. |
| Recovery | Recovery or rollback state when required. |
| Next Step | Next required lifecycle action. |
| Limitations | Known unavailable tools, missing context, or unverified claims. |

---

## Lifecycle State Values

| State | Meaning |
|---|---|
| `REQUESTED` | Request received but not yet normalized. |
| `BOOTSTRAPPING` | Agent bootstrap and required context loading are active. |
| `INTAKE` | Goal, constraints, acceptance criteria, and permissions are being captured. |
| `CONTEXT_LOADING` | Project, rules, workflows, domain references, and memory are being loaded. |
| `ROUTING` | Workflow and task routing are being selected. |
| `REASONING` | Risk, rule, strategy, and dependency review are active. |
| `PLANNING` | Ordered work plan and validation path are being prepared. |
| `PERMISSION_REVIEW` | Planned actions are checked against active boundaries. |
| `EXECUTION_HANDOFF` | Planned tasks are handed to execution or coordination. |
| `VALIDATION` | Required validation and testing evidence is being produced or reviewed. |
| `REVIEW` | Result is compared against rules and acceptance criteria. |
| `REPORTING` | Final or interim report is being prepared. |
| `OBSERVABILITY_RECORDING` | Relevant events, diagnostics, and audit records are being recorded. |
| `MEMORY_UPDATE` | Allowed memory or learning records are being stored. |
| `RETENTION` | Records are retained or archived according to policy. |
| `COMPLETE` | Work is complete after `ACCEPTED` or policy-permitted `ACCEPTED_WITH_LIMITATIONS`. |
| `BLOCKED` | A required condition prevents progress. |
| `RECOVERY_REQUIRED` | Unsafe or incomplete state requires recovery before continuation. |
| `FAILED` | Work failed and no safe continuation path is currently available. |

---

## Task State Values

| State | Meaning |
|---|---|
| `NOT_STARTED` | Task exists but work has not begun. |
| `READY` | Dependencies are satisfied and task may be routed. |
| `ROUTED` | A task owner or execution path has been selected. |
| `IN_PROGRESS` | Work has begun. |
| `WAITING` | Task is waiting on dependency, permission, tool, or user input. |
| `BLOCKED` | Task cannot proceed until a blocker is resolved. |
| `VALIDATION_PENDING` | Work exists but the standardized validation decision is not terminal. |
| `VALIDATION_FAILED` | Validation decision is `REPAIR_REQUIRED`; repair and re-validation are required. |
| `COMPLETED` | Task is complete with required evidence. |
| `CANCELLED` | Task was cancelled by lifecycle, governance, or scope control. |

---

## Validation State Values

| State | Meaning |
|---|---|
| `NOT_REQUIRED` | Applicability analysis determined the item is not required and recorded why. |
| `REQUIRED` | Validation is required but not started. |
| `PENDING` | Validation is underway or waiting for evidence. |
| `PASSED` | Applicable authoritative evidence met the expected condition. |
| `FAILED` | Evidence did not meet the expected condition. |
| `UNAVAILABLE` | Validation could not be performed; reason and impact are recorded. |
| `WAIVED` | An authorized governance decision explicitly waived the item. |
| `STALE` | Earlier evidence was invalidated by a relevant change and must be produced again. |
| `CANCELLED` | The item was cancelled because the request or owning task was cancelled. |

---

## Validation Decision Values

The State Manager records, but does not independently calculate, the decision emitted by `14_ENGINE/VALIDATION.md`.

| Decision | Required State Effect |
|---|---|
| `ACCEPTED` | The validated task may move to `COMPLETED`; lifecycle may advance to `REVIEW` or `REPORTING`. |
| `ACCEPTED_WITH_LIMITATIONS` | The task may move to `COMPLETED` only when policy permits the limitations; limitations and residual risks remain attached. |
| `REPAIR_REQUIRED` | Task moves to `VALIDATION_FAILED` and lifecycle returns to the recorded responsible phase. |
| `CLARIFICATION_REQUIRED` | Task moves to `WAITING`; the missing decision and resume condition are recorded. |
| `BLOCKED` | Task and lifecycle move to `BLOCKED`; blocker ownership and next safe action are recorded. |
| `RECOVERY_REQUIRED` | Lifecycle moves to `RECOVERY_REQUIRED`; affected state and recovery route are preserved. |
| `REJECTED` | Task cannot complete; lifecycle moves to `FAILED` unless governance or workflow policy defines another terminal rejected state. |

---

## Transition Rules

- A task may move to `IN_PROGRESS` only after required dependencies are satisfied or explicitly waived.
- A task may move to `COMPLETED` only after a validation decision of `ACCEPTED` or policy-permitted `ACCEPTED_WITH_LIMITATIONS`.
- `UNAVAILABLE`, `WAIVED`, `NOT_REQUIRED`, `STALE`, and `CANCELLED` must never be normalized to `PASSED`.
- A changed validation subject, version, dependency, environment, rule, or acceptance criterion invalidates affected evidence and moves its items to `STALE`.
- A validation retry or repair appends attempt history and preserves earlier failures and evidence.
- Validation decisions must reference the same request, execution, task, subject, and version held by the active state record.
- A lifecycle phase may not skip a required gate.
- A failed validation returns work to the earliest responsible phase.
- A blocker must identify the blocked condition, responsible phase, and next safe action.
- Recovery state must preserve the failed or interrupted state rather than overwriting it.
- Completion state must not erase limitations, failed checks, or unavailable validation.

---

## Single Ownership Rule

One owner may hold a task at a time.

Parallel work must be represented as separate tasks with explicit dependencies, routing records, and ownership boundaries.

---

## Reporting Rule

The State Manager must support accurate progress reporting.

A current-state report should be able to answer:

- what request is active,
- what phase is active,
- what workflow is selected,
- what task is current,
- what changed,
- what is blocked,
- what validation is required,
- what evidence exists,
- what remains uncertain,
- and what the next safe step is.

---

## Rule

> State must reflect verified progress, blockers, validation evidence, and limitations. A task or lifecycle phase cannot be marked complete merely because execution was attempted.
