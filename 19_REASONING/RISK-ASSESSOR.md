# SquirrelForge Risk Assessor

Version: 1.0.0
Status: Draft
Owner: SquirrelForge Maintainers
Depends On: See component references
Used By: See layer README
Last Updated: 2026-07-01

## Purpose

The Risk Assessor identifies, evaluates, and prioritizes risks before implementation proceeds, reducing the likelihood of defects, regressions, security issues, and project delays.

## Responsibilities

- Identify technical risks.
- Identify security risks.
- Identify performance risks.
- Identify project risks.
- Estimate impact and likelihood.
- Recommend mitigation strategies.
- Forward assessed work to the Tradeoff Analyzer.

## Risk Categories

| Category | Description |
|---|---|
| Technical | Architecture, implementation, or dependency risks |
| Security | Authentication, authorization, data protection |
| Performance | Resource usage, scalability, responsiveness |
| Project | Schedule, scope, or resource constraints |
| Operational | Deployment, rollback, maintenance |
| External | Third-party services, libraries, APIs |

## Assessment Process

1. Receive the approved strategy.
2. Identify applicable risk categories.
3. Estimate likelihood.
4. Estimate impact.
5. Assign a risk level.
6. Recommend mitigation.
7. Record the assessment.
8. Pass the assessment to the Tradeoff Analyzer.

## Risk Matrix

| Likelihood | Impact | Risk Level |
|---|---|---|
| Low | Low | Low |
| Low | High | Medium |
| Medium | Medium | Medium |
| Medium | High | High |
| High | High | Critical |

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
| Status | Open / Mitigated / Accepted |

## Rule

Critical risks must be mitigated or explicitly accepted before implementation proceeds. Every identified risk must have a documented mitigation strategy or justification for acceptance.