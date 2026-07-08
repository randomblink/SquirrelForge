# SquirrelForge Audit Trail

Version: 1.0.0
Status: Stable
Owner: Observability Maintainers
Depends On: `23_GOVERNANCE`, `24_SECURITY`, `27_OBSERVABILITY/OBSERVABILITY-GOVERNANCE.md`, `37_STORAGE`
Used By: Governance, Security, Execution, Integrations, Runtime Config, Compliance, Release
Last Updated: 2026-07-08

## Purpose

The Audit Trail owns audit-event records and audit evidence references for security-sensitive, governance-relevant, configuration-changing, workflow-critical, integration-relevant, and release-relevant actions.

It records supplied event facts, actor references, action references, resource references, decision references, timestamps, outcomes, and evidence references.

It does not decide whether an action is allowed, certify compliance, own business state, perform security authorization, execute storage infrastructure, or validate task completion.

---

## Responsibilities

- Receive auditable event references from platform components.
- Check required audit-event fields.
- Produce immutable audit-event records through owning storage infrastructure.
- Preserve actor, action, resource, reason, outcome, timestamp, and evidence references.
- Expose audit evidence references to governance, security, compliance, and operational owners.

---

## Rules

1. Audit Trail records supplied audit facts; it does not make the domain decision that produced them.
2. Audit records must not contain raw secrets or prohibited sensitive payloads.
3. Audit record persistence must use owning storage infrastructure.
4. Audit Trail must not mark actions complete on behalf of execution or workflow owners.
