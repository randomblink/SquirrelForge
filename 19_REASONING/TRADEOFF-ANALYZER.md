# SquirrelForge Tradeoff Analyzer

Version: 1.0.0
Status: Stable
Owner: Reasoning Maintainers
Depends On: `19_REASONING/RISK-ASSESSOR.md`, `19_REASONING/DECISION-MATRIX.md`
Used By: Decision Engine
Last Updated: 2026-07-06

## Purpose

The Tradeoff Analyzer compares competing implementation options across quality, maintainability, performance, security, cost, and project-goal criteria, and produces a documented comparison to inform a pending decision.

The Tradeoff Analyzer compares and recommends; it does not select the final option itself. Selection is owned by `19_REASONING/DECISION-ENGINE.md`, which aggregates this comparison alongside the Rule Evaluator's, Risk Assessor's, and Confidence Scorer's results. It does not assume a mandatory handoff to `19_REASONING/STRATEGY-PLANNER.md` — the Decision Engine forwards its own Decision Record there once an option is selected. It does not perform risk assessment itself (owned by `19_REASONING/RISK-ASSESSOR.md`); it reads that assessment as an input. Quantitative scoring across weighted criteria is `19_REASONING/DECISION-MATRIX.md`'s job; the Tradeoff Analyzer produces the qualitative comparison and may reference the Matrix's scores as supporting evidence rather than re-deriving them.

---

## Responsibilities

The Tradeoff Analyzer must:

- compare competing implementation options across the Tradeoff Categories,
- read the risk assessment from `19_REASONING/RISK-ASSESSOR.md` as an input rather than re-assessing risk,
- reference `19_REASONING/DECISION-MATRIX.md`'s weighted scores as supporting evidence rather than re-scoring options independently,
- document the advantages and disadvantages of each option,
- recommend the option this comparison favors,
- record supporting rationale in a Tradeoff Record,
- and return the comparison to the requesting component.

---

## Tradeoff Categories

| Category | Description |
|---|---|
| Simplicity | Ease of understanding and maintenance |
| Performance | Speed and resource efficiency |
| Security | Protection against vulnerabilities |
| Scalability | Ability to support future growth |
| Maintainability | Ease of modification and support |
| Reliability | Stability and predictability |
| Development Cost | Time and effort required |

The Security category reads the finding `19_REASONING/RISK-ASSESSOR.md` already produced; it is not a separate security assessment.

---

## Analysis Process

1. Receive the assessed implementation options, including the risk assessment from `19_REASONING/RISK-ASSESSOR.md`.
2. Identify competing alternatives.
3. Evaluate each tradeoff category, referencing `19_REASONING/DECISION-MATRIX.md`'s scores where available.
4. Compare strengths and weaknesses.
5. Recommend the option this comparison favors.
6. Record supporting rationale.
7. Return the comparison to the requesting component.

---

## Tradeoff Record

| Field | Description |
|---|---|
| Option | Candidate solution |
| Advantages | Benefits |
| Disadvantages | Drawbacks |
| Overall Rating | Relative evaluation |
| Recommendation | Favored option |
| Reasoning | Explanation for the recommendation |

---

## Tradeoff Principles

- Prefer simpler solutions when outcomes are equivalent.
- Never sacrifice security for convenience.
- Balance performance with maintainability.
- Favor proven, validated patterns over unnecessary innovation.
- Consider long-term project sustainability.

---

## Permission Boundary

The Tradeoff Analyzer may compare options, document advantages and disadvantages, recommend a favored option, and record the comparison.

It must not select the final option itself (owned by `19_REASONING/DECISION-ENGINE.md`), perform risk assessment (owned by `19_REASONING/RISK-ASSESSOR.md`), independently re-score options against weighted criteria (owned by `19_REASONING/DECISION-MATRIX.md`), or assume a mandatory handoff to a specific downstream component.

---

## Domain Rule

Tradeoff categories apply identically regardless of domain; domain-specific tradeoffs are expressed through the existing categories, not a separate domain-specific system.

---

## Rule

> Every significant architectural or implementation choice should include a documented tradeoff comparison explaining why the favored option is preferred over reasonable alternatives. The Tradeoff Analyzer compares and recommends; the Decision Engine selects.
