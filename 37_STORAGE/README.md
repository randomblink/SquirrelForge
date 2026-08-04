# SquirrelForge Storage Layer

## Purpose

This directory defines how SquirrelForge stores, retrieves, protects, replicates, versions, archives, and disposes of all platform data.

The Storage Layer provides secure, reliable, scalable, and governed persistence services for every platform component, including AI memory, knowledge, workflows, configurations, communications, audit records, observability data, and operational metadata.

Storage is responsible for persistence only. It does not define business meaning, validation rules, or governance policies beyond storage operations.

---

# Component Roster

| Component | Responsibility |
|---|---|
| `STORAGE-MANAGER.md` | Coordinates all storage operations across object, document, vector, caching, versioning, backup, archival, replication, and governance concerns. |
| `DATA-MANAGER.md` | Coordinates all data operations: storing, retrieving, validating, indexing, caching, versioning, backing up, and governing data. |
| `OBJECT-STORAGE.md` | Stores binary and large objects (files, media, exports, model artifacts). |
| `DOCUMENT-STORAGE.md` | Stores structured and semi-structured documents. |
| `VECTOR-STORAGE.md` | Stores embeddings, vector collections, and semantic indexes. |
| `CACHE-MANAGER.md` | Manages temporary cached data: creation, retrieval, invalidation, expiration, synchronization. |
| `INDEX-MANAGER.md` | Creates, maintains, and optimizes searchable indexes over stored data. |
| `RETRIEVAL-MANAGER.md` | Locates, validates, and delivers stored data to authorized components. |
| `DATA-VALIDATOR.md` | Verifies the integrity, structure, and compliance of data moving through the Storage Layer. |
| `DATA-GOVERNANCE.md` | Establishes and enforces data lifecycle, protection, classification, retention, and disposal policies. |
| `BACKUP-MANAGER.md` | Coordinates backup creation, verification, and restoration. |
| `KEY-VALUE-STORAGE.md` | Stores and retrieves durable key-value records addressed by namespaced key. |
| `ARCHIVE-STORAGE.md` | Moves records whose active lifecycle has ended into governed long-term retention and retrieval. |
| `STORAGE-REPLICATION.md` | Replicates stored records to registered targets and reports replica health and integrity. |

The authoritative component roster must match files that actually exist in this directory.

---

# Storage Rule

All stored information must:

- Preserve integrity.
- Remain recoverable.
- Respect permissions.
- Support auditing.
- Support versioning.
- Support resilience.
- Support governance.
- Protect confidentiality.
