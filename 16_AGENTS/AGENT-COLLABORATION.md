# SquirrelForge Agent Collaboration

Version: 1.0.0
Status: Stable
Owner: Agent Maintainers
Depends On: `16_AGENTS/AGENT-PLANNER.md`, `16_AGENTS/README.md`, `17_COORDINATION`
Used By: Coordination, Planner, Governance
Last Updated: 2026-07-05

## Purpose

Agent Collaboration defines the structure of multi-agent cooperative work: the shared objective, the collaboration model, the participating agents and their roles, and the participation and ownership rules they operate under.

Collaboration defines structure. It does not perform synchronization, shared-context management, message passing, task scheduling, or conflict resolution itself — those are owned by `17_COORDINATION` (Message Bus, Task Queue, Handoff Protocol, Priority Manager, Progress Tracker, Conflict Resolution), which executes against the structure Collaboration defines.

---

## Responsibilities

Agent Collaboration must:

- define the shared objective and scope for a multi-agent effort,
- select the collaboration model appropriate to the work,
- identify participating agents and assign each a role and ownership boundary,
- enforce the one-owner-at-a-time participation rule from `16_AGENTS/README.md`,
- define escalation criteria for what counts as a collaboration conflict requiring `17_COORDINATION`'s Conflict Resolution process,
- record the collaboration structure,
- and hand off that structure to `17_COORDINATION` for actual scheduling, synchronization, and execution.

---

## Inputs

Collaboration should receive:

- the shared objective or goal,
- the role assignment plan from the Agent Planner, when one exists,
- candidate participating agents and their capabilities,
- applicable ownership and permission rules,
- and known risk or escalation conditions.

A collaboration structure must not be defined without an identified shared objective and at least one participating agent with a defined role.

---

## Outputs

Collaboration should produce:

- the selected collaboration model,
- the participating agents and their assigned roles and ownership boundaries,
- escalation criteria for conflict handling,
- a recorded collaboration structure,
- and a handoff to `17_COORDINATION` for execution.

---

## Collaboration Process

1. Receive the shared objective and, when available, the Planner's role assignment plan.
2. Select the collaboration model appropriate to the work.
3. Identify participating agents and assign each a role and ownership boundary.
4. Confirm participation follows the one-owner-at-a-time rule; support does not transfer ownership without a recorded handoff.
5. Define escalation criteria for what constitutes a collaboration conflict.
6. Record the collaboration structure.
7. Hand off the structure to `17_COORDINATION` for scheduling, synchronization, and execution.

---

## Collaboration Models

| Model | Description |
|---|---|
| Sequential | Agents execute in defined order. |
| Parallel | Agents execute simultaneously under `17_COORDINATION`'s dependency-aware scheduling. |
| Hierarchical | A lead agent coordinates subordinate agents. |
| Consensus | Multiple agents contribute to a shared decision. |
| Specialist Team | Agents contribute domain-specific expertise to a shared objective. |

---

## Collaboration Record

| Field | Description |
|---|---|
| Collaboration ID | Unique identifier. |
| Objective | Shared task or goal. |
| Collaboration Model | Selected structure from the table above. |
| Lead Agent | Coordinating agent, when the model requires one. |
| Participating Agents | Contributing agents and their assigned roles. |
| Ownership Boundaries | What each participant owns and must not own. |
| Escalation Criteria | Conditions that count as a collaboration conflict. |
| Handoff | Confirmation the structure was handed off to `17_COORDINATION`. |

---

## Escalation Criteria

Collaboration defines, but does not itself resolve, what counts as a conflict requiring `17_COORDINATION`'s Conflict Resolution process:

- conflicting outputs from participating agents,
- disputed ownership of the same work,
- incompatible technical approaches,
- a security, performance, or documentation concern raised by one participant against another's work,
- or an unresolved dependency between participants.

`17_COORDINATION/CONFLICT-RESOLUTION.md` detects, classifies, and resolves conflicts meeting this criteria; Collaboration only defines what should trigger that process.

---

## Collaboration Principles

- Shared objectives are explicitly defined before collaborative work begins.
- Roles, ownership boundaries, and responsibilities remain clear and individually traceable.
- One agent owns a given piece of work at a time; support does not transfer ownership without a recorded handoff.
- Escalation criteria are defined before execution, not improvised during a conflict.
- Collaboration history is preserved.

---

## Permission Boundary

Collaboration may define structure, roles, ownership boundaries, and escalation criteria, and may hand that structure to `17_COORDINATION`.

It must not perform synchronization, shared-context management, message passing, task scheduling, or conflict resolution itself — those remain owned by `17_COORDINATION`.

---

## Domain Rule

For WordPress work, participating agents must have access to applicable `38_WORDPRESS` references relevant to their assigned role.

For non-WordPress work, WordPress-specific role assignments must not be assumed.

---

## Handoff Rule

Collaboration's handoff to `17_COORDINATION` must include:

- the shared objective,
- the selected collaboration model,
- participating agents and their roles and ownership boundaries,
- escalation criteria,
- and known dependencies or risks between participants.

A handoff is incomplete if `17_COORDINATION` cannot determine who owns what, or what should trigger conflict resolution.

---

## Rule

> Every collaborative task must have a defined shared objective, collaboration model, participating agents with clear ownership boundaries, and escalation criteria before collaborative execution begins. Collaboration defines this structure; `17_COORDINATION` executes, synchronizes, and resolves conflicts against it.
