Status: Stable

---
# SquirrelForge WordPress Core Knowledge

## Purpose

This document defines the core WordPress concepts SquirrelForge must understand before designing, generating, reviewing, or approving WordPress code.

---

## Core Concept

WordPress is an event-driven PHP application built around:

- hooks
- filters
- templates
- options
- users
- roles
- capabilities
- posts
- taxonomies
- metadata
- themes
- plugins
- REST routes
- admin screens

A WordPress Agent must understand how these pieces interact before modifying a project.

---

## Execution Flow

A simplified WordPress request flow:

1. Load WordPress core.
2. Load active plugins.
3. Load the active theme.
4. Parse the request.
5. Query content.
6. Run hooks and filters.
7. Render admin, frontend, REST, AJAX, cron, or CLI response.

---

## Major WordPress Contexts

| Context | Meaning |
|---|---|
| Frontend | Public-facing site output. |
| Admin | WordPress dashboard and management screens. |
| REST API | JSON API requests. |
| AJAX | `admin-ajax.php` requests. |
| Cron | Scheduled background events. |
| WP-CLI | Command-line operations. |
| Login | Authentication and account screens. |

---

## Hook System

WordPress behavior is extended through:

| Type | Purpose |
|---|---|
| Action | Runs custom behavior at a specific point. |
| Filter | Modifies data before it is used or returned. |

SquirrelForge must document every hook it adds or depends on.

---

## Common Core Hooks

| Hook | Type | Common Use |
|---|---|---|
| `init` | Action | Register post types, taxonomies, shortcodes. |
| `admin_init` | Action | Register settings or admin setup. |
| `admin_menu` | Action | Add admin menu pages. |
| `wp_enqueue_scripts` | Action | Load frontend assets. |
| `admin_enqueue_scripts` | Action | Load admin assets. |
| `rest_api_init` | Action | Register REST routes. |
| `plugins_loaded` | Action | Load plugin dependencies/translations. |
| `after_setup_theme` | Action | Register theme support and menus. |
| `wp_ajax_*` | Action | Logged-in AJAX handlers. |
| `wp_ajax_nopriv_*` | Action | Public AJAX handlers. |

---

## Themes

Themes control presentation and template rendering.

SquirrelForge must distinguish between:

- classic themes
- child themes
- block themes
- hybrid themes

Theme code should focus on presentation, template structure, design tokens, assets, menus, sidebars, template parts, and block support.

---

## Plugins

Plugins extend functionality.

Plugin code should contain business logic, admin tools, custom data models, integrations, REST endpoints, shortcodes, blocks, settings, scheduled events, and reusable features.

A theme should not contain core business logic that must survive a theme change.

---

## Data Types

WordPress commonly stores data in:

| Storage | Common Use |
|---|---|
| Options | Site-wide settings. |
| Transients | Temporary cached values. |
| Post meta | Data attached to posts or custom post types. |
| User meta | Data attached to users. |
| Term meta | Data attached to taxonomy terms. |
| Custom tables | Complex or high-volume structured data. |

---

## Capabilities

WordPress permissions are checked using capabilities.

Examples:

- `manage_options`
- `edit_posts`
- `edit_pages`
- `upload_files`
- `edit_users`
- `delete_posts`

Administrative actions must check capabilities before making changes.

---

## Security Basics

WordPress code must use:

- sanitization for incoming data
- escaping for outgoing data
- nonces for intent verification
- capabilities for permission checks
- prepared SQL for database queries
- safe upload validation
- permission callbacks for REST routes

---

## Request Data

SquirrelForge must treat these as untrusted:

- `$_GET`
- `$_POST`
- `$_REQUEST`
- `$_COOKIE`
- uploaded files
- REST request parameters
- AJAX request parameters
- imported data
- external API responses

---

## Template Hierarchy

Classic themes use the WordPress template hierarchy to determine which template file renders a request.

Common files include:

- `index.php`
- `front-page.php`
- `home.php`
- `single.php`
- `page.php`
- `archive.php`
- `category.php`
- `tag.php`
- `search.php`
- `404.php`

---

## Block Themes

Block themes use:

- `theme.json`
- `templates/`
- `parts/`
- block patterns
- global styles
- site editor compatibility

---

## Asset Loading

Assets should be loaded through WordPress enqueue functions, not hardcoded directly.

Common functions:

- `wp_enqueue_style()`
- `wp_enqueue_script()`
- `wp_register_style()`
- `wp_register_script()`

Assets should be conditionally loaded when possible.

---

## Database Access

Database access should use WordPress APIs where possible.

When direct SQL is needed:

- use `$wpdb`
- use `$wpdb->prepare()`
- validate table names
- sanitize inputs
- avoid raw user input in queries

---

## REST API

REST routes must define:

- namespace
- route
- methods
- callback
- permission callback
- arguments
- validation
- sanitization

Routes without permission callbacks must fail review unless they are intentionally public and documented.

---

## Cron

WordPress cron is request-driven.

Scheduled tasks must:

- avoid duplicate scheduling
- clean themselves up on deactivation or uninstall
- fail safely
- avoid heavy work on every request

---

## Internationalization

User-facing strings should be translation-ready.

Common functions include:

- `__()`
- `_e()`
- `esc_html__()`
- `esc_html_e()`
- `esc_attr__()`
- `esc_attr_e()`

---

## Coding Standard Rule

Generated WordPress code should be readable, prefixed or namespaced, documented, and aligned with WordPress coding standards.

---

## Agent Rule

SquirrelForge must identify the WordPress context before generating code.

The context must be one of:

- plugin
- theme
- child theme
- block theme
- admin tool
- shortcode
- block
- REST endpoint
- AJAX handler
- cron task
- WooCommerce extension
- migration
- maintenance script

## Rule

WordPress core knowledge must guide compatibility, lifecycle, hook, and API decisions.
