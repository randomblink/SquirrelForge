# SquirrelForge Engine

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `00_OVERVIEW`, `01_RULES`, `21_CONFIGURATION`
Used By: All operational layers
Last Updated: 2026-07-01

## Purpose
Loads projects, interprets goals, selects workflows, plans and routes tasks, tracks state, and validates output.

## Components
Project Loader, Goal Planner, Task Decomposer, Dependency Analyzer, Execution Planner, Workflow Selector, Task Router, Context Manager, State Manager, Validation, and Output Rules.

## Request Execution Order
Goal Planner → Task Decomposer & Dependency Analyzer → Execution Planner → Workflow Selector & Task Router → Execution → Validation → Output.

## Dependencies
Rules, configuration, workflow and interface catalogs, memory, and reasoning.

## Rules
The engine owns lifecycle state; components exchange documented records rather than hidden state.

## Diagram
```text
Load → Plan → Reason → Route → Execute → Validate → Output
```
