# SquirrelForge Rules Layer

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: `README.md`, `ARCHITECTURE.md`, `11_OVERVIEW/`
Used By: Engine, Workflows, Agents, Validation, Security, Governance
Last Updated: 2026-07-04

## Purpose

The Rules Layer defines mandatory behavior, project standards, operating constraints, and domain-specific rules that govern SquirrelForge work.

Rules are not suggestions. They constrain planning, execution, validation, reporting, and completion.

---

## Layer Boundary

`01_RULES` owns:

- mandatory agent behavior,
- project operating rules,
- general system rules,
- domain-specific rule entry points,
- and rule conflict handling.

`01_RULES` does not own:

- domain knowledge handbooks,
- execution implementation,
- testing infrastructure,
- governance lifecycle policy,
- runtime configuration,
- or agent identity.

Those responsibilities remain in their respective layers.

---

## Components

| Component | Responsibility |
|---|---|
| `AGENT-BEHAVIOR.md` | Defines general agent operating rules. |
| `WORDPRESS-RULES.md` | Defines WordPress-specific implementation baseline rules. |

The component roster must match files that actually exist in this directory.

---

## Rule Loading Order

```text
General Agent Rules
   ↓
Project-Specific Rules
   ↓
Applicable Domain Rules
   ↓
Workflow Rules
   ↓
Security / Governance Constraints
   ↓
Approved Action
```

General rules should load for every request.

Domain-specific rules should load only when the request touches that domain.

For WordPress work, load `WORDPRESS-RULES.md` and the relevant `38_WORDPRESS` references.

---

## Dependencies

The Rules Layer depends on:

- the root repository map,
- the architecture overview,
- the Agent Layer,
- configuration settings,
- applicable workflow policy,
- applicable security policy,
- and applicable governance policy.

---

## Conflict Rule

Mandatory rules override recommendations.

When rules conflict, the agent must surface the conflict and resolve it using the active authority order before execution.

The agent must not hide conflicts by choosing whichever rule is more convenient.

---

## Domain Rule Boundary

A domain rule constrains work only when that domain is active.

Examples:

- WordPress rules apply to WordPress plugin, theme, block, REST API, WooCommerce, media, cron, database, and WordPress deployment work.
- Security rules apply whenever secrets, permissions, authentication, authorization, destructive actions, external systems, or sensitive data are involved.
- Governance rules apply whenever lifecycle, release, quality gates, deprecation, or architecture authority is involved.

This prevents one domain from becoming a hidden global requirement.

---

## Completion Criteria

The Rules Layer is healthy when:

- mandatory general rules are clear,
- domain rules are scoped correctly,
- stale layer references are removed,
- cross-layer dependencies point to existing locations,
- rule conflicts are explicitly handled,
- and no domain-specific rule is accidentally treated as universal.

---

## Rule

> Load general rules for every request. Load domain-specific rules only when the request touches that domain.
