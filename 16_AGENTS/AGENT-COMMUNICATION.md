# SquirrelForge Agent Communication

Version: 1.0.0
Status: Stable
Owner: Agent Maintainers
Depends On: `16_AGENTS/README.md`, `17_COORDINATION/MESSAGE-BUS.md`, `36_COMMUNICATION`
Used By: Coordination, Communication Layer, Governance
Last Updated: 2026-07-05

## Purpose

Agent Communication defines the participation rules for how specialist agents exchange information: which agent roles may send and receive which message types, what content each message type must carry to preserve handoff integrity, and role-level priority guidance.

Communication defines these rules. It does not implement message transport, routing, queueing, validation, retries, or security — those are owned by `36_COMMUNICATION` (Message Broker, Message Queue Manager, Message Validator, Communication Governance, Agent Communicator). Pipeline- and task-specific message types and delivery guarantees between pipeline stages are owned by `17_COORDINATION/MESSAGE-BUS.md`. This document defines what agents must say and to whom; those layers define how it's actually delivered.

---

## Responsibilities

Agent Communication must:

- define which agent roles may send and receive each message type,
- define the required content for each message type so a handoff preserves context, decisions, and evidence,
- define role-level priority and escalation guidance,
- identify when a communication must route through Governance or Security before proceeding,
- and hand off actual delivery to `36_COMMUNICATION`'s Agent Communicator, or to `17_COORDINATION/MESSAGE-BUS.md` for pipeline stage-to-stage messages.

---

## Inputs

Communication should receive:

- the sending agent's role and the message it needs to send,
- the intended recipient role,
- the applicable message type,
- and any governance or security conditions relevant to the content.

A message type with no defined required content or permitted sender/recipient pair must not be sent as if it were already a defined participation rule.

---

## Outputs

Communication should produce:

- the participation rule applicable to the message (permitted sender, recipient, required content),
- the assigned priority,
- a flag when governance or security review is required before delivery,
- and a handoff to `36_COMMUNICATION` or `17_COORDINATION/MESSAGE-BUS.md` for actual delivery.

---

## Communication Process

1. Identify the sending agent's role and the message type it needs to send.
2. Confirm the recipient role is a permitted recipient for that message type.
3. Confirm the message carries the required content for its type.
4. Assign priority per the role-level guidance below.
5. Flag the message for Governance or Security review when the content requires it.
6. Hand off the message to `36_COMMUNICATION`'s Agent Communicator, or to `17_COORDINATION/MESSAGE-BUS.md` for a pipeline stage handoff.

---

## Message Types

| Type | Description | Required Content |
|---|---|---|
| Request | Request an action or information from another role. | Requesting role, requested action or information, why it's needed. |
| Response | Reply to a previous request. | Correlation to the original request, the answer or result. |
| Notification | Informational update with no required response. | What changed and why it's relevant to the recipient. |
| Command | Instruction requiring execution within the recipient's permission boundary. | The instruction, its scope, and the permission it relies on. |
| Status | Operational state update. | Current state and what changed since the last update. |
| Event | Published operational event. | What occurred and any affected agents or tasks. |
| Alert | High-priority notification requiring attention. | The condition, its severity, and the required response. |

---

## Message Priorities

| Priority | Description |
|---|---|
| Low | Background communication with no material urgency. |
| Normal | Standard operational message. |
| High | Time-sensitive request; delayed handling has material impact. |
| Critical | Immediate attention required; delivery must not be queued behind lower-priority work. |

---

## Participation Rules

| Field | Description |
|---|---|
| Message Type | Category from the Message Types table. |
| Permitted Senders | Agent roles allowed to originate this message type. |
| Permitted Recipients | Agent roles allowed to receive this message type. |
| Required Content | Minimum content the message must carry. |
| Priority Guidance | Default priority unless the situation requires otherwise. |
| Governance Flag | Whether this message type requires Governance or Security review before delivery. |

---

## Communication Principles

- Every message has a defined sender role, recipient role, and required content before it is sent.
- Priority reflects actual urgency, not habitual escalation.
- Messages that touch security, governance, or production impact are flagged for the applicable review before delivery.
- Communication history, delivery, and acknowledgment are the receiving infrastructure's responsibility, not this document's.

---

## Permission Boundary

Communication may define participation rules, required content, priority guidance, and governance flags.

It must not implement message transport, routing, queueing, validation, retries, or security enforcement — those remain owned by `36_COMMUNICATION` and, for pipeline stage messages, `17_COORDINATION/MESSAGE-BUS.md`.

---

## Domain Rule

For WordPress work, messages between agents may reference applicable `38_WORDPRESS` context, but the participation rules above apply identically regardless of domain.

---

## Handoff Rule

Communication's handoff to the delivering layer must include:

- sender and recipient roles,
- message type,
- required content per the table above,
- assigned priority,
- and whether governance or security review is required before delivery.

A handoff is incomplete if the delivering layer cannot determine who the message is from, who it's for, or what it must contain.

---

## Rule

> Every message between agent roles must have a permitted sender, a permitted recipient, and its type's required content before it is handed off for delivery. Agent Communication defines these participation rules; `36_COMMUNICATION` and `17_COORDINATION/MESSAGE-BUS.md` deliver, queue, validate, and secure the message itself.
