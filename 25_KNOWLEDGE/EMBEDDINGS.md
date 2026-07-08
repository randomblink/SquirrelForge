# SquirrelForge Embeddings Manager

Version: 1.0.0
Status: Stable
Owner: Knowledge Maintainers
Depends On: `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`, `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md`, `25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md`, `21_CONFIGURATION/MODEL-CONFIG.md`, `37_STORAGE/VECTOR-STORAGE.md`
Used By: `25_KNOWLEDGE/SEMANTIC-SEARCH.md`, `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`
Last Updated: 2026-07-08

## Purpose

The Embeddings Manager generates and maintains embedding records for registered and validated knowledge assets.

It selects approved embedding configuration, requests or produces embedding vectors, records embedding metadata, and returns embedding references for semantic retrieval.

It does not own raw vector storage, semantic search execution, model policy, knowledge validation, memory lifecycle, knowledge version records, or general logging, audit, storage, or observability infrastructure.

---

## Responsibilities

- Generate embeddings.
- Record embedding metadata and vector storage references.
- Reference embedding model versions from approved model configuration.
- Refresh outdated embeddings.
- Support similarity calculations.
- Maintain embedding lifecycle metadata.
- Provide embedding references to Semantic Search.

---

## Embedding Process

1. Receive embedding request.
2. Identify knowledge asset.
3. Verify registration and knowledge validation references.
4. Select approved embedding configuration from `21_CONFIGURATION/MODEL-CONFIG.md`.
5. Generate or request embedding vector.
6. Store vector through `37_STORAGE/VECTOR-STORAGE.md` when persistence is required.
7. Record embedding metadata and storage reference.
8. Return embedding reference.

---

## Embedding Sources

| Source | Description |
|---|---|
| Documents | Structured documentation |
| Memory Reference | Validated, promoted memory-derived knowledge reference from `18_MEMORY` |
| Workflows | Workflow definitions |
| Rules | Operational and governance rules |
| Decisions | Recorded reasoning outcomes |
| Integrations | External knowledge sources |
| User Content | Approved user-provided information |

---

## Embedding Record

| Field | Description |
|---|---|
| Embedding ID | Unique identifier |
| Knowledge ID | Associated knowledge asset |
| Model | Embedding model used |
| Vector Dimension | Embedding size |
| Version | Model version |
| Status | Active / Refresh Required / Deprecated |
| Vector Storage Reference | Reference to vector storage when persisted |
| Knowledge Version Reference | Knowledge version used to generate the embedding |
| Timestamp | Generation time |

---

## Lifecycle States

| State | Description |
|---|---|
| Generated | Newly created |
| Indexed | Available for search |
| Active | Used for retrieval |
| Refresh Required | Requires regeneration |
| Deprecated | Superseded by newer version |
| Archived | Retained for historical reference |

---

## Management Principles

- Use approved embedding models.
- Preserve model version information.
- Refresh embeddings when source content changes.
- Maintain consistency between knowledge assets and vectors.
- Request observability/audit records for embedding generation when required.
- Prevent duplicate embedding records.

---

## Permission Boundary

The Embeddings Manager may generate embedding vectors for registered and validated knowledge assets, maintain embedding metadata, track refresh status, and provide embedding references for retrieval.

It must not own raw vector storage, execute semantic search, define model policy, validate knowledge, manage memory lifecycle or memory state, create knowledge version records, or own general logging, audit, storage, or observability infrastructure.

---

## Domain Rule

Embedding management applies identically regardless of domain. Domain-specific content must enter through registered and validated knowledge assets before embedding.

---

## Rule

Every knowledge asset participating in semantic retrieval must have a current embedding reference tied to a registered, validated knowledge asset and the knowledge version used to generate it.
