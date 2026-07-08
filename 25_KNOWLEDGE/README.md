# SquirrelForge Knowledge Layer

Version: 1.0.0
Status: Stable
Owner: Knowledge Maintainers
Depends On: `18_MEMORY`, `19_REASONING`, `21_CONFIGURATION`, `24_SECURITY`, `27_OBSERVABILITY`, `37_STORAGE`
Used By: Engine, Reasoning, Agents, Workflows, Validation, Reporting
Last Updated: 2026-07-07

## Purpose

This directory defines how SquirrelForge acquires, stores, organizes, validates, retrieves, and evolves knowledge used by workflows, reasoning systems, AI agents, and operational components.

The Knowledge Layer transforms information into trusted, searchable, versioned, and explainable knowledge that supports intelligent decision-making across the platform.

The Knowledge Layer manages knowledge assets and knowledge relationships. It does not own memory storage, raw document persistence, general observability records, or general storage infrastructure.

---

## Component Roster

| Component | Responsibility |
|---|---|
| `KNOWLEDGE-MANAGER.md` | Coordinates all knowledge operations. |
| `KNOWLEDGE-REGISTRY.md` | Maintains the catalog of knowledge assets. |
| `DOCUMENT-REPOSITORY.md` | Maintains knowledge-facing document references, collections, and document metadata. |
| `SEMANTIC-SEARCH.md` | Retrieves knowledge through semantic similarity. |
| `KNOWLEDGE-GRAPH.md` | Models relationships between knowledge entities. |
| `EMBEDDINGS.md` | Generates and maintains vector representations. |
| `CITATION-MANAGER.md` | Tracks knowledge sources and references. |
| `KNOWLEDGE-VALIDATOR.md` | Verifies knowledge quality and integrity. |
| `KNOWLEDGE-VERSIONING.md` | Manages knowledge evolution and history. |

The authoritative component roster must match the 9 component files that actually exist in `25_KNOWLEDGE`.

Memory management is not part of this layer. Memory ownership belongs to `18_MEMORY` and its component files. Knowledge may consume validated, promoted memory-derived records, but it does not manage temporary, episodic, semantic, or project memory itself.

---

## Layer Boundary

`25_KNOWLEDGE` owns:

- knowledge asset registration,
- knowledge metadata and cataloging,
- knowledge validation,
- knowledge citation and provenance references,
- knowledge graph relationships,
- semantic retrieval over approved knowledge,
- embedding records for knowledge assets,
- knowledge-facing document references,
- and knowledge version history.

`25_KNOWLEDGE` does not own:

- active task memory, episodic memory, semantic memory, project memory, indexing, retrieval, or retention (`18_MEMORY`),
- raw document storage and persistence (`37_STORAGE/DOCUMENT-STORAGE.md`),
- general logs, metrics, traces, alerts, dashboards, or audit infrastructure (`27_OBSERVABILITY`),
- runtime authorization decisions (`24_SECURITY/AUTHORIZATION-MANAGER.md`),
- general validation state (`14_ENGINE/VALIDATION.md` and `14_ENGINE/STATE-MANAGER.md`),
- or reasoning decisions that use knowledge (`19_REASONING`).

---

## Knowledge Principles

- Every knowledge asset has an authoritative source.
- Knowledge must be discoverable.
- Sources must remain traceable.
- Knowledge evolves through versioning.
- Retrieval should prioritize relevance and trust.
- Memory-derived material must be promoted through the appropriate Memory, Reasoning, and Knowledge process before it becomes reusable knowledge.
- Every significant fact should be explainable through citations.

---

## Rule

No reasoning, planning, or workflow component may rely on knowledge that has not been registered, validated, and made available through the Knowledge Layer.
