# SquirrelForge Reasoning Layer

Version: 1.0.0
Status: Stable
Owner: Reasoning Maintainers
Depends On: `14_ENGINE/PROMPT-COMPILER.md`, `26_INTEGRATIONS`, `18_MEMORY`, `14_ENGINE/VALIDATION.md`, `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`, `23_GOVERNANCE`, `01_RULES`
Used By: Planning, Execution, Agents
Last Updated: 2026-07-06

## Purpose

The Reasoning Layer performs SquirrelForge's intelligent decision-making: driving LLM interaction, selecting a course of action, verifying rule compliance, assessing risk, analyzing tradeoffs, planning an executable strategy, explaining the outcome, scoring confidence, and reflecting on completed work to surface reusable lessons.

Reasoning decides and explains; it does not execute, persist, validate, or govern. It reads and applies project rules rather than authoring them (owned by `23_GOVERNANCE` and `01_RULES`), reads and writes through Memory rather than storing state itself (`18_MEMORY`), reads validation evidence rather than producing it (`14_ENGINE/VALIDATION.md`), and hands a planned strategy to execution rather than carrying it out (`20_EXECUTION`).

---

## Layer Boundary

`19_REASONING` owns:

- LLM interaction orchestration, via the Prompt Compiler and Integrations layer (`AI-DRIVER.md`),
- selecting a course of action from feasible options (`DECISION-ENGINE.md`, `DECISION-MATRIX.md`),
- verifying compliance with already-authored project rules (`RULE-EVALUATOR.md`),
- identifying and prioritizing implementation risk (`RISK-ASSESSOR.md`),
- comparing competing implementation options (`TRADEOFF-ANALYZER.md`),
- converting an approved decision into an executable strategy (`STRATEGY-PLANNER.md`),
- explaining why a decision or strategy was selected (`EXPLANATION-ENGINE.md`),
- scoring confidence in a decision (`CONFIDENCE-SCORER.md`),
- and extracting lessons from completed, validated work (`REFLECTION-ENGINE.md`).

`19_REASONING` does not own:

- authoring or approving project rules, security rules, or governance policy (owned by `23_GOVERNANCE` and `01_RULES`),
- confirming that work actually passed validation (owned by `14_ENGINE/VALIDATION.md`),
- storing or retaining any memory record (owned by `18_MEMORY`),
- registering, approving, or promoting platform-wide reusable knowledge (owned by `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md`) — the Reflection Engine forwards candidate material; it does not decide promotion itself,
- executing or dispatching the planned strategy (owned by `20_EXECUTION`),
- enforcing security authorization (owned by `24_SECURITY`),
- and assembling or ordering the LLM prompt itself (owned by `14_ENGINE/PROMPT-COMPILER.md`; the AI Driver must use it rather than construct prompts directly).

---

## Components

| Component | Responsibility |
|---|---|
| `AI-DRIVER.md` | Orchestrates LLM interactions, using the Prompt Compiler for assembly and Integrations for execution. |
| `DECISION-ENGINE.md` | Selects the best course of action by evaluating goals, rules, risks, and available knowledge. |
| `DECISION-MATRIX.md` | Scores feasible options against weighted criteria to support the Decision Engine's selection. |
| `RULE-EVALUATOR.md` | Verifies that proposed actions comply with project rules, workflows, and standards. |
| `RISK-ASSESSOR.md` | Identifies, evaluates, and prioritizes implementation risks while recommending mitigation strategies. |
| `TRADEOFF-ANALYZER.md` | Compares competing implementation options and recommends the best balance of quality, security, performance, and maintainability. |
| `STRATEGY-PLANNER.md` | Converts approved decisions into executable implementation strategies and roadmaps. |
| `EXPLANATION-ENGINE.md` | Produces clear, traceable explanations describing why decisions and strategies were selected. |
| `CONFIDENCE-SCORER.md` | Assigns a confidence level to decisions based on evidence, validation history, risks, and knowledge. |
| `REFLECTION-ENGINE.md` | Extracts lessons from completed, validated work and forwards reusable candidates to the Knowledge Manager. |

The authoritative component roster must match files that actually exist in this directory.

---

## Execution Order

```text
AI Driver (LLM orchestration)
   ↓
Decision Engine (select course of action, scored via the Decision Matrix)
   ↓
Rule Evaluator (verify compliance)
   ↓
Risk Assessor (identify and prioritize risk)
   ↓
Tradeoff Analyzer (compare options)
   ↓
Strategy Planner (produce executable strategy)
   ↓
Explanation Engine (explain the selection)
   ↓
Confidence Scorer (score confidence before execution)
```

The Reflection Engine operates separately and retrospectively, reviewing completed and validated work after the fact — it is not a stage in this forward decision chain.

---

## Dependencies

Reasoning depends on:

- `14_ENGINE/PROMPT-COMPILER.md` and `26_INTEGRATIONS` for LLM prompt assembly and provider execution,
- `18_MEMORY` for the historical context the Decision Engine reads and the completed-task records the Reflection Engine reviews,
- `14_ENGINE/VALIDATION.md` for the validation evidence the Confidence Scorer and Reflection Engine read,
- `25_KNOWLEDGE/KNOWLEDGE-MANAGER.md` as the destination for reusable material the Reflection Engine forwards,
- `23_GOVERNANCE` and `01_RULES` for the project rules the Rule Evaluator applies,
- and `20_EXECUTION` as the destination for the strategy Reasoning hands off.

---

## State Rule

Reasoning does not persist task or lifecycle state on its own authority — that remains `14_ENGINE/STATE-MANAGER.md`'s responsibility. A decision, strategy, or confidence score Reasoning produces must be recorded through Memory and the State Manager, not held only inside Reasoning's own components.

---

## Domain Rule

Reasoning mechanics apply identically regardless of domain; domain-specific content is carried in the goals, rules, and options it evaluates, not interpreted by the reasoning process itself.

---

## Diagram

```text
Context → AI Driver → Decision Engine → Rule Evaluator → Risk Assessor → Tradeoff Analyzer → Strategy Planner → Explanation Engine → Confidence Scorer → Execution

Completed & Validated Work → Reflection Engine → 25_KNOWLEDGE (candidate promotion)
```

---

## Rule

> Every significant decision must be supported by documented reasoning, comply with applicable project rules, include a risk assessment, consider reasonable alternatives, provide a clear explanation, and carry a recorded confidence level before execution proceeds. Reasoning decides and explains; it does not execute, persist state, validate, or govern — those remain owned by Execution, Memory and the State Manager, Validation, and Governance respectively.
