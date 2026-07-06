# SquirrelForge Memory Architecture

Version: 1.0.0
Status: Stable
Owner: Memory Maintainers
Depends On: `18_MEMORY/MEMORY-MANAGER.md`, `18_MEMORY/WORKING-MEMORY.md`, `18_MEMORY/EPISODIC-MEMORY.md`, `18_MEMORY/SEMANTIC-MEMORY.md`, `18_MEMORY/PROJECT-MEMORY.md`, `18_MEMORY/MEMORY-INDEX.md`, `18_MEMORY/MEMORY-RETRIEVAL.md`, `18_MEMORY/MEMORY-RETENTION.md`
Used By: Engine, Reasoning, Agents, Coordination
Last Updated: 2026-07-05

## Purpose

The Memory Architecture is the authoritative structural map of the `18_MEMORY` layer: what each component owns, and how a memory record moves between them from active task context through indexing, retrieval, and retention.

This document describes Memory's internal structure only. `19_REASONING` (Learning and Reflection) and `25_KNOWLEDGE` (platform-wide reusable knowledge) are external consumers of Memory's records, not Memory components — their relationship to Memory is shown as an external interface, not an internal architecture stage.

---

## Structural Map

| Component | Ownership |
|---|---|
| `MEMORY-MANAGER.md` | Coordinates and routes Memory operations. |
| `WORKING-MEMORY.md` | Temporary active-context snapshot for the current task. |
| `EPISODIC-MEMORY.md` | Historical experience records of completed tasks. |
| `SEMANTIC-MEMORY.md` | Durable representations of approved, generalized knowledge. |
| `PROJECT-MEMORY.md` | Durable, project-specific context. |
| `MEMORY-INDEX.md` | Searchable references and relationships across memory types. |
| `MEMORY-RETRIEVAL.md` | Interprets queries, ranks candidates, and returns records. |
| `MEMORY-RETENTION.md` | Retention, archival, and pruning eligibility rules. |

This roster must match the actual files in `18_MEMORY`. A Knowledge Manager is not part of it; the platform's single Knowledge Manager is `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`.

---

## Objectives

- Preserve important information.
- Support consistent decision-making.
- Reduce repeated work.
- Improve future planning.
- Maintain project continuity.

Long-term learning and platform-wide knowledge reuse are objectives of `19_REASONING` and `25_KNOWLEDGE`, which Memory supports by supplying validated, retrievable records — not an objective Memory pursues on its own.

---

## Internal Memory Flow

```text
Task Request
      │
      ▼
Working Memory (active context)
      │
      ▼
Execution / Outcome
      │
      ▼
Validation (14_ENGINE/VALIDATION.md)
      │
      ▼
Episodic Memory (record the completed task)
      │
      ▼
Semantic Memory / Project Memory (only already-validated, promotable content)
      │
      ▼
Memory Index (index for discovery)
      │
      ▼
Memory Retrieval (interpret queries, rank, return results)
      │
      ▼
Memory Retention (retain or archive per policy)
```

Every stage above is owned inside `18_MEMORY`. No external component appears in this flow.

---

## External Relationships

Memory supplies material to these external layers; it does not import their architecture into its own flow:

| External Layer | Relationship |
|---|---|
| `19_REASONING/REFLECTION-ENGINE.md` | Reads completed, validated records from `EPISODIC-MEMORY.md` to identify lessons learned. Memory does not schedule or guarantee this happens automatically. |
| `19_REASONING/DECISION-ENGINE.md` | Referenced by `EPISODIC-MEMORY.md`'s decision references. Memory stores the reference, not the reasoning itself. |
| `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` | May register, validate, and promote material Memory has recorded and indexed into platform-wide reusable knowledge. This promotion happens on `25_KNOWLEDGE`'s own authority, outside Memory's architecture — it is not an automatic pipeline or a Memory lifecycle stage. |

There is no automatic "Reflection Engine → Knowledge Manager → Memory" pipeline in this architecture. Whether and when a record is picked up by Learning or Knowledge processes is decided by those layers, not scheduled by Memory.

---

## Memory Principles

- Store only useful information.
- Preserve validation history rather than re-deciding it.
- Separate temporary memory (Working Memory) from durable memory (Episodic, Semantic, Project Memory).
- Prefer a reference to reusable knowledge over duplicating it.
- Archive rather than delete historical records, per `MEMORY-RETENTION.md`.

---

## Rule

> Information is stored in the most appropriate Memory component, with temporary execution context retained only as long as the active task requires. Whether a durable Memory record is later promoted into platform-wide reusable knowledge is decided by `25_KNOWLEDGE`, not by Memory's own architecture.
