# SquirrelForge Versioning

Version: 1.0.0
Status: Stable
Owner: Governance Maintainers
Depends On: `23_GOVERNANCE/CHANGE-MANAGEMENT.md`, `23_GOVERNANCE/QUALITY-GATES.md`, `23_GOVERNANCE/DEPRECATION-POLICY.md`, `23_GOVERNANCE/VERSION-MANAGER.md`
Used By: All maintained components, release workflows, migration planning, documentation maintenance
Last Updated: 2026-07-07

## Purpose

Versioning defines how SquirrelForge records compatible changes, incompatible changes, release readiness, migration expectations, and governed document contracts.

It provides the version policy. It does not approve changes, run quality gates, create version records, or decide release readiness by itself.

---

## Ownership Boundaries

| Responsibility | Owner |
|---|---|
| Version policy and numbering rules | `23_GOVERNANCE/VERSIONING.md` |
| Change scope, impact, approval, rollout, and rollback plan | `23_GOVERNANCE/CHANGE-MANAGEMENT.md` |
| Required release evidence and completion gates | `23_GOVERNANCE/QUALITY-GATES.md` |
| Deprecated contract notice, migration window, and removal version | `23_GOVERNANCE/DEPRECATION-POLICY.md` |
| Immutable version records, history, comparison, and controlled rollback | `23_GOVERNANCE/VERSION-MANAGER.md` |
| Current workflow, task, validation, blocker, and completion state | `14_ENGINE/STATE-MANAGER.md` |

No component may use a version number to bypass change approval, validation evidence, migration requirements, or state reporting.

---

## Semantic Versioning Rule

SquirrelForge uses semantic versions:

- **Major** versions are for incompatible contracts, removed behavior, changed ownership boundaries, changed required lifecycle states, or changed behavior that existing consumers cannot safely use without migration.
- **Minor** versions are for backward-compatible capability, new optional behavior, expanded guidance, new supported integrations, or new validation requirements that do not invalidate existing compatible use.
- **Patch** versions are for backward-compatible corrections, clarifications, typo fixes, link fixes, metadata corrections, or non-contractual editorial cleanup.

Document versions describe the contract they specify, not merely the amount of text changed.

---

## Versioned Contract Scope

A governed version may cover:

- component responsibilities,
- lifecycle states,
- routing states,
- workflow rules,
- validation rules,
- security or permission boundaries,
- templates,
- interfaces,
- state schemas,
- deprecation notices,
- testing or quality gate requirements,
- and domain-specific standards.

When a document defines behavior used by other layers, the behavior is part of the versioned contract.

---

## Change Classification

Every material change must be classified before version selection.

| Change Type | Version Impact | Examples |
|---|---|---|
| Editorial correction | Patch | Typos, formatting, broken links, clearer wording with no changed requirement. |
| Clarification | Patch or Minor | Existing rule made clearer; use Minor if implementers must adjust behavior. |
| Added optional capability | Minor | New supported workflow, optional state, or additional evidence type. |
| Added mandatory requirement | Minor or Major | Minor when compatible with current consumers; Major when existing valid usage becomes invalid. |
| Ownership boundary change | Major | A component loses or gains decision authority that affects consumers. |
| State model change | Major | Lifecycle, routing, validation, or completion state semantics change incompatibly. |
| Removal | Major | Contract, behavior, file, API, or supported path is removed after deprecation. |
| Security correction | Patch, Minor, or Major | Select by compatibility impact; urgent security changes still require evidence. |

If classification is unclear, treat the change as higher risk until Change Management resolves the impact.

---

## Version Lifecycle

| State | Meaning |
|---|---|
| `DRAFT` | Version proposal exists but has not been approved. |
| `UNDER_REVIEW` | Impact, compatibility, migration, and validation are being assessed. |
| `APPROVED` | Required owners have approved the versioned change. |
| `ACTIVE` | Version is current for maintained use. |
| `DEPRECATED` | Version remains available but has an announced replacement or removal path. |
| `REMOVED` | Version is no longer supported for active use. |
| `ARCHIVED` | Version is preserved for history, audit, or recovery only. |
| `RESTORED` | Historical version has been restored through an approved rollback or recovery path. |

Only `APPROVED` and `ACTIVE` versions may be used for new maintained work.

---

## Version Selection Process

1. Identify the affected contract, component, domain, or artifact.
2. Identify current version, current state, and dependents.
3. Classify the change by compatibility impact.
4. Identify migration, deprecation, or rollback needs.
5. Identify required quality gates and validation evidence.
6. Select major, minor, or patch version.
7. Record rationale in the change record.
8. Create or update immutable version history through the Version Manager.
9. Update affected references, release notes, and migration guidance.
10. Report version, evidence, limitations, and unresolved risks before release.

---

## Required Version Evidence

A versioned change must record:

- previous version,
- proposed version,
- affected files or contracts,
- change classification,
- compatibility assessment,
- affected dependents,
- owner approval,
- validation evidence,
- migration requirement,
- deprecation requirement,
- rollback or recovery option,
- release readiness status,
- and any known limitation or unavailable check.

Missing evidence blocks release unless an approved governance exception records the gap and risk.

---

## State Manager Interaction

When versioning work is part of an active task:

1. The State Manager records the task lifecycle, current owner, blockers, validation state, and completion state.
2. Versioning records the proposed version and compatibility rationale.
3. Change Management records approval, impact, rollout, and rollback requirements.
4. Quality Gates records required evidence before completion.
5. The Version Manager records immutable history after approval.

Versioning must not mark a task complete. Completion belongs to the State Manager after required evidence exists or missing validation is explicitly reported.

---

## Deprecation Interaction

If a versioned change removes or replaces a stable contract, the change must include a deprecation record unless emergency governance explicitly waives the notice period.

A deprecation record must identify:

- affected contract,
- replacement path,
- first deprecated version,
- removal version,
- migration steps,
- compatibility window,
- owner,
- and validation required before removal.

Removal may occur only in the announced major version after migration support is available, unless an approved security or safety exception requires faster action.

---

## Release Rules

A version must not be released when:

- the compatibility impact is unknown,
- required owner approval is missing,
- required validation evidence is missing without disclosure,
- migration guidance is required but absent,
- deprecation requirements are ignored,
- affected references are stale,
- or rollback/recovery expectations are undefined for a risky change.

---

## Rule

> Versioning identifies the compatibility contract and required evidence for a change. It never substitutes a version number for approval, validation, migration, or truthful state reporting.
