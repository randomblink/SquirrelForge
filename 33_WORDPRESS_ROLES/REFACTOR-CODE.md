Status: Stable

---
# SquirrelForge WordPress Refactor Code Skill

## Purpose

This Skill defines the controlled workflow for restructuring existing WordPress code while preserving approved external behavior.

Refactoring may improve architecture, readability, maintainability, testability, duplication, dependency boundaries, or internal organization.

Refactoring must not silently change public behavior, stored data, permissions, API contracts, shortcode contracts, block compatibility, or other supported interfaces.

---

## Trigger Conditions

Use this Skill when the request is to:

- refactor existing code
- restructure existing code
- reduce duplication
- split large classes
- improve responsibility boundaries
- introduce services or repositories
- reorganize hooks
- simplify complex code
- improve testability
- replace internal implementation while preserving behavior
- improve maintainability

Do not use this Skill when the primary goal is to:

- add new features
- fix an unknown defect
- perform a data migration
- intentionally change public API behavior
- optimize a measured performance bottleneck

Use the appropriate specialized Skill instead.

---

## Required References

Before execution, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/WORDPRESS-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- applicable knowledge documents
- applicable standards in `38_WORDPRESS/STANDARDS/`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`
- `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`
- applicable Architect roles
- applicable Engineering roles
- `33_WORDPRESS_ROLES/SECURITY-ENGINEER.md`
- `33_WORDPRESS_ROLES/PERFORMANCE-ENGINEER.md` when required
- `33_WORDPRESS_ROLES/QA-ENGINEER.md`
- `33_WORDPRESS_ROLES/DOCUMENTATION-ENGINEER.md`
- `33_WORDPRESS_ROLES/RELEASE-ENGINEER.md` when part of a release

---

## Required Input

```text
Refactor Request

Project:
Project Type:
Component:
Current Problem:
Refactor Goal:
Files In Scope:
Files Out of Scope:
Behavior That Must Remain:
Public APIs:
Hooks:
REST Contracts:
Shortcodes:
Blocks:
Stored Data:
Compatibility Requirements:
Performance Requirements:
Testing Evidence:
Known Risks:
```

### Workflow

#### Stage 1 — Triage & Planning

Use `Role Manager` and `Project Architect`. The `Role Manager` receives the request. If changes are structural, the `Project Architect` approves the refactoring plan. A pre-existing test suite is highly recommended.

#### Stage 2 — Role Routing

Use `Role Manager` and `ROLE-ROUTING-MATRIX.md` to produce the `WordPress Role Routing Decision`.

#### Stage 3 — Implementation

The assigned implementation engineer(s) (e.g., `PHP Engineer`, `JS Engineer`) apply the refactoring changes in small, verifiable steps.

#### Stage 4 — Security Validation

Use `Security Engineer` to audit the changes to ensure the refactoring did not introduce new vulnerabilities or weaken existing controls. This is a **blocking gate**.

#### Stage 5 — Performance Validation

Use `Performance Engineer` to measure the impact if the refactoring was for performance or affects a performance-sensitive area. This is a **blocking gate** when required.

#### Stage 6 — QA & Regression

Use `QA Engineer` to execute a regression test plan to verify that no existing functionality was broken by the changes. This is a **blocking gate**.

#### Stage 7 — Documentation

Use `Documentation Engineer` to update any developer or user documentation affected by the architectural changes.

#### Stage 8 — Release Preparation

Use `Release Engineer` if the refactoring is part of a release, packaging the changes following all release readiness checks.

### Refactoring Final Report

Produce a final report summarizing the status of all stages.

---

## Rule

1.  **Preserve Behavior**: The primary rule of refactoring is to improve the internal structure of the code *without* changing its external behavior, unless explicitly authorized.
2.  **Test First**: Significant refactoring must not begin without a pre-existing and passing test suite to act as a safety net.
3.  **Small Steps**: Apply changes incrementally. A large, monolithic refactoring is difficult to review and debug.
4.  **Validate After**: All refactored code must pass the same security, performance, and QA gates as new code.
