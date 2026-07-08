# SquirrelForge AI Providers

Version: 1.0.0
Status: Deprecated
Owner: Integrations Maintainers
Depends On: `26_INTEGRATIONS/LLM-PROVIDERS.md`
Used By: Migration and compatibility references
Last Updated: 2026-07-08

## Purpose

This file is retained only as a compatibility redirect.

AI and LLM provider integration ownership now belongs to `26_INTEGRATIONS/LLM-PROVIDERS.md`, which defines provider-client interfaces, provider capability metadata, provider request and response normalization, credential-reference handoff, provider status reporting, and provider transport error normalization.

---

## Replacement

Use `26_INTEGRATIONS/LLM-PROVIDERS.md` for:

- external AI and LLM provider client interfaces,
- provider-specific request and response translation,
- provider capability and model metadata,
- provider transport status and error normalization,
- credential handshake through approved security and runtime-configuration references,
- and observability event references for provider calls.

---

## Boundary

This file does not own:

- model selection or fallback routing (`34_AIDRIVER/MODEL-ROUTER.md`),
- reasoning decisions (`19_REASONING`),
- platform authentication, authorization, or security enforcement (`24_SECURITY`),
- secret storage or runtime configuration (`28_RUNTIME-CONFIG` and `21_CONFIGURATION`),
- general retry, recovery, or rollback authority (`17_COORDINATION` and `20_EXECUTION`),
- or observability infrastructure (`27_OBSERVABILITY`).

---

## Rule

No new responsibility should be added to this file. New or updated AI/LLM provider integration behavior must be documented in `26_INTEGRATIONS/LLM-PROVIDERS.md`.
