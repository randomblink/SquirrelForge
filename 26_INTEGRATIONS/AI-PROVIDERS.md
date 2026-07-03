# SquirrelForge AI Providers Manager

## Purpose

The AI Providers Manager standardizes how SquirrelForge connects to AI models and providers, including hosted AI services, local models, specialized reasoning systems, and fallback providers.

---

## Responsibilities

- Register available AI providers.
- Identify provider capabilities.
- Select the correct model for each task.
- Route prompts and requests.
- Normalize AI responses.
- Track token, cost, and usage metadata.
- Monitor provider health.
- Manage fallback provider selection.

---

## AI Provider Process

1. Receive AI request.
2. Identify required capability.
3. Select approved provider and model.
4. Verify authentication or local availability.
5. Route request to selected provider.
6. Receive model response.
7. Normalize response format.
8. Record usage metadata.
9. Return result to the requesting workflow.

---

## Provider Types

| Provider Type | Description |
|---|---|
| Hosted API | External AI provider accessed by API |
| Local Model | Locally hosted model such as Ollama |
| Embedded Model | Model bundled into the system |
| Specialized Agent | Purpose-built AI component |
| Fallback Provider | Backup provider used when primary fails |

---

## Capability Categories

| Capability | Use |
|---|---|
| Reasoning | Planning, judgment, and decision support |
| Coding | Code generation, debugging, and refactoring |
| Vision | Image analysis and visual interpretation |
| Writing | Drafting, editing, and documentation |
| Retrieval | Search, summarization, and context extraction |
| Validation | Rule checking and output review |
| Automation | Tool use and workflow execution |

---

## Provider Record

| Field | Description |
|---|---|
| Provider ID | Unique identifier |
| Provider Name | AI provider name |
| Model | Selected model |
| Capability | Required task capability |
| Status | Active / Degraded / Failed / Fallback |
| Auth Status | Authenticated / Not Required / Failed |
| Usage | Token, cost, or runtime metadata |
| Timestamp | Request time |
| Result | Normalized response summary |

---

## Fallback Policy

When the selected provider is unavailable:

1. Record provider failure.
2. Identify required capability.
3. Select approved fallback provider.
4. Verify fallback availability.
5. Route request to fallback provider.
6. Record fallback usage.
7. Return normalized result.

---

## Rule

Every AI request must be routed through an approved provider, matched to a required capability, recorded with usage metadata, and returned in a normalized format.
