# WordPress Agent Generalization Test

Status: Dry-Run Evidence
Type: Routing/Generalization Validation
Modified Implementation Files: None

## Purpose

This document records a dry-run test of the SquirrelForge external AI bootstrap
chain against a WordPress plugin request that is materially different from the
`sf-private-records` example already present in this repository. The goal is to
provide evidence that `AI-BOOTSTRAP.md` → `12_AGENT` → `38_WORDPRESS` routing
generalizes beyond a single worked example, rather than only reproducing the
Skill Routing Map's own sample request.

No plugin, theme, or source files were created or modified as part of this test.

---

## Test Request

> Build a WordPress plugin that:
> - registers a custom taxonomy for organizing resources;
> - provides an admin settings page for configuring the plugin;
> - runs a daily scheduled maintenance task.

This request was chosen because it does not match the Skill Routing Map's own
"private member records" example and combines three distinct architectural
concerns (taxonomy, admin settings, cron) inside one plugin, none of which have
a dedicated Skill of their own.

---

## Bootstrap Chain Followed

1. [`AGENTS.md`](../../AGENTS.md) → delegates to vendor-neutral bootstrap
2. [`AI-BOOTSTRAP.md`](../../AI-BOOTSTRAP.md) → required startup chain
3. [`README.md`](../../README.md) → layer map / repository overview
4. [`00_CORE/SYSTEM-ORCHESTRATOR.md`](../../00_CORE/SYSTEM-ORCHESTRATOR.md) → control-plane lifecycle
5. [`12_AGENT/README.md`](../../12_AGENT/README.md) → Agent Layer entry
6. [`12_AGENT/BOOTSTRAP.md`](../../12_AGENT/BOOTSTRAP.md) → 13-step bootstrap sequence
7. [`12_AGENT/CAPABILITY-ROUTER.md`](../../12_AGENT/CAPABILITY-ROUTER.md) → "Build a plugin" routed to WordPress domain
8. [`38_WORDPRESS/WORDPRESS-MANAGER.md`](../../38_WORDPRESS/WORDPRESS-MANAGER.md) → WordPress control flow
9. [`38_WORDPRESS/PIPELINE.md`](../../38_WORDPRESS/PIPELINE.md) → Agent Pipeline stages
10. [`38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`](../../38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md) → knowledge mapping
11. [`38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md`](../../38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md) → Skill selection
12. [`38_WORDPRESS/SKILLS/CREATE-PLUGIN.md`](../../38_WORDPRESS/SKILLS/CREATE-PLUGIN.md) → Required Input schema (Data Requirements, Admin Features, Cron Requirements)
13. [`38_WORDPRESS/ROLES/ROLE-MANAGER.md`](../../38_WORDPRESS/ROLES/ROLE-MANAGER.md) + [`38_WORDPRESS/ROLES/ROLE-ROUTING-MATRIX.md`](../../38_WORDPRESS/ROLES/ROLE-ROUTING-MATRIX.md) → role routing
14. [`38_WORDPRESS/SECURITY-VALIDATOR.md`](../../38_WORDPRESS/SECURITY-VALIDATOR.md) + [`38_WORDPRESS/KNOWLEDGE/CRON.md`](../../38_WORDPRESS/KNOWLEDGE/CRON.md) → validation references

---

## Selected Domain

**WordPress** (`38_WORDPRESS`).

---

## Selected Primary Skill

**`CREATE-PLUGIN`**

A single plugin bundling a taxonomy, a settings page, and a cron job is a
plugin-scale system, not three independent deliverables. Taxonomy, admin
settings, and cron are architecture components inside `CREATE-PLUGIN`'s Plugin
Architecture Specification (which explicitly lists "admin architecture" and
"cron architecture" as line items), not separate Skills — there is no
dedicated `CREATE-TAXONOMY` or `CREATE-SETTINGS-PAGE` Skill in the routing map.

---

## Supporting Skills

- `CREATE-TESTS` — activation/deactivation, cron scheduling, and settings
  persistence all need explicit test coverage.
- `WRITE-DOCUMENTATION` — settings fields, taxonomy usage, and cron schedule
  need documented, validated behavior.

Rejected: `CREATE-REST-ENDPOINT`, `CREATE-BLOCK`, `CREATE-WIDGET`,
`CREATE-SHORTCODE` — none of these deliverables were requested.

---

## Selected Knowledge

| Component | Knowledge |
|---|---|
| Taxonomy | `38_WORDPRESS/KNOWLEDGE/TAXONOMIES.md`, `PLUGIN-HANDBOOK.md` |
| Settings page | `38_WORDPRESS/KNOWLEDGE/SETTINGS-API.md`, `ACCESSIBILITY.md` |
| Cron | `38_WORDPRESS/KNOWLEDGE/CRON.md` |
| Baseline (all `CREATE-PLUGIN` work) | `WORDPRESS-CORE.md`, `SECURITY.md`, `CODING-STANDARDS.md` |

