# SquirrelForge Integration Manager

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `21_CONFIGURATION`, `24_SECURITY`, `26_INTEGRATIONS/CONNECTOR-MANAGER.md`, `26_INTEGRATIONS/INTEGRATION-GOVERNANCE.md`, `27_OBSERVABILITY`, `28_RUNTIME-CONFIG`
Used By: Engine, Agents, Execution, Reasoning, Knowledge, WordPress
Last Updated: 2026-07-08

## Purpose

The Integration Manager coordinates integration requests that need communication with external systems, services, APIs, tools, databases, plugins, providers, and automation platforms.

It owns integration request intake, integration routing coordination, handoff to approved connector, provider, API, webhook, storage-service, database-service, or automation components, and aggregation of integration response, status, and evidence references.

It does not own connector registry records, platform security decisions, governance policy enforcement, external execution internals, business validation, retry or recovery execution, authoritative workflow state, storage, logging, audit, or observability infrastructure.

---

## Responsibilities

- Receive internal requests for external integration capabilities.
- Check request structure and required readiness references.
- Identify the integration capability needed by the requesting component.
- Request connector metadata, lifecycle status, capability metadata, and availability references from `CONNECTOR-MANAGER.md`.
- Consume credential, authentication, authorization, and secret-reference status from the owning security and runtime-configuration components.
- Consume integration governance decisions from `INTEGRATION-GOVERNANCE.md`.
- Coordinate routing to approved connector, provider, API, webhook, database-service, file-service, version-control, or automation components.
- Coordinate handoff and return of normalized response, error, status, and evidence references.
- Aggregate integration-domain status for callers.
- Emit integration-domain status and event references to observability owners.
- Request retry, recovery, rollback, or failure handling from owning execution, coordination, or resilience components when needed.

---

## Boundary

`INTEGRATION-MANAGER.md` owns:

- integration request intake,
- integration request structure and readiness-reference checks,
- integration routing coordination,
- approved component handoff coordination,
- response, error, status, and evidence-reference aggregation,
- integration-domain status reporting to callers,
- and integration-domain status/event references for observability owners.

`INTEGRATION-MANAGER.md` does not own:

- connector registry records, connector definitions, connector readiness checks, or connector lifecycle status (`CONNECTOR-MANAGER.md`),
- platform identity, authentication, authorization, credential verification, or security enforcement (`24_SECURITY`),
- secret storage, credential storage, key storage, or runtime configuration authority (`28_RUNTIME-CONFIG` and `21_CONFIGURATION`),
- integration governance policy definition or approval decisions (`INTEGRATION-GOVERNANCE.md`),
- general policy evaluation or platform governance (`23_GOVERNANCE`),
- external execution internals inside provider, connector, API, webhook, storage-service, database-service, or automation components,
- business validation, output validation, task-completion validation, or quality gates (`14_ENGINE/VALIDATION.md` and domain owners),
- retries, recovery execution, rollback, or workflow failure handling (`17_COORDINATION`, `20_EXECUTION`, and resilience owners),
- authoritative workflow, task, or lifecycle state (`14_ENGINE/STATE-MANAGER.md`),
- platform storage, persistence, or document storage infrastructure (`37_STORAGE`),
- or logs, metrics, traces, dashboards, alerts, audit infrastructure, or observability pipelines (`27_OBSERVABILITY`).

---

## Inputs

The Integration Manager may receive:

- internal service requests,
- workflow or agent requests for external capability,
- API operation requests,
- connector or provider capability requirements,
- request payload references,
- connector metadata and lifecycle status from `CONNECTOR-MANAGER.md`,
- credential/authentication/authorization status references,
- integration governance decision references,
- endpoint and protocol references,
- availability and status references,
- retry or recovery instructions from owning execution or coordination components,
- and external response, error, and evidence references from the called integration component.

---

## Outputs

The Integration Manager may produce:

- integration routing coordination records,
- connector, provider, API, webhook, storage-service, database-service, version-control, or automation handoff requests,
- readiness-reference gap reports,
- credential/authentication/authorization status requests to owning components,
- governance decision requests to `INTEGRATION-GOVERNANCE.md`,
- normalized response/status/evidence reference bundles,
- retry/recovery/failure-handling requests to owning components,
- and integration-domain status/event references for callers and observability owners.

---

## Integration Coordination Flow

```text
Internal request for external capability
   ↓
Integration Manager checks request structure and readiness references
   ↓
Integration Manager consumes connector metadata, credential/auth status, governance decisions, and availability references
   ↓
Integration Manager coordinates handoff to the approved integration component
   ↓
Owning connector/provider/API component performs the external interaction
   ↓
Integration Manager aggregates normalized response, error, status, and evidence references
   ↓
Caller receives integration-domain result references and remains responsible for next-state decisions
```

The Integration Manager coordinates the handoff and result aggregation only. It does not decide whether the business task is valid, complete, should retry, should recover, or should change authoritative workflow state.

---

## Supported Integration Targets

The Integration Manager may coordinate handoff for:

- connector-based services,
- REST APIs,
- GraphQL APIs,
- MCP servers,
- webhooks,
- version-control providers,
- AI and LLM providers,
- external database services,
- external file-storage services,
- email providers,
- calendar providers,
- payment platforms,
- analytics systems,
- deployment platforms,
- and external automation tools.

---

## Request Readiness Checks

Request readiness checks are limited to structure and required references.

They may confirm that:

- the request identifies the required external capability,
- the request has a requesting component reference,
- required payload or payload-reference fields are present,
- connector, provider, endpoint, or protocol references can be requested from the appropriate owner,
- credential/authentication/authorization status references exist or can be requested from owning components,
- integration governance decision references exist or can be requested,
- and availability/status references are available for routing consideration.

Request readiness checks do not validate business outcomes, inspect raw secrets, make authorization decisions, execute external work, evaluate general policy, or determine workflow completion.

---

## Integration Status References

| Status | Meaning |
|---|---|
| `Received` | Integration request was accepted for coordination. |
| `Readiness Blocked` | Required structure or readiness references are missing. |
| `Governance Blocked` | Integration governance owner did not approve the requested external connection. |
| `Credential Blocked` | Credential/authentication/authorization status owner did not provide an approved status reference. |
| `Routed` | Request was handed off to the approved integration component. |
| `Response Received` | Integration component returned response, status, error, or evidence references. |
| `External Failure Reported` | Integration component reported external failure status. |
| `Recovery Requested` | Retry, recovery, rollback, or failure handling was requested from the owning component. |
| `Completed Handoff` | Integration Manager returned the aggregated integration result references to the caller. |

These statuses are integration-coordination statuses only. They are not authoritative workflow state, task state, validation state, incident state, recovery state, or storage state.

---

## Rules

1. Every external communication request must pass through an approved Integration Layer component.
2. Integration Manager must use connector metadata and status from `CONNECTOR-MANAGER.md`; it must not maintain a parallel connector registry.
3. Integration Manager must consume credential/authentication/authorization status from the owning security and runtime-configuration components.
4. Integration Manager must consume integration governance decisions from `INTEGRATION-GOVERNANCE.md`; it must not define or approve integration governance policy itself.
5. Integration Manager may coordinate handoff and aggregate result references, but it must not execute external provider internals directly.
6. Integration Manager may request retry, recovery, rollback, or failure handling from owning components, but it must not execute those actions itself.
7. Integration Manager must emit status and event references through observability owners; it must not maintain separate logging, audit, or observability infrastructure.
8. Integration Manager must not mark business outcomes valid or tasks complete.
