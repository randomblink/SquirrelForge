# SquirrelForge Project Memory

Version: 1.0.0
Status: Stable
Owner: Memory Maintainers
Depends On: `16_AGENTS/AGENT-ARCHITECT.md`, `19_REASONING/DECISION-ENGINE.md`, `14_ENGINE/STATE-MANAGER.md`, `14_ENGINE/DEPENDENCY-ANALYZER.md`, `18_MEMORY/MEMORY-INDEX.md`
Used By: Memory Manager, Agents, Reasoning
Last Updated: 2026-07-05

## Purpose

Project Memory stores durable, project-specific reference records and snapshots — architecture, conventions, decisions, milestones, risks, and dependencies already established elsewhere — supporting future maintenance, onboarding, and architectural consistency.

Project Memory stores; it does not validate decisions (owned by `14_ENGINE/VALIDATION.md` and `19_REASONING/DECISION-ENGINE.md`), assess risk (owned by the relevant specialist agent), analyze dependencies (owned by `14_ENGINE/DEPENDENCY-ANALYZER.md`), or determine milestones (owned by `14_ENGINE/STATE-MANAGER.md`). It stores a reference or snapshot of each once already established elsewhere.

---

## Responsibilities

Project Memory must:

- store durable, project-specific reference records: architecture, conventions, decisions, milestones, risks, and dependencies,
- reference each item's already-established source rather than independently validating, assessing, analyzing, or determining it,
- record major implementation changes and historical context,
- preserve provenance and source references for each stored item,
- index the project record through `18_MEMORY/MEMORY-INDEX.md`,
- and make this history available for retrieval to support future maintenance, onboarding, and architectural consistency.

---

## Project Memory Contents

| Item | Description | Authoritative Owner |
|---|---|---|
| Project Name | Name of the project. | Project Memory |
| Architecture | Reference to the overall system design. | `16_AGENTS/AGENT-ARCHITECT.md` |
| Conventions | Naming, structure, and coding conventions specific to this project. | Project Memory |
| Decisions | Reference to important technical decisions. | `19_REASONING/DECISION-ENGINE.md` |
| Milestones | Reference to completed milestones. | `14_ENGINE/STATE-MANAGER.md` |
| Risks | Reference to already-assessed project risks. | Relevant specialist agent (for example `16_AGENTS/AGENT-SECURITY.md` or `16_AGENTS/AGENT-PERFORMANCE.md`) |
| Dependencies | Reference to long-term project dependencies. | `14_ENGINE/DEPENDENCY-ANALYZER.md` |
| Notes | Additional project context. | Project Memory |

Only Project Name, Conventions, and Notes are Project Memory's own authoritative content; every other field is a reference to a value owned elsewhere.

---

## Recording Process

1. Capture project information.
2. Reference already-validated decisions rather than validating them here.
3. Reference completed milestones from `14_ENGINE/STATE-MANAGER.md`.
4. Update architectural history with a reference to the Architect's decisions.
5. Reference dependency status from `14_ENGINE/DEPENDENCY-ANALYZER.md`.
6. Index the project record through `18_MEMORY/MEMORY-INDEX.md`.
7. Preserve for future reference.

---

## Usage

Project Memory supports:

- Future feature development
- Bug investigation
- Architectural consistency
- Project onboarding
- Maintenance planning
- Long-term evolution

These are downstream consumer uses of the stored record; they are not additional responsibilities Project Memory performs itself.

---

## Permission Boundary

Project Memory may store, reference, and index durable project-specific records.

It must not validate decisions (owned by `14_ENGINE/VALIDATION.md` and `19_REASONING/DECISION-ENGINE.md`), assess risks (owned by the relevant specialist agent), analyze dependencies (owned by `14_ENGINE/DEPENDENCY-ANALYZER.md`), or determine milestones (owned by `14_ENGINE/STATE-MANAGER.md`).

---

## Domain Rule

Project Memory mechanics apply identically regardless of domain; domain-specific content is carried in the stored record, not interpreted by Project Memory itself.

---

## Rule

> Project Memory stores only information specific to the current project, as durable references to decisions, milestones, risks, and dependencies already established elsewhere. General knowledge, reusable patterns, and best practices belong in Semantic Memory; Project Memory does not itself validate, assess, analyze, or determine any of the values it stores.
