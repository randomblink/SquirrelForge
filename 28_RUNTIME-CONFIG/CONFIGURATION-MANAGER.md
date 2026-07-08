# SquirrelForge Configuration Manager

Version: 1.0.0
Status: Stable
Owner: Runtime Configuration Maintainers
Depends On: `28_RUNTIME-CONFIG/CONFIGURATION-REGISTRY.md`, `28_RUNTIME-CONFIG/CONFIGURATION-VALIDATOR.md`, `28_RUNTIME-CONFIG/RUNTIME-CONFIGURATION.md`, `28_RUNTIME-CONFIG/CONFIGURATION-AUDIT.md`
Used By: Core, Engine, Execution, Integrations, Security, Observability, WordPress
Last Updated: 2026-07-08

## Purpose

The Configuration Manager coordinates runtime-configuration requests across the Runtime Configuration Layer.

It routes registration, lookup, validation, resolution, secret-reference, policy-configuration, feature-flag, environment, and audit-history requests to the owning Runtime Configuration component and aggregates configuration-domain status references.

It does not own every configuration record directly, perform platform-wide validation, make governance or security decisions, execute deployments, mutate workflow state, or maintain logging/audit/observability infrastructure.

---

## Responsibilities

- Receive configuration-domain coordination requests.
- Route registry requests to `CONFIGURATION-REGISTRY.md`.
- Route environment-overlay requests to `ENVIRONMENTS.md`.
- Route runtime-resolution requests to `RUNTIME-CONFIGURATION.md`.
- Route feature-flag requests to `FEATURE-FLAGS.md`.
- Route policy-configuration requests to `POLICY-CONFIGURATION.md`.
- Route secret-reference requests to `SECRETS-MANAGER.md`.
- Route configuration-domain validation requests to `CONFIGURATION-VALIDATOR.md`.
- Route configuration-history requests to `CONFIGURATION-AUDIT.md`.
- Aggregate configuration-domain status and evidence references for callers.

---

## Boundary

`CONFIGURATION-MANAGER.md` owns configuration coordination only.

It does not own:

- configuration item catalog records (`CONFIGURATION-REGISTRY.md`),
- environment profile records (`ENVIRONMENTS.md`),
- resolved active runtime bundles (`RUNTIME-CONFIGURATION.md`),
- feature flag records (`FEATURE-FLAGS.md`),
- policy configuration records (`POLICY-CONFIGURATION.md`),
- secret lifecycle records (`SECRETS-MANAGER.md`),
- configuration-domain validation conclusions (`CONFIGURATION-VALIDATOR.md`),
- configuration history records (`CONFIGURATION-AUDIT.md`),
- or non-configuration domain authority.

---

## Rules

1. Configuration Manager must route requests to the owning Runtime Configuration component.
2. Configuration Manager may aggregate status and evidence references only.
3. Configuration Manager must not replace governance, security, execution, validation, storage, or observability owners.
