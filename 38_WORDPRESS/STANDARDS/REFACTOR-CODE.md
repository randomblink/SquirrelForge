Status: Stable

---
# SquirrelForge Skill: Refactor WordPress Code

## Purpose

This skill defines how SquirrelForge improves existing WordPress code without changing its intended behavior.

Refactoring focuses on readability, maintainability, performance, consistency, and long-term sustainability while preserving functionality.

---

## Required References

Before refactoring, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/CSS-STANDARD.md`
- `38_WORDPRESS/STANDARDS/JAVASCRIPT-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`

---

## Refactoring Goals

Improve:

- readability
- consistency
- modularity
- maintainability
- testability
- performance
- documentation

Without changing expected behavior.

---

## Refactoring Workflow

1. Inspect the existing code.
2. Understand current behavior.
3. Identify improvement opportunities.
4. Preserve public interfaces unless approved.
5. Refactor incrementally.
6. Re-run validation.
7. Re-run testing.
8. Produce a refactoring report.

---

## Common Refactoring Tasks

Examples include:

- Split large classes.
- Split large functions.
- Remove duplicated code.
- Improve naming.
- Replace hardcoded values with configuration.
- Extract reusable services.
- Improve hook organization.
- Remove dead code.
- Improve documentation.
- Simplify conditional logic.
- Improve dependency structure.

---

## Do Not Change

Unless explicitly requested, do not change:

- public APIs
- database schema
- option names
- REST routes
- shortcode names
- custom hook names
- user-facing behavior
- plugin slug
- text domain

---

## Architecture Improvements

Look for:

- mixed responsibilities
- circular dependencies
- controller bloat
- duplicated business logic
- database logic outside repositories
- view logic inside services

---

## Security Review

Every refactoring must verify:

- sanitization
- escaping
- capability checks
- nonce verification
- SQL safety
- REST permissions
- AJAX permissions

Security must never be weakened.

---

## Performance Review

Identify:

- duplicate queries
- repeated API calls
- unnecessary asset loading
- inefficient loops
- unnecessary object creation
- duplicate cron events

---

## Documentation Review

Update documentation when:

- architecture changes
- file structure changes
- hooks change
- extension points change
- testing changes

---

## Validation Checklist

Verify:

- behavior is unchanged
- naming follows standards
- architecture is improved
- security still passes
- tests still pass
- documentation is current

---

## Refactoring Report

Produce:

```text
Refactoring Summary

Purpose:
Files Updated:
Architecture Changes:
Security Impact:
Performance Impact:
Documentation Updated:
Testing Results:
Remaining Recommendations:
```

---

## Approval States

| Status | Meaning |
|--------|---------|
| Complete | Refactoring successful with no regressions. |
| Complete with Recommendations | Improvements made, additional work suggested. |
| Blocked | Refactoring introduced risk or failed validation. |

---

## Rule

SquirrelForge must preserve functional behavior during refactoring unless the project requirements explicitly authorize behavioral changes.
