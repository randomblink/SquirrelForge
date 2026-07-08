# SquirrelForge Connector Manager

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `21_CONFIGURATION`, `24_SECURITY`, `26_INTEGRATIONS/INTEGRATION-GOVERNANCE.md`, `27_OBSERVABILITY`, `28_RUNTIME-CONFIG`
Used By: `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, `26_INTEGRATIONS/SERVICE-DISCOVERY.md`, `26_INTEGRATIONS/INTEGRATION-MONITOR.md`
Last Updated: 2026-07-08

## Purpose

The Connector Manager owns the registry of approved connector definitions used by the Integrations layer.

It records connector capability metadata, endpoint references, protocol references, owner references, readiness references, lifecycle status, activation and deactivation records, and availability references. It exposes connector metadata and connector status to `INTEGRATION-MANAGER.md` so integration routing can use approved connector information.

The Connector Manager performs connector schema and readiness checks only. It does not select business routing, execute integrations, monitor general health infrastructure, execute recovery, own secrets, make authentication or authorization decisions, own storage, maintain logging or audit infrastructure, own observability pipelines, or validate business outcomes.

---

## Responsibilities

- Register approved connector definitions.
- Maintain connector identifiers, names, versions, owners, and supported protocol references.
- Record connector endpoint references and service-discovery references.
- Record connector capability metadata and supported-operation metadata.
- Record connector readiness references from configuration, security, governance, and availability checks.
- Maintain connector lifecycle status.
- Record connector activation and deactivation events.
- Record connector availability references supplied by integration monitoring and observability owners.
- Expose connector metadata and lifecycle status to `INTEGRATION-MANAGER.md`.
- Preserve connector history and status references through the owning storage, audit, and observability infrastructure.

---

## Boundary

`CONNECTOR-MANAGER.md` owns:

- connector registry records,
- connector definitions,
- connector capability metadata,
- endpoint and protocol references,
- connector owner references,
- connector readiness references,
- connector lifecycle status,
- activation and deactivation records,
- and connector availability references.

`CONNECTOR-MANAGER.md` does not own:

- business routing or task routing decisions (`26_INTEGRATIONS/INTEGRATION-MANAGER.md` and the calling workflow owner),
- integration execution or external request execution,
- general health monitoring, metrics, traces, alerts, dashboards, logging, audit, or observability infrastructure (`27_OBSERVABILITY`),
- recovery execution, rollback, retries, or failure handling (`17_COORDINATION` and `20_EXECUTION`),
- platform security decisions, authentication, authorization, or security enforcement (`24_SECURITY`),
- secret storage, credential storage, or runtime configuration authority (`28_RUNTIME-CONFIG` and `21_CONFIGURATION`),
- platform storage or persistence infrastructure (`37_STORAGE`),
- or validation of business outcomes and task completion (`14_ENGINE/VALIDATION.md` and domain owners).

---

## Connector Record

Each connector record may include:

| Field | Description |
|---|---|
| Connector ID | Stable connector identifier. |
| Connector Name | Human-readable connector name. |
| Version | Connector definition version. |
| Owner Reference | Owning team, component, or domain reference. |
| Provider Reference | External provider or service reference. |
| Endpoint Reference | Endpoint or service-discovery reference, not a raw secret. |
| Protocol Reference | REST, GraphQL, MCP, webhook, database, file service, or other protocol reference. |
| Capability Metadata | Supported operations and declared capability metadata. |
| Configuration Reference | Configuration record reference from the owning configuration component. |
| Credential Reference | Approved credential reference, never raw credential material. |
| Governance Reference | Integration governance approval or restriction reference. |
| Readiness References | Schema, configuration, credential, governance, and availability check references. |
| Lifecycle Status | Current connector lifecycle state. |
| Availability Reference | Latest availability/status reference from integration monitoring or observability. |

---

## Connector Readiness Checks

Connector readiness checks are limited to registry and connector-definition readiness.

Readiness checks may confirm that:

- required connector metadata is present,
- the connector definition follows the expected schema,
- endpoint, protocol, configuration, and credential references exist,
- governance approval or restriction references exist,
- declared capabilities are internally consistent,
- and availability/status references are current enough for routing owners to consider.

Readiness checks do not validate business outcomes, execute external work, authorize access, retrieve raw secrets, perform general health monitoring, or decide whether a workflow should proceed.

---

## Connector States

| State | Meaning |
|---|---|
| `Registered` | Connector definition exists in the registry. |
| `Readiness Pending` | Required readiness references are incomplete or not yet checked. |
| `Ready` | Connector schema and required references are present. |
| `Active` | Connector is approved for routing consideration by `INTEGRATION-MANAGER.md`. |
| `Degraded` | Availability reference indicates reduced or unreliable service. |
| `Suspended` | Connector has been administratively removed from routing consideration. |
| `Retired` | Connector is no longer available for new integration use. |

Connector states are connector-registry states only. They are not workflow state, task state, incident state, validation state, or recovery state.

---

## Interaction with Integration Manager

`INTEGRATION-MANAGER.md` requests connector metadata and status from the Connector Manager when an integration task needs an external connector.

The Connector Manager returns:

- connector identity and owner references,
- capability metadata,
- endpoint and protocol references,
- readiness references,
- lifecycle status,
- availability references,
- and governance/configuration/security reference pointers.

`INTEGRATION-MANAGER.md` remains responsible for integration routing and handoff coordination. The Connector Manager does not select the business route or execute the integration request.

---

## Rules

1. Connector records must use references for credentials, configuration, endpoints, governance approvals, and availability evidence.
2. Raw secrets must never be stored in connector definitions or connector metadata.
3. Connector readiness checks must remain limited to connector schema and readiness references.
4. Connector activation and deactivation are registry lifecycle decisions only; they are not recovery execution or workflow state changes.
5. Connector status must be exposed to `INTEGRATION-MANAGER.md` without replacing integration routing authority.
6. Connector activity, lifecycle events, and status changes must be recorded through the owning storage, audit, and observability infrastructure.
7. The Connector Manager must not validate business outcomes or mark integration tasks complete.
