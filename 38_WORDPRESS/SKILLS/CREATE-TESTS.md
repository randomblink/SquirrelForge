Status: Stable

---
# SquirrelForge WordPress Create Tests Skill

## Purpose

This Skill defines the controlled workflow for creating test plans, test cases, and testing infrastructure for a WordPress project.

It coordinates specialist roles to ensure that for every feature built, a corresponding, verifiable testing strategy is also created.

---

## Trigger Conditions

Use this Skill when the request is to:

- create tests for a feature
- write a test plan
- set up a testing framework (e.g., PHPUnit, Playwright)

Do not use this Skill to *execute* tests as part of a QA process. Use the `QA Engineer` role for that.

---

## Required References

Before execution, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `29_TESTING/README.md`
- `38_WORDPRESS/ROLES/QA-ENGINEER.md`
- `38_WORDPRESS/ROLES/SECURITY-ENGINEER.md`
- `38_WORDPRESS/ROLES/PERFORMANCE-ENGINEER.md`

---

## Required Input

```text
Test Creation Request

Project:
Feature/Component to Test:
Implementation Reports:
Architecture Documents:
```

---

## Workflow

#### Stage 1 — Test Planning

Use `QA Engineer` to analyze the feature's requirements and architecture to create a high-level test plan.

#### Stage 2 — Specialist Input

The `Security Engineer` provides input on security-specific test cases (e.g., permission failures). The `Performance Engineer` provides input on load and stress test cases.

#### Stage 3 — Test Case Generation

Use `QA Engineer` to generate the detailed test cases, including manual steps and stubs for automated tests (e.g., PHPUnit, Playwright).

#### Stage 4 — Technical Review

The original implementation engineer(s) review the test plan for technical feasibility and to identify any missed edge cases. This is a **blocking gate**.

#### Stage 5 — Finalization

The `QA Engineer` incorporates feedback and produces the final, approved test plan and any generated test files.

---

## Rule

1.  **Cover All Angles**: The generated test plan must include cases for success, failure, security, and accessibility where applicable.
2.  **Be Specific**: Manual test steps must be clear, unambiguous, and include a specific expected outcome. Automated test stubs should have descriptive names.
3.  **Automate Where Practical**: Prefer generating stubs for automated unit and integration tests for backend logic over purely manual checklists for the same logic.
4.  **Follow Both Standards**: All generated test plans and files must adhere to the WordPress-specific dimensions in `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md` and the general test-category execution and reporting responsibilities in `29_TESTING/README.md`. See that standard's Relationship to the General Testing Layer section for how the two map onto each other.
