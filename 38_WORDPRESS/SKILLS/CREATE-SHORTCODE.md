# SquirrelForge WordPress Create Shortcode Skill

## Purpose

This Skill defines the controlled workflow for creating a WordPress shortcode.

It coordinates requirements, knowledge selection, architecture, role routing, specialist implementation, security, performance validation, QA, and documentation.

---

## Trigger Conditions

Use this Skill when the request is to:

- create a new shortcode
- wrap existing functionality in a shortcode

Do not use this Skill when the task is to:

- create a block
- create a widget
- create a full plugin

Use the appropriate specialized Skill instead.

---

## Required References

Before execution, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/SHORTCODES.md`
- `38_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `38_WORDPRESS/ROLES/ROLE-MANAGER.md`
- `38_WORDPRESS/ROLES/ROLE-ROUTING-MATRIX.md`
- `38_WORDPRESS/ROLES/PHP-ENGINEER.md`
- `38_WORDPRESS/ROLES/SECURITY-ENGINEER.md`
- `38_WORDPRESS/ROLES/QA-ENGINEER.md`
- `38_WORDPRESS/ROLES/DOCUMENTATION-ENGINEER.md`

---

## Required Input

```text
Shortcode Creation Request

Tag:
Purpose:
Attributes:
Content Support:
Known Constraints:
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