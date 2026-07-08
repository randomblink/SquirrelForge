# SquirrelForge Knowledge Versioning Manager

Version: 1.0.0
Status: Stable
Owner: Knowledge Maintainers
Depends On: `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`, `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md`, `25_KNOWLEDGE/CITATION-MANAGER.md`, `23_GOVERNANCE/VERSIONING.md`, `37_STORAGE`
Used By: `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`, `25_KNOWLEDGE/CITATION-MANAGER.md`, Reasoning, Agents
Last Updated: 2026-07-08

## Purpose

The Knowledge Versioning Manager owns Knowledge Layer version records and lineage for knowledge assets.

It records revisions, parent-child relationships, supersession, branch/merge references, rollback references, and historical reconstruction references for knowledge assets.

It follows platform version policy from `23_GOVERNANCE/VERSIONING.md`, but it does not define general versioning policy, validate updated knowledge, publish approved knowledge, execute rollback, own raw source/document storage, own citation records, or own general audit/observability infrastructure.

---

## Responsibilities

- Create knowledge versions.
- Preserve knowledge revision lineage.
- Record knowledge branching references.
- Record knowledge merge references.
- Track superseded knowledge versions.
- Record rollback references for knowledge assets.
- Maintain historical traceability.
- Provide version references to Knowledge Manager, Registry, Validator, and Citation Manager.

---

## Versioning Process

1. Receive knowledge update.
2. Verify current version.
3. Create new revision.
4. Preserve previous version.
5. Attach validation reference from `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md` when available.
6. Record version lineage, supersession, branch, merge, or rollback reference.
7. Return version reference to `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`.
8. Provide registry update reference to `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`.

---

## Version Lifecycle

| Stage | Description |
|---|---|
| Draft | Under development |
| Review | Awaiting approval |
| Approved | Validated and available |
| Active | Current production version |
| Superseded | Replaced by newer version |
| Deprecated | Scheduled for retirement |
| Archived | Retained for historical reference |

---

## Version Record

| Field | Description |
|---|---|
| Version ID | Unique identifier |
| Knowledge ID | Associated knowledge asset |
| Parent Version | Previous revision |
| Change Type | Create / Update / Merge / Rollback |
| Status | Current lifecycle state |
| Author | Responsible component |
| Validation Reference | Knowledge validation result associated with this version, when applicable |
| Citation References | Citation records associated with this version, when applicable |
| Supersedes | Prior version superseded by this version, when applicable |
| Timestamp | Version creation time |

---

## Versioning Principles

- Every change creates a new version.
- Historical versions remain immutable.
- Knowledge rollback references generate new knowledge versions rather than modifying history.
- Branches must remain traceable.
- Merges preserve lineage.
- Superseded knowledge remains discoverable.

Version records do not activate knowledge by themselves. Knowledge lifecycle transitions are coordinated through `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` using validation, registry, citation, and version references.

---

## Reconstruction Capabilities

The Knowledge Versioning Manager supports:

- Historical workflow reconstruction.
- Decision replay.
- Audit or investigation references through owning observability/audit infrastructure.
- Knowledge rollback references.
- Version comparison.
- Change impact analysis.

It supports reconstruction by supplying knowledge version references and lineage; it does not own execution replay, audit infrastructure, or workflow rollback.

---

## Permission Boundary

The Knowledge Versioning Manager may create immutable knowledge version records, preserve lineage, record branch/merge/supersession/rollback references, compare knowledge versions, and provide version references to other Knowledge components.

It must not define general version policy, validate updated knowledge, publish approved versions, execute rollback, own raw source/document storage, own citation records, own source version history outside knowledge assets, or own general logging, audit, storage, or observability infrastructure.

---

## Domain Rule

Knowledge versioning applies identically regardless of domain. Domain-specific knowledge assets use the same version record model rather than separate domain-specific versioning systems.

---

## Rule

Every modification to a knowledge asset must create a new immutable Knowledge Versioning record before it can be considered for activation, retrieval, or reasoning.
