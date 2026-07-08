# SquirrelForge File Storage Connector

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `21_CONFIGURATION`, `24_SECURITY`, `26_INTEGRATIONS/AUTHENTICATION.md`, `26_INTEGRATIONS/CONNECTOR-MANAGER.md`, `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, `27_OBSERVABILITY`, `28_RUNTIME-CONFIG`, `37_STORAGE`
Used By: `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, Components requiring approved external file-service handoff
Last Updated: 2026-07-08

## Purpose

The File Storage Connector adapts approved external file-storage services into standardized Integration-layer request and response interfaces.

It owns external file-service protocol adaptation, file-service request translation, storage-provider response normalization, and external file-service status references.

It does not own SquirrelForge storage infrastructure, artifact lifecycle, retention, backup/restore authority, version history, credential storage, authorization, business validation, recovery execution, logging, audit, observability infrastructure, or authoritative workflow state.

---

## Responsibilities

- Adapt approved external file-service handoff requests to provider-specific protocols.
- Consume connector, endpoint, credential, governance, and configuration references from owning components.
- Translate create, read, update, delete, copy, move, sync, archive, and restore handoff requests when approved by the caller.
- Normalize external file-service responses, errors, checksums, version references, and availability status.
- Return file-service response, error, status, and evidence references to the caller.
- Emit file-service integration event references through observability owners.

---

## Boundary

`FILE-STORAGE.md` owns:

- external file-service protocol adaptation,
- file-service request translation,
- file-service response normalization,
- external file-service status references,
- external checksum/version references returned by providers,
- and file-service handoff evidence references.

`FILE-STORAGE.md` does not own:

- SquirrelForge storage infrastructure, document storage, artifact persistence, or retention (`37_STORAGE`),
- file lifecycle policy, backup/restore authority, or archival governance,
- runtime authorization decisions,
- credential or secret storage,
- business validation or task-completion validation,
- retry, rollback, recovery, or workflow failure handling,
- logging, audit, metrics, traces, dashboards, alerts, or observability infrastructure,
- or authoritative workflow/task lifecycle state.

---

## Supported Handoff Types

| Type | Description |
|---|---|
| `Create` | Submit an approved external file create request. |
| `Read` | Retrieve file content or metadata from an approved external file-service reference. |
| `Update` | Submit an approved external file update request. |
| `Delete` | Submit an approved external file delete request. |
| `Copy` | Submit an approved external file copy request. |
| `Move` | Submit an approved external file move request. |
| `Synchronize` | Submit an approved sync request and return external service status. |
| `Archive` | Submit an approved external archive request. |
| `Restore` | Submit an approved external restore request. |

These are external file-service handoff types only. Platform storage ownership remains with `37_STORAGE`.

---

## Rules

1. File Storage Connector may process only approved external file-service handoffs.
2. File Storage Connector must use credential, connector, endpoint, governance, and configuration references from owning components.
3. File Storage Connector must not store raw secrets or become the platform storage owner.
4. File Storage Connector may report provider checksum, version, and integrity references, but it must not validate business outcomes.
5. File Storage Connector must return normalized response, error, status, and evidence references to the caller.
6. File Storage Connector must emit event references through observability owners.
