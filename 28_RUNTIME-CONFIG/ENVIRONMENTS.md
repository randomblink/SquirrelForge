# SquirrelForge Environments

Version: 1.0.0
Status: Stable
Owner: Runtime Configuration Maintainers
Depends On: `28_RUNTIME-CONFIG/CONFIGURATION-REGISTRY.md`, `28_RUNTIME-CONFIG/CONFIGURATION-VALIDATOR.md`, `28_RUNTIME-CONFIG/CONFIGURATION-AUDIT.md`
Used By: `28_RUNTIME-CONFIG/RUNTIME-CONFIGURATION.md`, `28_RUNTIME-CONFIG/CONFIGURATION-MANAGER.md`
Last Updated: 2026-07-08

## Purpose

Environments owns environment profile records, environment overlays, inheritance rules, environment-specific configuration references, and override precedence for runtime configuration.

It does not own deployment targets, deployment approval, infrastructure provisioning, execution state, secret values, or platform validation.

---

## Responsibilities

- Maintain registered environment profiles.
- Record environment overlay references and inheritance order.
- Record allowed override scopes and precedence.
- Provide environment-specific configuration references to runtime resolution.
- Preserve environment profile changes through configuration-domain history.

---

## Environment Record

| Field | Description |
|---|---|
| Environment ID | Stable environment identifier. |
| Name | Environment name. |
| Parent Profile | Base profile reference, when inherited. |
| Overlay References | Environment-specific configuration overlays. |
| Override Rules | Allowed override scope and precedence. |
| Validation Reference | Configuration-domain validation record. |
| Lifecycle Status | Active, deprecated, or archived status. |

---

## Rules

1. Environment overlays must be deterministic and traceable.
2. Environment profiles may reference secret identifiers but must not contain raw secret values.
3. Environment validation is configuration-domain validation only; deployment readiness belongs to deployment/execution owners.
