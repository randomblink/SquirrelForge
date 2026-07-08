# SquirrelForge Knowledge Registry

Version: 1.0.0
Status: Stable
Owner: Knowledge Maintainers
Depends On: `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md`, `25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md`, `25_KNOWLEDGE/KNOWLEDGE-GRAPH.md`, `25_KNOWLEDGE/CITATION-MANAGER.md`, `18_MEMORY`
Used By: `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, `25_KNOWLEDGE/SEMANTIC-SEARCH.md`, Reasoning, Agents, Workflows
Last Updated: 2026-07-07

## Purpose

The Knowledge Registry is the authoritative catalog of knowledge asset metadata used within SquirrelForge.

It assigns and maintains knowledge identifiers, source references, ownership metadata, type classification, lifecycle status references, trust-level references, version references, citation references, and relationship references.

The Knowledge Registry records metadata only. It does not validate knowledge, assign trust independently, model graph relationships, own version records, resolve citations, retrieve knowledge, manage memory, or own general logging, audit, storage, or observability infrastructure.

---

## Responsibilities

- Register knowledge assets.
- Assign unique knowledge identifiers.
- Record source and ownership.
- Classify knowledge by type and purpose.
- Record trust-level references supplied by `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md` or governance decisions.
- Record lifecycle status based on authoritative specialist results.
- Record relationship references supplied by `25_KNOWLEDGE/KNOWLEDGE-GRAPH.md`.
- Record version references supplied by `25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md`.
- Record citation references supplied by `25_KNOWLEDGE/CITATION-MANAGER.md`.
- Support knowledge discovery.

---

## Registry Process

1. Receive knowledge registration.
2. Assign unique Knowledge ID.
3. Validate required metadata.
4. Record source and ownership.
5. Classify knowledge asset.
6. Record initial lifecycle status as metadata.
7. Attach validation, trust, citation, version, or relationship references when supplied by owning components.
8. Publish registry entry for discovery.

---

## Knowledge Types

| Type | Description |
|---|---|
| Document | Structured written reference |
| Artifact | Generated workflow output |
| Rule | Operational or governance rule |
| Decision | Recorded reasoning outcome |
| Memory Reference | Validated, promoted memory-derived knowledge reference from `18_MEMORY`; not active memory state |
| Dataset | Structured data source |
| Integration Record | Knowledge from external systems |
| Observation Reference | Referenced operational record from `27_OBSERVABILITY`; not raw observability data |

---

## Registry Record

| Field | Description |
|---|---|
| Knowledge ID | Unique identifier |
| Name | Knowledge asset name |
| Type | Knowledge category |
| Source | Originating source |
| Owner | Responsible component |
| Trust Level Reference | Trust result from `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md` or governance decision |
| Lifecycle Status | Draft / Validated / Active / Deprecated / Archived |
| Version Reference | Current version from `25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md` |
| Citation Reference | Citation/provenance reference from `25_KNOWLEDGE/CITATION-MANAGER.md` |
| Relationship References | Linked graph references from `25_KNOWLEDGE/KNOWLEDGE-GRAPH.md` |

---

## Trust Levels

| Trust Level | Meaning |
|---|---|
| Low | Unverified or experimental knowledge |
| Medium | Partially reviewed knowledge |
| High | Validated and reliable knowledge |
| Authoritative | Approved source of truth |

The Registry records trust levels. Knowledge trust assessment belongs to `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md` and approved governance decisions.

---

## Governance Principles

- Every knowledge asset must have one unique identifier.
- Source information must be recorded.
- Ownership must be explicit.
- Trust level references must exist before trusted use.
- Related asset references should link to Knowledge Graph records when relevant.
- Registry entries must remain discoverable and request observability/audit records through owning infrastructure when required.

---

## Permission Boundary

The Knowledge Registry may create and maintain knowledge catalog entries, identifiers, source metadata, ownership metadata, type classification, lifecycle status metadata, and references to validation, trust, citation, version, and graph relationship records.

It must not validate knowledge, assign trust independently, model graph relationships, own version records, resolve citations, retrieve knowledge, manage memory lifecycle or memory state, or own general logging, audit, storage, or observability infrastructure.

---

## Domain Rule

Registry metadata applies identically regardless of domain. Domain-specific knowledge assets are cataloged through the same registry fields rather than maintaining separate domain-specific registries.

---

## Rule

No knowledge asset may be used for reasoning, planning, validation, or execution unless it has a Knowledge Registry entry with source, owner, lifecycle status, and required specialist references.
