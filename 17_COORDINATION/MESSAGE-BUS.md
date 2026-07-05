# SquirrelForge Message Bus

Version: 1.0.0
Status: Stable
Owner: Coordination Maintainers
Depends On: `36_COMMUNICATION`, `16_AGENTS/AGENT-COMMUNICATION.md`, `17_COORDINATION/FAILURE-RECOVERY.md`, `14_ENGINE/STATE-MANAGER.md`
Used By: Coordination, Agents
Last Updated: 2026-07-05

## Purpose

The Message Bus delivers pipeline stage-to-stage messages — task assignment, status updates, validation results, review feedback, dependency alerts, error reports, and completion notices — between agents, preserving execution context and task correlation across a handoff.

The Bus rides on `36_COMMUNICATION`'s underlying transport, message validation, and security rather than re-implementing them. It does not update task lifecycle state itself — whichever component actually processes a delivered message (for example `14_ENGINE/STATE-MANAGER.md` for a Status Update) is responsible for any resulting state change. It does not retry or escalate failed deliveries itself either; that is `17_COORDINATION/FAILURE-RECOVERY.md`'s job.

---

## Responsibilities

The Message Bus must:

- deliver pipeline stage-to-stage messages between agents,
- preserve execution context and task correlation across a handoff,
- confirm a message carries its type's required fields, including a Task ID for task-related messages, before handing it to `36_COMMUNICATION` for transport and deeper validation,
- record message history for traceability,
- require explicit acknowledgment for Critical-priority messages,
- and hand a failed delivery to `17_COORDINATION/FAILURE-RECOVERY.md` rather than retrying or escalating independently.

---

## Inputs

The Bus should receive:

- sender and recipient agent,
- the message type,
- the payload,
- a Task ID for task-related messages,
- and an assigned priority.

A message missing its type's required fields must not be handed off as if it were already routing-ready.

---

## Outputs

The Bus should produce:

- the delivered message and its receipt confirmation,
- recorded message history,
- and, for a failed delivery, a handoff to `17_COORDINATION/FAILURE-RECOVERY.md`.

---

## Message Types

| Type | Purpose |
|---|---|
| Task Assignment | Assign work to an agent. |
| Status Update | Report execution progress. |
| Validation Result | Report validation outcome. |
| Review Feedback | Request revisions or approve work. |
| Dependency Alert | Report missing or conflicting dependencies. |
| Error Report | Report execution failure. |
| Completion Notice | Notify task completion. |

These are pipeline-specific message types, distinct from the general agent-participation categories (Request, Response, Notification, Command, Status, Event, Alert) defined in `16_AGENTS/AGENT-COMMUNICATION.md`. A pipeline message may map to one of those general categories for participation-rule purposes while still using its more specific type here.

---

## Message Format

| Field | Description |
|---|---|
| Message ID | Unique identifier. |
| Timestamp | Time sent. |
| Sender | Originating agent. |
| Recipient | Destination agent. |
| Message Type | Classification from the table above. |
| Task ID | Related task. |
| Payload | Message content. |
| Priority | Low / Medium / High / Critical. |

`36_COMMUNICATION/MESSAGE-VALIDATOR.md` enforces integrity and authorization on this format; the Bus confirms only that routing-required fields are present.

---

## Messaging Process

1. Create the message in the format above.
2. Confirm the message's type carries its required fields, including Task ID for task-related messages.
3. Hand off to `36_COMMUNICATION` for transport, deeper validation, and delivery.
4. Confirm receipt; for Critical priority, require explicit acknowledgment.
5. Record the message in history.
6. If delivery fails, hand off to `17_COORDINATION/FAILURE-RECOVERY.md` rather than retrying independently here.

---

## Delivery Rules

- Every message must have a sender and recipient.
- Every task-related message must include a Task ID.
- Critical messages require explicit acknowledgment.
- Failed deliveries are handed to Failure Recovery rather than retried independently by the Bus.

---

## Permission Boundary

The Bus may create, route, and record pipeline messages, and confirm routing-required fields are present.

It must not perform deep message validation or security enforcement (owned by `36_COMMUNICATION/MESSAGE-VALIDATOR.md`), update task lifecycle state (owned by `14_ENGINE/STATE-MANAGER.md`), or retry or escalate a failed delivery itself (owned by `17_COORDINATION/FAILURE-RECOVERY.md`).

---

## Domain Rule

Message delivery mechanics apply identically regardless of domain; domain-specific content travels in the payload, not in the Bus's own behavior.

---

## Rule

> No task state may change merely because a message was sent. State changes only when the receiving component processes a successfully delivered and, where required, acknowledged message — and a failed delivery is handed to Failure Recovery rather than silently dropped or retried ad hoc.
