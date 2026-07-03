# SquirrelForge Memory Manager

## Purpose

The Memory Manager governs how SquirrelForge stores, organizes, recalls, consolidates, and retires memory across sessions, workflows, and long-term operation, providing a structured cognitive memory architecture for intelligent behavior.

---

## Responsibilities

- Manage working memory.
- Maintain session memory.
- Consolidate long-term memory.
- Support episodic memory.
- Maintain semantic memory.
- Control memory retention.
- Record memory activity.
- Govern memory lifecycle.

---

## Memory Process

1. Receive memory request.
2. Identify memory type.
3. Verify authorization.
4. Store or retrieve memory.
5. Consolidate memory if required.
6. Apply retention policy.
7. Record memory activity.
8. Return requested memory.

---

## Memory Types

| Memory Type | Description |
|---|---|
| Working Memory | Active information used during execution |
| Session Memory | Information retained during a user session |
| Episodic Memory | Historical events and experiences |
| Semantic Memory | General knowledge and concepts |
| Procedural Memory | Learned processes and workflows |
| Long-Term Memory | Persistent retained knowledge |

---

## Memory Lifecycle

| Stage | Description |
|---|---|
| Created | Newly stored |
| Active | Available for recall |
| Consolidated | Merged into long-term knowledge |
| Archived | Retained for historical reference |
| Expired | Past retention policy |
| Removed | Deleted according to governance policy |

---

## Memory Record

| Field | Description |
|---|---|
| Memory ID | Unique identifier |
| Memory Type | Memory classification |
| Owner | Responsible workflow or component |
| Retention Policy | Applicable retention rule |
| Status | Current lifecycle state |
| Created | Initial storage time |
| Last Accessed | Most recent recall |

---

## Memory Governance

- Working memory is temporary.
- Session memory expires at session completion unless promoted.
- Long-term memory requires validation.
- Memory retention follows policy.
- Memory recall respects authorization.
- Memory changes are auditable.

---

## Rule

Every memory retained by SquirrelForge must have a defined memory type, lifecycle state, retention policy, and governance record before it may be recalled or used for reasoning.