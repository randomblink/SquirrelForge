# SquirrelForge Failure Recovery

Version: 1.0.0
Status: Stable
Owner: Coordination Maintainers
Depends On: `14_ENGINE/STATE-MANAGER.md`, `14_ENGINE/TASK-ROUTER.md`, `14_ENGINE/EXECUTION-PLANNER.md`, `14_ENGINE/VALIDATION.md`, `17_COORDINATION/MESSAGE-BUS.md`, `17_COORDINATION/HANDOFF-PROTOCOL.md`
Used By: Coordination
Last Updated: 2026-07-05

## Purpose

Failure Recovery detects and classifies failures reported by other coordination and engine components, attempts a safe recovery strategy, and escalates when recovery is not possible, while preserving task integrity throughout.

Failure Recovery does not independently record task or lifecycle state (owned by `14_ENGINE/STATE-MANAGER.md`), does not select a reassignment target itself (owned by `14_ENGINE/TASK-ROUTER.md`), and does not define what counts as a validated checkpoint (owned by `14_ENGINE/EXECUTION-PLANNER.md`'s scheduled recovery checkpoints and `14_ENGINE/VALIDATION.md`'s evidence). It triggers these components rather than reimplementing their authority.

---

## Responsibilities

Failure Recovery must:

- detect failures reported by other components, including `17_COORDINATION/MESSAGE-BUS.md` delivery failures and `17_COORDINATION/HANDOFF-PROTOCOL.md` rejections that recur without resolution,
- classify the failure type,
- request the State Manager record `RECOVERY_REQUIRED` or `BLOCKED` state as appropriate, rather than tracking this independently,
- attempt a safe recovery strategy,
- request reassignment through the Task Router rather than selecting a new owner directly, when a strategy calls for reassignment,
- escalate unrecoverable failures per the Escalation Rules,
- prevent repeated failure loops by recognizing recurrence against prior Recovery Records,
- and record recovery actions for future learning.

---

## Inputs

Failure Recovery should receive:

- the failure and its originating component,
- current task and lifecycle state from `14_ENGINE/STATE-MANAGER.md`,
- and the most recent validated checkpoint from `14_ENGINE/EXECUTION-PLANNER.md` and `14_ENGINE/VALIDATION.md`.

A recovery attempt must not proceed against state it has not confirmed with the State Manager.

---

## Outputs

Failure Recovery should produce:

- a classified failure record,
- a requested state change, when one is warranted,
- a requested reassignment, when one is warranted,
- an escalation, when recovery is not possible,
- and a recorded Recovery Record.

---

## Failure Types

| Type | Description |
|---|---|
| Validation Failure | Validation checkpoint failed (`14_ENGINE/VALIDATION.md`). |
| Dependency Failure | Required dependency unavailable (`14_ENGINE/DEPENDENCY-ANALYZER.md`). |
| Workflow Failure | Workflow could not complete. |
| Agent Failure | Assigned agent unable to finish. |
| Communication Failure | Message Bus delivery failure or Handoff Protocol rejection that recurs without resolution. |
| Resource Failure | Missing file, service, or tool. |
| Unknown Failure | Cause not yet identified. |

---

## Recovery Process

1. Detect the failure, as reported by the originating component.
2. Pause execution.
3. Request the State Manager record the current state as `RECOVERY_REQUIRED`.
4. Record failure details.
5. Determine recovery strategy.
6. Retry if appropriate.
7. If reassignment is warranted, request the Task Router select a new owner.
8. Escalate if recovery fails, per the Escalation Rules.
9. Resume execution from the most recent checkpoint the Execution Planner scheduled and Validation confirmed.

---

## Recovery Strategies

- Retry the operation.
- Reload context.
- Reload dependencies.
- Request reassignment through the Task Router.
- Roll back to the previous validated state confirmed by the State Manager and Validation.
- Request human intervention.
- Request the State Manager mark the task `BLOCKED`.

---

## Recovery Record

| Field | Description |
|---|---|
| Recovery ID | Unique identifier. |
| Task ID | Related task. |
| Failure Type | Classification from the table above. |
| Recovery Strategy | Action taken. |
| Retry Count | Number of attempts. |
| Outcome | Recovered / Escalated / Blocked. |
| Notes | Additional details. |

---

## Escalation Rules

Escalate when:

- A retry limit is exceeded.
- A critical dependency cannot be resolved.
- Validation repeatedly fails.
- Security or data integrity is at risk.
- Human approval is required.

---

## Permission Boundary

Failure Recovery may detect, classify, attempt recovery strategies, request state changes, request reassignment, and escalate.

It must not independently record task or lifecycle state itself (owned by `14_ENGINE/STATE-MANAGER.md`), select a reassignment target itself (owned by `14_ENGINE/TASK-ROUTER.md`), or redefine what counts as a validated checkpoint (owned by `14_ENGINE/EXECUTION-PLANNER.md` and `14_ENGINE/VALIDATION.md`).

---

## Domain Rule

Failure classification and recovery strategy apply identically regardless of domain; domain-specific failure content travels with the task, not with the recovery mechanics themselves.

---

## Rule

> Recovery must always resume from the most recent state the State Manager and Validation confirm as valid. No recovery process may skip required validation steps, compromise project integrity, or bypass the Task Router when selecting a new owner.
