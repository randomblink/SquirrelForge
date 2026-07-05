# SquirrelForge Priority Manager

Version: 1.0.0
Status: Stable
Owner: Coordination Maintainers
Depends On: `14_ENGINE/DEPENDENCY-ANALYZER.md`, `17_COORDINATION/TASK-QUEUE.md`
Used By: Task Queue, Coordination
Last Updated: 2026-07-05

## Purpose

The Priority Manager assigns and recalculates task priority — balancing urgency, dependency-blocking impact, and other prioritization factors — and supplies that priority to `17_COORDINATION/TASK-QUEUE.md` for ordering.

The Priority Manager decides priority. It does not determine dependency readiness itself (owned by `14_ENGINE/DEPENDENCY-ANALYZER.md`), reorder the queue directly (`17_COORDINATION/TASK-QUEUE.md` owns ordering and consumes the priority value this component supplies), or notify the Agent Orchestrator — the Orchestrator runs a fixed linear sequence per `16_AGENTS/AGENT-ORCHESTRATOR.md` and does not accept scheduling input.

---

## Responsibilities

The Priority Manager must:

- assign an initial priority to each task based on the priority factors,
- read dependency-blocking impact from `14_ENGINE/DEPENDENCY-ANALYZER.md` rather than independently re-deriving it,
- recalculate priority when a reprioritization trigger occurs,
- supply the current priority to `17_COORDINATION/TASK-QUEUE.md` for ordering,
- record the priority decision and its rationale,
- and never let priority override an unresolved required dependency without explicit authorization.

---

## Inputs

The Priority Manager should receive:

- the task and the priority factors relevant to it,
- dependency-blocking impact from `14_ENGINE/DEPENDENCY-ANALYZER.md`,
- and any reprioritization trigger that has occurred.

Dependency status must not be assumed or re-derived independently when the Dependency Analyzer has already reported it.

---

## Outputs

The Priority Manager should produce:

- the assigned or recalculated priority level,
- the rationale for that priority,
- a supply of the current priority to `17_COORDINATION/TASK-QUEUE.md`,
- and a recorded priority decision.

---

## Priority Levels

| Level | Meaning |
|---|---|
| Critical | Immediate action required. |
| High | Important to the current milestone. |
| Medium | Normal planned work. |
| Low | Nice-to-have or future work. |

This is a task-scheduling priority, distinct from the message-urgency priority defined in `16_AGENTS/AGENT-COMMUNICATION.md`'s Message Priorities — the two serve different purposes and are not interchangeable.

---

## Priority Factors

- User-requested urgency
- Dependency-blocking impact (from the Dependency Analyzer)
- Security implications
- Release readiness
- Technical risk
- Estimated effort
- Business value

---

## Prioritization Process

1. Evaluate the task against the priority factors.
2. Read dependency-blocking impact from `14_ENGINE/DEPENDENCY-ANALYZER.md`.
3. Assign the initial priority level.
4. Compare against the priority of active or queued tasks.
5. Supply the priority to `17_COORDINATION/TASK-QUEUE.md` for ordering.
6. When a reprioritization trigger occurs, recalculate and resupply the Task Queue.
7. Record the priority decision and rationale.

---

## Reprioritization Triggers

- New critical task
- Blocked dependency
- Validation failure
- Security issue
- User-requested change
- Release deadline

---

## Priority Record

| Field | Description |
|---|---|
| Task ID | Related task. |
| Priority | Critical / High / Medium / Low. |
| Reason | Why this priority was assigned. |
| Assigned By | Agent or system component. |
| Last Reviewed | Most recent evaluation. |

---

## Permission Boundary

The Priority Manager may assign and recalculate priority and record its rationale.

It must not determine dependency readiness itself, reorder the Task Queue directly, or let a priority assignment bypass a required, unresolved dependency without explicit authorization.

---

## Domain Rule

Priority factors apply identically regardless of domain; domain-specific urgency (for example a WordPress security advisory) is expressed through the existing factors, not a separate domain-specific priority system.

---

## Rule

> Priority decisions must consider both urgency and dependency-blocking impact as reported by the Dependency Analyzer. A high-priority task may not bypass unresolved required dependencies unless explicitly authorized.
