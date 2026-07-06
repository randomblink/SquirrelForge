# SquirrelForge Risk Assessor

Version: 1.0.0
Status: Stable
Owner: Reasoning Maintainers
Depends On: `16_AGENTS/AGENT-SECURITY.md`, `16_AGENTS/AGENT-PERFORMANCE.md`, `14_ENGINE/DEPENDENCY-ANALYZER.md`
Used By: Decision Engine
Last Updated: 2026-07-06

## Purpose

The Risk Assessor identifies, evaluates, and prioritizes implementation risk across technical, security, performance, project, operational, and external categories to inform a pending decision, recommending mitigation strategies where applicable.

The Risk Assessor produces a preliminary, decision-support risk assessment; it does not replace the dedicated security review (`16_AGENTS/AGENT-SECURITY.md`) or performance review (`16_AGENTS/AGENT-PERFORMANCE.md`) that occurs later in the pipeline against actual implementation. It does not select a strategy itself (owned by `19_REASONING/DECISION-ENGINE.md`), perform tradeoff comparison (owned by `19_REASONING/TRADEOFF-ANALYZER.md`), or assume a mandatory handoff to any specific downstream component — it returns its assessment to whichever component requested it, typically the Decision Engine.

---

## Responsibilities

The Risk Assessor must:

- identify technical, security, performance, project, operational, and external risks relevant to a proposed option,
- estimate likelihood and impact for each identified risk,
- assign a risk level using the Risk Matrix,
- recommend a mitigation strategy, or note that the risk requires explicit acceptance,
- record the assessment in a Risk Record,
- and return the assessment to the requesting component.

---

## Risk Categories

| Category | Description |
|---|---|
| Technical | Architecture, implementation, or dependency risks |
| Security | Authentication, authorization, data protection |
| Performance | Resource usage, scalability, responsiveness |
| Project | Schedule, scope, or resource constraints |
| Operational | Deployment, rollback, maintenance |
| External | Third-party services, libraries, APIs |

A Security or Performance finding here is a preliminary flag for decision support, not a substitute for the dedicated review `16_AGENTS/AGENT-SECURITY.md` and `16_AGENTS/AGENT-PERFORMANCE.md` perform once an implementation exists.

---

## Assessment Process

1. Receive the proposed option to assess.
2. Identify applicable risk categories.
3. Estimate likelihood.
4. Estimate impact.
5. Assign a risk level using the Risk Matrix.
6. Recommend mitigation, or note that the risk requires explicit acceptance.
7. Record the assessment.
8. Return the assessment to the requesting component.

---

## Risk Matrix

| Likelihood | Impact | Risk Level |
|---|---|---|
| Low | Low | Low |
| Low | High | Medium |
| Medium | Medium | Medium |
| Medium | High | High |
| High | High | Critical |

---

## Risk Record

| Field | Description |
|---|---|
| Risk ID | Unique identifier |
| Category | Risk classification |
| Description | Summary of the risk |
| Likelihood | Low / Medium / High |
| Impact | Low / Medium / High |
| Risk Level | Low / Medium / High / Critical |
| Mitigation | Recommended action |
| Status | Open / Mitigated / Accepted, with the recorded authorization when accepted |

---

## Permission Boundary

The Risk Assessor may identify risk, estimate likelihood and impact, assign a risk level, recommend mitigation, and record the assessment.

It must not select a strategy itself (owned by `19_REASONING/DECISION-ENGINE.md`), perform tradeoff comparison (owned by `19_REASONING/TRADEOFF-ANALYZER.md`), replace the dedicated security or performance review performed later in the pipeline (owned by `16_AGENTS/AGENT-SECURITY.md` and `16_AGENTS/AGENT-PERFORMANCE.md`), or assume a mandatory handoff to a specific downstream component.

---

## Domain Rule

Risk categories apply identically regardless of domain; domain-specific risk content (for example a WordPress plugin conflict) is expressed through the existing categories, not a separate domain-specific risk system.

---

## Rule

> Critical risks must be mitigated or explicitly accepted, with the acceptance recorded, before implementation proceeds. Every identified risk must have a documented mitigation strategy or justification for acceptance. The Risk Assessor identifies and prioritizes risk for decision support; it does not replace the dedicated security or performance review performed later in the pipeline.
