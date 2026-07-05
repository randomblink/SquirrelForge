# SquirrelForge Handoff Protocol

Version: 1.0.0
Status: Stable
Owner: Coordination Maintainers
Depends On: `17_COORDINATION/MESSAGE-BUS.md`, `14_ENGINE/VALIDATION.md`, `14_ENGINE/STATE-MANAGER.md`, `17_COORDINATION/FAILURE-RECOVERY.md`
Used By: Task Router, Delegation, Coordination
Last Updated: 2026-07-05

## Purpose

The Handoff Protocol executes the actual transfer of task ownership from one agent to the next, preserving execution context, current validation status, and progress, and confirming the receiving agent explicitly accepts before ownership changes.

The Protocol executes a handoff whose participants are already decided elsewhere — by `14_ENGINE/TASK-ROUTER.md`, `16_AGENTS/AGENT-DELEGATION.md`, or the current pipeline stage's own declared `next_stage`. It does not decide who the next owner is, does not independently define validation status (owned by `14_ENGINE/VALIDATION.md`), and does not update the task's lifecycle status on its own authority (owned by `14_ENGINE/STATE-MANAGER.md`) — it reads and carries these forward, and confirms the receiving agent got them.

---

## Responsibilities

The Handoff Protocol must:

- transfer task ownership only after the receiving agent explicitly accepts,
- preserve execution context, current validation status, and progress state across the transfer,
- attach required artifacts,
- prevent duplicate work by confirming only one agent owns the task at a time,
- send the handoff through `17_COORDINATION/MESSAGE-BUS.md`,
- confirm receipt and acceptance,
- record the completed or rejected handoff,
- and, if the receiving agent rejects the handoff, return ownership to the current agent and record the rejection reason.

---

## Inputs

The Protocol should receive:

- the task and its already-determined next owner,
- the current task status from `14_ENGINE/STATE-MANAGER.md`,
- the current validation status from `14_ENGINE/VALIDATION.md`,
- and the artifacts and context to carry forward.

A handoff must not proceed without an already-determined next owner; selecting one is not this document's job.

---

## Outputs

The Protocol should produce:

- the handoff message sent through the Message Bus,
- the acceptance or rejection outcome,
- the completed ownership transfer, when accepted,
- and a recorded handoff event.

---

## Handoff Process

1. Confirm the receiving agent has already been determined by the Task Router, Delegation, or the current stage's own `next_stage`.
2. Read the current task status and validation status from the State Manager and Engine Validation rather than redefining them.
3. Attach required artifacts and context.
4. Send the handoff message through `17_COORDINATION/MESSAGE-BUS.md`.
5. Receive acceptance or rejection from the next agent.
6. If accepted, transfer ownership and record the completed handoff.
7. If rejected, return ownership to the current agent, record the rejection reason, correct the identified issues, and repeat; escalate to `17_COORDINATION/FAILURE-RECOVERY.md` if rejection recurs without resolution.

---

## Required Handoff Information

| Field | Description |
|---|---|
| Task ID | Unique task identifier. |
| Current Agent | Agent completing the work. |
| Next Agent | Receiving agent, already determined before the handoff begins. |
| Task Status | Current status per `14_ENGINE/STATE-MANAGER.md`. |
| Validation Status | Current status per `14_ENGINE/VALIDATION.md`'s Validation States. |
| Artifacts | Files, documentation, or outputs. |
| Notes | Additional context. |

---

## Handoff Validation

Before ownership changes:

- [ ] Current task status confirmed from the State Manager.
- [ ] Current validation status confirmed from Engine Validation.
- [ ] Required artifacts included.
- [ ] Context preserved.
- [ ] Next agent already determined.
- [ ] Acceptance received from the next agent.

---

## Failure Handling

If the receiving agent rejects the handoff:

1. Return ownership to the current agent.
2. Record the rejection reason.
3. Correct the identified issues.
4. Repeat the handoff process.
5. If rejection recurs without resolution, escalate to `17_COORDINATION/FAILURE-RECOVERY.md`.

---

## Permission Boundary

The Protocol may execute the transfer, confirm acceptance, and record the outcome.

It must not select the receiving agent, independently define validation status, or update task lifecycle state on its own vocabulary — it triggers the State Manager to record the ownership change rather than maintaining a separate status of its own.

---

## Domain Rule

Handoff mechanics apply identically regardless of domain; domain-specific context travels in the artifacts and notes, not in the Protocol's own behavior.

---

## Rule

> Task ownership changes only after the receiving agent explicitly accepts a complete handoff carrying current status, validation state, and required artifacts. The Protocol executes this transfer — it does not decide who receives it or redefine the status it carries.
