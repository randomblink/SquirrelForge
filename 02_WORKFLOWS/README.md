# SquirrelForge Workflows

Version: 1.0.0
Status: Stable
Owner: Workflow Maintainers
Depends On: `01_RULES`, `14_ENGINE`
Used By: Agents and Execution
Last Updated: 2026-07-01

## Purpose
Defines repeatable procedures for development, review, testing, optimization, and release.

## Components
Feature, bug-fix, plugin, theme, code-review, security, accessibility, performance, testing, and release workflows.

## Execution Order
Select primary workflow → load supporting workflows → execute phases → validate → report.

## Dependencies
Rules, engine routing, agents, checklists, and testing.

## Rules
Use one primary workflow. Supporting workflows cannot bypass primary acceptance criteria.

## Diagram
```text
Request → Selector → Primary Workflow ─┬→ Supporting Workflow
                                      └→ Validation → Result
```
