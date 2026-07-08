# SquirrelForge Webhook Manager

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `21_CONFIGURATION`, `24_SECURITY`, `26_INTEGRATIONS/AUTHENTICATION.md`, `26_INTEGRATIONS/INTEGRATION-GOVERNANCE.md`, `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, `27_OBSERVABILITY`, `28_RUNTIME-CONFIG`
Used By: `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, External event and notification integrations
Last Updated: 2026-07-08

## Purpose

The Webhook Manager adapts inbound and outbound webhook communications between SquirrelForge and approved external systems.

It owns webhook protocol handling, signature/reference checks, payload-shape checks, outbound webhook payload translation, delivery status normalization, and webhook event/status evidence references.

It does not own business event routing, workflow execution, security authorization, credential storage, retry/recovery execution, business validation, logging, audit, observability infrastructure, or authoritative workflow state.

---

## Responsibilities

- Receive approved inbound webhook requests at Integration-layer webhook endpoints.
- Check webhook signature, token, timestamp, replay, and payload-shape references using approved configuration and security inputs.
- Normalize inbound webhook event metadata and payload references.
- Return inbound webhook event references to `INTEGRATION-MANAGER.md` or the approved caller for routing.
- Translate approved outbound webhook handoffs into provider-specific payloads.
- Coordinate outbound webhook signing/authentication using approved references.
- Normalize outbound delivery response, error, status, and evidence references.
- Emit webhook event references through observability owners.

---

## Boundary

`WEBHOOK-MANAGER.md` owns:

- webhook protocol handling,
- inbound webhook payload-shape checks,
- webhook signature and replay-reference checks,
- outbound webhook payload translation,
- webhook delivery status normalization,
- webhook event/status evidence references,
- and webhook acknowledgment handling.

`WEBHOOK-MANAGER.md` does not own:

- business event routing or task routing (`INTEGRATION-MANAGER.md` and workflow owners),
- workflow execution,
- platform authentication or authorization decisions,
- credential or secret storage,
- security policy enforcement,
- business validation or task-completion validation,
- retry, recovery, rollback, or workflow failure handling,
- logging, audit, metrics, traces, dashboards, alerts, or observability infrastructure,
- or authoritative workflow/task lifecycle state.

---

## Webhook Statuses

| Status | Meaning |
|---|---|
| `Received` | Inbound webhook request was received. |
| `Rejected` | Required signature, token, timestamp, replay, or payload-shape reference failed. |
| `Accepted` | Webhook passed protocol-level checks and produced an event reference. |
| `Dispatched` | Event reference was handed to the approved caller or Integration Manager. |
| `Delivery Submitted` | Outbound webhook delivery was submitted. |
| `Delivered` | External system accepted outbound webhook delivery. |
| `Delivery Failed` | External system rejected or failed outbound delivery. |

These are webhook transport statuses only. They are not business validation, workflow state, incident state, or recovery state.

---

## Rules

1. Webhook Manager must process only approved webhook endpoints and outbound handoffs.
2. Webhook Manager must use credential, signing, endpoint, governance, and configuration references from owning components.
3. Webhook Manager may perform protocol-level signature, replay, and payload-shape checks only.
4. Webhook Manager must not route business events independently of Integration Manager or workflow owners.
5. Webhook Manager may report retryable delivery status, but retry/recovery decisions belong to execution and coordination owners.
6. Webhook Manager must emit event references through observability owners.
