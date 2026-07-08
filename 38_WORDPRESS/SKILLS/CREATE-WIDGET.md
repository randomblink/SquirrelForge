Status: Stable

---
# SquirrelForge WordPress Create Widget Skill

## Purpose

This Skill defines the controlled workflow for creating a safe WordPress widget or sidebar feature.

It coordinates requirements, knowledge selection, architecture, role routing, specialist implementation, security, performance validation, QA, and documentation.

---

## Trigger Conditions

Use this Skill when the request is to:

- create a new widget
- create a classic widget for a sidebar

Do not use this Skill when the task is to:

- create a block
- create a shortcode
- create a full plugin

Use the appropriate specialized Skill instead.

---

## Required References

Before execution, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`
- `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`
- `33_WORDPRESS_ROLES/PHP-ENGINEER.md`
- `33_WORDPRESS_ROLES/SECURITY-ENGINEER.md`
- `33_WORDPRESS_ROLES/QA-ENGINEER.md`
- `33_WORDPRESS_ROLES/DOCUMENTATION-ENGINEER.md`

---

## Required Input

```text
Widget Creation Request

Name:
Purpose:
Settings:
Known Constraints:
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
