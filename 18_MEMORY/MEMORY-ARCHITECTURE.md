# SquirrelForge Memory Architecture

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Memory Architecture defines how SquirrelForge captures, organizes, retrieves, and maintains knowledge across tasks, workflows, agents, and projects.

## Objectives

- Preserve important information.
- Support consistent decision-making.
- Reduce repeated work.
- Improve future planning.
- Enable long-term learning.
- Maintain project continuity.

## Memory Layers

| Layer | Purpose |
|---|---|
| Working Memory | Active task context |
| Episodic Memory | Completed task history |
| Semantic Memory | Reusable knowledge and standards |
| Project Memory | Project-specific decisions |
| Knowledge Manager | Validated reusable knowledge |
| Memory Index | Fast discovery across memory |

## Memory Flow

```text
User Request
      │
      ▼
Working Memory
      │
      ▼
Execution
      │
      ▼
Reflection Engine
      │
      ▼
Knowledge Manager
      │
      ▼
Semantic / Episodic / Project Memory
      │
      ▼
Memory Index
```

## Memory Principles

- Store only useful information.
- Preserve validation history.
- Separate temporary and permanent memory.
- Prefer reusable knowledge over duplicated information.
- Archive rather than delete historical records.

## Rule

Information should be stored in the most appropriate memory layer, with validated knowledge promoted to long-term memory and temporary execution context retained only as long as necessary.