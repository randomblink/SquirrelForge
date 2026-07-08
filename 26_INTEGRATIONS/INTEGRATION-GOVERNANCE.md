# SquirrelForge Integration Governance

Version: 1.0.0
Status: Stable
Owner: Integrations Maintainers
Depends On: `19_REASONING/RISK-ASSESSOR.md`, `23_GOVERNANCE/POLICY-ENGINE.md`, `24_SECURITY`, `26_INTEGRATIONS/CONNECTOR-MANAGER.md`, `26_INTEGRATIONS/SERVICE-DISCOVERY.md`, `27_OBSERVABILITY`, `37_STORAGE`
Used By: `26_INTEGRATIONS/INTEGRATION-MANAGER.md`, `26_INTEGRATIONS/CONNECTOR-MANAGER.md`, `26_INTEGRATIONS/SERVICE-DISCOVERY.md`
Last Updated: 2026-07-08

## Purpose

Integration Governance owns integration-domain policy requirements, external-connection approval records, integration exceptions, allowed and prohibited integration constraints, required evidence lists, and integration governance decision records.

It reviews supplied evidence from security, policy, risk, compliance, connector, and service-discovery owners, then issues integration-domain governance decisions for `INTEGRATION-MANAGER.md` to consume.

It does not own general policy evaluation, security-domain policy, runtime authorization, authentication, risk assessment, compliance certification, integration execution, monitoring infrastructure, recovery, storage, audit, or observability infrastructure.

---

## Responsibilities

- Define integration-domain policy requirements for external connections.
- Define required evidence for integration approval requests.
- Review integration proposals for completeness against integration-domain requirements.
- Review supplied security, policy, risk, compliance, connector, and service-discovery evidence.
- Issue integration-domain approval, rejection, deferral, exception, and restriction decisions.
- Record allowed and prohibited external-connection constraints.
- Record integration exceptions and required conditions.
- Maintain integration governance decision records and evidence references.
- Provide governance decision references to `INTEGRATION-MANAGER.md`.
- Preserve governance history by recording governance records and evidence references through the owning storage, audit, and observability infrastructure.

---

## Boundary

`INTEGRATION-GOVERNANCE.md` owns:

- integration-domain policy requirements,
- integration approval criteria,
- required evidence lists,
- external-connection approval records,
- integration exception records,
- allowed and prohibited integration constraints,
- integration-specific conditions and limitations,
- and integration governance decision records.

`INTEGRATION-GOVERNANCE.md` does not own:

- general policy evaluation or platform-wide governance decisions (`23_GOVERNANCE/POLICY-ENGINE.md` and `23_GOVERNANCE`),
- security-domain policy definition, security exceptions, or security risk acceptance (`24_SECURITY/SECURITY-GOVERNANCE.md`),
- runtime authorization decisions (`24_SECURITY/AUTHORIZATION-MANAGER.md`),
- authentication, credential verification, MFA, session issuance, or provider credential handshakes (`24_SECURITY/AUTHENTICATION-MANAGER.md` and `26_INTEGRATIONS/AUTHENTICATION.md`),
- independent risk assessment (`19_REASONING/RISK-ASSESSOR.md` and assigned risk owners),
- compliance certification or compliance-domain assessment authority (`24_SECURITY/COMPLIANCE.md` and assigned compliance owners),
- connector registry records or connector readiness checks (`26_INTEGRATIONS/CONNECTOR-MANAGER.md`),
- service discovery records or endpoint verification (`26_INTEGRATIONS/SERVICE-DISCOVERY.md`),
- integration routing, handoff coordination, or response aggregation (`26_INTEGRATIONS/INTEGRATION-MANAGER.md`),
- integration execution or external system modification,
- recovery execution, retries, rollback, or failure handling (`17_COORDINATION` and `20_EXECUTION`),
- platform storage or persistence infrastructure (`37_STORAGE`),
- or logs, metrics, traces, dashboards, alerts, audit infrastructure, or observability pipelines (`27_OBSERVABILITY`).

---

## Governance Inputs

Integration Governance may review:

