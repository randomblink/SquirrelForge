# SquirrelForge Observability Layer

Version: 1.0.0
Status: Stable
Owner: Observability Maintainers
Depends On: `14_ENGINE`, `20_EXECUTION`, `23_GOVERNANCE`, `24_SECURITY`, `26_INTEGRATIONS`, `28_RUNTIME-CONFIG`, `37_STORAGE`
Used By: Engine, Execution, Integrations, Security, Governance, Testing, Runtime Config, WordPress
Last Updated: 2026-07-08

## Purpose

This directory defines how SquirrelForge collects, records, measures, traces, audits, diagnoses, alerts, reports, and presents operational signals across platform components.

The Observability Layer owns observability records, signal pipelines, operational findings, dashboards, alerts, health reports, audit-event records, and observability governance records. It provides visibility and evidence references; it does not own business execution, security decisions, compliance certification, recovery execution, storage infrastructure, or authoritative workflow state.

---

## Component Roster

| Component | Responsibility |
|---|---|
| `OBSERVABILITY-MANAGER.md` | Coordinates observability intake, routing, status aggregation, and component handoffs. |
| `OBSERVABILITY-GOVERNANCE.md` | Defines observability-domain standards, retention requirements, redaction rules, and evidence requirements. |
| `TELEMETRY-COLLECTOR.md` | Collects and normalizes approved operational telemetry events. |
| `TELEMETRY.md` | Deprecated compatibility redirect to `TELEMETRY-COLLECTOR.md`. |
| `LOG-MANAGER.md` | Produces structured log records and log availability references. |
| `LOGGING.md` | Deprecated compatibility redirect to `LOG-MANAGER.md`. |
| `METRICS-MANAGER.md` | Produces metric records, aggregates, and time-series references. |
| `METRICS.md` | Deprecated compatibility redirect to `METRICS-MANAGER.md`. |
| `TRACE-MANAGER.md` | Produces trace, span, correlation, and execution-path references. |
| `TRACING.md` | Deprecated compatibility redirect to `TRACE-MANAGER.md`. |
| `AUDIT-TRAIL.md` | Maintains audit-event records and audit evidence references. |
| `DIAGNOSTICS-ENGINE.md` | Interprets observability signals to produce diagnostic findings and probable-cause reports. |
| `ALERT-MANAGER.md` | Produces alert records, alert status, notification requests, and escalation references. |
| `ALERTING.md` | Deprecated compatibility redirect to `ALERT-MANAGER.md`. |
| `HEALTH-REPORTER.md` | Produces platform, component, dependency, and service health reports. |
| `DASHBOARD-MANAGER.md` | Produces dashboard views and visualization references from observability records. |
| `DASHBOARDS.md` | Deprecated compatibility redirect to `DASHBOARD-MANAGER.md`. |

The authoritative component roster must match the 17 component files that actually exist in `27_OBSERVABILITY`.

---

## Layer Boundary

`27_OBSERVABILITY` owns:

- telemetry collection and normalization,
- structured log records,
- metric records and aggregates,
- trace and span records,
- audit-event records,
- diagnostic findings from observability signals,
- alert records and alert notification requests,
- health reports,
- dashboard views,
- observability-domain retention, redaction, and signal standards,
- and observability evidence references.

`27_OBSERVABILITY` does not own:

- business execution or external integration execution,
- authoritative workflow/task lifecycle state (`14_ENGINE/STATE-MANAGER.md`),
- validation authority or task-completion decisions (`14_ENGINE/VALIDATION.md` and domain owners),
- recovery execution, retries, rollback, or failure handling (`17_COORDINATION`, `20_EXECUTION`, and resilience owners),
- security-domain decisions, threat detection, incident ownership, authentication, or authorization (`24_SECURITY`),
- compliance certification or compliance-domain assessment authority (`24_SECURITY/COMPLIANCE.md` and governance/compliance owners),
- general governance policy evaluation (`23_GOVERNANCE/POLICY-ENGINE.md`),
- raw storage infrastructure, document storage, or persistence engine ownership (`37_STORAGE`),
- or runtime configuration and secret storage (`28_RUNTIME-CONFIG`).

---

## Observability Flow

```text
Platform/component event
   ↓
Telemetry Collector normalizes approved telemetry
   ↓
Log, Metrics, and Trace Managers produce observability records
   ↓
Diagnostics, Alert Manager, Health Reporter, and Dashboard Manager consume records
   ↓
Observability Manager aggregates status and evidence references
   ↓
Owning workflow, execution, security, governance, or domain component makes the domain decision
```

---

## Rule

No significant workflow, execution, integration, validation, security, governance, configuration, or release action should be considered operationally visible unless it emits appropriate observability records or evidence references through this layer.
