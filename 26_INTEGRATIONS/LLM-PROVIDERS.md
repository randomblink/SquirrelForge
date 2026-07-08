# SquirrelForge LLM Providers

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `21_CONFIGURATION/MODEL-CONFIG.md`, `24_SECURITY`, `27_OBSERVABILITY`, `28_RUNTIME-CONFIG`
Used By: `19_REASONING/AI-DRIVER.md`, `34_AIDRIVER/MODEL-ROUTER.md`, `26_INTEGRATIONS/INTEGRATION-MANAGER.md`
Last Updated: 2026-07-08

## Purpose

The LLM Providers component defines how SquirrelForge communicates with external AI and large-language-model provider APIs through standardized provider clients.

It owns provider-specific request translation, response normalization, capability and model metadata references, provider transport status normalization, and provider credential-handshake coordination through approved security and runtime-configuration inputs.

It does not select the model for a task, make reasoning decisions, own provider fallback policy, store secrets, make platform authorization decisions, own general retry or recovery behavior, or maintain observability infrastructure.

---

## Responsibilities

- Define a standard provider-client interface for external AI and LLM providers.
- Implement provider-specific clients for approved providers.
- Translate internal model-execution requests into provider-specific API requests.
- Normalize provider responses into the internal provider-response format.
- Maintain provider capability, model, endpoint, and transport-limit metadata references.
- Coordinate provider credential handshakes using approved credential references from security and runtime-configuration owners.
- Normalize provider transport errors, rate-limit responses, quota responses, and provider availability status.
- Emit provider-call observability events through the observability owner.
- Return provider response, error, status, usage, and evidence references to the calling AI Driver, Model Router, or Integration Manager component.

---

## Boundary

`LLM-PROVIDERS.md` owns:

- external AI and LLM provider client interfaces,
- provider-specific protocol and payload translation,
- provider response normalization,
- provider capability and model metadata references,
- provider transport status and error normalization,
- provider usage metadata returned by the external service,
- and credential handshake coordination for provider calls.

`LLM-PROVIDERS.md` does not own:

- model selection, provider selection, fallback model routing, or routing policy (`34_AIDRIVER/MODEL-ROUTER.md` and `21_CONFIGURATION/MODEL-CONFIG.md`),
- reasoning decisions, prompt intent, or response interpretation (`19_REASONING`),
- prompt compilation (`14_ENGINE/PROMPT-COMPILER.md`),
- platform identity, authentication, authorization, or security enforcement (`24_SECURITY`),
- secret storage, credential storage, or runtime configuration authority (`28_RUNTIME-CONFIG` and `21_CONFIGURATION`),
- general retry, recovery, rollback, or workflow failure handling (`17_COORDINATION` and `20_EXECUTION`),
- logs, metrics, traces, dashboards, alerts, audit infrastructure, or observability pipelines (`27_OBSERVABILITY`),
- or validation authority for task completion or response quality.

---

## Provider Interaction Flow

```text
AI Driver or Model Router supplies an approved provider/model target
   ↓
LLM Providers resolves provider endpoint and credential references
   ↓
Provider client translates the internal request into provider API format
   ↓
External provider returns response, error, rate-limit, quota, or availability status
   ↓
Provider client normalizes result and emits observability events through the owning infrastructure
   ↓
Caller receives normalized response, error, status, usage, and evidence references
```

The caller remains responsible for the next decision. LLM Providers returns provider-call results; it does not decide whether the task is complete, should retry, should fall back, or should be replanned.

---

## Provider Metadata

Provider metadata may include:

| Field | Description |
|---|---|
| Provider ID | Stable provider identifier. |
| Provider Name | External provider name. |
| Supported Models | Model identifiers exposed by the provider or configured endpoint. |
| Capability Metadata | Declared model capabilities consumed by routing owners. |
| Endpoint Reference | Provider endpoint or service-discovery reference. |
| Credential Reference | Approved credential reference, never raw secret material. |
| Transport Limits | Rate limits, quota limits, timeout expectations, or payload constraints. |
| Availability Status | Provider-call availability status reported to callers and observability. |
| Usage Metadata | Token, cost, quota, or runtime usage data returned by the provider. |

Capability and routing policy are defined outside this component. This component records and exposes provider metadata for owners that make routing decisions.

---

## Provider States

| State | Meaning |
|---|---|
| `Available` | Provider endpoint is reachable and approved for configured use. |
| `Degraded` | Provider is reachable but returning constrained, delayed, or partial service. |
| `Unavailable` | Provider endpoint cannot currently serve requests. |
| `Rate Limited` | Provider rejected or delayed requests due to rate limits. |
| `Quota Exceeded` | Provider rejected requests because configured quota was exhausted. |
| `Authentication Failed` | Provider credential handshake failed or was rejected. |
| `Configuration Invalid` | Provider endpoint, model, or credential reference is missing or invalid. |

These are provider-transport states only. They are not workflow state, task state, validation state, or incident state.

---

## Rules

1. Provider-specific request formatting and response parsing must remain inside provider clients.
2. Raw secrets must never be stored in provider client definitions or provider metadata.
3. Provider clients may use approved credential references only through the owning security and runtime-configuration components.
4. Provider clients must return normalized response, error, status, and usage metadata to the caller.
5. Provider clients may report transport failures and provider status, but retry, fallback, recovery, and task-state decisions remain with their owning components.
6. Provider-call observability must be emitted through `27_OBSERVABILITY`; this component must not maintain separate logging or audit infrastructure.
7. `AI-PROVIDERS.md` is deprecated and must not receive new provider responsibilities.
