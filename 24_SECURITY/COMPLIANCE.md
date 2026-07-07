# SquirrelForge Compliance Manager

Version: 1.0.0
Status: Stable
Owner: Security Maintainers
Depends On: `24_SECURITY/SECURITY-GOVERNANCE.md`, `23_GOVERNANCE`
Used By: `24_SECURITY/SECURITY-GOVERNANCE.md`, `24_SECURITY/SECURITY-MANAGER.md`
Last Updated: 2026-07-07

## Purpose

The Compliance Manager tracks how SquirrelForge satisfies registered organizational policies, regulatory requirements, contractual obligations, and internal governance standards by maintaining compliance-domain assessment records and evidence references.

The Compliance Manager owns compliance requirement registration, control mapping, compliance-domain assessment records, evidence references, compliance findings, and remediation-status tracking. It consumes authoritative policy-evaluation, validation, security-control, quality-gate, and specialist evidence; it does not replace the technical authority that produced that evidence.

The Compliance Manager tracks and reports compliance status only. It does not define general governance policy, approve exceptions, accept risk, execute remediation, or own general audit, logging, storage, or observability infrastructure. Approved exceptions and risk-acceptance decisions are issued by `24_SECURITY/SECURITY-GOVERNANCE.md`, which the Compliance Manager records against.

---

## Responsibilities

- Register compliance requirements.
- Map controls to requirements.
- Maintain compliance-domain assessment records.
- Collect and attach evidence references from authoritative owners.
- Assess compliance status against registered requirements.
- Report compliance findings.
- Track remediation status without executing remediation.
- Record approved exceptions and risk-acceptance references from `24_SECURITY/SECURITY-GOVERNANCE.md`.

---

## Compliance Process

1. Identify applicable registered requirements.
2. Map required controls to the components, policies, gates, or specialists that own them.
3. Collect supporting evidence references from authoritative owners.
4. Assess compliance status against the registered requirement.
5. Record compliance-domain findings.
6. Attach validation, policy-evaluation, quality-gate, security-control, specialist, or governance evidence references.
7. Record approved exception or risk-acceptance references from `24_SECURITY/SECURITY-GOVERNANCE.md` when applicable.
8. Report compliance findings to `24_SECURITY/SECURITY-GOVERNANCE.md` and `24_SECURITY/SECURITY-MANAGER.md`.
9. Track remediation status where required without assigning or executing remediation itself.

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

Compliance statuses apply only to Compliance Manager assessment records. They are not release acceptance, quality-gate status, validation outcome, legal certification, or authoritative workflow/task state.

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
| Status | Current compliance-record state |
| Evidence | Supporting evidence references |
| Evidence Owner | Component, policy, validation, quality-gate, specialist, or governance owner that produced the evidence |
| Finding | Compliance-domain finding or gap |
| Remediation Status | Not Required / Required / In Progress / Completed / Exception Accepted |
| Exception Reference | Approved exception or risk-acceptance decision from `24_SECURITY/SECURITY-GOVERNANCE.md`, when applicable |
| Assessed By | Responsible reviewer |
| Timestamp | Assessment time |

---

## Compliance Principles

- Requirements must be documented.
- Controls must be traceable.
- Evidence references must be retained through the owning storage, observability, audit, validation, or specialist infrastructure.
- Assessments must be repeatable.
- Exceptions require formal approval from `24_SECURITY/SECURITY-GOVERNANCE.md`.
- Remediation status must be tracked, but remediation execution remains with the responsible owner.
- Approved exceptions and risk-acceptance decisions can change compliance disposition and must be recorded explicitly.

---

## Permission Boundary

The Compliance Manager may register compliance requirements, map controls, maintain compliance-domain assessment records, collect evidence references, assess compliance against registered requirements, report findings, and track remediation status.

It must not define general governance policy, approve exceptions, accept risk, execute remediation, replace authoritative policy-evaluation or validation evidence, certify legal compliance, own quality gates, or own general audit, logging, storage, or observability infrastructure. Those decisions, actions, and systems remain owned by `23_GOVERNANCE`, `24_SECURITY/SECURITY-GOVERNANCE.md`, `14_ENGINE/VALIDATION.md`, `23_GOVERNANCE/QUALITY-GATES.md`, specialist owners, `27_OBSERVABILITY`, and `37_STORAGE` as applicable.

---

## Domain Rule

Compliance tracking applies identically regardless of domain; domain-specific regulatory obligations are registered as requirements within this component rather than tracked separately by domain layers.

---

## Rule

Every applicable compliance requirement must have documented controls, supporting evidence references, recorded compliance assessment, and either tracked remediation status or an approved exception/risk-acceptance reference before the compliance record may be considered dispositioned.
