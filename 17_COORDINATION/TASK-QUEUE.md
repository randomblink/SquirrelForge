# SquirrelForge Task Queue

Version: 1.0.0
Status: Stable
Owner: Coordination Maintainers
Depends On: `17_COORDINATION/PRIORITY-MANAGER.md`, `14_ENGINE/TASK-ROUTER.md`, `14_ENGINE/STATE-MANAGER.md`, `17_COORDINATION/HANDOFF-PROTOCOL.md`
Used By: Coordination, Task Router
Last Updated: 2026-07-05

## Purpose

The Task Queue holds and orders tasks that the Task Router has confirmed are ready for routing but do not yet have an available owner, and dequeues them in the correct order once one becomes available.

The Queue orders and dequeues. It does not decide task priority itself (owned by `17_COORDINATION/PRIORITY-MANAGER.md`), confirm dependency readiness or select an owner (owned by `14_ENGINE/TASK-ROUTER.md`), or track the task's overall lifecycle status (owned by `14_ENGINE/STATE-MANAGER.md`). The Queue's own state describes only an entry's position in this queue, not the task's broader lifecycle.

---

## Responsibilities

The Task Queue must:

- hold tasks the Task Router has confirmed are ready but not yet actively owned,
- order entries by the priority `17_COORDINATION/PRIORITY-MANAGER.md` assigns and the readiness the Task Router confirms,
- prevent duplicate entries for a task already queued or active,
- dequeue the next entry when an owner becomes available,
- hand off a dequeued task to `17_COORDINATION/HANDOFF-PROTOCOL.md`,
- and preserve queue entry history for audit.

---

## Inputs

The Queue should receive:

- the task, already confirmed ready by `14_ENGINE/TASK-ROUTER.md`,
- its assigned priority from `17_COORDINATION/PRIORITY-MANAGER.md`,
- and, when dequeuing, confirmation an owner is available.

A task must not be enqueued before the Task Router has confirmed its dependencies are satisfied.

---

## Outputs

The Queue should produce:

- the ordered set of queued entries,
- the dequeued task and its handoff to `17_COORDINATION/HANDOFF-PROTOCOL.md`,
- and a recorded queue event for each enqueue, dequeue, or rejected duplicate.

---

## Queue Position States

| State | Meaning |
|---|---|
| `QUEUED` | Entry is waiting in the queue for an available owner. |
| `DEQUEUED` | Entry was removed from the queue and handed to the Handoff Protocol. |
| `SKIPPED` | Entry was rejected as a duplicate of an already-queued or active task. |

These describe the entry's position in this queue only. The task's actual lifecycle status is tracked by `14_ENGINE/STATE-MANAGER.md`.

---

## Queue Record

| Field | Description |
|---|---|
| Queue Entry ID | Unique identifier for this queue entry. |
| Task ID | The task this entry represents. |
| Priority | Priority assigned by `17_COORDINATION/PRIORITY-MANAGER.md`. |
| Position State | `QUEUED`, `DEQUEUED`, or `SKIPPED`. |
| Enqueued At | Time the entry was added. |
| Dequeued At | Time the entry was removed, if applicable. |
| Handoff Reference | Reference to the `17_COORDINATION/HANDOFF-PROTOCOL.md` record, once dequeued. |

---

## Queue Process

1. Receive a task the Task Router has confirmed is ready for routing.
2. Read its assigned priority from the Priority Manager.
3. Reject the entry as `SKIPPED` if the task is already queued or active.
4. Insert the entry in priority and readiness order.
5. When an owner becomes available, dequeue the next entry.
6. Hand off the dequeued task to the Handoff Protocol.
7. Record the queue event.

---

## Permission Boundary

The Queue may order, enqueue, dequeue, and hand off entries.

It must not assign priority itself, confirm dependency readiness, select an owner, or track the task's overall lifecycle status — those remain owned by the Priority Manager, the Task Router, and the State Manager respectively.

---

## Domain Rule

Queue ordering applies identically regardless of domain; domain-specific context travels with the task itself, not with queue position.

---

## Rule

> A task may be dequeued only after the Task Router has confirmed its dependencies are satisfied and an owner is available. The Queue orders and dequeues; it does not decide priority, readiness, ownership, or lifecycle status itself.
