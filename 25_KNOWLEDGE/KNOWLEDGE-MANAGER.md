# SquirrelForge Knowledge Manager

Version: 1.0.0
Status: Stable
Owner: Knowledge Maintainers
Depends On: `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`, `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md`, `25_KNOWLEDGE/CITATION-MANAGER.md`, `25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md`, `25_KNOWLEDGE/KNOWLEDGE-GRAPH.md`, `25_KNOWLEDGE/SEMANTIC-SEARCH.md`, `25_KNOWLEDGE/EMBEDDINGS.md`, `25_KNOWLEDGE/DOCUMENT-REPOSITORY.md`
Used By: Engine, Reasoning, Agents, Workflows, Validation, Reporting
Last Updated: 2026-07-07

## Purpose

The Knowledge Manager coordinates the Knowledge Layer. It receives knowledge registration, retrieval, validation, citation, versioning, embedding, document-reference, and relationship requests, then routes each request to the owning Knowledge component.

The Knowledge Manager aggregates knowledge-domain status and references. It coordinates lifecycle transitions only through authoritative results from the responsible specialist component.

It does not generate embeddings, execute semantic search, validate knowledge directly, resolve citations, own version records, own raw document storage, own memory lifecycle or memory state, or own general logging, audit, or observability infrastructure.

---

## Responsibilities

- Receive Knowledge Layer requests.
- Identify the owning Knowledge component for each request.
- Route registration requests to `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`.
- Route validation requests to `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md`.
- Route citation requests to `25_KNOWLEDGE/CITATION-MANAGER.md`.
- Route versioning requests to `25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md`.
- Route relationship requests to `25_KNOWLEDGE/KNOWLEDGE-GRAPH.md`.
- Route retrieval requests to `25_KNOWLEDGE/SEMANTIC-SEARCH.md` or the appropriate repository/reference component.
- Route embedding requests to `25_KNOWLEDGE/EMBEDDINGS.md`.
- Route document-reference requests to `25_KNOWLEDGE/DOCUMENT-REPOSITORY.md`.
- Aggregate knowledge-domain status, references, and limitations.
- Coordinate lifecycle transitions only after specialist results exist.

---

## Knowledge Process

1. Receive knowledge request.
2. Identify request type and required owner.
3. Verify whether a Knowledge Registry reference exists.
4. Route missing registration to `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`.
5. Route validation, citation, versioning, relationship, embedding, document-reference, or retrieval work to the owning component.
6. Receive owner-produced status, evidence references, result references, or limitations.
7. Coordinate lifecycle transition only when the required owner results allow it.
8. Request observability or audit recording through the owning infrastructure when required.
9. Return the owner-produced knowledge result or status summary.

---

## Knowledge Sources

| Source | Description |
|---|---|
| Documents | Structured reference material represented through knowledge-facing document references. |
| Workflows | Workflow definitions and outputs promoted into knowledge. |
| Memory References | Validated, promoted references from `18_MEMORY`; not memory state or lifecycle. |
| Integrations | External information sources registered as knowledge assets. |
| Configuration | Approved configuration knowledge. |
| Observability References | Operational records referenced from `27_OBSERVABILITY`; not raw observability ownership. |
| User Input | Approved user-provided information after registration and validation. |

---

## Knowledge Record

| Field | Description |
|---|---|
| Knowledge ID | Unique identifier |
| Source | Origin of the knowledge |
| Category | Knowledge classification |
| Status | Draft / Validated / Active / Deprecated / Archived |
| Registry Reference | Registry entry from `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md` |
| Validation Reference | Validation result from `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md` |
| Citation Reference | Citation/provenance reference from `25_KNOWLEDGE/CITATION-MANAGER.md` |
| Version Reference | Version record from `25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md` |
| Relationship Reference | Graph relationship reference from `25_KNOWLEDGE/KNOWLEDGE-GRAPH.md`, when applicable |
| Owner | Responsible component |
| Timestamp | Last modification |

---

## Knowledge Lifecycle

| Stage | Description |
|---|---|
| Ingested | Newly acquired |
| Registered | Registry entry exists |
| Validated | Knowledge Validator approved the asset |
| Active | Available for retrieval |
| Updated | Revised version available |
| Deprecated | Scheduled for retirement |
| Archived | Historical reference only |

Lifecycle transitions must be based on the authoritative result from the owning component. The Knowledge Manager coordinates the transition and status summary; it does not independently validate, version, archive, or publish knowledge.

---

## Governance Principles

- Every knowledge asset has an owner.
- Knowledge must be validated before use.
- Version history must be preserved.
- Retrieval must respect authorization policies.
- Knowledge changes must request observability or audit records through owning infrastructure.
- Deprecated knowledge remains traceable.

---

## Permission Boundary

The Knowledge Manager may receive Knowledge Layer requests, identify the owning Knowledge component, route work, aggregate status and references, coordinate lifecycle transitions through owner-produced results, and return knowledge-domain summaries.

It must not generate embeddings, execute semantic search, validate knowledge directly, resolve citations, own version records, own raw document storage, manage memory lifecycle or memory state, make reasoning decisions, make authorization decisions, or own general logging, audit, storage, or observability infrastructure.

---

## Domain Rule

Knowledge coordination applies identically regardless of domain. Domain-specific material becomes Knowledge Layer content only after it is registered, validated, cited where required, versioned where required, and made available through the appropriate Knowledge component.

---

## Rule

Every knowledge request must be routed to the component that owns the required knowledge operation. The Knowledge Manager coordinates and reports; it does not replace specialist authority.
