# SquirrelForge Security Manager

Version: 1.0.0
Status: Stable
Owner: Security Maintainers
Depends On: `24_SECURITY/SECURITY-GOVERNANCE.md`, `24_SECURITY/SECURITY-MONITOR.md`, `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`, `23_GOVERNANCE/POLICY-ENGINE.md`
Used By: `24_SECURITY/IDENTITY-MANAGER.md`, `24_SECURITY/AUTHENTICATION-MANAGER.md`, `24_SECURITY/AUTHORIZATION-MANAGER.md`, `24_SECURITY/ENCRYPTION-MANAGER.md`, `24_SECURITY/THREAT-DETECTOR.md`, `24_SECURITY/INCIDENT-MANAGER.md`
Last Updated: 2026-07-07

## Purpose

The Security Manager is the central coordinator for Security Layer activity. It receives security events, alerts, findings, and status references from owning components, routes them to the responsible security component, and consolidates correlation/status summaries for reporting.

The Security Manager coordinates security workflows only. It does not authenticate identities (owned by `24_SECURITY/AUTHENTICATION-MANAGER.md` and `24_SECURITY/IDENTITY-MANAGER.md`), authorize requests (owned by `24_SECURITY/AUTHORIZATION-MANAGER.md`), perform cryptographic operations (owned by `24_SECURITY/ENCRYPTION-MANAGER.md`), detect or classify threats (owned by `24_SECURITY/THREAT-DETECTOR.md`), monitor security posture (owned by `24_SECURITY/SECURITY-MONITOR.md`), coordinate incident-response mechanics (owned by `24_SECURITY/INCIDENT-MANAGER.md`), assess compliance (owned by `24_SECURITY/COMPLIANCE.md`), remediate vulnerabilities (owned by the assigned remediation owner and coordinated through `24_SECURITY/VULNERABILITY-MANAGEMENT.md`), define security policy or approve exceptions (owned by `24_SECURITY/SECURITY-GOVERNANCE.md`), evaluate policy (owned by `23_GOVERNANCE/POLICY-ENGINE.md`), or handle raw secrets (owned by `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`).

---

## Responsibilities

- Coordinate all Security Layer components.
- Receive security events, alerts, findings, and status references from owning components.
- Route each security item to the responsible security component.
- Preserve correlation identifiers across handoffs.
- Track routing and coordination status without owning authoritative lifecycle state.
- Forward policy references, approval references, or exception references from `24_SECURITY/SECURITY-GOVERNANCE.md` without defining, evaluating, or enforcing them.
- Request recording, alerting, or reporting through `24_SECURITY/SECURITY-MONITOR.md` and `27_OBSERVABILITY` rather than owning general observability infrastructure.
- Consolidate security coordination status from component-owned records.

---

## Security Event Workflow

1. A security event, alert, finding, or status reference is produced by its owning component.
2. The item is forwarded to the Security Manager when cross-component routing or coordination is required.
3. The Security Manager reads owner-supplied routing metadata, such as category, severity, status, policy reference, incident reference, vulnerability reference, or correlation ID.
4. The Security Manager routes the item to the responsible component without independently deciding severity, policy compliance, authorization, threat classification, compliance status, or remediation outcome.
5. The Security Manager tracks correlation and routing status only.
6. The responsible owner records domain status and evidence.
7. The Security Manager requests logging, monitoring, alerting, or reporting through `24_SECURITY/SECURITY-MONITOR.md` and `27_OBSERVABILITY`.

---

## Coordinated Components

