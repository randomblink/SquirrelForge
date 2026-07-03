# SquirrelForge Knowledge Manager

## Purpose

The Knowledge Manager serves as the central authority for managing knowledge throughout SquirrelForge, coordinating knowledge acquisition, organization, validation, retrieval, lifecycle management, and distribution to authorized components.

---

## Responsibilities

- Register knowledge assets.
- Coordinate knowledge ingestion.
- Organize knowledge collections.
- Manage indexing and retrieval.
- Coordinate validation.
- Manage knowledge lifecycle.
- Record knowledge activity.
- Distribute approved knowledge.

---

## Knowledge Process

1. Receive knowledge request.
2. Identify requested knowledge asset.
3. Verify registration.
4. Validate knowledge status.
5. Retrieve or update knowledge.
6. Record knowledge activity.
7. Return approved knowledge.

---

## Knowledge Sources

| Source | Description |
|---|---|
| Documents | Structured reference material |
| Workflows | Workflow definitions and outputs |
| Agent Memory | AI agent operational memory |
| Integrations | External information sources |
| Configuration | System configuration knowledge |
| Observability | Operational history and metrics |
| User Input | Approved user-provided information |

---

## Knowledge Record

| Field | Description |
|---|---|
| Knowledge ID | Unique identifier |
| Source | Origin of the knowledge |
| Category | Knowledge classification |
| Status | Draft / Validated / Active / Deprecated / Archived |
| Version | Current knowledge version |
| Owner | Responsible component |
| Timestamp | Last modification |

---

## Knowledge Lifecycle

| Stage | Description |
|---|---|
| Ingested | Newly acquired |
| Indexed | Searchable |
| Validated | Quality verified |
| Active | Available for retrieval |
| Updated | Revised version available |
| Deprecated | Scheduled for retirement |
| Archived | Historical reference only |

---

## Governance Principles

- Every knowledge asset has an owner.
- Knowledge must be validated before use.
- Version history must be preserved.
- Retrieval must respect authorization policies.
- Knowledge changes must be auditable.
- Deprecated knowledge remains traceable.

---

## Rule

Every knowledge asset used within SquirrelForge must be registered, validated, versioned, and distributed through the Knowledge Manager before it may support reasoning or execution.
