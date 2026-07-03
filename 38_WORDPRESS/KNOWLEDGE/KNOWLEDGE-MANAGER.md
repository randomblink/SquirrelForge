# SquirrelForge WordPress Knowledge Manager

## Purpose

The Knowledge Manager determines which WordPress Knowledge Base documents must be consulted before SquirrelForge plans, generates, reviews, validates, or approves WordPress work.

It acts as the routing layer between a WordPress task and the correct WordPress reference material.

---

## Responsibilities

- Identify the type of WordPress task.
- Select the required knowledge documents.
- Load only relevant references.
- Resolve conflicts between knowledge documents.
- Record which references were used.
- Support traceable decisions.
- Ensure WordPress work is grounded in the Knowledge Base.

---

## Knowledge Selection Workflow

1. Receive WordPress task.
2. Identify task type.
3. Identify project context.
4. Select required knowledge documents.
5. Select optional supporting documents.
6. Pass references to the WordPress Manager.
7. Record references used in the final report.

---

## Task Reference Map

| Task | Required Knowledge |
|---|---|
| Build plugin | `PLUGIN-HANDBOOK.md`, `SECURITY.md`, `CODING-STANDARDS.md` |
| Build theme | `THEME-HANDBOOK.md`, `ACCESSIBILITY.md`, `CODING-STANDARDS.md` |
| Build block theme | `THEME-HANDBOOK.md`, `BLOCK-EDITOR.md`, `ACCESSIBILITY.md` |
| Create settings page | `SETTINGS-API.md`, `SECURITY.md`, `CODING-STANDARDS.md` |
| Create REST endpoint | `REST-API.md`, `SECURITY.md`, `PERFORMANCE.md` |
| Create shortcode | `SHORTCODES.md`, `SECURITY.md`, `ACCESSIBILITY.md` |
| Create cron task | `CRON.md`, `SECURITY.md`, `PERFORMANCE.md` |
| Add database table | `DATABASE.md`, `SECURITY.md`, `PERFORMANCE.md` |
| Add custom post type | `CUSTOM-POST-TYPES.md`, `TAXONOMIES.md`, `SECURITY.md` |
| Add media handling | `MEDIA.md`, `SECURITY.md`, `PERFORMANCE.md` |
| Add WooCommerce feature | `WOOCOMMERCE.md`, `PLUGIN-HANDBOOK.md`, `SECURITY.md` |
| Review code | `SECURITY.md`, `CODING-STANDARDS.md`, `PERFORMANCE.md`, `TESTING-CHECKLIST.md` |

---

## Conflict Priority

When knowledge documents disagree, use this priority order:

1. Security
2. Official WordPress behavior
3. Project-specific rules
4. Accessibility
5. Performance
6. Maintainability
7. Convenience

---

## Required Reference Record

Every WordPress task report should include:

```text
References Consulted:
- Document:
- Reason:
- Decision Impact:
Agent Rule

SquirrelForge must not perform WordPress planning, generation, validation, or approval without first selecting the relevant Knowledge Base documents.


Next file:

```text
32_WORDPRESS/KNOWLEDGE/PLUGIN-HANDBOOK.md
