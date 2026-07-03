# SquirrelForge Testing

Version: 1.0.0
Status: Stable
Owner: Test Maintainers
Depends On: Requirements, Interfaces, Execution
Used By: Validation and Quality Gates
Last Updated: 2026-07-01

## Purpose
Defines test planning, test levels, regression coverage, smoke checks, and reporting.

## Components
Test Planner, Unit, Integration, System, Smoke, Regression, and Test Reporting.

## Execution Order
Plan → unit → integration → system → smoke → regression → report.

## Dependencies
Acceptance criteria, stable environments, fixtures, interfaces, and execution logs.

## Rules
Tests must be repeatable, isolated where appropriate, mapped to requirements, and reported with evidence.

## Diagram
```text
Requirements → Plan → Unit → Integration → System → Smoke/Regression → Report
```
