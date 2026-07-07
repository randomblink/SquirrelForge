# SquirrelForge Compliance Manager

Version: 1.0.0
Status: Stable
Owner: Security Maintainers
Depends On: `24_SECURITY/SECURITY-GOVERNANCE.md`, `23_GOVERNANCE`
Used By: `24_SECURITY/SECURITY-GOVERNANCE.md`, `24_SECURITY/SECURITY-MANAGER.md`
Last Updated: 2026-07-06

## Purpose

The Compliance Manager governs how SquirrelForge satisfies organizational policies, regulatory requirements, contractual obligations, and internal governance standards while maintaining continuous evidence of compliance.

The Compliance Manager tracks and reports compliance status only. It does not itself approve exceptions or accept risk — approved exceptions are documented decisions issued by `24_SECURITY/SECURITY-GOVERNANCE.md`, which the Compliance Manager records against.

---

## Responsibilities

- Register compliance requirements.
- Map controls to requirements.
- Verify policy adherence.
- Collect compliance evidence.
- Coordinate compliance assessments.
- Report compliance status.
- Track remediation activities.
- Maintain compliance history.

---

## Compliance Process

1. Identify applicable requirements.
2. Map required controls.
3. Verify control implementation.
4. Collect supporting evidence.
5. Evaluate compliance status.
6. Record assessment results.
7. Report compliance findings to `24_SECURITY/SECURITY-GOVERNANCE.md` and `24_SECURITY/SECURITY-MANAGER.md`.
8. Track remediation where required.

---

## Compliance Categories

| Category | Description |
|---|---|
| Organizational | Internal governance policies |
| Security | Platform security controls |
| Privacy | Protection of personal information |
| Operational | Workflow and operational procedures |
| Configuration | Configuration governance |
| Audit | Audit readiness and evidence |
| Data Retention | Record preservation requirements |
| Third-Party | External integration obligations |

---

## Compliance Status

| Status | Meaning |
|---|---|
| Compliant | Requirement fully satisfied |
| Partially Compliant | Some controls require improvement |
| Non-Compliant | Requirement not satisfied |
| Under Review | Assessment in progress |
| Exempt | Approved exception documented via `24_SECURITY/SECURITY-GOVERNANCE.md` |

---

## Compliance Record

| Field | Description |
|---|---|
| Compliance ID | Unique identifier |
| Requirement | Applicable standard or policy |
| Control | Implemented safeguard |
| Status | Current compliance state |
| Evidence | Supporting documentation reference |
| Assessed By | Responsible reviewer |
| Timestamp | Assessment time |

---

## Compliance Principles

- Requirements must be documented.
- Controls must be traceable.
- Evidence must be retained.
- Assessments must be repeatable.
- Exceptions require formal approval from `24_SECURITY/SECURITY-GOVERNANCE.md`.
- Remediation activities must be tracked.

---

## Permission Boundary

The Compliance Manager may register requirements, map controls, assess adherence, collect evidence, and report compliance status.

It must not approve exceptions or accept risk on its own authority — those decisions remain owned by `24_SECURITY/SECURITY-GOVERNANCE.md`.

---

## Domain Rule

Compliance tracking applies identically regardless of domain; domain-specific regulatory obligations are registered as requirements within this component rather than tracked separately by domain layers.

---

## Rule

Every applicable compliance requirement must have documented controls, supporting evidence, recorded assessments, and tracked remediation before compliance may be considered complete.
