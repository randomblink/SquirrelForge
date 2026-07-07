# SquirrelForge Governance

Version: 1.0.0
Status: Stable
Owner: Governance Maintainers
Depends On: `11_OVERVIEW/LIFECYCLE.md`, `14_ENGINE/STATE-MANAGER.md`, `14_ENGINE/VALIDATION.md`, `23_GOVERNANCE/VERSIONING.md`, `23_GOVERNANCE/CHANGE-MANAGEMENT.md`, `23_GOVERNANCE/QUALITY-GATES.md`
Used By: All maintained components, release workflows, documentation maintenance, migration planning
Last Updated: 2026-07-07

## Purpose

The Governance layer defines the rules for versioning, change approval, quality gates, policy decisions, deprecation, release readiness, and controlled rollback.

Governance makes work accountable. It does not execute implementation work, own runtime task state, perform every validation check, or hide missing evidence.

---

## Components

| Component | Responsibility |
|---|---|
| `VERSIONING.md` | Defines semantic versioning, compatibility classification, lifecycle states, and release evidence expectations. |
| `VERSION-MANAGER.md` | Maintains immutable version records, lineage, comparison, rollback support, and version audit history. |
| `CHANGE-MANAGEMENT.md` | Records motivation, scope, owner, impact, migration, rollout, rollback, and approval for material changes. |
| `QUALITY-GATES.md` | Defines required release, review, architecture, documentation, test, migration, and validation gates. |
| `DEPRECATION-POLICY.md` | Defines notice, migration, compatibility window, replacement, and removal rules. |
| `POLICY-ENGINE.md` | Applies governance policy decisions and records policy outcomes. |

---

## Ownership Boundaries

Governance owns:

- version policy,
- compatibility classification,
- material change approval requirements,
- quality gate requirements,
- release readiness rules,
- deprecation and removal policy,
- rollback authorization requirements,
- and governance audit expectations.

Governance does not own:

- task lifecycle state, which belongs to `14_ENGINE/STATE-MANAGER.md`,
- validation coordination, which belongs to `14_ENGINE/VALIDATION.md`,
- execution planning or execution, which belongs to `14_ENGINE` and `20_EXECUTION`,
- specialist implementation decisions, which belong to the assigned domain or agent layer,
- security authorization, which belongs to `24_SECURITY`,
- or storage mechanics, which belong to `37_STORAGE`.

Governance decisions must be recorded in state when they affect an active task, blocker, validation item, route, release, rollback, or completion claim.

---

## Execution Order

Governed work follows this order:

1. Propose the change or policy decision.
2. Identify affected contracts, owners, users, and dependencies.
3. Classify compatibility and version impact.
4. Identify required validation, quality gates, migration, and rollback needs.
5. Request owner approval where required.
6. Route implementation through the Engine and responsible specialist owner.
7. Record state, blockers, and evidence during execution.
8. Run applicable validation and quality gates.
9. Approve, release, defer, reroute, rollback, deprecate, or archive.
10. Preserve audit and version history.

Implementation must not start when approval, permission, prerequisite, or risk gates are blocking the work.

---

## Dependencies

Governance depends on:

- ownership metadata,
- current lifecycle state,
- affected contract references,
- dependency analysis,
- permission and authorization status,
- validation results,
- quality gate evidence,
- migration and rollback plans,
- release notes,
- deprecation records,
- and immutable audit/version history.

When these inputs are missing, Governance must record the gap instead of assuming approval or readiness.

---

## Governance States

| State | Meaning |
|---|---|
| `NOT_REQUIRED` | No governance decision is required for the task. |
| `REVIEW_REQUIRED` | Scope, compatibility, policy, risk, or release impact requires governance review. |
| `UNDER_REVIEW` | Governance assessment is underway. |
| `APPROVED` | Required governance decision has approved the work. |
| `CHANGES_REQUESTED` | Governance requires changes before approval. |
| `BLOCKED` | Governance cannot approve because required information, evidence, permission, or owner decision is missing. |
| `WAIVED` | A required governance gate was waived by an approved exception with recorded risk. |
| `REJECTED` | Governance rejects the proposed change or release. |
| `ARCHIVED` | Governance decision is preserved for history and no longer active. |

---

## Quality Gate Model

Governance quality gates may require:

- architecture consistency,
- compatibility review,
- ownership approval,
- rule compliance,
- security review,
- performance review,
- accessibility review,
- testing evidence,
- documentation updates,
- migration readiness,
- rollback or recovery plan,
- release notes,
- deprecation notice,
- and state/reporting accuracy.

Only applicable gates should be required. Required gates must either pass, fail, be marked unavailable, or be waived by an approved governance exception.

---

## State Manager Interaction

For active tasks, Governance reports decisions and requirements to the State Manager.

The State Manager records:

- current governance state,
- blockers caused by governance,
- required quality gates,
- validation evidence state,
- approved exceptions or waivers,
- reroute or recovery needs,
- release readiness,
- completion eligibility,
- and limitations that must be reported.

Governance must not overwrite task history or mark work complete. Completion remains controlled by the State Manager after required validation and governance evidence are recorded.

---

## Validation Interaction

Governance does not perform every validation check directly.

It defines which governance gates are required and relies on the Engine, testing, security, execution, specialist, and domain layers to produce evidence.

Governance approval must distinguish:

- evidence passed,
- evidence failed,
- evidence missing,
- evidence unavailable,
- waived evidence,
- and checks that are not applicable.

A release or completion claim must not treat missing or unavailable validation as passed.

---

## Deprecation and Release Interaction

Stable contract removal requires:

1. compatibility impact assessment,
2. deprecation notice,
3. replacement or migration path,
4. owner approval,
5. announced removal version,
6. validation of migration readiness,
7. release notes,
8. and preserved version history.

Emergency safety or security exceptions must record why the normal deprecation window could not be honored.

## Rules

- No undocumented breaking change.
- No release without required gates and an accountable owner.
- No governance approval without impact and evidence review.
- No removal of stable behavior without a deprecation path or approved exception.
- No rollback without authorization and recovery evidence.
- No completion claim when required governance or validation evidence is missing without disclosure.
- No policy decision may erase prior state, audit records, failed checks, or known limitations.

---

## Diagram

```text
Proposal
  → Impact Review
  → Version Classification
  → Approval / Block / Reject
  → Routed Implementation
  → Validation + Quality Gates
  → Release / Rollback / Deprecation / Archive
```

---

## Rule

> Governance controls accountable change, release, version, and deprecation decisions. It must preserve evidence, state, and auditability rather than replacing them with approval language.
