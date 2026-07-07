# SquirrelForge AI Driver Support Layer

Version: 1.0.0
Status: Stable
Owner: AI Driver Maintainers
Depends On: `19_REASONING`, `14_ENGINE`, `20_EXECUTION`, `21_CONFIGURATION`, `23_GOVERNANCE`, `24_SECURITY`
Used By: `19_REASONING/AI-DRIVER.md`, `20_EXECUTION/ACTION-DISPATCHER.md`
Last Updated: 2026-07-07

## Purpose

This directory provides AI-driving-domain-specific support components that plug into the central reasoning core (`19_REASONING`) and engine (`14_ENGINE`) without duplicating them: AI-specific governance, a final pre-dispatch safety re-confirmation, runtime model routing, post-execution result review, and runtime tool selection.

`34_AIDRIVER` originally contained 12 files. Six were found to be near-exact duplicates of already-Stable components elsewhere (`AI-DRIVER.md`, `PROMPT-COMPILER.md`, `CONTEXT-BUILDER.md`, `ACTION-SELECTOR.md`, `EXPLANATION-GENERATOR.md`, and `GOAL-INTERPRETER.md`, superseded respectively by `19_REASONING/AI-DRIVER.md`, `14_ENGINE/PROMPT-COMPILER.md`, `14_ENGINE/CONTEXT-MANAGER.md`, `19_REASONING/DECISION-ENGINE.md`, `19_REASONING/EXPLANATION-ENGINE.md`, and `14_ENGINE/GOAL-PLANNER.md`) and were removed. The remaining five were genuinely distinct and have been cleaned and grounded.

---

## Layer Boundary

`34_AIDRIVER` owns:

- AI-specific governance, specializing platform governance for AI-driven behavior (`AI-DRIVER-GOVERNANCE.md`),
- the final pre-dispatch safety re-confirmation for AI-driven actions (`AI-SAFETY-GATE.md`),
- runtime model selection and routing (`MODEL-ROUTER.md`),
- real-time post-execution goal-status review and next-step recommendation (`RESULT-REVIEWER.md`),
- and runtime tool selection (`TOOL-SELECTOR.md`).

`34_AIDRIVER` does not own:

- central AI reasoning and orchestration (owned by `19_REASONING/AI-DRIVER.md`),
- prompt assembly (owned by `14_ENGINE/PROMPT-COMPILER.md`),
- context assembly (owned by `14_ENGINE/CONTEXT-MANAGER.md`),
- goal interpretation (owned by `14_ENGINE/GOAL-PLANNER.md`),
- action/decision selection, explanation generation, rule compliance, or risk assessment (owned by `19_REASONING/DECISION-ENGINE.md`, `19_REASONING/EXPLANATION-ENGINE.md`, `19_REASONING/RULE-EVALUATOR.md`, and `19_REASONING/RISK-ASSESSOR.md`),
- authorization decisions (owned by `24_SECURITY/AUTHORIZATION-MANAGER.md`),
- declarative model, tool, or permission configuration (owned by `21_CONFIGURATION/MODEL-CONFIG.md`, `21_CONFIGURATION/TOOL-CONFIG.md`, and `21_CONFIGURATION/PERMISSIONS.md`),
- execution result collection (owned by `20_EXECUTION/RESULT-COLLECTOR.md`),
- output validation (owned by `14_ENGINE/VALIDATION.md`),
- and platform-wide governance (owned by `23_GOVERNANCE` and `01_RULES`).

---

## Components

| Component | Responsibility |
|---|---|
| `AI-DRIVER-GOVERNANCE.md` | Specializes platform governance for AI-driven behavior; approves operational boundaries. |
| `AI-SAFETY-GATE.md` | Re-confirms Rule Evaluator's and Risk Assessor's findings still hold immediately before dispatch. |
| `MODEL-ROUTER.md` | Selects the model for a compiled prompt package at runtime, per Model Config's declared capability requirements. |
| `RESULT-REVIEWER.md` | Reads collected results and validation findings and recommends the AI Driver's next step. |
| `TOOL-SELECTOR.md` | Selects which registered, permitted, healthy tool executes a given action. |

The authoritative component roster must match files that actually exist in this directory.

---

## Execution Order

```text
14_ENGINE/GOAL-PLANNER.md (structured goal)
   ↓
19_REASONING (Decision Engine → Rule Evaluator → Risk Assessor → Tradeoff Analyzer → Strategy Planner → Explanation Engine → Confidence Scorer)
   ↓
TOOL-SELECTOR.md (select tool)
   ↓
MODEL-ROUTER.md (select model, if an LLM call is required)
   ↓
AI-SAFETY-GATE.md (final pre-dispatch re-confirmation)
   ↓
20_EXECUTION/ACTION-DISPATCHER.md (dispatch and execute)
   ↓
20_EXECUTION/RESULT-COLLECTOR.md (assemble results)
   ↓
RESULT-REVIEWER.md (compare to goal, recommend next step)
   ↓
Continue → back to 19_REASONING/DECISION-ENGINE.md
Complete → 19_REASONING/REFLECTION-ENGINE.md (retrospective lessons)
```

`AI-DRIVER-GOVERNANCE.md` operates continuously alongside this chain, reviewing and auditing rather than acting as a pipeline stage.

---

## Dependencies

`34_AIDRIVER` depends on:

- `19_REASONING` for the decisions, rule findings, and risk assessments its components re-confirm or route around,
- `14_ENGINE` for prompt compilation, context assembly, and goal interpretation,
- `20_EXECUTION` for dispatch and result collection,
- `21_CONFIGURATION` for the declarative model, tool, and permission policy its runtime components apply,
- `23_GOVERNANCE` and `01_RULES` for the platform governance `AI-DRIVER-GOVERNANCE.md` specializes,
- and `24_SECURITY` for the authorization decisions the AI Safety Gate re-confirms.

---

## State Rule

`34_AIDRIVER` does not persist task or lifecycle state on its own authority — that remains `14_ENGINE/STATE-MANAGER.md`'s responsibility. Its components produce recommendations and decisions that are recorded through Memory and the State Manager, not held only inside this directory's components.

---

## Domain Rule

AI-driving mechanics apply identically regardless of domain; domain-specific content is carried in the goals, tools, and models it routes between, not interpreted by this layer itself.

---

## Rule

> No AI-driven action may bypass tool selection, model routing, or the final safety gate; no AI-driven task may be marked complete, retried, or replanned without the Result Reviewer comparing actual results against the original goal.
