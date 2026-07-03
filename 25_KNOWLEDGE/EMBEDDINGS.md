# SquirrelForge Embeddings Manager

## Purpose

The Embeddings Manager generates, stores, maintains, and versions vector embeddings for knowledge assets, enabling semantic search, similarity analysis, clustering, and retrieval-augmented reasoning throughout SquirrelForge.

---

## Responsibilities

- Generate embeddings.
- Store embedding vectors.
- Version embedding models.
- Refresh outdated embeddings.
- Support similarity calculations.
- Manage embedding metadata.
- Record embedding activity.
- Maintain embedding lifecycle.

---

## Embedding Process

1. Receive embedding request.
2. Identify knowledge asset.
3. Select approved embedding model.
4. Generate embedding vector.
5. Store vector and metadata.
6. Verify embedding integrity.
7. Record embedding activity.
8. Return embedding reference.

---

## Embedding Sources

| Source | Description |
|---|---|
| Documents | Structured documentation |
| Memory | Stored cognitive memory |
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
- Record all embedding generation events.
- Prevent duplicate embedding records.

---

## Rule

Every knowledge asset participating in semantic retrieval must have a current, versioned, and validated embedding before it may be used by the Semantic Search Manager.