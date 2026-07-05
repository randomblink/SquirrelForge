# SquirrelForge Agent Manager

Version: 1.0.0
Status: Stable
Owner: Agent Maintainers
Depends On: `16_AGENTS/AGENT-REGISTRY.md`, `16_AGENTS/AGENT-SPECIALIZATION.md`, `16_AGENTS/AGENT-LIFECYCLE.md`, `16_AGENTS/AGENT-MONITOR.md`, `16_AGENTS/AGENT-DELEGATION.md`, `14_ENGINE/TASK-ROUTER.md`
Used By: Coordination, Task Router, Governance
Last Updated: 2026-07-05

## Purpose

The Agent Manager is the aggregation point for assigning work to an agent: it confirms the agent is registered, specialized for the work, in an eligible lifecycle state, and healthy enough to accept it, before the assignment proceeds.

The Manager consults each of those authorities rather than owning any of them itself: identity and capability profile belong to `16_AGENTS/AGENT-REGISTRY.md`, domain matching to `16_AGENTS/AGENT-SPECIALIZATION.md`, operational state to `16_AGENTS/AGENT-LIFECYCLE.md`, health to `16_AGENTS/AGENT-MONITOR.md`, delegation authorization to `16_AGENTS/AGENT-DELEGATION.md`, and the actual capability-based routing decision to `14_ENGINE/TASK-ROUTER.md`. The Manager blocks assignment when any of these checks fails; it does not perform the check itself.

---

## Responsibilities

The Agent Manager must:

- accept a work-assignment request, whether direct or delegated from another agent,
- confirm the candidate agent is listed in the Agent Registry,
- confirm the agent's specialization actually matches the requested work, via Specialization,
- confirm the agent's lifecycle state is eligible for new work, via the Lifecycle Manager,
- confirm the agent's health status permits new work, via the Monitor,
- confirm delegation authorization when the assignment originates as a delegation, via Delegation,
- hand off the actual routing decision to `14_ENGINE/TASK-ROUTER.md`,
- record the assignment outcome,
- and report aggregate agent status across the checks above.

---

## Inputs

The Manager should receive:

- the work to be assigned,
- the candidate agent or agent class,
- current records from the Registry, Specialization, Lifecycle Manager, Monitor, and Delegation for that agent,
- and, for delegated assignments, the Delegation authorization record.

An assignment must not proceed on a stale or assumed status from any of these sources — each must be current at the time of assignment.

---

## Outputs

The Manager should produce:

- the aggregate assignment decision (proceed, reject, or refer),
- the specific check that failed, when rejected,
- a handoff to `14_ENGINE/TASK-ROUTER.md` when all checks pass,
- and a recorded assignment outcome.

---

## Agent Management Process

1. Receive the assignment request, direct or delegated.
2. Confirm the candidate agent is listed in the Agent Registry; reject if not.
3. Confirm specialization match via `16_AGENTS/AGENT-SPECIALIZATION.md`; follow its Collaboration or escalation outcome if there is no clean match.
4. Confirm lifecycle eligibility via `16_AGENTS/AGENT-LIFECYCLE.md`; reject if the agent is not in an eligible state.
5. Confirm health status via `16_AGENTS/AGENT-MONITOR.md`; reject or flag per the agent's current health classification.
6. If the assignment is a delegation, confirm authorization via `16_AGENTS/AGENT-DELEGATION.md`.
7. Hand off to `14_ENGINE/TASK-ROUTER.md` for the actual routing decision.
8. Record the assignment outcome and update aggregate agent status.

---

## Agent Record

| Field | Description |
|---|---|
| Assignment ID | Unique identifier for this assignment decision. |
| Agent ID | Per `16_AGENTS/AGENT-REGISTRY.md`. |
| Requested Work | The work being assigned. |
| Specialization Match | Outcome from `16_AGENTS/AGENT-SPECIALIZATION.md`. |
| Lifecycle State | Current state from `16_AGENTS/AGENT-LIFECYCLE.md`. |
| Health Status | Current status from `16_AGENTS/AGENT-MONITOR.md`. |
| Delegation Reference | Applicable `16_AGENTS/AGENT-DELEGATION.md` record, if delegated. |
| Assignment Outcome | Proceeded / Rejected / Referred, with the failing check if rejected. |
| Timestamp | Decision time. |

The authoritative roster of agent roles is `16_AGENTS/README.md`'s Role Categories, matched through Specialization — this document does not maintain a separate category list.

---

## Operational Principles

- Every assignment is checked against Registry, Specialization, Lifecycle, and Monitor status before it proceeds — none of these checks is assumed from a role's name alone.
- Assignment decisions and their basis are recorded and auditable.
- A rejected assignment records which specific check failed, not just that it failed.
- The Manager aggregates; it does not duplicate the authority of the components it consults.

---

## Permission Boundary

The Manager may accept, reject, or refer an assignment request based on the checks above, and hand off a passing assignment to the Task Router.

It must not register agents, transition lifecycle state, classify health, match specialization, authorize delegation, define collaboration structure, or perform the capability-based routing decision itself — those remain owned by the Registry, Lifecycle Manager, Monitor, Specialization, Delegation, Collaboration, and Task Router respectively.

---

## Domain Rule

For WordPress work, the candidate agent's specialization match must include applicable `38_WORDPRESS` context per `16_AGENTS/AGENT-SPECIALIZATION.md`.

For non-WordPress work, WordPress-specific specialization must not be assumed.

---

## Rule

> No task may be assigned to an agent until it is confirmed registered, specialized for the work, in an eligible lifecycle state, and healthy enough to accept it. The Agent Manager aggregates these checks and hands off to the Task Router — it does not perform any of them itself.
