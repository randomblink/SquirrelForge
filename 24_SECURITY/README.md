# SquirrelForge Security Layer

Version: 1.0.0
Status: Stable
Owner: Security Maintainers
Depends On: `23_GOVERNANCE/POLICY-ENGINE.md`, `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`, `21_CONFIGURATION/PERMISSIONS.md`, `27_OBSERVABILITY`
Used By: Agents, Execution, Configuration, Coordination
Last Updated: 2026-07-07

## Purpose

This directory defines the security architecture, security-domain policy boundaries, and security coordination model that protect SquirrelForge, its workflows, integrations, data, users, and infrastructure.

The Security Layer establishes defense-in-depth through identity lifecycle management, authentication, runtime authorization, approved cryptographic operations, threat detection, security-domain monitoring, security-incident response coordination, compliance assessment records, and vulnerability lifecycle coordination.

Security components produce security decisions, findings, records, and evidence within their own domains. They do not replace general governance, execution, validation, storage, observability, or authoritative workflow/task state ownership.

---

## Layer Boundary

`24_SECURITY` owns:

- security routing, coordination, correlation, and status aggregation (`SECURITY-MANAGER.md`),
- security-domain policy definition, approvals, exceptions, and risk-acceptance decisions (`SECURITY-GOVERNANCE.md`),
- security-domain signal interpretation, findings, and posture reporting over observability inputs (`SECURITY-MONITOR.md`),
- identity lifecycle and identity records (`IDENTITY-MANAGER.md`),
- credential verification, MFA enforcement, and session/token issuance (`AUTHENTICATION-MANAGER.md`),
- runtime grant/deny authorization decisions for authenticated identities (`AUTHORIZATION-MANAGER.md`),
- cryptographic operations using governance-approved standards and approved key references (`ENCRYPTION-MANAGER.md`),
- threat detection, evidence correlation, and threat classification (`THREAT-DETECTOR.md`),
- authorized security-incident response coordination and incident-record lifecycle (`INCIDENT-MANAGER.md`),
- compliance requirement registration, control mapping, assessment records, evidence references, findings, and remediation-status tracking (`COMPLIANCE.md`),
- and vulnerability lifecycle coordination, remediation handoff, remediation-status tracking, and evidence references (`VULNERABILITY-MANAGEMENT.md`).

`24_SECURITY` does not own:

