# SquirrelForge Memory

Version: 1.0.0
Status: Stable
Owner: Memory Maintainers
Depends On: Memory API, Retention Policy
Used By: Engine, Reasoning, Agents
Last Updated: 2026-07-01

## Purpose
Stores and retrieves active context, execution history, reusable knowledge, and project decisions.

## Components
Working, Episodic, Semantic, and Project Memory; Knowledge Manager; Memory Index; Retention.

## Execution Order
Retrieve → use → validate outcome → classify → store → index → retain/archive.

## Dependencies
Memory API, project identity, validation records, and governance.

## Rules
Store the minimum useful data; preserve provenance; never promote unvalidated claims to semantic memory.

## Diagram
```text
Index → Retrieve → Working Memory → Outcome → Knowledge Manager → Long-term Memory
```
