# SquirrelForge Configuration

Version: 1.0.0
Status: Stable
Owner: Configuration Maintainers
Depends On: Governance
Used By: Engine and Execution
Last Updated: 2026-07-01

## Purpose
Separates runtime policy and project settings from component behavior.

## Components
Defaults, Project Settings, Model Config, Tool Config, and Permissions.

## Execution Order
Defaults → project overrides → model/tool settings → permission validation → immutable runtime snapshot.

## Dependencies
Environment capabilities and governance policy.

## Rules
Precedence is explicit; unknown keys fail validation; secrets are referenced, never stored in documentation.

## Diagram
```text
Defaults + Project Overrides + Environment → Validated Runtime Configuration
```
