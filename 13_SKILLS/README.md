# SquirrelForge Skills

Version: 1.0.0
Status: Stable
Owner: Skill Maintainers
Depends On: Rules, Workflows, Templates
Used By: Agents and Workflow Selector
Last Updated: 2026-07-01

## Purpose

Defines reusable capabilities for planning, implementation, review, testing, optimization, documentation, and deployment.

## Components

The skill specifications in this directory and `SKILL-CATALOG.md`.

## Execution Order

Select skill → verify inputs and permissions → execute linked workflow → validate success criteria → report.

## Dependencies

Rules, the linked workflow, required templates, tools, and project context.

## Rules

A skill cannot bypass its workflow, permissions, or validation criteria. Catalog entries must resolve to an existing specification.

## Diagram

```text
Task → Skill Catalog → Skill → Workflow → Validated Output
```
