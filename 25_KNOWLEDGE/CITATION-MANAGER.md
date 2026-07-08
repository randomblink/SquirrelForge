# SquirrelForge Citation Manager

Version: 1.0.0
Status: Stable
Owner: Knowledge Maintainers
Depends On: `25_KNOWLEDGE/KNOWLEDGE-REGISTRY.md`, `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md`, `25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md`, `25_KNOWLEDGE/DOCUMENT-REPOSITORY.md`, `37_STORAGE`
Used By: `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md`, Reasoning, Reporting, Agents
Last Updated: 2026-07-08

## Purpose

The Citation Manager owns citation records and citation-to-source-reference relationships for Knowledge Layer assets.

It creates stable citation identifiers and records source references, source-version references, locator metadata, retrieval timestamps, citation status, and provenance references.

The Citation Manager checks citation-format integrity, locator completeness, and source-reference resolvability. It provides citation status and provenance references to `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md`.

It supports reasoning and explanation components by supplying citation records. It does not perform reasoning, assess overall knowledge trust or validity, own raw source/document storage, create knowledge version records, own source version history, or own general audit or observability infrastructure.

---

## Responsibilities

- Register citations.
- Associate citations with knowledge assets.
- Create stable citation identifiers.
- Record source references and locator metadata.
- Record source-version references.
- Record retrieval timestamps.
- Check citation-format integrity.
- Check source-reference resolvability.
- Publish citation status for Knowledge Validator.
- Support citation retrieval.
- Maintain citation-record history.
- Support explainable reasoning by supplying citation records.

---

## Citation Process

1. Receive citation request.
2. Identify referenced knowledge asset.
3. Verify source registration.
4. Create or retrieve stable Citation ID.
5. Check citation format and locator completeness.
6. Resolve source reference or record unresolved status.
7. Record source-version reference when available.
8. Link citation record to the knowledge asset.
9. Return citation status and provenance reference.

---

## Citation Types

| Type | Description |
|---|---|
| Primary Source | Original authoritative source |
| Secondary Source | Derived reference |
| Internal Document | SquirrelForge-managed documentation |
| External Reference | Approved outside source |
| Workflow Output | Generated execution artifact |
| Decision Record | Recorded reasoning result |

---

## Citation Record

| Field | Description |
|---|---|
| Citation ID | Unique identifier |
| Knowledge ID | Referenced knowledge asset |
| Source Reference | Source or document reference |
| Source Version Reference | Version reference for the source, when available |
| Locator Metadata | Page, section, URL, anchor, timestamp, range, or other locator |
| Citation Type | Classification |
| Retrieval Timestamp | Time the source reference was retrieved or checked |
| Citation Status | Active / Unresolved / Stale / Superseded / Deprecated |
| Provenance Reference | Traceable provenance record for downstream validation or explanation |

---

## Provenance Principles

- Every citation must reference a registered source.
- Source ownership and source-version references must remain traceable.
- Citation-record history must be preserved.
- Updated sources should update citation status or source-version references; knowledge version records remain owned by `25_KNOWLEDGE/KNOWLEDGE-VERSIONING.md`.
- Broken or unresolved references must be detected and reported as citation status.
- Citation relationships must request observability or audit records through owning infrastructure when required.

---

## Citation Quality Guidelines

- Prefer authoritative sources.
- Preserve original source attribution.
- Avoid duplicate citations.
- Maintain citation consistency.
- Support explainable reasoning paths.
- Respect authorization policies for protected sources.

Citation quality does not determine overall knowledge trust. Knowledge trust assessment belongs to `25_KNOWLEDGE/KNOWLEDGE-VALIDATOR.md`.

---

## Permission Boundary

The Citation Manager may create citation records, maintain citation-to-source-reference relationships, check citation-format integrity, check locator completeness, check source-reference resolvability, record source-version references, and provide citation status/provenance references to other Knowledge components.

It must not assess overall knowledge trust or validity, perform reasoning, own raw source or document storage, create knowledge version records, own source version history, or own general logging, audit, storage, or observability infrastructure.

---

## Domain Rule

Citation records apply identically regardless of domain. Domain-specific citation locators may be stored as metadata, but citation ownership remains centralized here.

---

## Rule

Every knowledge asset that requires source support must have citation records with source references, locator metadata, citation status, and provenance references before it may be presented as trusted information.
