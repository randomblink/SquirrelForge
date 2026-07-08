# SquirrelForge Runtime Configuration

Version: 1.0.0
Status: Stable
Owner: Runtime Configuration Maintainers
Depends On: `28_RUNTIME-CONFIG/CONFIGURATION-REGISTRY.md`, `28_RUNTIME-CONFIG/ENVIRONMENTS.md`, `28_RUNTIME-CONFIG/FEATURE-FLAGS.md`, `28_RUNTIME-CONFIG/POLICY-CONFIGURATION.md`, `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`, `28_RUNTIME-CONFIG/CONFIGURATION-VALIDATOR.md`
Used By: `28_RUNTIME-CONFIG/CONFIGURATION-MANAGER.md`, Core, Engine, Execution, Integrations, Security, Observability, WordPress
Last Updated: 2026-07-08

## Purpose

Runtime Configuration owns active runtime configuration resolution for running components.

It combines registered configuration records, environment overlays, feature-flag configuration, policy-configuration references, and secret references into validated active configuration bundles.

It does not own the configuration registry, environment definitions, feature-flag records, policy evaluation, secret values, execution state, deployment, recovery, or authoritative workflow state.

---

## Responsibilities

- Resolve active runtime configuration bundles.
- Apply environment overlays and approved override precedence.
- Include feature-flag and policy-configuration references.
- Include secret references without exposing raw secret values.
- Refresh active configuration bundles after approved configuration-domain changes.
- Return runtime configuration status and evidence references to callers.

---

## Runtime States

| State | Meaning |
|---|---|
| `Initializing` | Runtime configuration bundle is being resolved. |
| `Active` | Validated configuration bundle is available for use. |
| `Refreshing` | Approved configuration changes are being re-resolved. |
| `Invalid` | Configuration-domain validation failed. |
| `Expired` | Configuration bundle is no longer valid for use. |

These are runtime-configuration states only. They are not execution, deployment, workflow, recovery, or validation states outside this layer.

---

## Rules

1. Runtime Configuration may expose secret references only, never raw secret values.
2. Runtime Configuration must use registered and configuration-domain-validated records.
3. Runtime refresh does not authorize deployment or change workflow state.
