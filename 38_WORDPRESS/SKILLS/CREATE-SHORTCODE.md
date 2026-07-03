# SquirrelForge Skill: Create WordPress Shortcode

## Purpose

This skill defines how SquirrelForge creates safe, reusable WordPress shortcodes.

---

## Required References

Before creating a shortcode, consult:

- `32_WORDPRESS/PIPELINE.md`
- `32_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `32_WORDPRESS/KNOWLEDGE/SHORTCODES.md`
- `32_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `32_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `32_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `32_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `32_WORDPRESS/SECURITY-VALIDATOR.md`

---

## Workflow

1. Identify shortcode purpose.
2. Define shortcode tag.
3. Define accepted attributes.
4. Define default values.
5. Define sanitization.
6. Define output rendering.
7. Escape all output.
8. Register shortcode.
9. Create tests.
10. Produce final report.

---

## Required Planning Output

```text
Shortcode Plan

Tag:
Purpose:
Attributes:
Defaults:
Sanitization:
Output:
Assets:
Testing:
```
### Security Gates

Every shortcode must:

- sanitize attributes
- escape output
- avoid unsafe raw HTML
- avoid exposing private data
- avoid heavy queries on every render
### Testing Gates

Verify:

- shortcode renders
- default attributes work
- custom attributes work
- invalid attributes fail safely
- output is escaped
- assets load only when needed
## Rule

SquirrelForge must not create a shortcode that outputs unescaped dynamic data.