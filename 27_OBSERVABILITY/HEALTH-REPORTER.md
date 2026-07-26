# SquirrelForge Health Reporter

Version: 1.1.0
Status: Stable
Owner: Observability Maintainers
Depends On: `27_OBSERVABILITY/LOG-MANAGER.md`, `27_OBSERVABILITY/METRICS-MANAGER.md`, `27_OBSERVABILITY/TRACE-MANAGER.md`, `27_OBSERVABILITY/DIAGNOSTICS-ENGINE.md`, `27_OBSERVABILITY/ALERT-MANAGER.md`
Used By: `27_OBSERVABILITY/DASHBOARD-MANAGER.md`, Engine, Execution, Integrations, Security, Governance
Last Updated: 2026-07-26

## Purpose

The Health Reporter owns operational health reports derived from observability records and component-supplied status references.

It reports platform, component, dependency, integration, service, and readiness health. It does not own workflow state, incident state, security posture decisions, compliance assessment, recovery execution, or business validation.

---

## Responsibilities

- Consume metric, log, trace, diagnostic, alert, and status references.
- Produce health reports, health summaries, dependency-health references, and readiness references.
- Distinguish observed health evidence from inferred degradation.
- Provide health references to dashboards, alerting, governance, and owning domain components.

---

## Health States

| State | Meaning |
|---|---|
| `Healthy` | Current evidence indicates expected operation. |
| `Degraded` | Evidence indicates reduced, delayed, or unstable operation. |
| `Unhealthy` | Evidence indicates the component or service cannot reliably operate. |
| `Unknown` | Required evidence is missing, stale, or insufficient. |

Health states are observability health reports only. They do not replace authoritative lifecycle, incident, validation, or recovery states.

---

## Rules

1. Health Reporter must consume evidence from owning Observability and domain components.
2. Health Reporter must not execute recovery or change workflow state.
3. Health reports must identify evidence freshness and source references.

---

## Reference Runtime

Provider dependency readiness consumes a redacted `ProviderHealthInterface` decision and aggregate `ProviderTelemetryInterface` counters. It reports health, readiness, circuit state, and evidence freshness without copying provider URLs, credentials, payloads, remote response bodies, identities, or internal exception details.

The reference `GET /v1/health/providers` binding returns `200` only when the provider reports healthy and its circuit is not open; otherwise it returns `503`. This status is operational evidence and must not be reinterpreted as an authentication, authorization, incident, or recovery decision.
