# SquirrelForge Confidence Scorer

Version: 1.0.0
Status: Stable
Owner: Reasoning Maintainers
Depends On: `19_REASONING/DECISION-ENGINE.md`, `19_REASONING/RULE-EVALUATOR.md`, `19_REASONING/RISK-ASSESSOR.md`, `19_REASONING/TRADEOFF-ANALYZER.md`, `14_ENGINE/VALIDATION.md`, `18_MEMORY/MEMORY-RETRIEVAL.md`
Used By: Decision Engine
Last Updated: 2026-07-06

## Purpose

The Confidence Scorer evaluates how reliable a candidate decision is, based on its supporting evidence, assumptions, unresolved limitations, referenced rule, risk, and tradeoff evaluations, and available validation signals, producing a confidence level the Decision Engine incorporates into its Decision Record.

The Confidence Scorer is triggered by a request from `19_REASONING/DECISION-ENGINE.md` during decision formation — it does not depend on a completed explanation as its trigger. The `19_REASONING/EXPLANATION-ENGINE.md` runs later and simply summarizes the confidence reference this component already produced. The Confidence Scorer reads the rule-compliance evaluation from `19_REASONING/RULE-EVALUATOR.md`, the risk assessment from `19_REASONING/RISK-ASSESSOR.md`, the tradeoff comparison from `19_REASONING/TRADEOFF-ANALYZER.md`, and available validation signals from `14_ENGINE/VALIDATION.md`, rather than re-deriving any of them.

---

## Responsibilities

The Confidence Scorer must:

- receive a candidate option and its supporting evidence, assumptions, and unresolved limitations from `19_REASONING/DECISION-ENGINE.md`,
- read the rule-compliance evaluation from `19_REASONING/RULE-EVALUATOR.md`,
- read the risk assessment from `19_REASONING/RISK-ASSESSOR.md`,
- read the tradeoff comparison from `19_REASONING/TRADEOFF-ANALYZER.md`,
- read available validation signals from `14_ENGINE/VALIDATION.md`,
- weigh assumptions and unresolved limitations as factors that reduce confidence,
- calculate a confidence level,
- record the reasoning behind the score in a Confidence Record,
- provide a recommendation when confidence is low,
- and return the confidence assessment to the requesting component.

---

## Confidence Evaluation Process

1. Receive the candidate option, its supporting evidence, assumptions, and unresolved limitations from `19_REASONING/DECISION-ENGINE.md`.
2. Read the rule-compliance evaluation from `19_REASONING/RULE-EVALUATOR.md`.
3. Read the risk assessment from `19_REASONING/RISK-ASSESSOR.md`.
4. Read the tradeoff comparison from `19_REASONING/TRADEOFF-ANALYZER.md`.
5. Read available validation signals from `14_ENGINE/VALIDATION.md`.
6. Weigh assumptions and unresolved limitations.
7. Calculate the confidence level.
8. Record the reasoning behind the score.
9. Return the confidence assessment to the requesting component.

---

## Confidence Factors

| Factor | Description | Source |
|---|---|---|
| Validation Signals | Has the approach been validated, and to what extent? | `14_ENGINE/VALIDATION.md` |
| Rule Compliance | Does it satisfy applicable rules? | `19_REASONING/RULE-EVALUATOR.md` |
| Risk Level | What is the remaining implementation risk? | `19_REASONING/RISK-ASSESSOR.md` |
| Tradeoff Strength | How strongly does the comparison favor this option? | `19_REASONING/TRADEOFF-ANALYZER.md` |
| Supporting Evidence | How much supporting evidence exists? | `19_REASONING/DECISION-ENGINE.md` |
| Assumptions | How many unverified assumptions does the option rely on? | `19_REASONING/DECISION-ENGINE.md` |
| Unresolved Limitations | What known gaps or uncertainties remain? | `19_REASONING/DECISION-ENGINE.md` |
| Knowledge | Has this approach succeeded before? | `18_MEMORY/MEMORY-RETRIEVAL.md` |
| Complexity | Does complexity reduce confidence? | Confidence Scorer's own judgment |

---

## Confidence Levels

| Level | Meaning |
|---|---|
| Very High | Strong evidence, validated, minimal risk |
| High | Well-supported with manageable risk |
| Moderate | Acceptable but requires monitoring |
| Low | Limited evidence or elevated risk |
| Very Low | Significant uncertainty; review recommended |

---

## Confidence Record

| Field | Description |
|---|---|
| Decision ID | Candidate option under evaluation |
| Confidence Level | Very High / High / Moderate / Low / Very Low |
| Supporting Evidence | Summary of evidence |
| Assumptions | Key unverified assumptions relied upon |
| Unresolved Limitations | Known gaps or uncertainties |
| Remaining Risks | Outstanding concerns, referencing the Risk Assessor |
| Recommendations | Suggested follow-up actions |
| Date | Evaluation date |

---

## Permission Boundary

The Confidence Scorer may read referenced evaluations and validation signals, weigh assumptions and limitations, calculate a confidence level, and record its rationale.

It must not independently perform rule evaluation (owned by `19_REASONING/RULE-EVALUATOR.md`), risk assessment (owned by `19_REASONING/RISK-ASSESSOR.md`), or tradeoff comparison (owned by `19_REASONING/TRADEOFF-ANALYZER.md`), confirm validation results itself (owned by `14_ENGINE/VALIDATION.md`), or wait for a completed explanation before running — it is triggered by the Decision Engine during decision formation.

---

## Domain Rule

Confidence factors apply identically regardless of domain; domain-specific evidence is expressed through the existing factors, not a separate domain-specific scoring system.

---

## Rule

> Every significant decision must receive a documented confidence assessment, based on its supporting evidence, assumptions, unresolved limitations, and the already-produced rule, risk, tradeoff, and validation signals, before an option is selected. Confidence scoring runs during decision formation; it does not wait for a completed explanation, and it does not re-derive the evaluations it reads.
