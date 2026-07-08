# SquirrelForge Log Manager

Version: 1.0.0
Status: Stable
Owner: Observability Maintainers
Depends On: `27_OBSERVABILITY/OBSERVABILITY-GOVERNANCE.md`, `27_OBSERVABILITY/TELEMETRY-COLLECTOR.md`, `37_STORAGE`
Used By: `27_OBSERVABILITY/DIAGNOSTICS-ENGINE.md`, `27_OBSERVABILITY/ALERT-MANAGER.md`, `27_OBSERVABILITY/DASHBOARD-MANAGER.md`, `27_OBSERVABILITY/HEALTH-REPORTER.md`
Last Updated: 2026-07-08

## Purpose

The Log Manager owns structured log records derived from normalized telemetry and component event references.

It classifies log severity, preserves correlation context, applies redaction requirements, records log availability references, and exposes searchable log references through owning storage/search infrastructure.

It does not generate source events, own audit-event records, own storage infrastructure, perform diagnostics, certify compliance, execute recovery, or validate business outcomes.

---

## Responsibilities

- Produce structured log records from normalized telemetry.
- Classify log severity and source category.
- Preserve correlation, source, timestamp, and context references.
- Apply redaction and retention requirements from Observability Governance.
- Provide log availability references to Observability components.

---

## Rules

1. Log Manager must not store raw secrets or prohibited sensitive data.
2. Log Manager owns log records only; audit-event records belong to `AUDIT-TRAIL.md`.
3. Log storage is performed through owning storage infrastructure, not owned locally by Log Manager.
