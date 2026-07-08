# SquirrelForge Agent Optimizer

Version: 1.0.0
Status: Stable
Owner: Optimization Maintainers
Depends On: agent execution evidence, 18_MEMORY references, 19_REASONING outputs, 27_OBSERVABILITY, 30_LEARNING
Used By: OPTIMIZATION-VALIDATOR.md, OPTIMIZATION-GOVERNANCE.md, OPTIMIZATION-MANAGER.md
Last Updated: 2026-07-08

## Purpose

The Agent Optimizer owns analysis and proposals for measurable improvement of agent planning, reasoning efficiency, memory use, tool use, collaboration, and execution participation.

## Responsibilities

- Consume authoritative agent, reasoning, memory, tool, collaboration, workflow, and observability references.
- Identify agent-performance inefficiencies and improvement opportunities.
- Produce agent optimization findings, measurable targets, and behavior-change proposals.
- Preserve evidence and explainability references for validation and governance review.

## Boundary

The Agent Optimizer does not make reasoning decisions, change agent behavior directly, own agent definitions, manage active memory, select tools at runtime, dispatch actions, alter workflow execution, perform platform validation, enforce governance, execute rollback or recovery, collect telemetry, or own audit infrastructure.

## Rule

Agent optimization analysis may recommend changes to owned agent systems, but only authoritative agent, reasoning, memory, execution, validation, and governance owners may approve or perform their respective changes.