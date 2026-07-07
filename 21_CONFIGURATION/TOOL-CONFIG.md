# SquirrelForge Tool Configuration

Version: 1.0.0
Status: Stable
Owner: Configuration Maintainers
Depends On: `21_CONFIGURATION/PERMISSIONS.md`, `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`
Used By: `34_AIDRIVER/TOOL-SELECTOR.md`, `20_EXECUTION/ACTION-DISPATCHER.md`, `27_OBSERVABILITY/HEALTH-REPORTER.md`
Last Updated: 2026-07-06

## Purpose

Tool Config owns declarative tool configuration and registration metadata — what a tool is, what it supports, and what it requires — without deciding retry policy, side-effect classification, permissions, health, availability, or which tool executes a given action.

Registering a tool here is a configuration prerequisite for it to be usable at all, but registration is not itself health, permission, or availability. Retry policy is owned by `17_COORDINATION/FAILURE-RECOVERY.md`, within the bounded-retry default `21_CONFIGURATION/DEFAULTS.md` sets. Side-effect classification and permission decisions are owned by `21_CONFIGURATION/PERMISSIONS.md`. Tool health monitoring is owned by `27_OBSERVABILITY/HEALTH-REPORTER.md`. Which tool executes a given action is selected by `34_AIDRIVER/TOOL-SELECTOR.md`, and the selected tool is actually executed by `20_EXECUTION/ACTION-DISPATCHER.md`. Secrets are stored and referenced through `28_RUNTIME-CONFIG/SECRETS-MANAGER.md`, never held directly in this configuration.

---

## Responsibilities

Tool Config must:

- register a tool identifier,
- record the provider or adapter reference,
- declare supported actions,
- reference the input schema,
- reference the output schema,
- configure the timeout value,
- reference the required permission (owned by `21_CONFIGURATION/PERMISSIONS.md`),
- reference the side-effect classification (owned by `21_CONFIGURATION/PERMISSIONS.md`),
- reference environment or endpoint configuration,
- and hold tool-specific configuration parameters, referencing secrets from `28_RUNTIME-CONFIG/SECRETS-MANAGER.md` rather than storing them directly.

It must not:

- define retry policy,
- classify side effects authoritatively,
- grant or deny permissions,
- perform tool health monitoring,
- determine runtime availability,
- select which tool should execute an action,
- execute tools,
- or store secrets directly.

---

## Tool Config Record

| Field | Description | Owner / Reference |
|---|---|---|
| Tool ID | Unique tool identifier. | Tool Config |
| Provider / Adapter Reference | Provider or adapter implementing the tool. | Tool Config |
| Supported Actions | Declared actions the tool can perform. | Tool Config |
| Input Schema Reference | Reference to the input schema. | Tool Config |
| Output Schema Reference | Reference to the output schema. | Tool Config |
| Timeout | Configured timeout value. | Tool Config |
| Required Permission Reference | Reference to the required permission. | `21_CONFIGURATION/PERMISSIONS.md` |
| Side-Effect Classification Reference | Reference to the tool's side-effect class. | `21_CONFIGURATION/PERMISSIONS.md` |
| Environment / Endpoint Reference | Reference to environment or endpoint configuration. | Tool Config |
| Secret Reference | Reference to a secret held by the Secrets Manager. | `28_RUNTIME-CONFIG/SECRETS-MANAGER.md` |

---

## Registration and Availability

Registration here is a configuration prerequisite: only a registered tool may be considered for selection. Registration is not itself health or availability.

Health comes from `27_OBSERVABILITY/HEALTH-REPORTER.md`. Runtime availability is determined by `34_AIDRIVER/TOOL-SELECTOR.md` and `20_EXECUTION/ACTION-DISPATCHER.md`, combining this configuration, `21_CONFIGURATION/PERMISSIONS.md`'s decision, and the Health Reporter's current assessment. A registered tool that is unhealthy, unpermitted, or otherwise unavailable is not usable — but that determination is made by the runtime or execution authority selecting it, not fixed as a property of this configuration.

---

## Permission Boundary

Tool Config may register and declare tool configuration, and reference the components that own permission, side-effect classification, health, and secrets.

It must not define retry policy, authoritatively classify side effects, grant or deny permissions, monitor tool health, determine runtime availability, select which tool executes an action, execute tools, or store secrets directly.

---

## Domain Rule

Tool configuration applies identically regardless of domain; domain-specific content is carried in the tool's declared actions and schemas, not interpreted by Tool Config itself.

---

## Rule

> A tool is usable only when it is registered here, permitted by `21_CONFIGURATION/PERMISSIONS.md`, and healthy per `27_OBSERVABILITY/HEALTH-REPORTER.md` — as determined by the runtime or execution authority selecting it, not by this configuration alone.