Conflict priority: Security > WordPress core behavior > project requirements >
performance > convenience.

---

## Role Execution Chain

Route 1 (`CREATE-PLUGIN`), Complexity: **Moderate** (three bounded components,
no custom tables, no public contract).

```text
Project Architect
↓
Role Manager
↓
Plugin Architect
↓
PHP Engineer                 (taxonomy registration, settings page, cron callback)
↓
Security Engineer            (required — settings input + cron safety)
↓
Performance Engineer         (conditional — triggered by cron per Role Routing Matrix)
↓
QA Engineer
↓
Documentation Engineer
↓
Release Engineer             (conditional — only if production release intended)
```

Not triggered: Database Engineer (no custom tables), REST/Block/JavaScript
Engineer (no API, block, or client-side interaction requested), CSS Engineer
(default WP admin chrome unless custom styling is requested).

---

## Missing Requirements Found

Per `CREATE-PLUGIN`'s Required Input schema, these fields were unresolved at
the time of this test:

- **Taxonomy target** — which post type(s) does the taxonomy organize? Not
  specified as an existing or new CPT.
- **Taxonomy shape** — hierarchical (category-like) or flat (tag-like)?
  Public-facing or admin-only?
- **Settings page contents** — no fields, options, or sections were specified.
- **Settings capability** — confirm `manage_options` or a custom capability.
- **Maintenance task definition** — undefined; determines workload size,
  batching need, and whether sensitive data or external systems are touched.
- **Distribution/release intent** — dry-run/demo vs. production release.
- **Compatibility requirements** — multisite behavior, minimum WP/PHP version.

Per the Skill's rule *"Critical requirements must not be invented,"* these gaps
blocked progression past Stage 1 (Requirements Definition).

---

## Security Gates

Mandatory per `38_WORDPRESS/SECURITY-VALIDATOR.md` (Security Engineer is a
required, independent role for `CREATE-PLUGIN`):

- **Settings page**: `sanitize_callback` on every `register_setting()`;
  `settings_fields()` nonce present in the form; capability check on the admin
  menu registration; `esc_attr()`/`esc_html()` on all rendered option values.
- **Taxonomy**: capability checks if custom term-management capabilities are
  introduced; sanitize/escape any custom term meta or admin-rendered term
  data; confirm `show_in_rest` exposure doesn't leak data beyond intended
  visibility.
- **Cron**: the scheduled callback must not trust request-bound data (cron
  runs outside a normal request context); no secrets or credentials hardcoded
  into the maintenance routine; confirm the cron hook is not directly
  triggerable via an unauthenticated public path.

---

## Cron Validation Gates

Per `38_WORDPRESS/KNOWLEDGE/CRON.md`, valid only when all of the following are
addressed:

1. **No duplicate scheduling** — guard with `wp_next_scheduled()` before
   `wp_schedule_event()`.
2. **Activation/deactivation behavior defined** — schedule on activation,
   clear via `wp_clear_scheduled_hook()` on deactivation/uninstall.
3. **Recurrence correctness** — "daily" maps to WordPress's built-in `daily`
   schedule.
4. **Bounded/batchable workload** — no timeout or memory exhaustion risk on
   large datasets.
5. **Overlap prevention** — a lock if the task could run long enough to
   overlap a subsequent daily firing.
6. **Safe failure/retry** — partial failure must not corrupt state or loop.
7. **Cleanup on removal** — obsolete scheduled hooks removed on
   deactivation/uninstall.
8. **Observability** — logging sufficient to confirm the task ran.
9. **Performance impact reviewed** — routed to Performance Engineer per the
   Conditional Role Trigger Matrix.

QA must additionally test activation, deactivation, and reactivation cron
behavior per `CREATE-PLUGIN` Stage 11.

---

## Completion Status

**Blocked — Needs More Information.**

Skill selection (`CREATE-PLUGIN`) and role routing were determined
successfully, but Stage 1 (Requirements Definition) could not close without
the taxonomy target/shape, settings page content, and maintenance-task
definition. No architecture, code, or file changes were produced or
attempted for the plugin itself.

---

## Conclusion

The SquirrelForge bootstrap chain (`AI-BOOTSTRAP.md` → `12_AGENT` →
`38_WORDPRESS`) routed a request structurally different from the
`sf-private-records` worked example — one combining taxonomy, settings-page,
and cron concerns with no dedicated Skill of its own — to the correct primary
Skill (`CREATE-PLUGIN`), correct supporting Skills, correct Knowledge set, and
a correct, non-trivial role execution chain (including the conditional
Performance Engineer trigger for cron work). It also correctly refused to
invent missing requirements and halted at the Requirements Definition gate
rather than proceeding to code generation.

This is evidence that the routing chain generalizes beyond its own
documentation examples rather than only pattern-matching a single known
request.
