# SquirrelForge Explanation Engine

Version: 1.0.0
Status: Stable
Owner: Reasoning Maintainers
Depends On: `19_REASONING/DECISION-ENGINE.md`, `19_REASONING/STRATEGY-PLANNER.md`, `19_REASONING/RULE-EVALUATOR.md`, `19_REASONING/RISK-ASSESSOR.md`, `19_REASONING/TRADEOFF-ANALYZER.md`
Used By: Reasoning, Reporting
Last Updated: 2026-07-06

## Purpose

The Explanation Engine produces a clear, traceable explanation of a decision and its resulting strategy, referencing the evaluation records already produced elsewhere in Reasoning, so users and agents understand not only what was chosen but why.

The Explanation Engine explains; it does not re-derive the evaluations it references. It reads the Decision Record from `19_REASONING/DECISION-ENGINE.md` — which already carries references to the Rule Evaluator's, Risk Assessor's, Tradeoff Analyzer's, and Confidence Scorer's results — and the Strategy Record from `19_REASONING/STRATEGY-PLANNER.md`, and summarizes them. It does not independently collect rules, re-assess risk, re-compare tradeoffs, or recompute confidence.

---

## Responsibilities

The Explanation Engine must:

- receive the Decision Record and Strategy Record for the work being explained,
- summarize the rules, risks, tradeoffs, and knowledge already referenced in those records rather than re-deriving them,
- describe the tradeoffs considered and why rejected alternatives were not selected,
- document the execution rationale,
- produce an Explanation Record suitable for audit and review,
- and return the explanation to the requesting component.

---

## Explanation Process

1. Receive the Decision Record from `19_REASONING/DECISION-ENGINE.md` and the Strategy Record from `19_REASONING/STRATEGY-PLANNER.md`.
2. Summarize the referenced rule evaluation, risk assessment, and tradeoff comparison.
3. Summarize the knowledge referenced in the Decision Record.
4. Describe why rejected alternatives were not selected.
5. Generate a clear explanation.
6. Record the Explanation Record.
7. Return the explanation to the requesting component.

---

## Explanation Model

| Field | Description |
|---|---|
| Decision Reference | Reference to the `19_REASONING/DECISION-ENGINE.md` Decision Record being explained. |
| Strategy Reference | Reference to the `19_REASONING/STRATEGY-PLANNER.md` Strategy Record being explained. |
| Goal | Original objective. |
| Rules Applied | Summary referencing the Rule Evaluation Record. |
| Tradeoffs | Summary referencing the Tradeoff Record. |
| Knowledge Used | Summary of relevant memory or patterns referenced in the Decision Record. |
| Reasoning | Why this approach was selected. |
| Expected Outcome | Intended result. |

---

## Explanation Principles

- Be concise.
- Be technically accurate.
- Reference evidence whenever possible.
- Distinguish facts from assumptions.
- Explain why rejected alternatives were not selected.
- Maintain consistency across explanations.

---

## Permission Boundary

The Explanation Engine may summarize, describe, and record an explanation referencing already-produced evaluation records.

It must not independently collect or re-derive rule compliance (owned by `19_REASONING/RULE-EVALUATOR.md`), risk assessment (owned by `19_REASONING/RISK-ASSESSOR.md`), tradeoff comparison (owned by `19_REASONING/TRADEOFF-ANALYZER.md`), or confidence scoring (owned by `19_REASONING/CONFIDENCE-SCORER.md`), and it must not assume a mandatory handoff to a specific downstream component.

---

## Domain Rule

Explanation mechanics apply identically regardless of domain; domain-specific content is carried in the referenced records, not interpreted by the Explanation Engine itself.

---

## Rule

> Every significant decision should have a documented explanation, built from already-produced evaluation records, that allows another developer or agent to understand and reproduce the reasoning behind it. The Explanation Engine explains; it does not re-derive the evaluations it summarizes.
