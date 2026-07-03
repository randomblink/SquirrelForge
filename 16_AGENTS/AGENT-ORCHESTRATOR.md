# SquirrelForge Agent Orchestrator

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Agent Orchestrator coordinates all SquirrelForge agents, ensuring work flows through the correct sequence while maintaining state, context, and quality.

## Responsibilities

- Receive the execution plan.
- Assign work to the appropriate agent.
- Manage agent handoffs.
- Monitor progress.
- Resolve workflow routing.
- Track overall execution.
- Report final completion.

## Managed Agents

| Agent | Responsibility |
|---|---|
| Agent Architect | Solution architecture |
| Agent Planner | Execution planning |
| Agent Developer | Implementation |
| Agent Reviewer | Code review |
| Agent Security | Security validation |
| Agent Performance | Performance validation |
| Agent Documentation | Documentation |
| Agent Release | Release readiness |

## Orchestration Process

1. Receive execution plan.
2. Determine the first responsible agent.
3. Assign work.
4. Wait for completion.
5. Validate handoff.
6. Assign the next agent.
7. Repeat until release is complete.
8. Return final status.

## Handoff Sequence

```text
Architect
    ↓
Planner
    ↓
Developer
    ↓
Reviewer
    ↓
Security
    ↓
Performance
    ↓
Documentation
    ↓
Release
```

## Status Model

| Status | Meaning |
|---|---|
| Pending | Waiting to start |
| Assigned | Assigned to an agent |
| Running | Currently executing |
| Waiting | Awaiting another agent |
| Blocked | Cannot continue |
| Complete | Successfully finished |

## Rule

Only one agent owns a task at any given time. Every transfer between agents must be an explicit, validated handoff.