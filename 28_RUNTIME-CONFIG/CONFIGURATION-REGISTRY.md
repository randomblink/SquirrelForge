# SquirrelForge Configuration Registry

Version: 1.0.0
Status: Stable
Owner: Runtime Configuration Maintainers
Depends On: `28_RUNTIME-CONFIG/CONFIGURATION-AUDIT.md`, `37_STORAGE`
Used By: `28_RUNTIME-CONFIG/CONFIGURATION-MANAGER.md`, `28_RUNTIME-CONFIG/RUNTIME-CONFIGURATION.md`, `28_RUNTIME-CONFIG/CONFIGURATION-VALIDATOR.md`
Last Updated: 2026-07-08

## Purpose

The Configuration Registry owns the catalog of configuration items used by SquirrelForge.

It records configuration identifiers, names, ownership, scope, data type, default references, lifecycle status, version references, and registry metadata.

It does not own environment overlays, active runtime resolution, feature-flag evaluation, policy evaluation, secret values, validation authority outside configuration-domain constraints, storage infrastructure, or audit infrastructure.

---

## Responsibilities

- Register configuration item records.
- Assign stable configuration identifiers.
- Record owner, scope, type, default, version, and lifecycle metadata.
- Expose registry metadata to Runtime Configuration components.
- Preserve registry-change references through `CONFIGURATION-AUDIT.md`.

---

## Registry States

| State | Meaning |
|---|---|
| `Draft` | Configuration item metadata is incomplete or not approved for use. |
| `Registered` | Configuration item exists in the catalog. |
| `Active` | Configuration item may be resolved by runtime configuration. |
| `Deprecated` | Configuration item remains traceable but is scheduled for removal. |
| `Archived` | Configuration item is retained for history only. |

These are registry states only. They are not environment, runtime, validation, deployment, or workflow states.

---

## Rules

1. Every runtime configuration item must have one registry record.
2. Registry records must identify the owning component and scope.
3. Registry changes must create configuration-domain history references.
