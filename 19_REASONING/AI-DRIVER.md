# Reasoning: AI Driver

Version: 1.0.0
Status: Stable
Owner: Reasoning Maintainers
Depends On: `14_ENGINE/PROMPT-COMPILER.md`, `26_INTEGRATIONS/README.md`
Used By: `00_CORE/SYSTEM-ORCHESTRATOR.md`
Last Updated: 2026-07-04

## Purpose

The AI Driver is the central component responsible for interacting with Large Language Models (LLMs). It orchestrates the process of taking structured context, compiling it into a model-ready prompt, sending it to the appropriate LLM provider, and parsing the response back into a structured format for the rest of the system.

---

## Responsibilities

-   Receive a reasoning request, including the structured context from the `Context Manager`.
-   Invoke the `Prompt Compiler` to assemble the final prompt string.
-   Select the appropriate LLM provider and model based on the task requirements and configuration.
-   Send the compiled prompt to the selected LLM via the `Integrations` layer.
-   Receive the raw response from the LLM.
-   Parse and validate the LLM's response, converting it into a structured data object.
-   Handle errors, retries, and fallbacks for LLM interactions.
-   Log the prompt, response, and metadata for traceability and optimization.

---

## Execution Flow

1.  **Receive Context:** The AI Driver is invoked with a task and the associated context bundle from the `Context Manager`.
2.  **Compile Prompt:** It passes the context bundle to the `Prompt Compiler`, which returns a single, formatted prompt string according to its strict assembly order.
3.  **Select Model:** It determines which configured LLM provider (e.g., Anthropic, OpenAI) and model to use.
4.  **Execute Call:** It sends the prompt to the selected LLM provider's API.
5.  **Parse Response:** It receives the raw text or JSON response from the model.
6.  **Structure Output:** It parses the response into a structured object, validating its format and content.
7.  **Return Result:** It returns the structured result to the calling component (e.g., the `System Orchestrator` or a specific agent).

---

## Interaction with Prompt Compiler

The AI Driver's primary input for the LLM is the output of the `Prompt Compiler`. It does not assemble, order, or format the prompt itself. This separation of concerns ensures that prompt engineering is centralized and consistent, while the AI Driver focuses on the mechanics of model interaction.

```text
Context Manager
       │
       ▼ (Provides structured context)
  AI Driver
       │
       ▼ (Passes context to be compiled)
Prompt Compiler
       │
       ▼ (Returns formatted prompt string)
  AI Driver
       │
       ▼ (Sends prompt to LLM)
 LLM Provider
```

---

## Rules

1.  **Compiler Authority:** The AI Driver **must** use the `Prompt Compiler` to generate the final prompt. It must not construct prompts on its own.
2.  **Provider Agnostic:** The core logic of the AI Driver should remain independent of any specific LLM provider. Provider-specific details must be handled within dedicated integration clients.
3.  **Structured I/O:** The AI Driver must always aim to receive structured data and return structured data, treating the LLM as a function that operates on well-defined inputs and outputs.
4.  **Traceability:** Every LLM call, including the compiled prompt (or its hash), the raw response, and the final parsed output, must be logged.