- secrets storage, key storage, key rotation, or key revocation (owned by `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`),
- general policy evaluation or platform-wide governance policy (owned by `23_GOVERNANCE/POLICY-ENGINE.md`, `23_GOVERNANCE`, and `01_RULES`),
- general telemetry, logging, metrics, tracing, dashboard, alerting, and audit infrastructure (owned by `27_OBSERVABILITY`),
- authoritative workflow/task lifecycle state (owned by `14_ENGINE/STATE-MANAGER.md`),
- general validation authority or completion evidence coordination (owned by `14_ENGINE/VALIDATION.md` and applicable testing, execution, specialist, or domain owners),
- execution failure handling, recovery execution, rollback, or remediation implementation (owned by `20_EXECUTION`, `17_COORDINATION`, and assigned implementation owners),
- WordPress-domain security validation — nonces, sanitization, escaping, capability checks (owned by `38_WORDPRESS/SECURITY-VALIDATOR.md`),
- and declarative tool/configuration permission policy (owned by `21_CONFIGURATION/PERMISSIONS.md` — a configuration-scoped model distinct from Authorization Manager's runtime access decisions).

**Resolved:** `IDENTITY-MANAGER.md` and `AUTHENTICATION-MANAGER.md` previously both claimed credential verification, MFA enforcement, and token issuance. This has been resolved: Identity Manager owns identity lifecycle and identity records (including role assignments and credential-type references); Authentication Manager owns credential verification, MFA, and session/token issuance, reading identity records from Identity Manager rather than maintaining its own identity store.

---

## Components

| Component | Responsibility |
|---|---|
| `SECURITY-MANAGER.md` | Coordinates security routing, correlation, handoffs, and status aggregation only. |
| `SECURITY-GOVERNANCE.md` | Defines security-domain policy and records approvals, exceptions, and risk-acceptance decisions. |
| `SECURITY-MONITOR.md` | Interprets security-domain signals and reports findings/posture using observability inputs. |
| `IDENTITY-MANAGER.md` | Identity lifecycle and identity records; role assignments and credential-type references. |
| `AUTHENTICATION-MANAGER.md` | Credential verification, MFA, session/token issuance against Identity Manager's records. |
| `AUTHORIZATION-MANAGER.md` | Runtime grant/deny decisions for authenticated identities using policy and permission inputs. |
| `ENCRYPTION-MANAGER.md` | Cryptographic operations using approved standards and approved key references. |
| `THREAT-DETECTOR.md` | Detects threats, correlates evidence, classifies threat severity, and reports threat assessments. |
| `INCIDENT-MANAGER.md` | Coordinates authorized security-incident response and maintains incident-domain records. |
| `COMPLIANCE.md` | Maintains compliance assessment records, evidence references, findings, and remediation-status tracking. |
| `VULNERABILITY-MANAGEMENT.md` | Coordinates vulnerability lifecycle, remediation handoff, remediation tracking, and evidence references. |

The authoritative component roster must match files that actually exist in this directory.

---

## Coordination Flow

```text
Security signal / event / alert / finding / status reference
   ↓
Owning component supplies evidence, classification, status, policy reference, or routing metadata
   ↓
Security Manager coordinates routing, correlation, and status aggregation only
   ↓
Responsible owner handles the domain decision or record:
   Identity Manager → identity lifecycle records
   Authentication Manager → credential verification, MFA, sessions, tokens
   Authorization Manager → runtime grant/deny decisions
   Encryption Manager → cryptographic operations using approved key references
   Threat Detector → threat detection, evidence correlation, threat classification
   Incident Manager → authorized incident-response coordination and incident records
   Compliance → compliance assessment records and findings
   Vulnerability Management → vulnerability lifecycle and remediation tracking
   ↓
Security Governance records approvals, exceptions, or risk acceptance when required
   ↓
Validation / Testing / Execution / Recovery / Remediation owners provide evidence when required
   ↓
Security Monitor interprets security posture from owner-supplied signals and observability inputs
   ↓
27_OBSERVABILITY records logs, metrics, traces, dashboards, alerts, and audit infrastructure
14_ENGINE/STATE-MANAGER.md remains authoritative for workflow/task state
```

The Security Manager does not decide severity, enforce policy, validate completion, execute recovery, perform remediation, or write authoritative lifecycle state. It preserves and routes references from the components that own those decisions.

---

## Dependencies

Security depends on:

- `23_GOVERNANCE/POLICY-ENGINE.md`, `23_GOVERNANCE`, and `01_RULES` for general policy evaluation and platform-wide governance authority,
- `24_SECURITY/SECURITY-GOVERNANCE.md` for security-domain policy definition, approvals, exceptions, and risk acceptance,
- `28_RUNTIME-CONFIG/SECRETS-MANAGER.md` for secret/key storage, approved key references, rotation, and revocation,
- `21_CONFIGURATION/PERMISSIONS.md` for declarative permission policy consumed by Authorization Manager at runtime,
- `14_ENGINE/VALIDATION.md` and applicable testing/specialist/domain owners for validation evidence,
- `14_ENGINE/STATE-MANAGER.md` for authoritative workflow/task state,
- `20_EXECUTION` and `17_COORDINATION` for execution, failure handling, recovery, and rollback mechanics,
- and `27_OBSERVABILITY` for platform-wide telemetry, logging, metrics, tracing, dashboard, alerting, and audit infrastructure.

---

## Security Principles

- Apply defense in depth.
- Follow the principle of least privilege.
- Authenticate identity before authorization.
- Treat token and role claims as authorization inputs only, not automatic authorization.
- Require explicit runtime authorization before protected access.
- Encrypt sensitive data in transit and at rest.
- Use approved key references without moving key lifecycle ownership out of Secrets Manager.
- Preserve security-relevant evidence and request observability records through the owning infrastructure.
- Coordinate security incidents through authorized owners.
- Report security posture from owner-supplied security signals and observability inputs.
- Keep security controls traceable and auditable without duplicating general audit infrastructure.

---

## State Rule

Security does not persist authoritative task or lifecycle state on its own authority — that remains `14_ENGINE/STATE-MANAGER.md`'s responsibility.

Security components may maintain domain records such as identity records, authentication attempts, authorization decisions, threat assessments, incident records, compliance assessments, and vulnerability records. Those records do not replace workflow/task state, validation state, recovery state, or release state.

General logs, metrics, traces, dashboards, alerts, and audit infrastructure remain owned by `27_OBSERVABILITY`.

---

## Domain Rule

Security controls apply identically regardless of domain. Domain-specific security requirements — for example WordPress nonce and capability checks — are owned by the relevant domain layer (`38_WORDPRESS/SECURITY-VALIDATOR.md`), not reimplemented here.

---

## Diagram

```text
Signal/Record
  → Owning Security Component
  → Security Manager (routing/correlation/status only, when coordination is needed)
  → Responsible Security Owner
  → Security Governance (approval/exception/risk acceptance, when required)
  → Validation/Execution/Recovery/Remediation owner evidence, when required
  → Security Monitor (security posture interpretation)
  → 27_OBSERVABILITY (general recording/reporting infrastructure)
```

---

## Rule

> Every workflow, integration, component, and service within SquirrelForge must comply with the Security Layer before interacting with protected resources.
