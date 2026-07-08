# SquirrelForge Runtime Configuration Layer

Version: 1.0.0
Status: Stable
Owner: Runtime Configuration Maintainers
Depends On: `14_ENGINE`, `23_GOVERNANCE`, `24_SECURITY`, `27_OBSERVABILITY`, `37_STORAGE`
Used By: Core, Engine, Agents, Execution, Integrations, Security, Observability, WordPress
Last Updated: 2026-07-08

## Purpose

This directory defines how SquirrelForge registers, resolves, validates, applies, protects, versions, and records runtime configuration.

The Runtime Configuration Layer owns runtime configuration records, configuration resolution, environment overlays, feature-flag configuration, policy-configuration references, configuration-domain validation, secret lifecycle records, and configuration-domain history.

It does not own general governance, security decisions, platform validation, deployment authority, execution state, recovery execution, storage infrastructure, audit infrastructure, or observability infrastructure.

---

## Component Roster

| Component | Responsibility |
|---|---|
| `CONFIGURATION-MANAGER.md` | Coordinates runtime-configuration requests, resolution, and status references. |
| `CONFIGURATION-REGISTRY.md` | Maintains configuration item catalog records, ownership, scope, defaults, and lifecycle metadata. |
| `ENVIRONMENTS.md` | Maintains environment profiles, overlays, inheritance rules, and environment-specific configuration references. |
| `RUNTIME-CONFIGURATION.md` | Resolves validated active configuration bundles for running components. |
| `FEATURE-FLAGS.md` | Maintains feature-flag configuration records, targeting references, rollout settings, and flag states. |
| `POLICY-CONFIGURATION.md` | Maintains configurable policy values and policy-reference records used by policy owners. |
| `SECRETS-MANAGER.md` | Maintains secret lifecycle, secret metadata, secret references, rotation status, and revocation status. |
| `CONFIGURATION-VALIDATOR.md` | Validates configuration structure, schema, references, dependencies, and constraints. |
| `CONFIGURATION-AUDIT.md` | Maintains configuration-domain change history and audit evidence references. |

The authoritative component roster must match the 9 component files that actually exist in `28_RUNTIME-CONFIG`.

---

## Layer Boundary

`28_RUNTIME-CONFIG` owns:

- runtime configuration records,
- configuration registry metadata,
- environment overlays and inheritance references,
- active runtime configuration resolution,
- feature-flag configuration,
- configurable policy value references,
- configuration-domain validation records,
- secret lifecycle and secret-reference metadata,
- configuration change history,
- and configuration-domain status/evidence references.

`28_RUNTIME-CONFIG` does not own:

- general policy evaluation or platform governance decisions (`23_GOVERNANCE/POLICY-ENGINE.md`),
- security-domain policy, authentication, runtime authorization, threat handling, or incident response (`24_SECURITY`),
- cryptographic operations or encryption decisions (`24_SECURITY/ENCRYPTION-MANAGER.md`),
- platform-wide validation or task-completion validation (`14_ENGINE/VALIDATION.md`),
- deployment, release approval, execution, recovery, rollback, or workflow state (`20_EXECUTION`, `17_COORDINATION`, and `14_ENGINE/STATE-MANAGER.md`),
- logs, metrics, traces, dashboards, alerts, audit infrastructure, or observability pipelines (`27_OBSERVABILITY`),
- or storage infrastructure and persistence engines (`37_STORAGE`).

---

## Rule

No component may use runtime configuration, feature flags, policy configuration values, environment overlays, or secret references that have not been registered, validated for configuration-domain constraints, and resolved through this layer.
