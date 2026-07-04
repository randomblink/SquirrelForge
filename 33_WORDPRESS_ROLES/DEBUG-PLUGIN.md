# SquirrelForge WordPress Debug Plugin Skill

## Purpose

This Skill defines the controlled workflow for diagnosing and fixing WordPress plugin issues.

It coordinates requirements, knowledge selection, role routing, specialist implementation, independent validation, QA, documentation, and release review.

---

## Trigger Conditions

Use this Skill when the request is to:

- debug a plugin
- fix a bug in a plugin
- diagnose a plugin conflict
- resolve a plugin-related error

Do not use this Skill when the task is only:

- creating a new plugin
- reviewing existing plugin code
- refactoring an existing plugin
- migrating an existing plugin

Use the appropriate specialized Skill instead.

---

## Required References

Before execution, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/WORDPRESS-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/PLUGIN-HANDBOOK.md`
- `38_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/JAVASCRIPT-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`
- `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`

Additional references must be selected according to the nature of the bug.

---

## Required Input

```text
Plugin Debugging Request

Plugin Name:
Symptom:
Steps to Reproduce:
Expected Behavior:
Actual Behavior:
Environment Details:
Error Logs:
Known Constraints:
```

If steps to reproduce are missing, the first stage must be to define them.

### Workflow

#### Stage 1 — Defect Triage

Use:

`33_WORDPRESS_ROLES/QA-ENGINEER.md`

The `QA Engineer` must:

1.  Reproduce the bug.
2.  Isolate the issue (e.g., conflict test).
3.  Create a formal defect report.

Produce:

```text
QA Defect

ID:
Title:
Severity:
Component:
Environment:
Preconditions:
Steps to Reproduce:
Expected Result:
Actual Result:
Evidence:
Likely Owner:
Status:
```

#### Stage 2 — Role Routing

Use:

`33_WORDPRESS_ROLES/ROLE-MANAGER.md`

and:

`33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`

The `Role Manager` assigns the defect to the appropriate specialist roles for root cause analysis.

Produce:

```text
WordPress Role Routing Decision

Task:
Selected Skill: DEBUG-PLUGIN
Project Type: Plugin
Complexity:
Required Roles:
Optional Roles:
Role Sequence:
Required Gates:
Conditional Gates:
Expected Reports:
Known Risks:
Routing Status:
```

#### Stage 3 — Root Cause Analysis

The assigned implementation engineer(s) investigate the root cause.

Possible roles:

- `PHP Engineer`
- `JavaScript Engineer`
- `Database Engineer`
- `REST Engineer`
- `Block Engineer`

The engineer must:

1.  Analyze logs and evidence.
2.  Trace code execution.
3.  Identify the specific line(s) causing the failure.
4.  Propose the smallest safe fix.

Produce:

```text
Root Cause Analysis Report

Symptom:
Root Cause:
Files Affected:
Proposed Fix:
Security Impact:
Performance Impact:
Regression Risk:
```

#### Stage 4 — Fix Implementation

The assigned engineer implements the approved fix.

The fix must adhere to all relevant standards.

#### Stage 5 — Security Validation

Use:

`33_WORDPRESS_ROLES/SECURITY-ENGINEER.md`

The `Security Engineer` must review the fix if it touches:

- permissions
- sanitization
- escaping
- database queries
- REST/AJAX handlers

A fix that introduces a security vulnerability must be rejected.

#### Stage 6 — Performance Validation

Use:

`33_WORDPRESS_ROLES/PERFORMANCE-ENGINEER.md`

The `Performance Engineer` must review the fix if it affects a performance-sensitive area.

#### Stage 7 — Fix Verification (QA)

Use:

`33_WORDPRESS_ROLES/QA-ENGINEER.md`

The `QA Engineer` must:

1.  Verify that the original bug is resolved.
2.  Execute a regression test plan to ensure no new bugs were introduced.

The fix is not complete until QA verification passes.

#### Stage 8 — Documentation

Use:

`33_WORDPRESS_ROLES/DOCUMENTATION-ENGINEER.md`

Update applicable documentation:

- `CHANGELOG.md`
- `README.md` (if known limitations change)
- Developer documentation (if behavior changes)

#### Stage 9 — Release Preparation

Use:

`33_WORDPRESS_ROLES/RELEASE-ENGINEER.md`

If the fix is part of a release, the `Release Engineer` packages it into a new version, following all release readiness checks.

### Required Handoff Contract

Every role transition must use:

```text
Role Handoff

From Role:
To Role:
Project:
Task:
Input:
Work Completed:
Output:
Validation Performed:
Open Risks:
Blocking Issues:
Required Next Action:
```

### Debugging Final Report

Produce:

```text
Plugin Debugging Final Report

Plugin:
Symptom:

Defect Report:

Root Cause Analysis:

Fix Applied:

Security Status:

Performance Status:

QA Status:

Documentation Status:

Release Status:

Final Result:

Next Step:
```

### Completion Criteria

The `Debug Plugin` Skill is complete only when:

- the root cause is identified
- a fix is implemented
- the fix passes all required validation gates (Security, Performance, QA)
- documentation is updated
- the fix is included in a release when applicable

## Rule

A bug fix must address the identified root cause, not just suppress the symptom. The fix must pass all relevant validation and verification gates before release.