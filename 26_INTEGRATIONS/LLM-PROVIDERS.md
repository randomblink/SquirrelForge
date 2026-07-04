# Integrations: LLM Providers

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `19_REASONING/AI-DRIVER.md`, `21_CONFIGURATION/README.md`
Used By: `19_REASONING/AI-DRIVER.md`
Last Updated: 2026-07-04

## Purpose

The LLM Providers component is a collection of clients responsible for managing all communication with external Large Language Model (LLM) APIs (e.g., Anthropic, OpenAI, Google Gemini). It abstracts provider-specific implementation details, allowing the `AI Driver` to interact with any supported LLM through a standardized interface.

---

## Responsibilities

-   Implement provider-specific clients that conform to a common `LlmClientInterface`.
-   Handle authentication and authorization for each external LLM provider, using credentials from the `Configuration Manager`.
-   Translate the standard internal request format into the provider's specific API format.
-   Normalize the provider's response back into a standard internal format.
-   Manage provider-specific errors, rate limits, and retry logic.
-   Provide a registry of available models for each configured provider.

---

## Interaction with AI Driver

The `AI Driver` depends on this component to execute LLM calls. The flow is as follows:

1.  The `AI Driver` compiles a prompt using the `Prompt Compiler`.
2.  It selects a target model and provider based on the task and configuration.
3.  It invokes the corresponding client within this component, passing the compiled prompt.
4.  The client handles the API communication and returns a normalized response or error to the `AI Driver`.

This separation ensures that the core reasoning logic in the `AI Driver` remains completely independent of any specific LLM provider's implementation.

```text
AI Driver
   │
   ▼ (Selects model and passes compiled prompt)
LLM Providers Component
   │
   ├─ Anthropic Client ───> Anthropic API
   │
   ├─ OpenAI Client ──────> OpenAI API
   │
   └─ Gemini Client ──────> Google AI API
```

---

## Configuration

Each provider is configured via the `21_CONFIGURATION` layer. Configuration includes API keys, endpoint URLs, available model names, and default parameters. Secrets like API keys must be managed through a secure secrets manager and referenced, not stored directly in configuration files.

---

## Rules

1.  **Provider-Specific Logic Isolation:** All code specific to a single LLM provider (e.g., API request formatting, error codes) **must** be contained within that provider's client.
2.  **Standardized Interface:** Every provider client **must** implement the common `LlmClientInterface` to ensure interchangeability.
3.  **Agnostic AI Driver:** The `AI Driver` must not contain any `if/else` logic based on the provider name. It should treat all provider clients identically through the shared interface.
4.  **Traceability:** All outgoing requests and incoming responses (raw and normalized) must be logged for observability and debugging.