# SquirrelForge Rules

Version: 1.0.0
Status: Stable
Owner: SquirrelForge Maintainers
Depends On: `00_OVERVIEW`
Used By: Engine, Workflows, Agents, Validation
Last Updated: 2026-07-01

## Purpose

Defines mandatory behavior and project standards.

## Components

- `AGENT-BEHAVIOR.md`: agent operating rules.
- `WORDPRESS-RULES.md`: WordPress implementation baseline.

## Execution Order

Agent Behavior → WordPress Rules → Project-specific rules → Workflow.

## Dependencies

System architecture, configuration, and applicable governance policy.

## Rules

Mandatory rules override recommendations. Conflicts must be surfaced and resolved before execution.

## Diagram

```text
Request → Global Rules → Project Rules → Workflow Rules → Approved Action
```
