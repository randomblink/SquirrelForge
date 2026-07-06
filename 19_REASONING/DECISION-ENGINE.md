# SquirrelForge Decision Engine

Version: 1.0.0
Status: Stable
Owner: Reasoning Maintainers
Depends On: `14_ENGINE/WORKFLOW-SELECTOR.md`, `19_REASONING/RULE-EVALUATOR.md`, `19_REASONING/RISK-ASSESSOR.md`, `19_REASONING/TRADEOFF-ANALYZER.md`, `19_REASONING/CONFIDENCE-SCORER.md`, `18_MEMORY/MEMORY-RETRIEVAL.md`, `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`
Used By: Reasoning, Strategy Planner
Last Updated: 2026-07-06

## Purpose

The Decision Engine forms a decision by aggregating specialist evaluations, comparing feasible options, and selecting a preferred course of action, producing a traceable Decision Record.

The Decision Engine aggregates; it does not perform the specialist evaluations itself. Rule compliance is owned by `19_REASONING/RULE-EVALUATOR.md`; risk assessment by `19_REASONING/RISK-ASSESSOR.md`; tradeoff comparison by `19_REASONING/TRADEOFF-ANALYZER.md`; confidence scoring by `19_REASONING/CONFIDENCE-SCORER.md`; workflow selection by `14_ENGINE/WORKFLOW-SELECTOR.md`; converting the decision into an executable strategy is `19_REASONING/STRATEGY-PLANNER.md`'s job. The Decision Engine reads historical context through `18_MEMORY/MEMORY-RETRIEVAL.md` and already-approved reusable knowledge through `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, rather than treating "Memory and the Knowledge Manager" as an undifferentiated source.

---

## Responsibilities

The Decision Engine must:

- receive the execution request and the active workflow already selected by `14_ENGINE/WORKFLOW-SELECTOR.md`,
- retrieve relevant historical context through `18_MEMORY/MEMORY-RETRIEVAL.md` and approved reusable knowledge through `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`,
- generate feasible options,
- aggregate the rule-compliance evaluation from `19_REASONING/RULE-EVALUATOR.md`, the risk assessment from `19_REASONING/RISK-ASSESSOR.md`, the tradeoff comparison from `19_REASONING/TRADEOFF-ANALYZER.md`, and the confidence score from `19_REASONING/CONFIDENCE-SCORER.md`,
- select the preferred option based on those aggregated evaluations,
- record the decision, rationale, alternatives considered, supporting evaluation references, and any unresolved limitations in a Decision Record,
- and forward the Decision Record to `19_REASONING/STRATEGY-PLANNER.md`.

---

## Decision Process

1. Receive the execution request and the active workflow already selected by `14_ENGINE/WORKFLOW-SELECTOR.md`.
2. Retrieve relevant historical context from `18_MEMORY/MEMORY-RETRIEVAL.md` and approved reusable knowledge from `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`.
3. Generate feasible options.
4. Request a rule-compliance evaluation from `19_REASONING/RULE-EVALUATOR.md`.
5. Request a risk assessment from `19_REASONING/RISK-ASSESSOR.md`.
6. Request a tradeoff comparison from `19_REASONING/TRADEOFF-ANALYZER.md`.
7. Request a confidence score from `19_REASONING/CONFIDENCE-SCORER.md`.
8. Select the preferred option from the aggregated evaluations.
9. Record the Decision Record.
10. Forward the Decision Record to `19_REASONING/STRATEGY-PLANNER.md`.

---

## Decision Inputs

| Input | Description | Source |
|---|---|---|
| User Goal | Requested outcome. | Request |
| Active Workflow | Already-selected workflow. | `14_ENGINE/WORKFLOW-SELECTOR.md` |
| Historical Context | Relevant prior experience. | `18_MEMORY/MEMORY-RETRIEVAL.md` |
| Approved Knowledge | Validated, reusable patterns. | `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` |
| Constraints | Time, scope, or technical limitations. | Request |

---

## Decision Record

| Field | Description |
|---|---|
| Decision ID | Unique identifier. |
| Selected Option | The chosen course of action. |
| Rationale | Why it was selected. |
| Alternatives Considered | Other viable options and why they were not chosen. |
| Rule Evaluation Reference | Reference to `19_REASONING/RULE-EVALUATOR.md`'s result. |
| Risk Assessment Reference | Reference to `19_REASONING/RISK-ASSESSOR.md`'s result. |
| Tradeoff Reference | Reference to `19_REASONING/TRADEOFF-ANALYZER.md`'s result. |
| Confidence Reference | Reference to `19_REASONING/CONFIDENCE-SCORER.md`'s result. |
| Unresolved Limitations | Known gaps, uncertainties, or unavailable evaluations. |

---

## Decision Principles

- Prefer the option with the most favorable aggregated evaluation across rule compliance, risk, tradeoff, and confidence.
- Minimize unnecessary complexity.
- Preserve consistency with prior decisions recorded in Memory.
- Record every significant decision, including its unresolved limitations.

---

## Permission Boundary

The Decision Engine may generate options, request specialist evaluations, select a preferred option, and record the Decision Record.

It must not independently perform rule evaluation (owned by `19_REASONING/RULE-EVALUATOR.md`), risk analysis (owned by `19_REASONING/RISK-ASSESSOR.md`), tradeoff analysis (owned by `19_REASONING/TRADEOFF-ANALYZER.md`), confidence scoring (owned by `19_REASONING/CONFIDENCE-SCORER.md`), or workflow selection (owned by `14_ENGINE/WORKFLOW-SELECTOR.md`), and it must not convert the decision into an executable strategy itself (owned by `19_REASONING/STRATEGY-PLANNER.md`).

---

## Domain Rule

Decision aggregation applies identically regardless of domain; domain-specific content is carried in the options and evaluations, not interpreted by the Decision Engine itself.

---

## Rule

> Every significant decision must aggregate a rule-compliance evaluation, a risk assessment, a tradeoff comparison, and a confidence score before an option is selected, and must be recorded as a traceable Decision Record including alternatives considered and unresolved limitations. The Decision Engine selects; it does not perform the specialist evaluations it aggregates.