| Component | Coordinated Function |
|---|---|
| `24_SECURITY/IDENTITY-MANAGER.md` | Owns identity records and lifecycle; receives identity-related routing from Security Manager when coordination is required. |
| `24_SECURITY/AUTHENTICATION-MANAGER.md` | Owns credential verification, MFA, sessions, and token issuance; receives authentication-related routing from Security Manager. |
| `24_SECURITY/AUTHORIZATION-MANAGER.md` | Owns runtime authorization decisions; receives access-decision routing from Security Manager. |
| `28_RUNTIME-CONFIG/SECRETS-MANAGER.md` | Owns secret and key storage, access, rotation, and revocation; receives secret/key coordination references only. |
| `24_SECURITY/ENCRYPTION-MANAGER.md` | Owns cryptographic operations using governance-defined standards and Secrets Manager key references. |
| `23_GOVERNANCE/POLICY-ENGINE.md` | Owns general policy evaluation; Security Manager passes policy-evaluation references without evaluating policy itself. |
| `24_SECURITY/THREAT-DETECTOR.md` | Owns threat detection, correlation, classification, and threat evidence. |
| `24_SECURITY/INCIDENT-MANAGER.md` | Owns security-incident classification, response coordination, incident record lifecycle, and post-incident review. |
| `24_SECURITY/COMPLIANCE.md` | Owns compliance-domain assessment records, evidence mapping, posture reporting, and compliance follow-up tracking. |
| `24_SECURITY/VULNERABILITY-MANAGEMENT.md` | Owns vulnerability lifecycle coordination, prioritization, remediation tracking, and evidence references. |
| `24_SECURITY/SECURITY-GOVERNANCE.md` | Owns security-domain policy definition, approvals, exceptions, and risk-acceptance decisions. |
| `24_SECURITY/SECURITY-MONITOR.md` | Owns security-domain signal interpretation, findings, posture assessments, and security reporting. |
| `27_OBSERVABILITY` | Owns general telemetry, logging, metrics, tracing, dashboard, alerting, and audit infrastructure. |

---

## Safety Rules

The Security Manager must never:

- Define, enforce, or evaluate policy.
- Decide event severity except as routing metadata supplied by the owning component.
- Treat routing metadata as authoritative lifecycle, validation, incident, compliance, or vulnerability state.
- Handle raw secrets, keys, or credentials directly.
- Perform authentication, authorization, encryption, threat detection, monitoring, incident response, compliance assessment, or vulnerability remediation.
- Own general telemetry, logging, metrics, tracing, dashboard, alerting, or audit infrastructure.
- Drop a routed security item without preserving its source reference and correlation ID.
- Route an event to an incorrect or unauthorized handler.

---

## Failure Handling

If coordination fails:

- Preserve the original event, alert, finding, or status reference.
- Preserve correlation and routing context.
- Notify the owner of the item and the intended destination when possible.
- Request a monitoring or alerting record through `24_SECURITY/SECURITY-MONITOR.md` and `27_OBSERVABILITY`.
- Escalate according to the owning component's policy, governance decision, or incident process.
- Maintain coordination traceability.

---

## Coordination Record

Every significant action coordinated by the Security Manager should preserve or request recording of:

- Operation ID
- Timestamp
- Source reference
- Item type
- Source Component
- Target Component
- Owner-supplied severity, category, status, or policy reference when present
- Correlation ID
- Routing outcome

The Security Manager may maintain coordination records and request logging through the owning observability component. It must not own general audit, log, telemetry, dashboard, metric, tracing, or alert infrastructure.

---

## Success Criteria

The Security Manager succeeds when:

- Security events, alerts, findings, and status references are routed to the correct owner promptly and reliably.
- Correlation and routing status remain traceable.
- Specialist ownership boundaries are preserved.
- Security posture reporting receives accurate component-owned status references.
- Required recording is requested through Security Monitor and Observability rather than hidden inside coordination.

---

## Permission Boundary

The Security Manager may receive security events, alerts, findings, and status references; route them to responsible owners; preserve correlation; track coordination status; and consolidate component-owned status references for reporting.

It must not perform authentication, authorization, cryptographic operations, threat detection, security monitoring, incident-response mechanics, compliance assessment, vulnerability remediation, policy definition, policy evaluation, or general audit/observability operations itself, and it must not handle raw secrets. Each of those remains owned by its respective specialist component.

---

## Domain Rule

Security orchestration applies identically regardless of domain; domain-specific security requirements are handled by the relevant domain layer, not reimplemented here.

---

## Rule

The Security Manager coordinates security routing and status correlation only. It must route work to the owning security component and preserve references to owner-supplied decisions, evidence, policy references, and status without replacing them with its own authority.
