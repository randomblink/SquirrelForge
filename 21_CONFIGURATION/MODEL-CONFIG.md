# SquirrelForge Model Configuration

Version: 1.0.0
Status: Stable
Owner: Configuration Maintainers
Depends On: `23_GOVERNANCE/POLICY-ENGINE.md`
Used By: `34_AIDRIVER/MODEL-ROUTER.md`, `19_REASONING/AI-DRIVER.md`, `16_AGENTS`
Last Updated: 2026-07-07

## Purpose

Model Config defines the declarative model capability requirements, routing criteria, context limits, output constraints, fallback behavior, and data-handling classification that govern which AI models may be used and how. Model selection must be capability-based and auditable.

Model Config is a policy document, not a routing decision engine. It does not select a model for a specific request at runtime — that is `34_AIDRIVER/MODEL-ROUTER.md`'s responsibility, which reads this configuration as its primary input alongside live availability, cost, and governance status.

---

## Responsibilities

- Declare model capability requirements per task type (reasoning depth, context window, tool-calling support, structured output support, multimodal capability).
- Declare routing criteria (latency, cost, quality priorities).
- Declare context window limits per model.
- Declare output constraints (format, length, structured-output schema expectations).
- Declare fallback behavior when a primary model is unavailable or disqualified.
- Declare data-handling classification governing which models may process which sensitivity of data.
- Keep declarations consistent with `23_GOVERNANCE/POLICY-ENGINE.md`.
- Version and record changes to model configuration.

---

## Configuration Model

| Field | Description |
|---|---|
| Model ID | Registered model identifier. |
| Capability Requirements | Reasoning depth, context window, tool-calling, structured output, multimodal support. |
| Routing Criteria | Relative priority of latency, cost, and quality for this model. |
| Context Limits | Maximum context window and reserved response space. |
| Output Constraints | Required format, length limits, structured-output schema. |
| Fallback Behavior | Which model to route to when this one is unavailable or disqualified. |
| Data-Handling Classification | Sensitivity levels this model is approved to process. |

---

## Process

1. Register a model's capability requirements, routing criteria, context limits, output constraints, fallback behavior, and data-handling classification.
2. Validate the declaration against `23_GOVERNANCE/POLICY-ENGINE.md`.
3. Store the declaration in the active model configuration set.
4. Make the declaration available for `34_AIDRIVER/MODEL-ROUTER.md` to apply at runtime.
5. Record every change to the configuration for audit.

---

## Permission Boundary

Model Config may declare, version, and expire the static capability, routing-criteria, context-limit, fallback, and data-handling policy that governs model usage.

It must not select a model for a specific request or issue a routing decision — that remains owned by `34_AIDRIVER/MODEL-ROUTER.md`, which treats this file as configuration input rather than a decision authority.

---

## Domain Rule

Model configuration applies identically regardless of domain; domain-specific model requirements are declared as capability requirements here, not decided ad hoc by any domain layer.

---

## Rule

No model may be selected for a request unless it is declared here and its capability requirements, context limits, and data-handling classification are satisfied; model selection must be capability-based and auditable.
