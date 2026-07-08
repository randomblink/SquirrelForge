# SquirrelForge Integration Monitor

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `21_CONFIGURATION`, `26_INTEGRATIONS/CONNECTOR-MANAGER.md`, `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, `26_INTEGRATIONS/SERVICE-DISCOVERY.md`, `27_OBSERVABILITY`
Used By: `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, `26_INTEGRATIONS/CONNECTOR-MANAGER.md`, `26_INTEGRATIONS/SERVICE-DISCOVERY.md`, `26_INTEGRATIONS/INTEGRATION-GOVERNANCE.md`
Last Updated: 2026-07-08

## Purpose

The Integration Monitor interprets integration-domain telemetry, status references, availability references, and provider/connector signals to produce integration health, availability, performance, and status findings.

It consumes observability inputs and Integration-layer status references, then reports integration-domain findings to Integration components and callers.

It does not own general observability infrastructure, logging, metrics pipelines, traces, dashboards, alerts, audit storage, security monitoring, compliance assessment, governance approval, recovery execution, or authoritative workflow state.

---

## Responsibilities

- Consume integration telemetry and status references from observability owners.
- Interpret integration-domain health, availability, latency, error, rate-limit, authentication-failure, and degradation signals.
- Produce integration health and availability findings.
- Provide availability references to `CONNECTOR-MANAGER.md`, `SERVICE-DISCOVERY.md`, and `INTEGRATION-MANAGER.md`.
- Report integration-domain degradation, outage, or status findings to owning components.
- Preserve integration-monitor finding references through owning observability, audit, and storage infrastructure.

---

## Boundary

`INTEGRATION-MONITOR.md` owns:

- integration-domain telemetry interpretation,
- integration health findings,
- integration availability findings,
- integration performance findings,
- integration degradation findings,
- and integration status references supplied to Integration components.

`INTEGRATION-MONITOR.md` does not own:

- general logs, metrics, traces, dashboards, alerts, audit records, or observability pipelines (`27_OBSERVABILITY`),
- security-domain monitoring or threat detection (`24_SECURITY/SECURITY-MONITOR.md` and `24_SECURITY/THREAT-DETECTOR.md`),
- compliance assessment (`24_SECURITY/COMPLIANCE.md`),
- integration approval or exception decisions (`INTEGRATION-GOVERNANCE.md`),
- connector registry lifecycle (`CONNECTOR-MANAGER.md`),
- integration routing or handoff coordination (`INTEGRATION-MANAGER.md`),
- recovery execution, retries, rollback, or failure handling,
- external request execution,
- or authoritative workflow/task lifecycle state.

---

## Monitored Integration Signals

Integration Monitor may interpret:

| Signal | Meaning |
|---|---|
| `Availability` | External service, connector, or endpoint availability reference. |
| `Latency` | Integration call duration or delay signal. |
| `Error Rate` | Error frequency signal for a connector, provider, endpoint, or API. |
| `Rate Limit` | Rate-limit hit or quota-status signal. |
| `Authentication Failure` | External authentication failure signal from Integration Authentication or provider components. |
| `Timeout` | Transport timeout signal. |
| `Degradation` | Reduced capability, partial outage, or unstable provider status. |

These signals are interpreted as integration-domain findings only. They do not become security incidents, compliance findings, or workflow state without the owning component making that decision.

---

## Finding States

| State | Meaning |
|---|---|
| `Healthy` | Current signals show expected integration behavior. |
| `Degraded` | Signals show reduced or unreliable behavior. |
| `Unavailable` | Signals show the integration cannot currently serve requests. |
| `Rate Limited` | Signals show active rate-limit or quota pressure. |
| `Authentication Failing` | Signals show external authentication failures. |
| `Unknown` | Required observability or status references are missing or stale. |

Finding states are integration-monitor states only. They are not connector lifecycle, workflow, validation, recovery, compliance, or incident states.

---

## Rules

1. Integration Monitor must consume telemetry and status references from owning observability and Integration components.
2. Integration Monitor may produce integration-domain findings, but it must not maintain observability infrastructure.
3. Integration Monitor must not approve integrations, enforce governance, execute recovery, or change workflow state.
4. Integration Monitor must keep findings separate from security incidents, compliance findings, and validation outcomes unless those owners consume the finding.
5. Integration Monitor must provide status and availability references to Integration components without replacing their ownership.
