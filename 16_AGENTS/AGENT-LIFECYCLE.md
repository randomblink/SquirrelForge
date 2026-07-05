# SquirrelForge Agent Lifecycle Manager

Version: 1.0.0
Status: Stable
Owner: Agent Maintainers
Depends On: `16_AGENTS/AGENT-MANAGER.md`, `14_ENGINE/STATE-MANAGER.md`
Used By: Agent Manager, Monitor, Governance
Last Updated: 2026-07-05

## Purpose

The Agent Lifecycle Manager owns the operational state machine for AI agent entities themselves — their creation, activation, operation, suspension, retirement, and archival — and validates and records every transition between those states.

This is distinct from `11_OVERVIEW/LIFECYCLE.md`, which governs the lifecycle of a single request as it moves through the system (intake through retention). The Agent Lifecycle Manager governs the lifecycle of an agent entity across many requests, from registration to archival.

The Lifecycle Manager owns the state machine and transition validity. It does not own agent registration bookkeeping, routing, or coordination — those remain with the Agent Manager, which enforces eligibility based on the state the Lifecycle Manager reports.

---

## Responsibilities

The Agent Lifecycle Manager must:

- define the valid operational states an agent entity may occupy,
- define which state transitions are valid,
- validate every requested transition before it takes effect,
- reject invalid transitions rather than silently allowing them,
- record every transition with its requester, timestamp, and validation result,
- publish the agent's current lifecycle state for the Agent Manager and Monitor to consume,
- and preserve archived records as immutable history.

---

## Inputs

The Lifecycle Manager should receive:

- the agent's current lifecycle state,
- the requested transition and requesting owner,
- applicable governance or suspension conditions from Governance or the Monitor,
- and any evidence required to validate the transition (for example, a completed registration before activation).

A transition without a current state on record must not be validated as if the agent were already known-good.

---

## Outputs

The Lifecycle Manager should produce:

- the transition's validation result,
- the agent's new lifecycle state, when valid,
- a recorded lifecycle event,
- and a rejection reason, when invalid.

---

## Lifecycle Process

1. Receive the lifecycle transition request and requesting owner.
2. Identify the target agent and its current recorded state.
3. Validate that the requested transition is allowed from the current state.
4. Reject the request if the transition is not in the valid transition table, recording the rejection reason.
5. Apply the transition and update the agent's lifecycle state.
6. Record the lifecycle event.
7. Publish the updated state for the Agent Manager and Monitor.

---

## Lifecycle States

| State | Description |
|---|---|
| `DRAFT` | Under development; not yet registered. |
| `REGISTERED` | Added to the agent registry; not yet initialized. |
| `INITIALIZED` | Configuration completed; not yet activated. |
| `ACTIVE` | Available for work assignment. |
| `BUSY` | Currently executing assigned work. |
| `SUSPENDED` | Temporarily unavailable; cannot receive new work. |
| `MAINTENANCE` | Under maintenance or upgrade; cannot receive new work. |
| `RETIRED` | Removed from operational use; cannot be reactivated. |
| `ARCHIVED` | Historical record retained; immutable. |

---

## Valid Lifecycle Transitions

| From | To |
|---|---|
| `DRAFT` | `REGISTERED` |
| `REGISTERED` | `INITIALIZED` |
| `INITIALIZED` | `ACTIVE` |
| `ACTIVE` | `BUSY` |
| `BUSY` | `ACTIVE` |
| `ACTIVE` | `SUSPENDED` |
| `SUSPENDED` | `ACTIVE` |
| `ACTIVE` | `MAINTENANCE` |
| `MAINTENANCE` | `ACTIVE` |
| `ACTIVE` | `RETIRED` |
| `RETIRED` | `ARCHIVED` |

Any transition not listed here is invalid and must be rejected.

---

## Lifecycle Record

| Field | Description |
|---|---|
| Lifecycle ID | Unique identifier for the transition event. |
| Agent ID | Associated agent. |
| Previous State | State prior to the transition. |
| New State | State after the transition. |
| Requested By | User, workflow, agent, or system that requested the transition. |
| Timestamp | Transition time. |
| Validation | `Passed` or `Rejected`, with reason if rejected. |

---

## Lifecycle Principles

- Every lifecycle transition must be validated against the transition table before it takes effect.
- Invalid state transitions are rejected, not silently coerced to the nearest valid state.
- `SUSPENDED`, `MAINTENANCE`, and `RETIRED` agents cannot receive new work.
- `RETIRED` agents cannot be reactivated; a retired agent that must return to service is re-registered as a new agent entity.
- `ARCHIVED` records are immutable.

---

## Permission Boundary

The Lifecycle Manager may validate transitions, apply valid state changes, and record lifecycle events.

It must not register new agents, route work, or make eligibility decisions itself — those remain owned by the Agent Manager, which consults the Lifecycle Manager's recorded state rather than duplicating it.

---

## Domain Rule

Lifecycle states and transitions are domain-independent and apply identically to WordPress and non-WordPress agent work.

---

## Handoff Rule

The Lifecycle Manager's published state update must include:

- the agent's new lifecycle state,
- the transition that produced it,
- validation result and rejection reason if applicable,
- and the timestamp.

An update is incomplete if the Agent Manager cannot determine whether the agent is currently eligible for work.

---

## Rule

> Every AI agent entity must occupy exactly one lifecycle state at a time, and every transition between states must be validated against the transition table and recorded before the new state takes effect. Invalid transitions are rejected, never silently applied.
