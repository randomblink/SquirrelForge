# SquirrelForge Working Memory

Version: 1.0.0
Status: Stable
Owner: Memory Maintainers
Depends On: `14_ENGINE/STATE-MANAGER.md`, `14_ENGINE/DEPENDENCY-ANALYZER.md`, `14_ENGINE/VALIDATION.md`, `18_MEMORY/EPISODIC-MEMORY.md`
Used By: Memory Manager, Agents, Reasoning
Last Updated: 2026-07-05

## Purpose

Working Memory holds a temporary active-context snapshot for the current request or task, giving agents fast, local access to the goal, workflow, task, dependencies, temporary decisions, and validation state without querying each owning component directly.

Working Memory caches references to state owned elsewhere; it does not decide or record the authoritative value of any of them. The active goal, workflow, task, and routing state remain owned by `14_ENGINE/STATE-MANAGER.md`; dependency status remains owned by `14_ENGINE/DEPENDENCY-ANALYZER.md`; validation status remains owned by `14_ENGINE/VALIDATION.md`. If Working Memory's cached copy and the owning component ever disagree, the owning component is correct.

---

## Responsibilities

Working Memory must:

- hold a temporary snapshot of the active context for the current task,
- cache references to the active goal, workflow, task, agent, dependencies, temporary decisions, and validation state from their owning components,
- refresh cached references when the owning component's state changes,
- provide fast local context to active agents without requiring a fresh query to every owning component for routine access,
- preserve the snapshot across handoffs within the same task,
- hand off completed context to `18_MEMORY/EPISODIC-MEMORY.md` once the task is complete and validated,
- and clear the snapshot after the handoff, retaining nothing beyond what Episodic Memory now holds.

---

## Working Memory Contents

| Item | Description | Authoritative Owner |
|---|---|---|
| Active Goal | Cached reference to the current goal. | `14_ENGINE/STATE-MANAGER.md` |
| Active Workflow | Cached reference to the selected workflow. | `14_ENGINE/STATE-MANAGER.md` (via `14_ENGINE/WORKFLOW-SELECTOR.md`) |
| Active Agent | Cached reference to the current task owner. | `14_ENGINE/STATE-MANAGER.md` (routing) |
| Current Task | Cached reference to the task in progress. | `14_ENGINE/STATE-MANAGER.md` |
| Dependencies | Cached reference to required files, workflows, or tools. | `14_ENGINE/DEPENDENCY-ANALYZER.md` |
| Temporary Decisions | Short-term decisions scoped to this task only. | Working Memory |
| Validation State | Cached reference to current validation status. | `14_ENGINE/VALIDATION.md` |

Only Temporary Decisions are Working Memory's own authoritative content; every other field is a cached reference to a value owned elsewhere.

---

## Lifecycle

1. Initialize when a new task begins, loading references from the owning components.
2. Refresh cached references as the owning components' state changes.
3. Preserve the snapshot during handoffs (`17_COORDINATION/HANDOFF-PROTOCOL.md`).
4. Hand the completed context to `18_MEMORY/EPISODIC-MEMORY.md` once the task is complete and validated.
5. Clear the snapshot after the handoff.

---

## Permission Boundary

Working Memory may cache, refresh, and hand off context, and record temporary decisions scoped to the current task.

It must not independently set the authoritative goal, workflow, task, routing, dependency, or validation state — those remain owned by `14_ENGINE/STATE-MANAGER.md`, `14_ENGINE/DEPENDENCY-ANALYZER.md`, and `14_ENGINE/VALIDATION.md` respectively — and it must not decide on its own authority that temporary context becomes durable, reusable knowledge. Candidate material for that judgment is handed to `18_MEMORY/EPISODIC-MEMORY.md` and, from there, evaluated by `19_REASONING/REFLECTION-ENGINE.md` and `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` — not decided here.

---

## Domain Rule

Working Memory mechanics apply identically regardless of domain; domain-specific content is carried in the cached references, not interpreted by Working Memory itself.

---

## Rule

> Working Memory holds a temporary snapshot of context owned by other components; it is cleared once a task completes and its record moves to Episodic Memory. It does not decide that temporary context becomes durable knowledge — that judgment belongs to the Reflection Engine and the Knowledge Manager.
