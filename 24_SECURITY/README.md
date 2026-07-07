# SquirrelForge Security Layer

Version: 1.0.0
Status: Stable
Owner: Security Maintainers
Depends On: `23_GOVERNANCE/POLICY-ENGINE.md`, `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`, `21_CONFIGURATION/PERMISSIONS.md`, `27_OBSERVABILITY`
Used By: Agents, Execution, Configuration, Coordination
Last Updated: 2026-07-06

## Purpose

This directory defines the security architecture, policies, and operational controls that protect SquirrelForge, its workflows, integrations, data, users, and infrastructure.

The Security Layer establishes defense-in-depth by governing identity, authentication, authorization, encryption, threat detection, vulnerability management, incident response, compliance, and continuous security monitoring.

---

## Layer Boundary

`24_SECURITY` owns:

- central security orchestration (`SECURITY-MANAGER.md`),
- security policy authority — approvals, exceptions, risk acceptance, standards (`SECURITY-GOVERNANCE.md`),
- continuous security observability (`SECURITY-MONITOR.md`),
- identity lifecycle (`IDENTITY-MANAGER.md`),
- credential verification and session/token issuance (`AUTHENTICATION-MANAGER.md`),
- authorization and access decisions (`AUTHORIZATION-MANAGER.md`),
- cryptographic operations (`ENCRYPTION-MANAGER.md`),
- threat detection and reporting (`THREAT-DETECTOR.md`),
- incident response coordination (`INCIDENT-MANAGER.md`),
- regulatory and organizational compliance tracking (`COMPLIANCE.md`),
- and vulnerability discovery, prioritization, and remediation tracking (`VULNERABILITY-MANAGEMENT.md`).

`24_SECURITY` does not own:

- secrets storage (owned by `28_RUNTIME-CONFIG/SECRETS-MANAGER.md` — Encryption Manager and Identity Manager reference it rather than storing secrets themselves),
- platform-wide policy evaluation for non-security-specific requests (owned by `23_GOVERNANCE/POLICY-ENGINE.md`, which Security Manager and Security Governance coordinate with),
- WordPress-domain security validation — nonces, sanitization, escaping, capability checks (owned by `38_WORDPRESS/SECURITY-VALIDATOR.md`),
- and declarative tool/configuration permission policy (owned by `21_CONFIGURATION/PERMISSIONS.md` — a configuration-scoped model distinct from Authorization Manager's runtime access decisions).

**Known open issue:** `IDENTITY-MANAGER.md` and `AUTHENTICATION-MANAGER.md` currently both claim credential verification, MFA enforcement, and token issuance. This overlap has not yet been resolved. Until a dedicated cleanup separates identity lifecycle from authentication mechanics, treat both as jointly responsible for this ground rather than assuming either alone is authoritative or complete.

---

## Components

| Component | Responsibility |
|---|---|
| `SECURITY-MANAGER.md` | Central orchestrator coordinating all Security Layer components. |
| `SECURITY-GOVERNANCE.md` | Authoritative approvals, exceptions, risk acceptance, and policy standards. |
| `SECURITY-MONITOR.md` | Continuous observability of security operations and posture. |
| `IDENTITY-MANAGER.md` | Identity lifecycle; currently also claims credential verification (see open issue above). |
| `AUTHENTICATION-MANAGER.md` | Credential verification, MFA, session/token issuance (see open issue above). |
| `AUTHORIZATION-MANAGER.md` | Access and permission decisions for authenticated identities. |
| `ENCRYPTION-MANAGER.md` | Cryptographic operations: encryption, hashing, signatures, key management. |
| `THREAT-DETECTOR.md` | Detects and reports security threats; does not remediate. |
| `INCIDENT-MANAGER.md` | Coordinates investigation, containment, recovery, and review of incidents. |
| `COMPLIANCE.md` | Regulatory and organizational compliance tracking. |
| `VULNERABILITY-MANAGEMENT.md` | Vulnerability discovery, prioritization, and remediation tracking. |

The authoritative component roster must match files that actually exist in this directory.

---

## Execution Order

```text
Security Event (from Security Monitor or Threat Detector)
   ↓
Security Manager (evaluate severity/type against governance policy)
   ↓
Route to the responsible component:
   Identity Manager / Authentication Manager (identity & credential issues)
   Authorization Manager (access decisions)
   Encryption Manager (cryptographic operations)
   Threat Detector → Incident Manager (confirmed threats)
   Vulnerability Management (discovered vulnerabilities)
   Compliance (regulatory findings)
   ↓
Security Governance (approvals, exceptions, risk acceptance, as needed)
   ↓
Security Monitor (records outcome, reports posture)
```

---

## Dependencies

Security depends on:

- `23_GOVERNANCE/POLICY-ENGINE.md` for the runtime policy evaluation Security Manager and Security Governance coordinate with,
- `28_RUNTIME-CONFIG/SECRETS-MANAGER.md` for the secret storage Encryption Manager and Identity Manager reference rather than perform themselves,
- `21_CONFIGURATION/PERMISSIONS.md` for the declarative permission policy Authorization Manager enforces at runtime,
- and `27_OBSERVABILITY` for the platform-wide telemetry Security Monitor synthesizes into security-specific reporting.

---

## Security Principles

- Apply defense in depth.
- Follow the principle of least privilege.
- Verify every request before granting access.
- Encrypt sensitive data in transit and at rest.
- Record security-relevant events.
- Respond rapidly to security incidents.
- Continuously monitor security posture.
- Security controls must be auditable.

---

## State Rule

Security does not persist task or lifecycle state on its own authority — that remains `14_ENGINE/STATE-MANAGER.md`'s responsibility. Security records security-specific events, decisions, and audit history within its own domain.

---

## Domain Rule

Security controls apply identically regardless of domain. Domain-specific security requirements — for example WordPress nonce and capability checks — are owned by the relevant domain layer (`38_WORDPRESS/SECURITY-VALIDATOR.md`), not reimplemented here.

---

## Diagram

```text
Event → Security Manager → [Identity/Auth | Authorization | Encryption | Threat Detector → Incident Manager | Vulnerability Mgmt | Compliance] → Security Governance (as needed) → Security Monitor (record + report)
```

---

## Rule

> Every workflow, integration, component, and service within SquirrelForge must comply with the Security Layer before interacting with protected resources.
