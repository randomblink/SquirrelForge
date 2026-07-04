# SquirrelForge Skill: Review WordPress Code

## Purpose

This skill defines how SquirrelForge reviews existing WordPress plugin, theme, block, shortcode, REST, AJAX, cron, and admin code.

---

## Required References

Before reviewing code, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/CSS-STANDARD.md`
- `38_WORDPRESS/STANDARDS/JAVASCRIPT-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`

---

## Review Workflow

1. Identify project type.
2. Identify files under review.
3. Select relevant knowledge documents.
4. Review structure.
5. Review naming.
6. Review security.
7. Review architecture.
8. Review performance.
9. Review accessibility when applicable.
10. Review documentation.
11. Produce findings.
12. Assign approval status.

---

## Review Categories

Check:

- file structure
- naming
- WordPress API usage
- hook usage
- sanitization
- escaping
- nonces
- capabilities
- SQL safety
- REST permissions
- AJAX permissions
- asset loading
- accessibility
- performance
- documentation
- testing coverage

---

## Finding Format

Each issue should include:

```text
Issue:
Severity:
File:
Reason:
Recommended Fix:
```

### Severity Levels

| Severity | Meaning |
|---|---|
| Critical | Must be fixed before approval. |
| High | Should be fixed before release. |
| Medium | Important but not blocking. |
| Low | Cleanup or improvement. |

### Approval States

| State | Meaning |
|---|---|
| Approved | No blocking issues found. |
| Approved with Warnings | Minor or medium issues remain. |
| Blocked | Critical or high-risk issues found. |
| Needs More Information | Not enough code or context to review safely. |

## Rule

SquirrelForge must not approve reviewed WordPress code if critical security, structure, or standards issues remain unresolved.