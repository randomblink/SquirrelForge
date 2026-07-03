# SquirrelForge WordPress Knowledge Base

## Purpose

This directory stores the WordPress-specific knowledge SquirrelForge needs to make safe, useful, and accurate development decisions for plugins, themes, blocks, admin tools, REST APIs, WooCommerce extensions, and WordPress-powered products.

The Knowledge Base gives the WordPress Agent a reference layer so it does not rely only on general reasoning.

---

## Component Roster

| Component | Responsibility |
|---|---|
| `WORDPRESS-CORE.md` | Defines core WordPress concepts, lifecycle, hooks, files, and execution flow. |
| `PLUGIN-HANDBOOK.md` | Defines plugin development concepts, structure, lifecycle, and best practices. |
| `THEME-HANDBOOK.md` | Defines classic, child, and block theme development concepts. |
| `BLOCK-EDITOR.md` | Defines Gutenberg/block editor concepts and block development rules. |
| `SETTINGS-API.md` | Defines how settings pages, sections, fields, sanitization, and options work. |
| `REST-API.md` | Defines REST route creation, permission callbacks, schemas, and responses. |
| `DATABASE.md` | Defines safe database usage, `$wpdb`, schema changes, and custom tables. |
| `CUSTOM-POST-TYPES.md` | Defines custom post type registration and use cases. |
| `TAXONOMIES.md` | Defines custom taxonomy registration and use cases. |
| `SHORTCODES.md` | Defines shortcode creation, attributes, rendering, and safety. |
| `CRON.md` | Defines WordPress scheduled events and cleanup rules. |
| `MEDIA.md` | Defines uploads, attachments, image handling, and media safety. |
| `SECURITY.md` | Defines WordPress security principles and common failure patterns. |
| `PERFORMANCE.md` | Defines caching, asset loading, query safety, and optimization rules. |
| `ACCESSIBILITY.md` | Defines accessibility expectations for themes, admin pages, and frontend output. |
| `INTERNATIONALIZATION.md` | Defines translation and localization rules. |
| `WOOCOMMERCE.md` | Defines WooCommerce extension concepts, hooks, products, checkout, and orders. |
| `CODING-STANDARDS.md` | Defines WordPress PHP, JS, CSS, and documentation standards. |

---

## Usage Rule

Before SquirrelForge designs, generates, reviews, or approves WordPress code, it must consult the relevant Knowledge Base documents.

---

## Knowledge Priority

When documents conflict, priority should be:

1. Security rules
2. WordPress official behavior
3. Project-specific requirements
4. Performance rules
5. Convenience

---

## Agent Behavior

The WordPress Agent should use this Knowledge Base to:

- choose the correct WordPress API
- avoid unsafe shortcuts
- explain why a design decision was made
- detect missing files
- detect missing validation
- detect security risks
- generate better plugin and theme architecture
- create more accurate testing checklists

---

## Rule

The WordPress Knowledge Base must be maintained as a practical engineering reference, not as vague documentation.