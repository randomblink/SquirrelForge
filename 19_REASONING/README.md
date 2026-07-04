# SquirrelForge Reasoning Layer

Version: 1.0.0
Status: Draft
Owner: Reasoning Maintainers
Depends On: Engine context, Rules, Memory
Used By: Planning and Execution
Last Updated: 2026-07-01

## Purpose

This directory defines the components responsible for SquirrelForge's intelligent decision-making. It includes the `AI Driver` for managing LLM interactions, as well as components for rule compliance, risk management, strategic planning, and self-assessment. It ensures that the agent's actions are strategically sound, compliant, and explainable.

---

## Components

| Component | Responsibility |
|---|---|
| `AI-DRIVER.md` | Orchestrates LLM interactions, using the `Prompt Compiler` for assembly and `LLM Providers` for execution. |
| `DECISION-ENGINE.md` | Selects the best course of action by evaluating goals, rules, risks, and available knowledge. |
| `RULE-EVALUATOR.md` | Verifies that proposed actions comply with project rules, workflows, and standards. |
| `RISK-ASSESSOR.md` | Identifies, evaluates, and prioritizes implementation risks while recommending mitigation strategies. |
| `TRADEOFF-ANALYZER.md` | Compares competing implementation options and recommends the best balance of quality, security, performance, and maintainability. |
| `STRATEGY-PLANNER.md` | Converts approved decisions into executable implementation strategies and roadmaps. |
| `EXPLANATION-ENGINE.md` | Produces clear, traceable explanations describing why decisions and strategies were selected. |
| `CONFIDENCE-SCORER.md` | Assigns a confidence level to decisions based on evidence, validation history, risks, and knowledge. |
| `REFLECTION-ENGINE.md` | Extracts lessons from completed work. |

## Execution Order

AI Driver (Prompt Compilation & LLM Call) → Decision → Rule Evaluation → Risk/Tradeoff Analysis → Strategy → Explanation → Confidence.

## Dependencies

Engine context, rules, memory, project constraints, and the `26_INTEGRATIONS/LLM-PROVIDERS.md` component.

---

## Rules

Every significant decision made by SquirrelForge must:

1. Be supported by documented reasoning.
2. Comply with all applicable project rules.
3. Include an assessment of implementation risks.
4. Consider reasonable alternative approaches when appropriate.
5. Provide a clear explanation of the selected strategy.
6. Record an appropriate confidence level before execution proceeds.

## Diagram

```text
Context → AI Driver → Decision → Rules → Risk/Tradeoffs → Strategy → Explanation + Confidence
```
