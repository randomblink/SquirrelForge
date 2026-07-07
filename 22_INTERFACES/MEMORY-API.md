# SquirrelForge Memory API

Version: 1.0.0
Status: Stable
Owner: Architecture Maintainers
Depends On: `18_MEMORY/MEMORY-MANAGER.md`, `18_MEMORY/MEMORY-RETRIEVAL.md`, `18_MEMORY/MEMORY-RETENTION.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`
Used By: `14_ENGINE`, `19_REASONING`, `16_AGENTS`
Last Updated: 2026-07-07

## Purpose

The Memory API owns the versioned interface contract for memory-facing operations: storing a record, retrieving a record by reference, searching by query and scope, submitting a candidate for promotion review, and requesting archival.

The Memory API exposes requests and responses. It does not own the decisions or state behind them. It does not decide which memory component owns a stored record (owned by `18_MEMORY/MEMORY-MANAGER.md`, which routes by type to `18_MEMORY/WORKING-MEMORY.md`, `18_MEMORY/EPISODIC-MEMORY.md`, `18_MEMORY/SEMANTIC-MEMORY.md`, or `18_MEMORY/PROJECT-MEMORY.md`), retrieve or search records itself (owned by `18_MEMORY/MEMORY-RETRIEVAL.md`), decide that a record becomes platform-wide reusable knowledge (owned by `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, reached via `19_REASONING/REFLECTION-ENGINE.md`'s candidate material — per `18_MEMORY/MEMORY-MANAGER.md`, promotion is explicitly not a Memory lifecycle stage), decide archival or retention (owned by `18_MEMORY/MEMORY-RETENTION.md`), or authorize requests (owned by `24_SECURITY/AUTHORIZATION-MANAGER.md` — every call must already carry a valid permission reference).

---

## Responsibilities

- Define the request and response schema for storing a memory record.
- Define the request and response schema for retrieving a record by reference.
- Define the request and response schema for searching records by query and scope.
- Define the request and response schema for submitting a record as a promotion candidate.
- Define the request and response schema for requesting archival.
- Return typed responses, typed errors, and correlation references for every call.
- Require identity, a permission reference, and a correlation ID on every call.
- Version the contract; breaking changes require a major version and migration path.

---

## Contract Operations

| Operation | Signature | Semantics |
|---|---|---|
| Store record | `put(record, classification, provenance, identity, permissionRef, correlationId) → memoryRef` | Submits a record for storage. Does not decide which memory component owns it — `18_MEMORY/MEMORY-MANAGER.md` routes it by type to the owning component. |
| Retrieve record | `get(memoryRef, identity, permissionRef, correlationId) → record` | Retrieves a single record by reference, routed through `18_MEMORY/MEMORY-RETRIEVAL.md`. Read-only. |
| Search records | `search(query, scope, identity, permissionRef, correlationId) → matches` | Submits a search request, routed through `18_MEMORY/MEMORY-RETRIEVAL.md`. Does not rank or filter results itself. |
| Submit promotion candidate | `promote(memoryRef, evidence, identity, permissionRef, correlationId) → result` | Submits a record as a candidate for promotion to platform-wide reusable knowledge. Does not decide promotion — that judgment belongs to `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, informed by `19_REASONING/REFLECTION-ENGINE.md`. Returns a result acknowledging the candidate was received and correlated, not that promotion occurred. |
| Request archival | `archive(memoryRef, reason, identity, permissionRef, correlationId) → result` | Submits a request that a record be archived. Does not decide archival or apply retention policy — that determination belongs to `18_MEMORY/MEMORY-RETENTION.md`. |

---

## Request and Response Requirements

Every request must include:

- Actor identity
- A permission reference to a valid authorization from `24_SECURITY/AUTHORIZATION-MANAGER.md`
- A correlation ID

Store requests must additionally include a classification and provenance record.

Every response must include:

- A typed result or typed error
- The correlation ID from the originating request
- A reference identifier (memory reference or result) when the operation produced one

Access, retention, and provenance checks apply to every operation and are enforced by the owning component, not the API.

Typed errors must distinguish, at minimum: invalid schema, missing or invalid authorization, unknown memory reference, and downstream rejection (surfaced from the owning component, not reinterpreted by the API).

---

## Permission Boundary

The Memory API may define and validate request/response schemas, accept requests carrying a valid permission reference, and return typed responses, errors, references, and results.

It must not route or store records itself, retrieve or search records itself, decide promotion or archival, or authorize requests — those remain owned by `18_MEMORY/MEMORY-MANAGER.md`, `18_MEMORY/MEMORY-RETRIEVAL.md`, `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, `18_MEMORY/MEMORY-RETENTION.md`, and `24_SECURITY/AUTHORIZATION-MANAGER.md` respectively.

---

## Domain Rule

The Memory API contract applies identically regardless of domain; domain-specific content is carried in the record and query payloads it transports, not interpreted by the API itself.

---

## Rule

> Every Memory API call must carry a valid permission reference and correlation ID and must be treated as a request, not a decision; the API returns what the owning component decided, it does not decide, store, retrieve, promote, or archive on its own authority.
