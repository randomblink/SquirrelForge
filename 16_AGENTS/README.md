# SquirrelForge Agents

Version: 1.0.0
Status: Stable
Owner: Agent Maintainers
Depends On: Engine, Reasoning, Interfaces
Used By: Coordination and Execution
Last Updated: 2026-07-01

## Purpose
Defines specialized roles for architecture, planning, implementation, review, security, performance, documentation, orchestration, and release.

## Components
The `AGENT-*.md` role specifications in this directory.

## Execution Order
Architect → Planner → Developer → Reviewer → Security/Performance → Documentation → Release.

## Dependencies
Agent API, workflows, task state, rules, tools, and permissions.

## Rules
One owner per task; explicit validated handoffs; agents may not exceed role permissions.

## Diagram
```text
Architect → Planner → Developer → Reviewers → Documentation → Release
```
