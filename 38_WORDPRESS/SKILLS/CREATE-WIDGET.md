# SquirrelForge Skill: Create WordPress Widget

## Purpose

This skill defines how SquirrelForge creates safe WordPress widgets and sidebar features.

---

## Required References

Before creating a widget, consult:

- `32_WORDPRESS/PIPELINE.md`
- `32_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `32_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `32_WORDPRESS/STANDARDS/CSS-STANDARD.md`
- `32_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `32_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `32_WORDPRESS/SECURITY-VALIDATOR.md`

---

## Workflow

1. Identify widget purpose.
2. Define widget name, ID, and class.
3. Define widget settings.
4. Define sanitization rules.
5. Define frontend output.
6. Escape all output.
7. Register the widget.
8. Create tests.
9. Produce final report.

---

## Required Planning Output

```text
Widget Plan

Name:
ID:
Class:
Purpose:
Settings:
Sanitization:
Frontend Output:
Assets:
Testing:
```
### Security Gates

Every widget must:

- sanitize saved settings
- escape frontend output
- avoid exposing private data
- avoid unsafe raw HTML unless explicitly allowed and filtered
### Testing Gates

Verify:

- widget registers
- widget appears in widget area
- settings save
- frontend output renders
- invalid input fails safely
- output is escaped
## Rule

SquirrelForge must not create a widget that stores unsanitized settings or outputs unescaped dynamic data.