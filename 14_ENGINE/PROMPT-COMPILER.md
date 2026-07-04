# Agent Engine: Prompt Compiler

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `14_ENGINE/CONTEXT-MANAGER.md`
Used By: `19_REASONING/AI-DRIVER.md`
Last Updated: 2026-07-04

## Purpose

The Prompt Compiler is responsible for assembling the final, formatted prompt string that is sent to the Large Language Model (LLM). It translates the structured context provided by the `Context Manager` into a coherent, optimized prompt that guides the model's reasoning process.

---

## Responsibilities

-   Consume the structured context from the `Context Manager`.
-   Arrange context components in the precise order defined by the `Prompt Assembly Order`.
-   Format each context section with clear headings and separators.
-   Ensure the final prompt adheres to model-specific formatting requirements (e.g., system vs. user roles).
-   Produce a final, ready-to-use prompt string for the `AI Driver`.

---

## Prompt Assembly Order

The Prompt Compiler must assemble the final prompt in a specific, prioritized order to ensure the LLM focuses on the most relevant and immediate information. This order directly reflects the loading priority established by the `14_ENGINE/CONTEXT-MANAGER.md`, with the highest-priority context placed at the end of the prompt for maximum influence.

The final prompt is structured as follows, from top to bottom:

```text
# Core System Rules
{Content of 01_RULES/AGENT-BEHAVIOR.md and other core rules}

---

# Project Context
{Content from the Project Loader, including project-wide goals and file manifests}

---

# Domain-Specific Knowledge
{Content from the relevant Knowledge Manager, e.g., 38_WORDPRESS/KNOWLEDGE-MANAGER.md}

---

# Active Workflow State
{Current state of the active workflow, including completed and pending steps}

---

# Current Task
## Goal
{The specific request, its goals, and any user clarifications}

## Evidence
{Facts, files, and outputs directly related to the immediate step being executed}
```

---

## Rules

1.  **Strict Ordering:** The prompt sections must be assembled in the exact order specified above.
2.  **Context Authority:** The Prompt Compiler must not load or generate context itself; it must only format the context provided by the `Context Manager`.
3.  **Clarity and Separation:** Each section must be clearly demarcated with Markdown headings and separators to prevent ambiguity.
4.  **Traceability:** The final compiled prompt (or a hash of it) should be logged to provide a traceable record of what the LLM was asked to do.