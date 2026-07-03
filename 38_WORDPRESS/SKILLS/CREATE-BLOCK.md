# SquirrelForge Skill: Create WordPress Block

## Purpose

This skill defines how SquirrelForge creates safe, accessible WordPress block editor features.

---

## Required References

Before creating a block, consult:

- `32_WORDPRESS/PIPELINE.md`
- `32_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `32_WORDPRESS/KNOWLEDGE/BLOCK-EDITOR.md`
- `32_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `32_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `32_WORDPRESS/STANDARDS/CSS-STANDARD.md`
- `32_WORDPRESS/STANDARDS/JAVASCRIPT-STANDARD.md`
- `32_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `32_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `32_WORDPRESS/SECURITY-VALIDATOR.md`

---

## Workflow

1. Identify block purpose.
2. Define block name and namespace.
3. Define attributes.
4. Define editor behavior.
5. Define frontend rendering.
6. Register block assets.
7. Validate input and attributes.
8. Escape rendered output.
9. Verify accessibility.
10. Create tests.
11. Produce final report.

---

## Required Planning Output

```text
Block Plan

Name:
Namespace:
Purpose:
Attributes:
Editor UI:
Frontend Output:
Assets:
Server Rendering:
Security:
Accessibility:
Testing:
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