# SquirrelForge WordPress Review Code Skill

## Purpose

This Skill defines the controlled workflow for reviewing existing WordPress code.

It coordinates requirements, knowledge selection, role routing, and independent validation by the `Security Engineer`, `Performance Engineer`, `QA Engineer`, and `Documentation Engineer`.

---

## Trigger Conditions

Use this Skill when the request is to:

- review existing WordPress code
- audit a plugin or theme
- perform a security review
- perform a performance review
- check for standards compliance

Do not use this Skill when the task is to:

- create new functionality
- debug a specific issue
- refactor code

Use the appropriate specialized Skill instead.

---

## Required References

Before execution, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/STANDARDS/CODE-REVIEW-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`
- `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`
- `33_WORDPRESS_ROLES/SECURITY-ENGINEER.md`
- `33_WORDPRESS_ROLES/PERFORMANCE-ENGINEER.md`
- `33_WORDPRESS_ROLES/QA-ENGINEER.md`
- `33_WORDPRESS_ROLES/DOCUMENTATION-ENGINEER.md`

---

## Required Input

```text
Code Review Request

Project:
Code to Review:
Review Scope:
Review Focus:
Known Constraints:
```

### Workflow

#### Stage 1 — Triage

Use:

`33_WORDPRESS_ROLES/ROLE-MANAGER.md`

The `Role Manager` deconstructs the request and assigns the code to the appropriate validation roles based on the `ROLE-ROUTING-MATRIX.md`.

#### Stage 2 — Security Validation

Use:

`33_WORDPRESS_ROLES/SECURITY-ENGINEER.md`

Perform a full security audit of the target code. This is a **blocking gate**.

#### Stage 3 — Performance Validation

Use:

`33_WORDPRESS_ROLES/PERFORMANCE-ENGINEER.md`

Analyze the code for performance anti-patterns and bottlenecks. This is a **blocking gate** if performance is in scope.

#### Stage 4 — Functional & Standards Review

Use:

`33_WORDPRESS_ROLES/QA-ENGINEER.md`

Verify the code against standards and check for logical errors, maintainability issues, and adherence to best practices. This is a **blocking gate**.

#### Stage 5 — Documentation Review

Use:

`33_WORDPRESS_ROLES/DOCUMENTATION-ENGINEER.md`

Check for documentation accuracy, completeness, and impact.

#### Stage 6 — Reporting

Use:

`33_WORDPRESS_ROLES/ROLE-MANAGER.md`

Aggregate all findings from the validation roles into a single, comprehensive code review report.

### Code Review Final Report

Produce:

```text
Code Review Final Report

Project:
Scope:

Security Status:
Security Findings:

Performance Status:
Performance Findings:

QA & Standards Status:
QA & Standards Findings:

Documentation Status:
Documentation Findings:

Overall Status:

Next Step:
```

---

## Rule

1.  **Be Objective**: The review must be based on the established `KNOWLEDGE` and `STANDARDS` documents, not on subjective preference.
2.  **Be Specific**: All findings must include the file name, line number, a description of the issue, a severity level, and a clear recommendation for how to fix it.
3.  **Prioritize Gates**: Security, Performance, and QA findings are blocking gates. Code with unaddressed critical issues must never be approved.
4.  **Provide Rationale**: For each finding, the agent should cite the specific standard or knowledge document that justifies the recommendation.