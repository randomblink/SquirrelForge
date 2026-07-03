# SquirrelForge Model Router

## Purpose

The Model Router selects the most appropriate AI model for each AI-driven request. It evaluates task type, model capabilities, context size, latency, cost, privacy requirements, availability, reliability, and governance policies to route work to the best supported model.

The Model Router does not generate prompts or make reasoning decisions. It receives compiled prompt packages and determines which approved model should process them.

---

# Responsibilities

- Select appropriate AI models.
- Evaluate model capabilities.
- Match models to task requirements.
- Route prompt packages.
- Support fallback routing.
- Balance latency, cost, and quality.
- Enforce privacy and governance constraints.
- Track model availability.
- Record routing activity.
- Support multi-model execution.

---

# Inputs

The Model Router receives:

- Compiled prompt packages
- Structured goals
- Task type
- Model registry
- Model capabilities
- Model availability
- Token requirements
- Privacy requirements
- Cost constraints
- Governance policies

---

# Outputs

The Model Router produces:

- Model routing decisions
- Selected model target
- Fallback model target
- Routing metadata
- Model execution requests
- Governance review requests
- Model routing audit records

---

# Model Routing Workflow

1. Receive compiled prompt package.
2. Identify task requirements.
3. Review model capability registry.
4. Filter unavailable or unauthorized models.
5. Evaluate privacy and governance constraints.
6. Compare cost, latency, and quality.
7. Select primary model.
8. Select fallback model when appropriate.
9. Route prompt package for execution.
10. Record audit information.

---

# Model Selection Criteria

Model selection considers:

- Task type
- Reasoning depth
- Context window size
- Tool-calling support
- Structured output support
- Multimodal capability
- Latency requirements
- Cost constraints
- Privacy requirements
- Governance approval status

---

# Supported Model Types

The Model Router may route to:

- Local models
- Cloud models
- Vision models
- Coding models
- Reasoning models
- Fast-response models
- Long-context models
- Tool-capable models
- Specialized domain models
- Fallback models

---

# Routing Strategies

Supported routing strategies include:

- Best capability match
- Lowest latency
- Lowest cost
- Highest quality
- Local-first routing
- Privacy-first routing
- Tool-capable routing
- Long-context routing
- Fallback routing
- Governance-restricted routing

---

# Fallback Handling

Fallback routing may occur when:

- Primary model is unavailable.
- Primary model exceeds cost limits.
- Primary model lacks required capability.
- Primary model fails execution.
- Privacy policy blocks the primary model.
- Governance policy requires local execution.
- Context exceeds model limits.

---

# Model Registry

The Model Router maintains model metadata including:

- Model ID
- Provider
- Deployment type
- Capabilities
- Context window
- Tool support
- Cost profile
- Latency profile
- Reliability status
- Governance status

---

# Privacy Routing

Privacy-aware routing ensures:

- Sensitive prompts stay on approved models.
- Local execution is preferred when required.
- Cloud routing follows governance policy.
- Restricted data is never sent to unauthorized providers.
- Prompt classifications are respected.

---

# Cost and Latency Management

The Model Router balances:

- Model quality
- Response time
- Token cost
- Inference cost
- Tool-use cost
- Availability
- Operational priority
- User requirements

---

# Integration Responsibilities

The Model Router coordinates with:

- AI Driver
- Prompt Compiler
- Tool Selector
- AI Safety Gate
- Execution Layer
- Observability Layer
- Cost Optimizer
- AI Driver Governance

---

# Data Protection

The Model Router must:

- Protect prompt packages.
- Enforce privacy requirements.
- Prevent unauthorized model access.
- Protect provider credentials.
- Preserve routing integrity.
- Maintain audit records.

---

# Safety Rules

The Model Router must never:

- Route sensitive data to unauthorized models.
- Use unapproved models.
- Ignore governance restrictions.
- Exceed defined cost limits without approval.
- Bypass safety requirements.
- Fabricate model capability.
- Hide routing failures.

---

# Failure Handling

If model routing fails:

- Preserve routing context.
- Record routing failure.
- Attempt approved fallback routing.
- Notify the AI Driver.
- Escalate persistent failures.
- Return blocked state when no safe model is available.
- Maintain audit continuity.

---

# Audit Requirements

Every model routing operation records:

- Model routing ID
- Timestamp
- Goal ID
- Prompt compilation ID
- Selected model
- Fallback model
- Routing reason
- Privacy classification
- Governance status
- Final outcome

---

# Success Criteria

The Model Router succeeds when:

- Requests are routed to suitable models.
- Model capability matches task requirements.
- Cost, latency, and quality are balanced.
- Privacy requirements are enforced.
- Fallback routing works when needed.
- Governance policies are consistently applied.
- Audit records remain complete.