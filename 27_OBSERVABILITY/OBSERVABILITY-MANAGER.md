# SquirrelForge Observability Manager

Version: 1.0.0
Status: Stable
Owner: Observability Maintainers
Depends On: `23_GOVERNANCE`, `27_OBSERVABILITY/OBSERVABILITY-GOVERNANCE.md`, `37_STORAGE`
Used By: Engine, Execution, Integrations, Security, Governance, Runtime Config, Testing
Last Updated: 2026-07-08

## Purpose

The Observability Manager coordinates observability requests, signal handoffs, status aggregation, and evidence-reference routing across the Observability Layer.

It does not collect telemetry directly, write logs directly, calculate metrics, build traces, diagnose issues, create alerts, validate business outcomes, execute recovery, own storage infrastructure, or change workflow state.

---

## Responsibilities

- Receive observability coordination requests.
- Route telemetry, logging, metrics, tracing, audit, diagnostics, alert, dashboard, and health-reporting requests to the owning Observability component.
- Check observability request structure and required references.
- Aggregate observability-domain status and evidence references.
- Coordinate with Observability Governance for retention, redaction, and signal-standard references.
- Return observability status references to callers.

---

## Boundary

`OBSERVABILITY-MANAGER.md` owns observability coordination only.

It does not own:

- telemetry collection (`TELEMETRY-COLLECTOR.md`),
- log records (`LOG-MANAGER.md`),
- metric records (`METRICS-MANAGER.md`),
- trace records (`TRACE-MANAGER.md`),
- audit-event records (`AUDIT-TRAIL.md`),
- diagnostics (`DIAGNOSTICS-ENGINE.md`),
- alerts (`ALERT-MANAGER.md`),
- health reports (`HEALTH-REPORTER.md`),
- dashboards (`DASHBOARD-MANAGER.md`),
- observability standards and retention rules (`OBSERVABILITY-GOVERNANCE.md`),
- storage infrastructure (`37_STORAGE`),
- or domain decisions outside Observability.

---

## Rules

1. Observability Manager must route work to the owning Observability component.
2. Observability Manager may aggregate status and evidence references only.
3. Observability Manager must not replace authoritative workflow, security, governance, execution, validation, storage, or recovery owners.
