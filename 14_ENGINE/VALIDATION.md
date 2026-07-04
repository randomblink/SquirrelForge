# SquirrelForge Engine Validation

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `03_CHECKLISTS`, `29_TESTING`, `14_ENGINE/STATE-MANAGER.md`, applicable domain layers
Used By: Engine, Workflow Selector, Task Router, Output Rules, Reporting
Last Updated: 2026-07-04

## Purpose

Engine Validation coordinates the validation requirements for an active request, workflow, task, and output.

It identifies what must be checked before completion can be claimed and records whether validation evidence exists, failed, is unavailable, or was explicitly waived by an approved governance decision.

Engine Validation does not perform every test itself. Test execution belongs to the appropriate testing, execution, specialist agent, or domain component.

---

## Validation Principle

> A task is complete only when the required acceptance, rule, safety, and quality evidence exists or missing validation is honestly reported.

A changed file, generated document, or executed action is not proof of completion by itself.

---

## Validation Categories

Validation may include:

- acceptance criteria validation,
- rule compliance validation,
- domain-specific validation,
- security validation,
- permission validation,
- code quality validation,
- documentation validation,
- test evidence validation,
- performance validation,
- accessibility validation,
- recovery or rollback validation,
- output/report validation,
- and governance quality-gate validation.

Only applicable categories should be required for a given request.

---

## Validation Process

1. Read the request, acceptance criteria, project context, selected workflow, and task state.
2. Identify validation categories required by request type, domain, risk, and workflow.
3. Identify which layer or component owns each validation item.
4. Identify required tools, tests, checklists, or evidence.
5. Mark unavailable validation explicitly.
6. Route validation work to the proper testing, execution, domain, security, or review component.
7. Record evidence status in the State Manager.
8. Return failed validation to the earliest responsible lifecycle phase.
9. Block completion when required validation failed or is missing without disclosure.
10. Pass validation summary to Output Rules and Reporting.

---

## General Validation Checklist

For every material task, confirm:

- [ ] The output addresses the user's request.
- [ ] The expected artifact or answer is present.
- [ ] The active scope was respected or scope changes were disclosed.
- [ ] Mandatory rules were followed.
- [ ] Existing user work was preserved.
- [ ] Known limitations are identified.
- [ ] Required validation evidence exists or unavailable validation is disclosed.
- [ ] The next action is clear.

---

## Domain Validation Rule

Domain-specific validation loads only when the active request touches that domain.

For WordPress work, validation may include:

- WordPress coding standards,
- PHP syntax checks,
- plugin activation checks,
- theme activation checks,
- REST API permission checks,
- nonce and capability checks,
- sanitization and escaping checks,
- block build checks,
- accessibility checks,
- responsive checks,
- and WordPress-specific security review.

For non-WordPress work, WordPress-specific validation must not be treated as universal.

---

## Validation States

| State | Meaning |
|---|---|
| `NOT_REQUIRED` | This validation item is not required for the task. |
| `REQUIRED` | Validation is required but not started. |
| `PENDING` | Validation is underway or waiting on evidence. |
| `PASSED` | Validation evidence passed. |
| `FAILED` | Validation evidence failed. |
| `UNAVAILABLE` | Validation could not be performed and must be reported. |
| `WAIVED` | Validation was waived by an approved governance or permission decision. |

---

## Failure Handling

If validation fails, the Engine must:

1. record the failed validation item,
2. identify the responsible phase,
3. identify whether repair, rerouting, rollback, or recovery is required,
4. prevent false completion reporting,
5. return to the earliest responsible lifecycle phase,
6. and require re-validation after repair.

---

## Reporting Rule

The final response must not claim validation that did not occur.

Reports should distinguish:

- validation performed,
- validation passed,
- validation failed,
- validation unavailable,
- validation waived,
- and validation not applicable.

---

## Rule

> Engine Validation coordinates required evidence and completion gates. It must never treat attempted execution, generated output, or unavailable checks as proof that validation passed.
