# SquirrelForge Configuration Validator

Version: 1.0.0
Status: Stable
Owner: Runtime Configuration Maintainers
Depends On: `14_ENGINE/VALIDATION.md`, `23_GOVERNANCE/POLICY-ENGINE.md`, `24_SECURITY`, `28_RUNTIME-CONFIG/CONFIGURATION-REGISTRY.md`
Used By: `28_RUNTIME-CONFIG/CONFIGURATION-MANAGER.md`, `28_RUNTIME-CONFIG/RUNTIME-CONFIGURATION.md`, `28_RUNTIME-CONFIG/ENVIRONMENTS.md`, `28_RUNTIME-CONFIG/FEATURE-FLAGS.md`, `28_RUNTIME-CONFIG/POLICY-CONFIGURATION.md`
Last Updated: 2026-07-08

## Purpose

The Configuration Validator owns configuration-domain validation for runtime configuration records.

It checks structure, schema, required fields, type constraints, dependency references, environment compatibility, override constraints, feature-flag dependencies, policy-configuration references, and secret-reference presence.

It does not own platform-wide validation, task-completion validation, business outcome validation, governance policy evaluation, security decisions, deployment approval, or execution state.

---

## Responsibilities

- Validate configuration schema and structure.
- Validate required configuration-domain fields and data types.
- Check dependency references and duplicate/conflicting configuration records.
- Check environment overlay compatibility.
- Check feature-flag and policy-configuration reference integrity.
- Check secret-reference presence without reading raw secret values.
- Produce configuration-domain validation records.

---

## Validation Status

| Status | Meaning |
|---|---|
| `Pending` | Configuration-domain validation has not completed. |
| `Valid` | Configuration-domain checks passed. |
| `Warning` | Non-blocking configuration-domain issue was found. |
| `Failed` | Configuration-domain checks failed. |
| `Blocked` | Required authoritative evidence or references are missing. |

These statuses are configuration-domain validation statuses only. They are not platform validation, quality-gate, deployment, workflow, or business outcome statuses.

---

## Rules

1. Configuration Validator must consume governance, security, and platform validation references when required rather than replacing those owners.
2. Configuration Validator must not evaluate general policy independently.
3. Configuration Validator must not approve task completion or deployment.