- integration proposal records,
- requesting component references,
- external service or provider references,
- connector metadata from `CONNECTOR-MANAGER.md`,
- service-discovery records from `SERVICE-DISCOVERY.md`,
- security evidence and security-governance decisions from `24_SECURITY`,
- runtime authorization decision references from `24_SECURITY/AUTHORIZATION-MANAGER.md`, when applicable,
- policy evaluation results from `23_GOVERNANCE/POLICY-ENGINE.md`,
- supplied risk assessments from assigned risk owners,
- compliance evidence or compliance-status records from `24_SECURITY/COMPLIANCE.md` or assigned compliance owners,
- data classification and handling requirements,
- operational constraints supplied by execution or domain owners,
- and historical integration governance records.

Integration Governance reviews supplied evidence. It does not replace the owner that produced that evidence.

---

## Governance Workflow

1. Receive an integration governance request.
2. Verify the request contains required proposal fields and owner references.
3. Identify the required integration-domain evidence list.
4. Request or consume connector and service-discovery references.
5. Review supplied security, policy, authorization, risk, compliance, and operational evidence.
6. Compare the proposal against integration-domain policy requirements and constraints.
7. Identify required conditions, limitations, exception needs, or missing evidence.
8. Issue an integration governance decision.
9. Record the decision, rationale, evidence references, and conditions through owning infrastructure.
10. Provide the decision reference to `INTEGRATION-MANAGER.md`.

---

## Governance Decisions

| Decision | Meaning |
|---|---|
| `Approved` | Integration may proceed under recorded requirements and references. |
| `Approved with Conditions` | Integration may proceed only when recorded conditions are satisfied. |
| `Exception Approved` | Integration exception is approved with documented scope, reason, and expiration or review requirements. |
| `Deferred` | Decision is postponed because timing, owner review, or external dependency is unresolved. |
| `Requires Additional Evidence` | Required evidence is missing or insufficient. |
| `Rejected` | Integration is not approved under current requirements and evidence. |
| `Prohibited` | Integration is not allowed by integration-domain constraints. |

Each decision must include documented rationale, scope, evidence references, and any conditions or limitations.

---

## Required Evidence Categories

Integration Governance may require evidence for:

- external service identity and ownership,
- connector definition and readiness,
- endpoint and protocol references,
- credential and authentication strategy references,
- authorization decision references, when the integration affects protected resources,
- security review or security-governance decision references,
- policy evaluation results,
- supplied risk assessment references,
- data classification and data-handling constraints,
- compliance evidence or compliance-status references,
- event-emission requirement references for observability owners,
- operational support ownership,
- and deprecation, suspension, or retirement expectations.

Required evidence lists are integration-domain requirements. Evidence conclusions remain owned by the components that produced them.

---

## Integration Constraints

Integration Governance may record constraints such as:

- allowed external services or providers,
- prohibited services or providers,
- allowed protocols,
- prohibited protocols,
- required credential-reference handling,
- required security or authorization references,
- allowed data classifications,
- outbound-data restrictions,
- required event-emission references for observability owners,
- connector activation conditions,
- exception expiration dates,
- review dates,
- and suspension or retirement conditions.

These constraints are consumed by `INTEGRATION-MANAGER.md`, `CONNECTOR-MANAGER.md`, and other Integration components. Satisfying or enforcing a constraint belongs to the component that owns the relevant control.

---

## Rules

1. Integration Governance decisions must be scoped to integration-domain external-connection governance.
2. Integration Governance must consume policy, security, authorization, risk, compliance, connector, and service-discovery evidence from the authoritative owners.
3. Integration Governance must not independently authenticate, authorize, evaluate general policy, assess risk, certify compliance, or execute integrations.
4. Integration Governance may approve, reject, defer, prohibit, condition, or approve exceptions for integration proposals only within its integration-domain scope.
5. Integration Governance decisions must include rationale, scope, evidence references, and conditions or limitations.
6. Integration Governance decision records must be preserved through the owning storage, audit, and observability infrastructure.
7. `INTEGRATION-MANAGER.md` consumes integration governance decision references; it must not replace this component's approval records.
