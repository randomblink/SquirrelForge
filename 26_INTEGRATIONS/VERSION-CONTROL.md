# SquirrelForge Version Control Connector

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `21_CONFIGURATION`, `24_SECURITY`, `26_INTEGRATIONS/AUTHENTICATION.md`, `26_INTEGRATIONS/CONNECTOR-MANAGER.md`, `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, `27_OBSERVABILITY`, `28_RUNTIME-CONFIG`
Used By: `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, Execution and release components requiring approved version-control handoff
Last Updated: 2026-07-08

## Purpose

The Version Control Connector adapts approved external version-control systems and repository APIs into standardized Integration-layer request and response interfaces.

It owns version-control protocol adaptation, repository API request translation, repository response normalization, and version-control transport/status evidence references.

It does not own source-code authorship, branch strategy, release governance, merge approval, validation, rollback, recovery, credential storage, logging, audit, observability infrastructure, or authoritative workflow state.

---

## Responsibilities

- Adapt approved repository handoff requests to provider-specific version-control APIs.
- Consume connector, repository, credential, governance, and configuration references from owning components.
- Translate clone, fetch, pull, push, branch, commit, tag, release, and pull-request handoffs when approved by the caller.
- Normalize repository API responses, commit references, branch references, pull-request references, errors, and provider status.
- Return version-control response, error, status, and evidence references to the caller.
- Emit version-control integration event references through observability owners.

---

## Boundary

`VERSION-CONTROL.md` owns:

- external version-control protocol adaptation,
- repository API request translation,
- version-control response normalization,
- repository reference metadata returned by providers,
- version-control transport status references,
- and version-control handoff evidence references.

`VERSION-CONTROL.md` does not own:

- source-code change authorship,
- branch strategy or merge policy,
- release approval or release governance,
- validation, CI results, or quality gates,
- rollback, recovery, or failure handling,
- credential or secret storage,
- business routing or integration routing,
- logging, audit, metrics, traces, dashboards, alerts, or observability infrastructure,
- or authoritative workflow/task lifecycle state.

---

## Supported Handoff Types

| Type | Description |
|---|---|
| `Clone` | Request an approved repository copy operation. |
| `Fetch` | Request approved remote metadata or object retrieval. |
| `Pull` | Request approved remote synchronization. |
| `Push` | Request approved publication of local commits. |
| `Branch` | Request approved branch creation or lookup. |
| `Commit` | Request approved commit creation using supplied changes. |
| `Tag` | Request approved tag creation or lookup. |
| `Release` | Request approved release API handoff. |
| `Pull Request` | Request approved pull-request API handoff. |

Approval, validation, and release decisions remain with the calling, governance, execution, or domain owner.

---

## Rules

1. Version Control Connector may process only approved version-control handoffs.
2. Version Control Connector must use repository, credential, connector, governance, and configuration references from owning components.
3. Version Control Connector must not define branch policy, release policy, validation policy, or rollback behavior.
4. Version Control Connector must return normalized response, error, status, and evidence references to the caller.
5. Version Control Connector must emit event references through observability owners.
