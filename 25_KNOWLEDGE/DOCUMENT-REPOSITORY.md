# SquirrelForge Document Repository

Version: 1.0.0
Status: Stable
Owner: Knowledge Maintainers
Depends On: `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`, `25_KNOWLEDGE/CITATION-MANAGER.md`, `25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md`, `37_STORAGE/DOCUMENT-STORAGE.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`
Used By: `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, `25_KNOWLEDGE/SEMANTIC-SEARCH.md`, `25_KNOWLEDGE/CITATION-MANAGER.md`, Reasoning, Agents
Last Updated: 2026-07-08

## Purpose

The Document Repository owns knowledge-facing document references, document collections, document metadata, and document-to-knowledge relationships used by the Knowledge Layer.

It does not store raw document content, retrieve raw document content directly, own document persistence, own raw document versions, execute archive/restore operations, make authorization decisions, or own general logging, audit, storage, or observability infrastructure.

Raw document persistence, storage lifecycle, archive/restore, and stored document version mechanics belong to `37_STORAGE/DOCUMENT-STORAGE.md` and related Storage components. Runtime access decisions belong to `24_SECURITY/AUTHORIZATION-MANAGER.md`.

---

## Responsibilities

- Register knowledge-facing document references.
- Organize document reference collections.
- Maintain document metadata used by Knowledge components.
- Link document references to Knowledge Registry entries.
- Attach citation and version references when available.
- Track repository status for knowledge-facing document references.
- Support metadata lookup and reference discovery.
- Consume authorization results for protected document references.

---

## Repository Process

1. Receive document request.
2. Identify target document reference.
3. Verify Knowledge Registry registration.
4. Confirm authorization result from `24_SECURITY/AUTHORIZATION-MANAGER.md` when protected access is involved.
5. Resolve storage reference through `37_STORAGE/DOCUMENT-STORAGE.md` when raw content is needed.
6. Attach citation, version, or metadata references when applicable.
7. Return document reference status, metadata, or storage reference.

---

## Document Types

| Type | Description |
|---|---|
| Specification | Technical and functional specifications |
| Policy | Governance and operational policies |
| Workflow | Workflow definitions and procedures |
| Knowledge | Reference documentation |
| Design | Architecture and design documents |
| Validation | Validation rules and reports |
| Audit Reference | Reference to audit evidence or historical records |
| User Documentation | Manuals, guides, and help resources |

---

## Supported Operations

| Operation | Description |
|---|---|
| Register Reference | Add a knowledge-facing document reference |
| Read Metadata | Retrieve repository metadata and references |
| Update Metadata | Update repository metadata |
| Archive Reference | Mark a document reference as archived based on storage status |
| Restore Reference | Mark a document reference as restored based on storage status |
| Version Reference | Attach document or knowledge version reference |
| Metadata Search | Locate document references by metadata |

---

## Repository Record

| Field | Description |
|---|---|
| Document ID | Unique identifier |
| Title | Document name |
| Type | Document category |
| Storage Reference | Owning storage document reference |
| Version Reference | Document or knowledge version reference |
| Citation References | Citation records associated with the document |
| Status | Draft / Active / Archived |
| Owner | Responsible component |
| Last Updated | Most recent modification |

---

## Repository Principles

- Every document has a unique identifier.
- Raw content storage is referenced, not owned.
- Version history is referenced from the owning version component.
- Access decisions are consumed from Authorization Manager.
- Document references remain discoverable.
- Historical versions remain traceable through version references.
- Repository activity requests observability/audit records through owning infrastructure when required.

---

## Permission Boundary

The Document Repository may create and maintain knowledge-facing document references, metadata, collections, citation references, version references, and storage references.

It must not own raw document storage, retrieve protected raw content without authorization, create raw document versions, execute archive/restore operations, make authorization decisions, perform semantic search, or own general logging, audit, storage, or observability infrastructure.

---

## Domain Rule

Document references apply identically regardless of domain. Domain-specific documents are represented as document references and metadata rather than separate domain-specific repositories.

---

## Rule

Every document used as Knowledge Layer content must have a knowledge-facing document reference with ownership, lifecycle status, and required storage, citation, version, and authorization references.
