# SquirrelForge Telemetry Collector

Version: 1.0.0
Status: Stable
Owner: Observability Maintainers
Depends On: `27_OBSERVABILITY/OBSERVABILITY-GOVERNANCE.md`, `28_RUNTIME-CONFIG`, `37_STORAGE`
Used By: `27_OBSERVABILITY/LOG-MANAGER.md`, `27_OBSERVABILITY/METRICS-MANAGER.md`, `27_OBSERVABILITY/TRACE-MANAGER.md`, `27_OBSERVABILITY/DIAGNOSTICS-ENGINE.md`, `27_OBSERVABILITY/HEALTH-REPORTER.md`, `27_OBSERVABILITY/ALERT-MANAGER.md`
Last Updated: 2026-07-08

## Purpose

The Telemetry Collector is the observability intake point for approved operational events.

It owns telemetry event intake, schema/reference checks, normalization, enrichment, filtering, correlation identifiers, and downstream distribution references for Observability components.

It does not analyze telemetry, make operational decisions, create alerts, calculate metrics, write logs directly, build traces directly, execute recovery, or change workflow state.

---

## Responsibilities

- Receive approved telemetry events and event references.
- Check telemetry schema, source, timestamp, correlation, and classification fields.
- Apply observability redaction and filtering rules supplied by Observability Governance.
- Normalize telemetry events for logs, metrics, traces, diagnostics, alerts, dashboards, and health reporting.
- Attach or preserve correlation identifiers.
- Forward normalized telemetry references to downstream Observability components.

---

## Rules

1. Telemetry Collector must not collect raw secrets or prohibited payloads.
2. Telemetry Collector must not suppress required audit/security evidence except through approved redaction rules.
3. Telemetry Collector produces telemetry references only; downstream components own their own records and findings.
