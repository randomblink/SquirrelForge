# SquirrelForge Feature Flags

Version: 1.0.0
Status: Stable
Owner: Runtime Configuration Maintainers
Depends On: `23_GOVERNANCE`, `24_SECURITY`, `28_RUNTIME-CONFIG/CONFIGURATION-REGISTRY.md`, `28_RUNTIME-CONFIG/CONFIGURATION-VALIDATOR.md`, `28_RUNTIME-CONFIG/CONFIGURATION-AUDIT.md`
Used By: `28_RUNTIME-CONFIG/RUNTIME-CONFIGURATION.md`, Engine, Execution, Integrations, WordPress
Last Updated: 2026-07-08

## Purpose

Feature Flags owns feature-flag configuration records, flag lifecycle status, targeting references, rollout settings, dependency references, and kill-switch configuration status.

It resolves whether a registered feature flag is configured as enabled, disabled, experimental, beta, deprecated, or retired for a supplied context.

It does not own authorization decisions, governance approval, deployment/release authority, business routing, execution state, incident response, recovery execution, or validation of business outcomes.

---

## Responsibilities

- Register feature-flag configuration records.
- Maintain flag state, lifecycle, owner, dependency, and rollout metadata.
- Evaluate configuration-level targeting rules against supplied context references.
- Record kill-switch configuration changes and status.
- Provide feature-flag configuration references to runtime configuration.
- Preserve feature-flag changes through configuration-domain history.

---

## Feature Flag States

| State | Meaning |
|---|---|
| `Disabled` | Feature is configured unavailable. |
| `Enabled` | Feature is configured available for allowed contexts. |
| `Experimental` | Feature is configured for limited evaluation. |
| `Beta` | Feature is configured for staged rollout. |
| `Deprecated` | Feature remains traceable but is scheduled for removal. |
| `Retired` | Feature is no longer available for new use. |

These are feature-flag configuration states only. They are not deployment, release, authorization, validation, or workflow states.

---

## Rules

1. Feature Flags may configure availability but must not authorize access to protected resources.
2. Feature Flags may record kill-switch configuration status but must not execute incident response or rollback.
3. Feature Flag changes must preserve configuration-domain history.
