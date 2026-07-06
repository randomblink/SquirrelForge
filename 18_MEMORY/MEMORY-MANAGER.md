# SquirrelForge Memory Manager

Version: 1.0.0
Status: Stable
Owner: Memory Maintainers
Depends On: `18_MEMORY/WORKING-MEMORY.md`, `18_MEMORY/EPISODIC-MEMORY.md`, `18_MEMORY/SEMANTIC-MEMORY.md`, `18_MEMORY/PROJECT-MEMORY.md`, `18_MEMORY/MEMORY-INDEX.md`, `18_MEMORY/MEMORY-RETRIEVAL.md`, `18_MEMORY/MEMORY-RETENTION.md`
Used By: Engine, Reasoning, Agents, Coordination
Last Updated: 2026-07-05

## Purpose

The Memory Manager is the Memory layer's coordination and routing component. It receives store and retrieval requests, identifies the correct memory component, routes the request, and returns that component's result.

The Memory Manager coordinates; it does not itself store, retrieve, index, or retain memory. Storage is owned by `18_MEMORY/WORKING-MEMORY.md`, `18_MEMORY/EPISODIC-MEMORY.md`, `18_MEMORY/SEMANTIC-MEMORY.md`, or `18_MEMORY/PROJECT-MEMORY.md` depending on type; retrieval is owned by `18_MEMORY/MEMORY-RETRIEVAL.md`; indexing is owned by `18_MEMORY/MEMORY-INDEX.md`; retention and archival decisions are owned by `18_MEMORY/MEMORY-RETENTION.md`. The Manager routes to these components and returns their result without duplicating their logic.

---

## Responsibilities

The Memory Manager must:

- receive memory store and retrieval requests,
- identify the appropriate memory component for the request,
- route storage to Working, Episodic, Semantic, or Project Memory depending on type,
- route retrieval through `18_MEMORY/MEMORY-RETRIEVAL.md`,
- coordinate indexing through `18_MEMORY/MEMORY-INDEX.md`,
- apply retention decisions through `18_MEMORY/MEMORY-RETENTION.md`,
- preserve authorization and audit requirements on every request,
- record memory activity for audit,
- and return the owning component's result without duplicating its storage or retrieval logic.

---

## Memory Process

1. Receive a memory request (store or retrieve).
2. Identify the memory type and the component that owns it.
3. Verify authorization for the request.
4. Route storage to the owning memory component, or route retrieval through `18_MEMORY/MEMORY-RETRIEVAL.md`.
5. Coordinate indexing through `18_MEMORY/MEMORY-INDEX.md` when a new or updated record is stored.
6. Apply retention decisions through `18_MEMORY/MEMORY-RETENTION.md` when a lifecycle transition is due.
7. Record memory activity for audit.
8. Return the owning component's result.

---

## Memory Types

| Memory Type | Description | Owning Component |
|---|---|---|
| Working Memory | Active context for the current task. | `18_MEMORY/WORKING-MEMORY.md` |
| Episodic Memory | Historical record of completed tasks and outcomes. | `18_MEMORY/EPISODIC-MEMORY.md` |
| Semantic Memory | Reusable, project-independent knowledge already promoted into memory. | `18_MEMORY/SEMANTIC-MEMORY.md` |
| Project Memory | Project-specific long-lived decisions and history. | `18_MEMORY/PROJECT-MEMORY.md` |

This table must match the actual `18_MEMORY` component roster. It does not include Session, Procedural, or Long-Term Memory, which have no corresponding components.

---

## Memory Lifecycle

| Stage | Description |
|---|---|
| Created | Newly stored. |
| Active | Available for recall. |
| Archived | Retained for historical reference, per `18_MEMORY/MEMORY-RETENTION.md`. |
| Expired | Past its retention period, pending pruning. |
| Removed | Deleted according to retention policy. |

Promotion into platform-wide reusable knowledge is not a Memory lifecycle stage. That judgment belongs to `19_REASONING/REFLECTION-ENGINE.md` and `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, downstream of Memory.

---

## Memory Record

| Field | Description |
|---|---|
| Memory ID | Unique identifier. |
| Memory Type | Classification from the table above. |
| Owner | Responsible workflow or component. |
| Retention Policy | Applicable rule from `18_MEMORY/MEMORY-RETENTION.md`. |
| Status | Current lifecycle state. |
| Created | Initial storage time. |
| Last Accessed | Most recent recall. |

---

## Memory Governance

- Working Memory is temporary and holds no independent retention.
- Episodic, Semantic, and Project Memory are durable and follow `18_MEMORY/MEMORY-RETENTION.md`'s policy.
- Memory recall respects authorization.
- Memory changes are auditable.

---

## Permission Boundary

The Memory Manager may receive requests, identify the owning component, route storage and retrieval, coordinate indexing and retention, and record activity.

It must not store, retrieve, index, or apply retention itself — those remain owned by `18_MEMORY/WORKING-MEMORY.md`, `18_MEMORY/EPISODIC-MEMORY.md`, `18_MEMORY/SEMANTIC-MEMORY.md`, `18_MEMORY/PROJECT-MEMORY.md`, `18_MEMORY/MEMORY-RETRIEVAL.md`, `18_MEMORY/MEMORY-INDEX.md`, and `18_MEMORY/MEMORY-RETENTION.md` respectively. It must not decide that a record becomes platform-wide reusable knowledge.

---

## Domain Rule

Routing mechanics apply identically regardless of domain; domain-specific content is carried in the routed record, not interpreted by the Memory Manager itself.

---

## Rule

> The Memory Manager routes every memory request to its owning component and returns that component's result. It does not duplicate storage, retrieval, indexing, or retention logic, and it does not decide that memory becomes durable, reusable knowledge.
