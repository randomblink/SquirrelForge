# SquirrelForge Memory Retrieval

Version: 1.0.0
Status: Stable
Owner: Memory Maintainers
Depends On: `18_MEMORY/MEMORY-INDEX.md`, `18_MEMORY/WORKING-MEMORY.md`, `18_MEMORY/EPISODIC-MEMORY.md`, `18_MEMORY/SEMANTIC-MEMORY.md`, `18_MEMORY/PROJECT-MEMORY.md`
Used By: Memory Manager, Agents, Reasoning
Last Updated: 2026-07-05

## Purpose

Memory Retrieval interprets a memory query, ranks candidate records referenced by `18_MEMORY/MEMORY-INDEX.md`, and returns results.

Retrieval is the query-serving counterpart to the Index's reference-maintenance role: the Index maintains searchable references and relationships, and Retrieval interprets queries against them, ranks candidates, and returns results. Retrieval does not create, modify, or delete index entries itself, and it does not alter the underlying authoritative memory record it returns.

---

## Responsibilities

Memory Retrieval must:

- receive a memory query,
- interpret the query against the indexed metadata `18_MEMORY/MEMORY-INDEX.md` maintains,
- rank candidate records by relevance,
- retrieve the original record from its owning memory component,
- and return the most relevant results.

---

## Inputs

Retrieval should receive:

- the query,
- and indexed metadata and relationships from `18_MEMORY/MEMORY-INDEX.md`.

A query must be interpreted against the Index's current metadata, not a stale or independently maintained copy.

---

## Outputs

Retrieval should produce:

- a ranked set of candidate records,
- and the retrieved original records from their owning memory components.

---

## Retrieval Process

1. Receive a query.
2. Search indexed metadata via `18_MEMORY/MEMORY-INDEX.md`.
3. Rank matching records by relevance.
4. Retrieve the original record from its owning memory component (Working, Episodic, Semantic, or Project Memory).
5. Return the most relevant results.

---

## Permission Boundary

Memory Retrieval may interpret queries, rank candidates, and return results.

It must not create, modify, or delete index entries itself (owned by `18_MEMORY/MEMORY-INDEX.md`), or alter the underlying authoritative memory record it returns (owned by each memory type's respective component).

---

## Domain Rule

Query interpretation and ranking apply identically regardless of domain; domain-specific content is carried in the retrieved record, not interpreted by Retrieval itself.

---

## Rule

> Memory Retrieval interprets queries and ranks candidates drawn from the Memory Index; it does not maintain the index itself, and it does not alter the records it returns.
