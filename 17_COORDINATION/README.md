# SquirrelForge Coordination Layer

Version: 1.0.0
Status: Stable
Owner: Coordination Maintainers
Depends On: `14_ENGINE/TASK-ROUTER.md`, `16_AGENTS/AGENT-COLLABORATION.md`, `16_AGENTS/AGENT-DELEGATION.md`, `36_COMMUNICATION`, `20_EXECUTION`
Used By: Agents, Execution, Reporting
Last Updated: 2026-07-05

## Purpose

The Coordination Layer executes the mechanics of getting already-decided work actually done by multiple owners: queuing, prioritizing, delivering pipeline messages, executing handoffs, tracking progress, resolving conflicts, and recovering from failure.

Coordination executes structure and decisions made elsewhere. It does not decide who is authorized to delegate, what a collaboration should look like, or which agent is capable of a task — it carries out what `16_AGENTS/AGENT-COLLABORATION.md`, `16_AGENTS/AGENT-DELEGATION.md`, and `14_ENGINE/TASK-ROUTER.md` have already decided.

---

## Layer Boundary

`17_COORDINATION` owns:

- task queuing and priority assignment for ready work,
- pipeline stage-to-stage message delivery (`MESSAGE-BUS.md`), distinct from `36_COMMUNICATION`'s platform-wide transport,
- executing task ownership handoffs and confirming receipt,
- tracking progress of in-flight and parallel work,
- detecting, classifying, and resolving conflicts against the escalation criteria a collaboration structure defines,
- and failure recovery for coordination-level breakdowns (a stuck handoff, an unresponsive owner, a stalled queue).

`17_COORDINATION` does not own:

- collaboration structure, participation rules, or ownership boundaries (owned by `16_AGENTS/AGENT-COLLABORATION.md`),
- delegation authorization (owned by `16_AGENTS/AGENT-DELEGATION.md`),
- capability matching or owner selection (owned by `14_ENGINE/TASK-ROUTER.md`),
- task decomposition (owned by `14_ENGINE/TASK-DECOMPOSER.md`),
- underlying message transport, validation, or security (owned by `36_COMMUNICATION`),
- or action dispatch and execution itself (owned by `20_EXECUTION`).

Those responsibilities remain in their respective layers; Coordination consumes their decisions rather than remaking them.

---

## Components

The Coordination layer may include components for:

- Task Queue,
- Priority Manager,
- Message Bus,
- Handoff Protocol,
- Progress Tracker,
- Conflict Resolution,
- and Failure Recovery.

The authoritative component roster must match files that actually exist in this directory.

---

## Execution Order

```text
Queue
   ↓
Prioritize
   ↓
Assign (per Task Router's owner selection)
   ↓
Message Bus (stage-to-stage delivery)
   ↓
Track Progress
   ↓
Handoff or Conflict Resolution
   ↓
Completion or Failure Recovery
```

---

## Dependencies

Coordination depends on:

- `14_ENGINE/TASK-ROUTER.md` for the owner each queued task is assigned to,
- `16_AGENTS/AGENT-COLLABORATION.md` for the structure and escalation criteria governing multi-agent work,
- `16_AGENTS/AGENT-DELEGATION.md` for authorized delegation requests it must execute the handoff for,
- `36_COMMUNICATION` for the underlying transport `MESSAGE-BUS.md` rides on,
- and `20_EXECUTION` for the actual dispatch of work once assigned.

---

## State Rule

Every ownership change and state transition coordinated here must be traceable: which task, which prior owner, which new owner, and why.

Coordination does not persist state on its own authority — it records transitions through `14_ENGINE/STATE-MANAGER.md` like every other layer.

---

## Domain Rule

Coordination mechanics apply identically regardless of domain. Domain-specific context (for example `38_WORDPRESS` references) is carried in the task and message payloads, not interpreted by Coordination itself.

---

## Diagram

```text
Queue → Assignment → Agent ↔ Message Bus → Handoff → Completion
                         └→ Conflict Resolution / Failure Recovery
```

---

## Rule

> Coordination carries out already-decided structure, authorization, and routing — queuing, delivering, handing off, tracking, and recovering. It does not decide collaboration structure, delegation authority, or task ownership itself; those decisions are made in `16_AGENTS` and `14_ENGINE`, and Coordination executes them.
