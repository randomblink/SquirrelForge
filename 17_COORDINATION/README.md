# SquirrelForge Coordination

Version: 1.0.0
Status: Stable
Owner: Coordination Maintainers
Depends On: Agent API, Engine API
Used By: Agents and Execution
Last Updated: 2026-07-01

## Purpose
Manages task priority, ownership, messages, handoffs, progress, conflicts, and recovery.

## Components
Task Queue, Priority Manager, Message Bus, Handoff Protocol, Progress Tracker, Conflict Resolution, and Failure Recovery.

## Execution Order
Queue → prioritize → assign → communicate → track → hand off or recover → complete.

## Dependencies
Engine state, agent roster, interfaces, and execution events.

## Rules
Every state transition and ownership change must be traceable.

## Diagram
```text
Queue → Assignment → Agent ↔ Message Bus → Handoff → Completion
                         └→ Recovery
```
