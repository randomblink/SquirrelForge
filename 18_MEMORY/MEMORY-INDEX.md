# SquirrelForge Memory Index

Version: 1.0.0
Status: Stable
Owner: Memory Maintainers
Depends On: `18_MEMORY/WORKING-MEMORY.md`, `18_MEMORY/EPISODIC-MEMORY.md`, `18_MEMORY/SEMANTIC-MEMORY.md`, `18_MEMORY/PROJECT-MEMORY.md`, `18_MEMORY/MEMORY-RETRIEVAL.md`
Used By: Memory Manager, Memory Retrieval
Last Updated: 2026-07-05

## Purpose

The Memory Index maintains searchable references and relationships across Working, Episodic, Semantic, and Project Memory, enabling discovery of relevant records.

The Index stores references only; it does not interpret queries, rank candidates, or return results — that is `18_MEMORY/MEMORY-RETRIEVAL.md`'s job. It also does not index Knowledge Layer content; platform-wide reusable knowledge is indexed and served by `25_KNOWLEDGE`, not by this component.

---

## Responsibilities

The Memory Index must:

- index records from Working, Episodic, Semantic, and Project Memory,
- generate searchable metadata for each record,
- prevent duplicate index entries or references, without altering or deleting the underlying authoritative memory record,
- track relationships between memory entries,
- keep the index current as memory records change,
- and hand off query interpretation, ranking, and result delivery to `18_MEMORY/MEMORY-RETRIEVAL.md`.

---

## Indexed Memory Sources

| Memory Layer | Indexed Content |
|---|---|
| Working Memory | Active execution context |
| Episodic Memory | Completed tasks and history |
| Semantic Memory | Reusable knowledge and standards |
| Project Memory | Project-specific information |

Knowledge Layer content is not indexed here; it is owned and served by `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`.

---

## Index Record

| Field | Description |
|---|---|
| Index ID | Unique identifier |
| Memory Type | Source memory layer |
| Record ID | Original record identifier |
| Title | Short descriptive title |
| Keywords | Search terms |
| Related Records | Connected entries |
| Last Updated | Most recent modification |

---

## Indexing Process

1. Receive a validated memory record.
2. Generate searchable metadata.
3. Create the index entry.
4. Link related records.
5. Remove duplicate index entries or references, without altering the underlying memory record.
6. Update search structures.
7. Hand off to `18_MEMORY/MEMORY-RETRIEVAL.md` for query handling.

---

## Permission Boundary

The Memory Index may create, link, update, and de-duplicate index entries and their metadata.

It must not delete or alter the underlying authoritative memory record (owned by each memory type's respective component), interpret queries or rank candidates (owned by `18_MEMORY/MEMORY-RETRIEVAL.md`), or index Knowledge Layer content (owned by `25_KNOWLEDGE`).

---

## Domain Rule

Indexing mechanics apply identically regardless of domain; domain-specific content is carried in the indexed record, not interpreted by the Memory Index itself.

---

## Rule

> Every long-term memory record must have exactly one index entry. The Memory Index stores references and relationships only — it does not alter, delete, or reconcile the underlying record, and it does not interpret queries or rank results; that belongs to `18_MEMORY/MEMORY-RETRIEVAL.md`.
