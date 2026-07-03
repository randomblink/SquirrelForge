# SquirrelForge AI Driver Layer

## Purpose

This directory defines the AI reasoning core that drives SquirrelForge as an Agent.

The AI Driver Layer interprets user goals, selects actions, coordinates planning, reviews results, decides next steps, and explains decisions while remaining controlled by governance, safety, validation, observability, and audit systems.

The AI Driver does not bypass rules, permissions, approvals, or execution controls. It provides intelligent direction inside a governed platform.

---

# Component Roster

| Component | Responsibility |
|---|---|
| `AI-DRIVER.md` | Central reasoning core that drives agent behavior. |
| `GOAL-INTERPRETER.md` | Converts user intent into structured goals. |
| `ACTION-SELECTOR.md` | Chooses the next best action. |
| `TOOL-SELECTOR.md` | Selects approved tools and integrations. |
| `CONTEXT-BUILDER.md` | Builds the working context for decisions. |
| `RESULT-REVIEWER.md` | Reviews outcomes and determines next steps. |
| `EXPLANATION-GENERATOR.md` | Explains decisions and actions clearly. |
| `AI-SAFETY-GATE.md` | Blocks unsafe or unauthorized AI-driven actions. |
| `PROMPT-COMPILER.md` | Compiles governed, model-ready prompts. |
| `MODEL-ROUTER.md` | Selects the appropriate AI model for each task. |
| `AI-DRIVER-GOVERNANCE.md` | Governs AI behavior, limits, and oversight. |

---

# AI Driver Rule

The AI Driver may recommend, plan, and decide, but it must never:

- Bypass governance.
- Execute unauthorized actions.
- Ignore safety rules.
- Override user permissions.
- Hide reasoning outcomes from audit.
- Modify protected records.
- Act without observable trace.
