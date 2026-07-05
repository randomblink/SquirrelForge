# SquirrelForge Agent Delegation

Version: 1.0.0
Status: Stable
Owner: Agent Maintainers
Depends On: `14_ENGINE/TASK-ROUTER.md`, `17_COORDINATION/HANDOFF-PROTOCOL.md`, `16_AGENTS/AGENT-LIFECYCLE.md`
Used By: Coordination, Task Router, Governance
Last Updated: 2026-07-05

## Purpose

Agent Delegation defines when an agent is authorized to hand part of its own work to another agent, which delegation types are valid, and what accountability must be preserved when it does.

Delegation defines authorization and accountability rules. It does not locate or select the receiving agent — capability matching and owner selection are performed by `14_ENGINE/TASK-ROUTER.md` — and it does not execute the handoff itself; the actual context transfer and acceptance confirmation are performed by `17_COORDINATION/HANDOFF-PROTOCOL.md`.

---

## Responsibilities

Agent Delegation must:

- determine whether the delegating agent is authorized to delegate this work at all,
- classify the delegation type,
- define the accountability that must be preserved once work is delegated,
- confirm the receiving agent is in an eligible lifecycle state per `16_AGENTS/AGENT-LIFECYCLE.md` before delegation proceeds,
- request capability matching and owner selection from `14_ENGINE/TASK-ROUTER.md` rather than selecting a receiving agent itself,
- request the actual handoff from `17_COORDINATION/HANDOFF-PROTOCOL.md`,
- record the delegation decision and outcome,
- and require alternative handling when a delegation is rejected rather than leaving the work unowned.

---

## Inputs

Delegation should receive:

- the delegating agent's role and authority,
- the work to be delegated and why,
- the delegation type being requested,
- the receiving agent's lifecycle state from `16_AGENTS/AGENT-LIFECYCLE.md`,
- and applicable escalation or fallback conditions.

A delegation must not be authorized without confirming the delegating agent actually holds the authority to delegate this class of work.

---

## Outputs

Delegation should produce:

- the authorization decision,
- the delegation type,
- a request to `14_ENGINE/TASK-ROUTER.md` for capability matching and owner selection,
- a request to `17_COORDINATION/HANDOFF-PROTOCOL.md` for the actual handoff,
- the delegation record,
- and, if rejected, the required alternative handling.

---

## Delegation Process

1. Confirm the delegating agent is authorized to delegate this work.
2. Classify the delegation type.
3. Confirm the intended receiving agent (or an agent class, if unspecified) is in an eligible lifecycle state.
4. Request capability matching and owner selection from `14_ENGINE/TASK-ROUTER.md`.
5. Request the handoff from `17_COORDINATION/HANDOFF-PROTOCOL.md` once an owner is selected.
6. Record the delegation decision, receiving agent, and outcome.
7. If the delegation is rejected or the receiving agent does not accept, require alternative handling rather than leaving the work unowned.

---

## Delegation Types

| Type | Description |
|---|---|
| Direct | One agent delegates directly to another at the same authority level. |
| Hierarchical | Delegation through a supervisory structure (for example, a lead agent to a subordinate). |
| Collaborative | Work is shared under a `16_AGENTS/AGENT-COLLABORATION.md` structure rather than fully transferred. |
| Escalation | Delegation to a higher-authority agent or to Governance. |
| Fallback | Delegation after the primary agent becomes unavailable or fails, per `16_AGENTS/AGENT-LIFECYCLE.md`. |

---

## Delegation Record

| Field | Description |
|---|---|
| Delegation ID | Unique identifier. |
| Task Reference | The work being delegated. |
| Delegating Agent | Original owner and its authority for this delegation. |
| Delegation Type | Category from the table above. |
| Receiving Agent | Agent selected by `14_ENGINE/TASK-ROUTER.md`, once known. |
| Authorization | Approved / Denied, with rationale. |
| Handoff Reference | Reference to the `17_COORDINATION/HANDOFF-PROTOCOL.md` record. |
| Status | Requested / Authorized / Handed Off / Accepted / Rejected. |
| Timestamp | Delegation decision time. |

---

## Acceptance Criteria

A delegation may proceed only when:

- the delegating agent holds the authority to delegate this class of work,
- the delegation type is valid for the situation,
- the receiving agent (once selected by the Task Router) is in an eligible lifecycle state,
- the accountability for the outcome remains traceable to a single owner at a time, per `16_AGENTS/README.md`'s Ownership Rule,
- and the receiving agent explicitly accepts the handoff through `17_COORDINATION/HANDOFF-PROTOCOL.md`.

---

## Delegation Principles

- Delegation authority is distinct from execution authority; an agent capable of doing work is not automatically authorized to delegate it.
- Capability matching and owner selection are the Task Router's job, not Delegation's.
- Responsibility remains traceable through every delegation.
- A rejected delegation requires defined alternative handling, not silent abandonment.
- Completion status returns to the originating workflow regardless of how many delegations occurred along the way.

---

## Permission Boundary

Delegation may authorize or deny a delegation request, classify its type, and confirm accountability and lifecycle conditions.

It must not select the receiving agent (owned by `14_ENGINE/TASK-ROUTER.md`) or execute the handoff itself (owned by `17_COORDINATION/HANDOFF-PROTOCOL.md`).

---

## Domain Rule

For WordPress work, delegation to a domain specialist requires that specialist have access to applicable `38_WORDPRESS` context; this does not change the authorization or accountability rules above.

---

## Handoff Rule

Delegation's request to the Task Router and Handoff Protocol must include:

- the work being delegated and its required capability,
- the delegation type and authorization rationale,
- accountability requirements,
- and any escalation or fallback condition that triggered the delegation.

A request is incomplete if the Task Router cannot determine what capability is required, or the Handoff Protocol cannot determine what context must be preserved.

---

## Rule

> No task may be delegated unless the delegating agent is authorized to delegate it, the receiving agent is selected through the Task Router and explicitly accepts responsibility through the Handoff Protocol, and the delegation is fully recorded for audit and traceability.
