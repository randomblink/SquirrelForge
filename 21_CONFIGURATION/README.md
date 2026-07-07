# SquirrelForge Configuration Layer

Version: 1.0.0
Status: Stable
Owner: Configuration Maintainers
Depends On: `23_GOVERNANCE`, `01_RULES`, `24_SECURITY`, `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`
Used By: `14_ENGINE`, `20_EXECUTION`, `19_REASONING`, `34_AIDRIVER`
Last Updated: 2026-07-06

## Purpose

The Configuration Layer separates runtime policy and project settings from component behavior — declaring baseline defaults, project-specific settings, model routing configuration, declarative tool registration metadata, and permission policy that other layers read and enforce.

Configuration declares values; it does not enforce them at runtime. The components that consume a declared value — validation, recovery, health monitoring, tool selection, execution — apply it in context; Configuration does not perform their job itself.

---

## Layer Boundary

`21_CONFIGURATION` owns:

- baseline default policy values (`DEFAULTS.md`),
- project-specific identity, settings, and permitted overrides (`PROJECT-SETTINGS.md`),
- model capability requirements and routing criteria (`MODEL-CONFIG.md`),
- declarative tool registration metadata (`TOOL-CONFIG.md`),
- and permission policy definition (`PERMISSIONS.md`).

`21_CONFIGURATION` does not own:

- retry or recovery strategy (owned by `17_COORDINATION/FAILURE-RECOVERY.md`),
- output validation (owned by `14_ENGINE/VALIDATION.md`),
- tool health monitoring (owned by `27_OBSERVABILITY/HEALTH-REPORTER.md`),
- tool selection or execution (owned by `34_AIDRIVER/TOOL-SELECTOR.md` and `20_EXECUTION/ACTION-DISPATCHER.md`),
- secrets storage (owned by `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`; Configuration holds references only),
- and authoring standards, workflow definitions, or release policy (owned by `01_RULES`, `14_ENGINE/WORKFLOW-SELECTOR.md`, and `23_GOVERNANCE` respectively — Project Settings only references these).

Those responsibilities remain in their respective layers; Configuration declares the values they read.

---

## Components

| Component | Responsibility |
|---|---|
| `DEFAULTS.md` | Baseline default policy values that other components enforce. |
| `PROJECT-SETTINGS.md` | Project identity, root, technology profile, and permitted overrides. |
| `MODEL-CONFIG.md` | Model capability requirements, routing criteria, and constraints. |
| `TOOL-CONFIG.md` | Declarative tool registration metadata. |
| `PERMISSIONS.md` | Permission policy: least privilege, scoped by actor, action, resource, and duration. |

The authoritative component roster must match files that actually exist in this directory.

---

## Execution Order

```text
Defaults (baseline policy)
   ↓
Project Settings (project-specific overrides, each stating its source)
   ↓
Model Config + Tool Config (capability and registration metadata)
   ↓
Permissions (policy evaluated against the above)
   ↓
Validated, immutable runtime configuration snapshot
```

---

## Dependencies

Configuration depends on:

- `23_GOVERNANCE` for the policy authority Project Settings' release policy and Permissions align with,
- `01_RULES` for the standards Project Settings references,
- `24_SECURITY` for the security policy Permissions aligns with,
- and `28_RUNTIME-CONFIG/SECRETS-MANAGER.md` for the secrets Tool Config references rather than stores.

---

## State Rule

Configuration declares values; it does not persist runtime task or lifecycle state (owned by `14_ENGINE/STATE-MANAGER.md`), and it does not itself decide validation, retry, health, or tool-selection outcomes — those remain owned by their respective runtime authorities, which read Configuration's declared values.

---

## Domain Rule

Configuration mechanics apply identically regardless of domain; domain-specific settings are expressed through Project Settings, not a separate configuration system.

---

## Diagram

```text
Defaults + Project Overrides + Model/Tool Config + Environment → Permissions Evaluation → Validated Runtime Configuration
```

---

## Rule

> Precedence is explicit: Defaults, then stated project overrides, then model and tool settings, then permission validation, producing an immutable runtime configuration snapshot. Unknown keys fail validation; secrets are referenced, never stored in documentation or configuration directly.
