# SquirrelForge Semantic Search Manager

Version: 1.0.0
Status: Stable
Owner: Knowledge Maintainers
Depends On: `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`, `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md`, `25_KNOWLEDGE/EMBEDDINGS.md`, `25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md`, `25_KNOWLEDGE/CITATION-MANAGER.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`
Used By: `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, Reasoning, Agents, Workflows
Last Updated: 2026-07-08

## Purpose

The Semantic Search Manager executes Knowledge Layer retrieval over registered, validated, authorized, and version-aware knowledge assets.

It interprets knowledge retrieval intent, uses embedding references and metadata filters, ranks candidate knowledge assets, and returns relevance-ranked knowledge references.

It does not generate embeddings, validate knowledge, assign trust, create citations, create version records, own raw document storage, make authorization decisions, make reasoning decisions, or own general logging, audit, storage, or observability infrastructure.

---

## Responsibilities

- Process semantic search requests.
- Generate semantic queries.
- Retrieve candidate knowledge assets.
- Calculate similarity scores.
- Rank search results.
- Apply filtering rules.
- Request observability/audit records when required.
- Return relevance-ranked results.

---

## Search Process

1. Receive search request.
2. Interpret search intent.
3. Retrieve or request embedding references from `25_KNOWLEDGE/EMBEDDINGS.md`.
4. Retrieve candidate knowledge references from registered and validated assets.
5. Calculate similarity scores.
6. Apply metadata, trust, version, citation, and authorization filters.
7. Rank results by relevance.
8. Request search activity recording when required.
9. Return search result references.

---

## Retrieval Methods

| Method | Description |
|---|---|
| Semantic Search | Meaning-based retrieval |
| Keyword Search | Exact text matching |
| Hybrid Search | Combined semantic and keyword search |
| Metadata Search | Search by structured fields |
| Filtered Search | Apply category or policy filters |
| Citation Search | Search by referenced sources |

---

## Search Record

| Field | Description |
|---|---|
| Search ID | Unique identifier |
| Query | Original search request |
| Search Type | Retrieval method |
| Results Returned | Number of matching assets |
| Top Relevance Score | Highest similarity score |
| Registry References | Knowledge Registry entries returned |
| Version References | Knowledge versions included |
| Citation References | Citation references included when required |
| Timestamp | Search time |
| Requesting Component | Originating subsystem |

---

## Ranking Principles

- Prioritize semantic relevance.
- Prefer authoritative knowledge.
- Respect trust results from `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md`.
- Consume authorization decisions from `24_SECURITY/AUTHORIZATION-MANAGER.md`.
- Favor current versions over deprecated content.
- Support deterministic ranking when scores are equal.

---

## Quality Guidelines

- Return meaningful results.
- Minimize irrelevant matches.
- Preserve explainability by returning citation and provenance references.
- Request retrieval decision records through owning observability infrastructure when required.
- Support scalable indexing.
- Continuously improve ranking quality.

---

## Permission Boundary

The Semantic Search Manager may interpret knowledge retrieval intent, retrieve candidate knowledge references, calculate similarity scores, apply metadata/trust/version/citation/authorization filters, rank results, and return relevance-ranked knowledge references.

It must not generate embeddings, validate knowledge, assign trust, create citations, create version records, own raw document storage, make authorization decisions, make reasoning decisions, or own general logging, audit, storage, or observability infrastructure.

---

## Domain Rule

Semantic retrieval applies identically regardless of domain. Domain-specific knowledge can be searched only after it is registered, validated, authorized for the requester, and represented through Knowledge Layer references.

---

## Rule

Every semantic search performed by SquirrelForge must return only authorized, registered, validated, version-aware, and relevance-ranked knowledge references.
