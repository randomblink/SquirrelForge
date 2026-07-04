# SquirrelForge WordPress Rules

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: `AGENT-BEHAVIOR.md`, `38_WORDPRESS/README.md`
Used By: WordPress workflows, WordPress-capable agents, and WordPress-specific reviews
Last Updated: 2026-07-04

## Purpose

This file defines the mandatory baseline rules for WordPress-specific work in SquirrelForge.

These rules apply only when the request involves WordPress plugins, themes, blocks, admin tools, REST APIs, WooCommerce, media, cron, database changes, WordPress deployment, or WordPress-powered products.

They do not replace the general agent behavior rules.

---

## Activation Rule

Load this file when the active request touches WordPress.

For WordPress work, also load the relevant documents from `38_WORDPRESS`.

Do not load this file as a global requirement for unrelated non-WordPress work.

---

## Mandatory WordPress Rules

- Validate and sanitize input.
- Escape output for its exact context.
- Enforce capabilities for privileged actions.
- Use nonces for state-changing admin and frontend actions where appropriate.
- Use WordPress APIs and coding standards where applicable.
- Do not directly modify WordPress core as a normal implementation method.
- Use hooks, filters, plugins, must-use plugins, themes, child themes, blocks, REST APIs, WP-CLI commands, or supported integration APIs for custom behavior.
- Parameterize database queries.
- Avoid direct schema assumptions unless verified against the active WordPress environment.
- Preserve backward compatibility or document a governed breaking change.
- Make user-facing strings translatable.
- Make interfaces accessible.
- Load assets only where needed.
- Measure performance-sensitive changes.
- Add tests appropriate to the risk.
- Record validation evidence.

---

## Security Baseline

WordPress work must protect:

- authentication,
- authorization,
- nonces,
- capabilities,
- input validation,
- output escaping,
- database queries,
- file uploads,
- media handling,
- REST endpoints,
- admin actions,
- AJAX actions,
- cron actions,
- and third-party integrations.

When security risk is present, load `24_SECURITY` in addition to WordPress-specific guidance.

---

## Validation Baseline

WordPress work should be validated according to the change type.

Relevant checks may include:

- PHP syntax checks,
- WordPress coding standards,
- static analysis,
- unit tests,
- integration tests,
- plugin activation tests,
- theme activation tests,
- REST API tests,
- block build tests,
- browser checks,
- accessibility checks,
- responsive checks,
- performance checks,
- and manual verification.

The agent must not claim a validation step passed unless it was actually performed.

---

## Rule

> WordPress rules are mandatory for WordPress work and inactive for unrelated domains unless explicitly required by the request.
