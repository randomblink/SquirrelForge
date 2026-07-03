# SquirrelForge Architecture

## Purpose

SquirrelForge is a layered AI agent architecture for planning, reasoning, executing workflows, observing outcomes, learning from results, and improving future behavior under governance and security controls.

This document provides the top-level view of how the system is organized.

## Core Architecture

SquirrelForge is organized into layered domains:

1. Core
2. Input
3. Validation
4. Reasoning
5. Planning
6. Workflow
7. Execution
8. Tools
9. Knowledge
10. Memory
11. Observability
12. Learning
13. Security
14. Governance
15. Response

## Primary Flow

```text
User Input
   ↓
Input Layer
   ↓
Validation Layer
   ↓
Reasoning Layer
   ↓
Planning Layer
   ↓
Workflow Layer
   ↓
Execution Layer
   ↓
Observation
   ↓
Learning
   ↓
Memory
   ↓
Response
```

## System Rule

No component should act alone.

Every important action must be:

- validated
- reasoned through
- executed through an approved workflow
- observed
- logged
- recoverable
- explainable
- governed

## Key Documents

- `00_CORE/SYSTEM-ORCHESTRATOR.md`
- `27_OBSERVABILITY/OBSERVABILITY-MANAGER.md`
- `30_LEARNING/LEARNING-MANAGER.md`
- `20_EXECUTION/ACTION-DISPATCHER.md`

## Completion Criteria

This file is complete when it explains the whole system at a high level and points readers to the deeper layer documents.

After this, the next step is:

`README.md` at the repository root.
