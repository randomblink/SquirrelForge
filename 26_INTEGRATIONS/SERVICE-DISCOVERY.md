# SquirrelForge Service Discovery

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `21_CONFIGURATION`, `24_SECURITY`, `26_INTEGRATIONS/CONNECTOR-MANAGER.md`, `26_INTEGRATIONS/INTEGRATION-GOVERNANCE.md`, `27_OBSERVABILITY`, `37_STORAGE`
Used By: `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, `26_INTEGRATIONS/CONNECTOR-MANAGER.md`, `26_INTEGRATIONS/INTEGRATION-GOVERNANCE.md`, `26_INTEGRATIONS/INTEGRATION-MONITOR.md`
Last Updated: 2026-07-08

## Purpose

Service Discovery owns integration-domain discovery records for external services, APIs, connectors, MCP servers, plugins, repositories, database services, file-storage services, automation platforms, and provider endpoints available for approved Integration-layer use.

It records endpoint references, capability metadata, protocol metadata, provider metadata, version references, ownership references, availability references, and discovery evidence references.

It does not establish external connections, execute requests, perform security authorization, approve integrations, own connector registry records, monitor general infrastructure, execute recovery, store secrets, maintain logging/audit/observability infrastructure, or own authoritative workflow state.

---

## Responsibilities

- Discover or ingest approved external service references.
- Record endpoint, provider, protocol, capability, and version metadata.
- Record ownership, governance, configuration, and credential-reference requirements.
- Record service availability references supplied by integration monitoring or observability owners.
- Provide discovery records to `INTEGRATION-MANAGER.md`, `CONNECTOR-MANAGER.md`, and `INTEGRATION-GOVERNANCE.md`.
- Preserve discovery history and evidence references through owning storage, audit, and observability infrastructure.

---

## Boundary

`SERVICE-DISCOVERY.md` owns:

- integration-domain service discovery records,
- endpoint references,
- service capability metadata,
- protocol and version metadata,
- provider and ownership references,
- service availability references,
- and discovery evidence references.

`SERVICE-DISCOVERY.md` does not own:

- connector registry records or connector lifecycle (`CONNECTOR-MANAGER.md`),
- integration routing or handoff coordination (`INTEGRATION-MANAGER.md`),
- integration approval decisions (`INTEGRATION-GOVERNANCE.md`),
- authentication, authorization, or security enforcement (`24_SECURITY`),
- credential or secret storage (`28_RUNTIME-CONFIG`),
- external request execution,
- recovery, rollback, retry, or failure handling,
- general health monitoring infrastructure,
- logging, audit, metrics, traces, dashboards, alerts, or observability pipelines,
- platform storage infrastructure,
- or authoritative workflow/task lifecycle state.

---

## Discovery Record

| Field | Description |
|---|---|
| Service ID | Stable service identifier. |
| Service Name | Human-readable service name. |
| Provider Reference | External provider or owner reference. |
| Endpoint Reference | Endpoint reference, not raw secret material. |
| Protocol Metadata | REST, GraphQL, MCP, webhook, database, file-service, or other protocol metadata. |
| Capability Metadata | Declared available operations or capabilities. |
| Version Reference | API, service, connector, or protocol version reference. |
| Credential Requirement Reference | Required credential/authentication reference type. |
| Governance Reference | Integration governance approval or restriction reference. |
| Availability Reference | Latest availability/status reference from monitoring or observability owners. |
| Discovery Status | Current discovery-record state. |

---

## Discovery States

| State | Meaning |
|---|---|
| `Discovered` | Service metadata was found or provided. |
| `Reference Pending` | Required endpoint, owner, governance, or credential references are incomplete. |
| `Verified` | Required discovery references are present. |
| `Available` | Availability reference indicates the service can be considered by routing owners. |
| `Degraded` | Availability reference indicates constrained service. |
| `Unavailable` | Availability reference indicates the service is not currently usable. |
| `Deprecated` | Service is scheduled for removal or replacement. |
| `Retired` | Service is no longer available for new integration use. |

Discovery states are service-discovery record states only. They are not connector lifecycle, workflow, validation, recovery, or incident states.

---

## Rules

1. Service Discovery must record references and metadata only; it must not execute external service calls except approved discovery checks.
2. Service Discovery must not approve services for use; governance decisions belong to `INTEGRATION-GOVERNANCE.md`.
3. Service Discovery must not create connector registry records; connector ownership belongs to `CONNECTOR-MANAGER.md`.
4. Service Discovery may expose availability references, but monitoring infrastructure belongs to `27_OBSERVABILITY` and `INTEGRATION-MONITOR.md`.
5. Service Discovery must not store raw secrets or bypass security decisions.
6. Discovery history and evidence references must be preserved through owning storage, audit, and observability infrastructure.
