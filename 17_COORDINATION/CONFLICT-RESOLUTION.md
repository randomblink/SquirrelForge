# SquirrelForge Conflict Resolution

Version: 1.0.0
Status: Stable
Owner: Coordination Maintainers
Depends On: `16_AGENTS/AGENT-COLLABORATION.md`, `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, `14_ENGINE/STATE-MANAGER.md`
Used By: Coordination, Agents
Last Updated: 2026-07-05

## Purpose

Conflict Resolution detects, classifies, and resolves disagreements between agents against the escalation criteria a collaboration structure has already defined, while preserving project quality, correctness, and forward progress.

Conflict Resolution resolves against criteria defined elsewhere: `16_AGENTS/AGENT-COLLABORATION.md` defines what counts as a collaboration conflict requiring escalation before execution begins. This document does not invent escalation criteria case by case; it applies them. It does not itself own project rules, security requirements, validation rules, or any other category it arbitrates between — it determines which already-owned rule takes precedence when two apply in conflict, records the decision, and forwards reusable resolutions to `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`.

---

## Responsibilities

Conflict Resolution must:

- detect conflicts between agents,
- classify the type of conflict,
- escalate according to the escalation criteria `16_AGENTS/AGENT-COLLABORATION.md` defines for the collaboration in question,
- select a resolution using the Resolution Priority order below,
- record the final decision and its source,
- clear the block so the paused component (queue, handoff, or execution) can resume, without itself re-executing work,
- prevent repeated conflicts by recognizing recurrence against prior Conflict Records,
- and forward validated, reusable resolutions to `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`.

---

## Conflict Types

| Type | Example |
|---|---|
| Technical | Different implementation approaches |
| Security | Security concern blocks implementation |
| Performance | Optimization conflicts with readability |
| Documentation | Documentation incomplete or inconsistent |
| Validation | Validation failure prevents completion |
| Workflow | Multiple workflows recommend different actions |

---

## Resolution Process

1. Detect the conflict.
2. Identify the affected task.
3. Gather supporting evidence.
4. Determine the applicable project rules.
5. Evaluate available solutions against the Resolution Priority order.
6. Select the preferred resolution.
7. Record the decision and its source.
8. Clear the block and hand control back to the paused component so execution can resume.

---

## Resolution Priority

When applicable rules conflict, higher entries take precedence:

1. Project Rules (`01_RULES`)
2. Security Requirements (`16_AGENTS/AGENT-SECURITY.md`, `24_SECURITY`)
3. Validation Rules (`14_ENGINE/VALIDATION.md`)
4. Architecture Decisions (`16_AGENTS/AGENT-ARCHITECT.md`)
5. Active Workflow (`14_ENGINE/WORKFLOW-SELECTOR.md`)
6. Coding Standards (`01_RULES`)
7. Performance Considerations (`16_AGENTS/AGENT-PERFORMANCE.md`)
8. Documentation Standards (`16_AGENTS/AGENT-DOCUMENTATION.md`)

This ordering decides precedence between already-owned rules; it does not grant Conflict Resolution authority over any of them independently.

---

## Conflict Record

| Field | Description |
|---|---|
| Conflict ID | Unique identifier. |
| Task ID | Related task. |
| Agents Involved | Participating agents. |
| Conflict Type | Classification from the table above. |
| Resolution | Final decision. |
| Decision Source | Rule or authority used, per the Resolution Priority order. |
| Status | Open / Resolved / Escalated. |

---

## Permission Boundary

Conflict Resolution may detect, classify, resolve, and record conflicts, and clear the block that paused a coordination component.

It must not define escalation criteria itself (owned by `16_AGENTS/AGENT-COLLABORATION.md`), redefine any rule category it arbitrates between (each remains owned by its respective layer), or resume execution directly — it clears the block and hands control back to the component that was paused.

---

## Domain Rule

Conflict classification and resolution priority apply identically regardless of domain; domain-specific rule content is supplied by the owning layer, not redefined here.

---

## Rule

> Conflicts must be resolved using documented project rules, in Resolution Priority order, whenever possible. Every resolved conflict must be recorded, and forwarded to `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` when it provides reusable guidance.
