# SquirrelForge Database Connector

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `21_CONFIGURATION`, `24_SECURITY`, `26_INTEGRATIONS/AUTHENTICATION.md`, `26_INTEGRATIONS/CONNECTOR-MANAGER.md`, `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, `27_OBSERVABILITY`, `28_RUNTIME-CONFIG`, `37_STORAGE`
Used By: `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, Components requiring approved external database handoff
Last Updated: 2026-07-08

## Purpose

The Database Connector adapts approved external database systems into standardized Integration-layer request and response interfaces.

It owns database connection protocol adaptation, query/request translation, database response normalization, transaction-status references returned by external systems, and database transport error/status references.

It does not own platform data persistence, database schema authority, business data validation, storage infrastructure, backup/restore authority, runtime authorization, credential storage, recovery execution, logging, audit, observability infrastructure, or authoritative workflow state.

---

## Responsibilities

- Adapt approved database handoff requests to external database protocols.
- Consume connector, endpoint, credential, governance, and configuration references from owning components.
- Translate approved query or operation references into provider-specific database requests.
- Normalize query results, write acknowledgments, transaction statuses, and database transport errors.
- Return database response, status, usage, and evidence references to the caller.
- Emit database integration event references through observability owners.

---

## Boundary

`DATABASE-CONNECTOR.md` owns:

- external database protocol adaptation,
- database request translation,
- database response normalization,
- database transport and transaction status references,
- database connection status references,
- and database handoff evidence references.

`DATABASE-CONNECTOR.md` does not own:

- platform data persistence or storage infrastructure (`37_STORAGE`),
- database schema design or migration authority,
- business data integrity validation or domain validation,
- backup, restore, retention, or recovery authority,
- runtime authorization decisions,
- credential or secret storage,
- query policy definition,
- retry, rollback, recovery, or workflow failure handling,
- logging, audit, metrics, traces, dashboards, alerts, or observability infrastructure,
- or authoritative workflow/task lifecycle state.

---

## Supported Handoff Types

| Type | Description |
|---|---|
| `Connect` | Establish or verify an external database connection reference. |
| `Query` | Submit an approved read request. |
| `Write` | Submit an approved write request. |
| `Transaction` | Submit an approved grouped-operation request and return transaction status. |
| `Metadata` | Retrieve approved external database metadata. |

Operation approval, authorization, and business validation are supplied by owning components before or after the handoff.

---

## Rules

1. Database Connector may process only approved database handoffs.
2. Database Connector must use credential, connector, endpoint, governance, and configuration references from owning components.
3. Database Connector must not store raw secrets or become the platform persistence owner.
4. Database Connector may report database transaction status, but it must not validate business outcome or mark tasks complete.
5. Database Connector must return normalized response, error, status, and evidence references to the caller.
6. Database Connector must emit event references through observability owners.
