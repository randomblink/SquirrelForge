# SquirrelForge Skill: Debug WordPress Plugin

## Purpose

This skill defines how SquirrelForge diagnoses and fixes WordPress plugin issues.

---

## Required References

Before debugging, consult:

- `32_WORDPRESS/PIPELINE.md`
- `32_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `32_WORDPRESS/KNOWLEDGE/PLUGIN-HANDBOOK.md`
- `32_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `32_WORDPRESS/STANDARDS/JAVASCRIPT-STANDARD.md`
- `32_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `32_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `32_WORDPRESS/SECURITY-VALIDATOR.md`

---

## Debug Workflow

1. Identify the symptom.
2. Identify the affected files.
3. Check recent changes.
4. Reproduce the issue.
5. Check PHP errors.
6. Check JavaScript console errors.
7. Check WordPress debug logs.
8. Check hook conflicts.
9. Check permissions, nonces, and request data.
10. Check database queries.
11. Apply the smallest safe fix.
12. Re-test.
13. Produce a debug report.

---

## Common Plugin Issues

Check for:

- PHP fatal errors
- missing required files
- duplicate class names
- bad hook callbacks
- broken activation hooks
- broken deactivation hooks
- invalid admin menu callbacks
- nonce failures
- capability failures
- AJAX failures
- REST endpoint failures
- missing assets
- database errors
- dependency conflicts

---

## Debug Report Format

```text
Debug Summary

Symptom:
Cause:
Files Affected:
Fix Applied:
Security Impact:
Testing Results:
Remaining Risks:
Next Step:
```

## Rule

SquirrelForge must prefer the smallest safe fix that resolves the confirmed cause.