# SquirrelForge Integrations Layer

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `21_CONFIGURATION`, `24_SECURITY`, `27_OBSERVABILITY`, `28_RUNTIME-CONFIG`, `37_STORAGE`
Used By: Engine, Agents, Execution, Reasoning, Knowledge, WordPress
Last Updated: 2026-07-08

## Purpose

This directory defines how SquirrelForge communicates with external systems, services, APIs, development tools, AI providers, version control platforms, databases, and automation platforms.

The Integrations Layer provides consistent external connector interfaces, protocol adaptation, provider handoff, integration routing, request and response normalization, connector status references, and integration-specific coordination.

The Integrations Layer does not own platform security, secrets, runtime configuration, storage persistence, observability infrastructure, execution state, retry or recovery authority, rollback, or workflow completion state.

---

## Component Roster

| Component | Responsibility |
|---|---|
| `INTEGRATION-MANAGER.md` | Coordinates integration routing, connector handoffs, status aggregation, and integration-domain lifecycle references. |
| `CONNECTOR-MANAGER.md` | Maintains the registry of configured external connectors, connector capabilities, and connector availability references. |
| `API-GATEWAY.md` | Handles approved API ingress/egress protocol normalization, transport checks, throttling, and API transport status references. |
| `AUTHENTICATION.md` | Coordinates external-service authentication handshakes using approved credential references and security/runtime-config inputs. |
| `WEBHOOK-MANAGER.md` | Receives, normalizes, verifies, and dispatches webhook events to the appropriate integration owner. |
| `SERVICE-DISCOVERY.md` | Discovers and resolves external service endpoints, capabilities, and availability metadata. |
| `VERSION-CONTROL.md` | Adapts external version-control systems and repository APIs into SquirrelForge integration interfaces. |
| `AI-PROVIDERS.md` | Deprecated compatibility redirect to `LLM-PROVIDERS.md`. |
| `LLM-PROVIDERS.md` | Adapts external large-language-model APIs for the Reasoning layer through standardized model-provider interfaces. |
| `DATABASE-CONNECTOR.md` | Adapts external database APIs and connection interfaces without owning platform data persistence. |
| `FILE-STORAGE.md` | Adapts external file-storage services without owning SquirrelForge storage infrastructure. |
| `AUTOMATION-CONNECTOR.md` | Adapts external automation platforms, schedulers, job queues, and CI/CD systems into integration handoff interfaces. |
| `FLOCK-PLUGIN-ADAPTER.md` | Translates Flock plugin requests into the Engine API contract and maps authoritative Engine result envelopes back into Flock responses. |
| `INTEGRATION-MONITOR.md` | Interprets integration-domain telemetry and status references to produce health, availability, and degradation findings. |
| `INTEGRATION-GOVERNANCE.md` | Defines integration-domain standards, registration requirements, and allowed external-connection rules. |

The authoritative component roster must match the 15 component files that actually exist in `26_INTEGRATIONS`.

---

## Layer Boundary

`26_INTEGRATIONS` owns:

- external connector interfaces,
- protocol adaptation,
- provider-specific request and response translation,
- integration routing and handoff coordination,
- connector registration and capability references,
- external endpoint discovery references,
- integration-domain authentication flow coordination,
- webhook normalization and dispatch,
- integration-domain health and availability findings,
- and integration-domain standards for allowed external connections.

`26_INTEGRATIONS` does not own:

- platform identity lifecycle, credential verification, MFA, runtime authorization, or security control enforcement (`24_SECURITY`),
- secret storage, credential storage, key storage, or runtime configuration authority (`28_RUNTIME-CONFIG` and `21_CONFIGURATION`),
- platform data persistence, document storage, or storage infrastructure (`37_STORAGE`),
- general logs, metrics, traces, dashboards, alerts, audit infrastructure, or observability pipelines (`27_OBSERVABILITY`),
- execution state, workflow lifecycle state, completion state, retries, rollback, or recovery execution (`14_ENGINE`, `17_COORDINATION`, and `20_EXECUTION`),
- provider selection decisions owned by reasoning, execution, or domain-specific components,
- or validation authority for task completion and quality gates.

Integration components may request, consume, or emit references to those owners. They must not replace the authoritative owner.

---

## Integration Principles

- Every integration has a defined interface.
- External communication must use registered connector definitions.
- Authentication to external systems must use approved credential references and security/runtime-config inputs.
- Integration failures must return structured status and evidence to the owning execution or coordination component.
- Integration activity must emit observability events through the observability owner rather than maintaining separate logging infrastructure.
- Retry, recovery, and rollback decisions must remain with the owning execution, coordination, or resilience component.
- Security and governance policies must be referenced and followed through their owning components.
- External storage, database, and file-service connectors adapt remote services; they do not become SquirrelForge storage owners.

---

## Integration Flow

```text
Internal request for external capability
   ↓
Integration Manager routes to the responsible connector or provider component
   ↓
Connector resolves configuration, credential references, endpoint references, and policy references from owning components
   ↓
Connector normalizes the external request and performs the external handoff
   ↓
Connector normalizes response, error, or status evidence
   ↓
Owning workflow, execution, reasoning, or domain component receives the result and remains responsible for next-state decisions
```

---

## Rule

No workflow may communicate directly with an external system without passing through the Integrations Layer, and no integration component may claim ownership of security, runtime configuration, storage, observability, execution, recovery, or validation authority that belongs to another layer.
