# SquirrelForge Model Router

Version: 1.0.0
Status: Stable
Owner: AI Driver Maintainers
Depends On: `14_ENGINE/PROMPT-COMPILER.md`, `21_CONFIGURATION/MODEL-CONFIG.md`, `23_GOVERNANCE/POLICY-ENGINE.md`, `32_OPTIMIZATION/COST-OPTIMIZER.md`
Used By: `19_REASONING/AI-DRIVER.md`, `34_AIDRIVER/AI-SAFETY-GATE.md`
Last Updated: 2026-07-07

## Purpose

The Model Router selects the most appropriate AI model for each AI-driven request. It evaluates task type, model capabilities, context size, latency, cost, privacy requirements, availability, reliability, and governance policies to route work to the best supported model.

The Model Router does not generate prompts or make reasoning decisions. It receives compiled prompt packages from `14_ENGINE/PROMPT-COMPILER.md` and determines which approved model should process them.

The Model Router makes the runtime routing decision only. It does not define model capability requirements, routing criteria, context limits, or fallback policy — that declarative configuration is owned by `21_CONFIGURATION/MODEL-CONFIG.md`, which the Model Router applies to each request rather than re-deciding.

---

## Responsibilities

- Select appropriate AI models for each request.
- Evaluate model capabilities against `21_CONFIGURATION/MODEL-CONFIG.md`'s declared requirements.
- Match models to task requirements.
- Route prompt packages to the selected model.
- Support fallback routing per configured fallback behavior.
- Balance latency, cost, and quality.
- Enforce privacy and governance constraints.
- Track model availability.
- Record routing activity.
- Support multi-model execution.

---

## Inputs

The Model Router receives:

- Compiled prompt packages (from `14_ENGINE/PROMPT-COMPILER.md`)
- Structured goals
- Task type
- Model registry
- Model capability requirements (from `21_CONFIGURATION/MODEL-CONFIG.md`)
- Model availability
- Token requirements
- Data-handling classification (from `21_CONFIGURATION/MODEL-CONFIG.md`)
- Cost constraints (from `32_OPTIMIZATION/COST-OPTIMIZER.md`)
- Governance policies (from `23_GOVERNANCE/POLICY-ENGINE.md`)

---

## Outputs

The Model Router produces:

- Model routing decisions
- Selected model target
- Fallback model target
- Routing metadata
- Model execution requests
- Governance review requests
- Model routing audit records

---

## Model Routing Workflow

1. Receive compiled prompt package from `14_ENGINE/PROMPT-COMPILER.md`.
2. Identify task requirements.
3. Review model capability registry against `21_CONFIGURATION/MODEL-CONFIG.md`.
4. Filter unavailable or unauthorized models.
5. Evaluate privacy and governance constraints via `23_GOVERNANCE/POLICY-ENGINE.md`.
6. Compare cost, latency, and quality, consulting `32_OPTIMIZATION/COST-OPTIMIZER.md` for cost signals.
7. Select primary model.
8. Select fallback model per `21_CONFIGURATION/MODEL-CONFIG.md`'s fallback behavior.
9. Route prompt package for execution.
10. Record audit information.

---

## Model Selection Criteria

Model selection considers:

- Task type
- Reasoning depth
- Context window size
- Tool-calling support
- Structured output support
- Multimodal capability
- Latency requirements
- Cost constraints
- Privacy requirements (data-handling classification)
- Governance approval status

Selection must be capability-based and auditable, per `21_CONFIGURATION/MODEL-CONFIG.md`.

---

## Routing Strategies

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

## Fallback Handling

Fallback routing may occur when:

- Primary model is unavailable.
- Primary model exceeds cost limits.
- Primary model lacks required capability.
- Primary model fails execution.
- Privacy policy blocks the primary model.
- Governance policy requires local execution.
- Context exceeds model limits.

Fallback behavior itself is declared in `21_CONFIGURATION/MODEL-CONFIG.md`; the Model Router executes it.

---

## Privacy Routing

Privacy-aware routing ensures:

- Sensitive prompts stay on approved models, per the data-handling classification in `21_CONFIGURATION/MODEL-CONFIG.md`.
- Local execution is preferred when required.
- Cloud routing follows `23_GOVERNANCE/POLICY-ENGINE.md`.
- Restricted data is never sent to unauthorized providers.

---

## Integration Responsibilities

The Model Router coordinates with:

- `19_REASONING/AI-DRIVER.md`
- `14_ENGINE/PROMPT-COMPILER.md`
- `34_AIDRIVER/TOOL-SELECTOR.md`
- `34_AIDRIVER/AI-SAFETY-GATE.md`
- `20_EXECUTION`
- `27_OBSERVABILITY`
- `32_OPTIMIZATION/COST-OPTIMIZER.md`
- `34_AIDRIVER/AI-DRIVER-GOVERNANCE.md`

---

## Safety Rules

The Model Router must never:

- Route sensitive data to unauthorized models.
- Use models not declared in `21_CONFIGURATION/MODEL-CONFIG.md`.
- Ignore governance restrictions.
- Exceed defined cost limits without approval.
- Fabricate model capability.
- Hide routing failures.

---

## Failure Handling

If model routing fails:

- Preserve routing context.
- Record routing failure.
- Attempt approved fallback routing per `21_CONFIGURATION/MODEL-CONFIG.md`.
- Notify `19_REASONING/AI-DRIVER.md`.
- Escalate persistent failures.
- Return blocked state when no safe model is available.
- Maintain audit continuity.

---

## Audit Requirements

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

## Success Criteria

The Model Router succeeds when:

- Requests are routed to suitable models.
- Model capability matches task requirements.
- Cost, latency, and quality are balanced.
- Privacy requirements are enforced.
- Fallback routing works when needed.
- Governance policies are consistently applied.
- Audit records remain complete.

---

## Permission Boundary

The Model Router may evaluate a compiled prompt package against declared model capabilities and select a primary and fallback model for that specific request.

It must not define model capability requirements, context limits, or fallback policy itself — that declarative configuration remains owned by `21_CONFIGURATION/MODEL-CONFIG.md`.

---

## Domain Rule

Model routing applies identically regardless of domain; domain-specific model requirements are declared as capability requirements in `21_CONFIGURATION/MODEL-CONFIG.md`, not decided ad hoc by the router.

---

## Rule

No prompt package may be sent to a model that is not declared, capability-matched, and approved per `21_CONFIGURATION/MODEL-CONFIG.md` and `23_GOVERNANCE/POLICY-ENGINE.md`.
