# SquirrelForge WordPress Create Block Skill

## Purpose

This Skill defines the controlled workflow for creating a WordPress block.

It coordinates requirements, knowledge selection, architecture, role routing, specialist implementation, security, performance validation, QA, and documentation.

---

## Trigger Conditions

Use this Skill when the request is to:

- create a new block
- add a block to an existing plugin or theme

Do not use this Skill when the task is to:

- create a full plugin
- create a shortcode
- create a widget

Use the appropriate specialized Skill instead.

---

## Required References

Before execution, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/KNOWLEDGE/BLOCK-EDITOR.md`
- `38_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `38_WORDPRESS/ROLES/ROLE-MANAGER.md`
- `38_WORDPRESS/ROLES/ROLE-ROUTING-MATRIX.md`
- `38_WORDPRESS/ROLES/BLOCK-ENGINEER.md`
- `38_WORDPRESS/ROLES/JAVASCRIPT-ENGINEER.md`
- `38_WORDPRESS/ROLES/PHP-ENGINEER.md`
- `38_WORDPRESS/ROLES/CSS-ENGINEER.md`
- `38_WORDPRESS/ROLES/SECURITY-ENGINEER.md`
- `38_WORDPRESS/ROLES/QA-ENGINEER.md`
- `38_WORDPRESS/ROLES/DOCUMENTATION-ENGINEER.md`

---

## Required Input

```text
Block Creation Request

Name:
Purpose:
Attributes:
Editor UI:
Frontend Output:
Dynamic Rendering Needs:
Known Constraints:
```
### Security Gates

Every block must:

- validate attributes
- escape rendered output
- avoid exposing private data
- avoid unsafe inline scripts
- avoid unnecessary global assets
### Accessibility Gates

Verify:

- keyboard usability
- proper labels
- readable contrast
- predictable focus behavior
- semantic output
### Testing Gates

Verify:

- block appears in editor
- attributes save correctly
- frontend renders correctly
- invalid data fails safely
- assets load correctly
- accessibility checks pass
## Rule

SquirrelForge must not create a block that renders unescaped dynamic content.


Next file:

```text
32_WORDPRESS/SKILLS/CREATE-WIDGET.md
```