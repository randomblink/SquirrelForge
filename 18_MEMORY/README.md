# SquirrelForge Memory Layer

Version: 1.0.0
Status: Stable
Owner: Memory Maintainers
Depends On: `14_ENGINE/STATE-MANAGER.md`, `14_ENGINE/VALIDATION.md`, `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, `23_GOVERNANCE`
Used By: Engine, Reasoning, Agents, Coordination
Last Updated: 2026-07-05

## Purpose

The Memory Layer stores and retrieves the working context, episodic records, semantic memory, and project memory an active request or long-running project needs, and governs how that memory is indexed, retained, and archived over time.

Memory stores and retrieves; it does not decide what counts as validated, reusable knowledge available platform-wide. Extracting, validating, and promoting knowledge into that broader reusable form is `25_KNOWLEDGE`'s job. Memory supplies material — agent memory, completed task history, project records — that `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` may register, validate, and promote; it does not run a parallel knowledge-management process of its own.

---

## Layer Boundary

`18_MEMORY` owns:

- working context for the currently active task (`WORKING-MEMORY.md`),
- episodic records of completed work (`EPISODIC-MEMORY.md`),
- semantic memory records — reusable, project-independent knowledge already promoted into memory (`SEMANTIC-MEMORY.md`),
- project memory — long-lived, project-specific decisions and history (`PROJECT-MEMORY.md`),
- memory indexing (`MEMORY-INDEX.md`) and retrieval (`MEMORY-RETRIEVAL.md`) across these memory types,
- retention and archival policy for memory records (`MEMORY-RETENTION.md`),
- and the coordinating process that routes requests to the correct memory type (`MEMORY-MANAGER.md`).

`18_MEMORY` does not own:

- knowledge extraction, validation, and promotion into platform-wide reusable knowledge (owned by `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`),
- validation of outcomes, decisions, or claims before they may be recorded (owned by `14_ENGINE/VALIDATION.md` and the relevant reviewing agent),
- task or lifecycle state (owned by `14_ENGINE/STATE-MANAGER.md`),
- and retention policy authorization beyond the mechanical archive/prune schedule it executes (owned by `23_GOVERNANCE`).

Memory records what was already validated elsewhere; it does not perform that validation itself.

---

## Components

| Component | Responsibility |
|---|---|
| `MEMORY-MANAGER.md` | Coordinates requests across memory types and applies retention policy. |
| `MEMORY-ARCHITECTURE.md` | Describes how the memory types relate and the flow between them. |
| `WORKING-MEMORY.md` | Active context for the current task. |
| `EPISODIC-MEMORY.md` | History of completed tasks and outcomes. |
| `SEMANTIC-MEMORY.md` | Reusable, project-independent knowledge already promoted into memory. |
| `PROJECT-MEMORY.md` | Project-specific long-lived decisions and history. |
| `MEMORY-INDEX.md` | Maintains searchable references and relationships across all memory types. |
| `MEMORY-RETRIEVAL.md` | Interprets queries, ranks candidates, and returns results from the index. |
| `MEMORY-RETENTION.md` | Retention periods, archival, and pruning policy. |

The authoritative component roster must match files that actually exist in this directory. A Knowledge Manager is not a Memory component; the platform's single Knowledge Manager is `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`.

---

## Execution Order

```text
Working Memory (active task context)
   ↓
Execution / Outcome
   ↓
Validation (owned by 14_ENGINE/VALIDATION.md)
   ↓
Episodic Memory (record the completed task)
   ↓
Semantic / Project Memory (only for already-validated, promotable content)
   ↓
Memory Index (index for discovery)
   ↓
Memory Retrieval (interpret queries, rank, return results)
   ↓
Memory Retention (retain or archive per policy)
```

Promotion to `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` for platform-wide reuse happens after Memory has recorded and indexed the material — Memory does not promote knowledge on its own authority.

---

## Dependencies

Memory depends on:

- `14_ENGINE/STATE-MANAGER.md` for the current task and lifecycle state a memory record describes,
- `14_ENGINE/VALIDATION.md` for confirming an outcome before it may be recorded as validated,
- `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` as the destination for material Memory makes available for platform-wide promotion,
- and `23_GOVERNANCE` for retention and archival policy authorization.

---

## State Rule

Memory must never promote an unvalidated claim into Semantic or Project Memory as though it were confirmed. A record's validation status must be preserved, not upgraded, as it moves between memory types.

Memory does not persist task or lifecycle state on its own authority — that remains `14_ENGINE/STATE-MANAGER.md`'s responsibility; Memory persists the historical record of it.

---

## Domain Rule

Memory mechanics apply identically regardless of domain; domain-specific content is carried in the stored record, not interpreted by the memory components themselves.

---

## Diagram

```text
Task → Working Memory → Outcome → Validation → Episodic Memory
                                                     │
                                    Semantic / Project Memory
                                                     │
                                              Memory Index
                                                     │
                                            Memory Retention
                                                     │
                                   25_KNOWLEDGE (platform promotion)
```

---

## Rule

> Memory stores and retrieves working context, episodic records, semantic memory, and project memory, and governs their indexing, retention, and archival. It does not validate outcomes, own task or lifecycle state, or itself extract and promote knowledge into platform-wide reusable form — those remain owned by Validation, the State Manager, and `25_KNOWLEDGE` respectively.
