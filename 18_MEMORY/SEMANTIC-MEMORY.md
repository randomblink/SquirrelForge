# SquirrelForge Semantic Memory

Version: 1.0.0
Status: Stable
Owner: Memory Maintainers
Depends On: `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, `14_ENGINE/VALIDATION.md`, `18_MEMORY/MEMORY-INDEX.md`, `18_MEMORY/MEMORY-RETRIEVAL.md`
Used By: Memory Manager, Agents, Reasoning
Last Updated: 2026-07-05

## Purpose

Semantic Memory stores durable representations of already-approved, generalized knowledge — reusable standards, patterns, architectural approaches, workflows, templates, best practices, and solutions — that is independent of any single task or project.

Semantic Memory stores; it does not validate, classify, or promote candidate knowledge itself, and it does not intake candidate material directly from `19_REASONING/REFLECTION-ENGINE.md` or `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` as an internal process. Validation happens in `14_ENGINE/VALIDATION.md`; approval and classification into a knowledge category happen in `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`. Semantic Memory receives and stores the finished, already-approved record.

---

## Responsibilities

Semantic Memory must:

- store durable representations of already-approved, generalized knowledge,
- preserve the category, source, and validation reference for each record rather than re-deciding them,
- record related workflows and review history,
- index each record through `18_MEMORY/MEMORY-INDEX.md`,
- and make approved knowledge available for retrieval through `18_MEMORY/MEMORY-RETRIEVAL.md`.

---

## Knowledge Categories

| Category | Description |
|---|---|
| Standards | Coding, documentation, and project standards |
| Patterns | Reusable implementation patterns |
| Architecture | Proven architectural approaches |
| Workflows | Validated workflow improvements |
| Templates | Reusable document and project templates |
| Best Practices | General recommendations |
| Solutions | Validated fixes for recurring problems |

These categories mirror `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`'s classification; Semantic Memory stores a record under the category already assigned there rather than classifying it independently.

---

## Semantic Memory Record

| Field | Description | Authoritative Owner |
|---|---|---|
| Record ID | Unique identifier. | Semantic Memory |
| Category | Knowledge classification. | `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` |
| Title | Short descriptive title. | Semantic Memory |
| Description | Detailed explanation. | Semantic Memory |
| Source Reference | Origin of the knowledge. | `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` |
| Validation Reference | Reference to the approval or validation record. | `14_ENGINE/VALIDATION.md` / `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` |
| Related Workflows | Associated workflows. | Semantic Memory |
| Last Reviewed | Most recent review date. | Semantic Memory |

Only Record ID, Title, Description, Related Workflows, and Last Reviewed are Semantic Memory's own authoritative content; Category, Source, and Validation are references to values owned elsewhere.

---

## Storage Process

1. Receive an already-approved knowledge record from `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`.
2. Store the record with its category, source, and validation references intact.
3. Index the record through `18_MEMORY/MEMORY-INDEX.md`.
4. Make it available for retrieval through `18_MEMORY/MEMORY-RETRIEVAL.md`.
5. Update Last Reviewed when the record is periodically reviewed.

---

## Permission Boundary

Semantic Memory may store, index, and serve already-approved knowledge records for retrieval, and may update its own review metadata.

It must not validate, classify, or promote candidate knowledge itself (owned by `14_ENGINE/VALIDATION.md` and `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`), and it does not intake candidate material directly from the Reflection Engine — approval happens upstream, in `25_KNOWLEDGE`, before a record reaches Semantic Memory.

---

## Domain Rule

Semantic storage mechanics apply identically regardless of domain; domain-specific content is carried in the stored record, not interpreted by Semantic Memory itself.

---

## Rule

> Semantic Memory stores only already-approved, generalized knowledge; it does not validate, classify, or promote candidate material itself. Project-specific information belongs in Project Memory, and knowledge approval remains `25_KNOWLEDGE`'s authority.
