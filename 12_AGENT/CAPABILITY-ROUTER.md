# SquirrelForge Capability Router

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: `12_AGENT/COLLECTION-MANIFEST.md`, Skill Catalog, Workflow Selector, Agent API
Used By: Bootstrap, Task Router, Project Loader, and agent hosts
Last Updated: 2026-07-04

## Purpose

The Capability Router maps an incoming request to the correct source layers, workflow, specialist agent, domain knowledge, tools, and validation requirements.

It prevents the agent from choosing capabilities by habit or loading unrelated domain rules.

---

## Routing Principle

> Route by requested outcome, affected domain, risk, and required evidence.

A request should have one primary route. Supporting routes may be added only when they represent distinct quality, safety, lifecycle, recovery, or domain needs.

---

## Primary Routing Table

| Request Type | Primary Source Layers | Primary Workflow | Lead Agent | Required Verification |
|---|---|---|---|---|
| Plan a project | `14_ENGINE`, `19_REASONING`, `02_WORKFLOWS`, `03_CHECKLISTS` | Project Planning | Architect / Planner | Architecture and project checklist |
| Clean documentation | `README.md`, `ARCHITECTURE.md`, affected layer READMEs, `23_GOVERNANCE` | Documentation Maintenance | Documentation Agent | Link/reference check and consistency review |
| Build a plugin | `38_WORDPRESS`, `14_ENGINE`, `20_EXECUTION`, `29_TESTING`, `24_SECURITY` | Plugin Development | Developer | Syntax, unit, integration, security, activation, smoke |
| Build a theme | `38_WORDPRESS`, `14_ENGINE`, `20_EXECUTION`, `29_TESTING`, `24_SECURITY` | Theme Development | Developer | Accessibility, responsive, template, system, smoke |
| Build a block | `38_WORDPRESS`, `26_INTEGRATIONS`, `29_TESTING`, `24_SECURITY` | Block Development | Developer | Build, editor, frontend, accessibility, smoke |
| Add a feature | Relevant domain layer, `14_ENGINE`, `19_REASONING`, `20_EXECUTION`, `29_TESTING` | Feature Development | Developer | Risk-based unit and integration tests |
| Fix a defect | Relevant domain layer, `20_EXECUTION`, `29_TESTING`, `35_RESILIENCE` | Bug Fix | Developer | Reproduction, fix evidence, regression test |
| Review code | `16_AGENTS`, `19_REASONING`, `24_SECURITY`, `29_TESTING` | Code Review | Reviewer | Review checklist and validation evidence |
| Audit security | `24_SECURITY`, relevant domain layer, `23_GOVERNANCE` | Security Review | Security Agent | Findings, severity, remediation, retest evidence |
| Improve performance | `32_OPTIMIZATION`, relevant domain layer, `27_OBSERVABILITY`, `29_TESTING` | Performance Optimization | Performance Agent | Before/after measurement and regression evidence |
| Review accessibility | Relevant domain layer, `29_TESTING`, `03_CHECKLISTS` | Accessibility Review | Reviewer | Accessibility evidence and retest |
| Add tests | `29_TESTING`, relevant domain layer, `20_EXECUTION` | Testing | Developer / Reviewer | Test report and coverage rationale |
| Write documentation | `36_COMMUNICATION`, `15_TEMPLATES`, relevant source layers | Documentation | Documentation Agent | Accuracy, links, metadata, accessibility |
| Prepare release | `23_GOVERNANCE`, `02_WORKFLOWS`, `29_TESTING`, `35_RESILIENCE` | Release | Release Agent | Smoke, regression, quality gates, rollback readiness |
| Recover from failure | `35_RESILIENCE`, `20_EXECUTION`, `27_OBSERVABILITY`, `29_TESTING` | Recovery | Recovery Agent | State review, rollback or repair evidence, validation |
| Configure automation | `33_AUTOMATION`, `21_CONFIGURATION`, `28_RUNTIME-CONFIG`, `23_GOVERNANCE` | Automation Setup | Automation Agent | Trigger, condition, permission, and audit evidence |
| Integrate external tool | `26_INTEGRATIONS`, `24_SECURITY`, `21_CONFIGURATION`, `29_TESTING` | Integration Development | Integration Agent | Auth, permission, contract, and failure-mode tests |
| Optimize agent behavior | `34_AIDRIVER`, `30_LEARNING`, `32_OPTIMIZATION`, `23_GOVERNANCE` | AI Driver / Optimization | AI Driver Agent | Evaluation evidence and rollback path |

---

## Domain Routing

| Domain | Load When | Source |
|---|---|---|
| WordPress | The request involves plugins, themes, blocks, WP admin, WP REST, WP database, WP cron, media, WooCommerce, or WordPress deployment. | `38_WORDPRESS` |
| Security | The request involves permissions, secrets, credentials, auth, data exposure, destructive actions, external access, or production risk. | `24_SECURITY` |
| Testing | The request changes behavior, fixes a defect, prepares release, or asks for validation. | `29_TESTING` |
| Governance | The request changes architecture, release rules, quality gates, lifecycle policy, or deprecation state. | `23_GOVERNANCE` |
| Observability | The request involves logs, metrics, traces, diagnostics, dashboards, alerts, or incident review. | `27_OBSERVABILITY` |
| Learning | The request involves feedback, evaluation, experience records, or governed improvement. | `30_LEARNING` |
| Automation | The request involves scheduled, event-driven, rule-driven, or conditional work. | `33_AUTOMATION` |
| Runtime Config | The request involves profiles, feature flags, secrets, runtime settings, or environment configuration. | `28_RUNTIME-CONFIG` |

---

## Domain Precedence Rule

A request may match both a general route in the Primary Routing Table and the WordPress trigger in Domain Routing above. WordPress precedence resolves the conflict:

1. If the request is clearly about WordPress code, plugins, themes, REST endpoints, block development, WordPress debugging, or WordPress deployment, `38_WORDPRESS/WORDPRESS-MANAGER.md` owns Skill and Role selection for that work. Route through `38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md` and `38_WORDPRESS/ROLES/ROLE-ROUTING-MATRIX.md` instead of the Primary Routing Table's Lead Agent column.
2. `16_AGENTS` specialists (Security, Performance, Reviewer, Release, and similar) act only as supporting specialists on WordPress work, and only when explicitly called by the WordPress route. They do not independently claim ownership of a WordPress Skill, Role selection, or finding.
3. If the request is not WordPress-specific, general Agent routing through the Primary Routing Table proceeds unchanged and the WordPress Manager is not invoked.
4. If a request spans WordPress and another domain, `38_WORDPRESS/WORDPRESS-MANAGER.md` owns the WordPress implementation boundary and the other domain's manager or specialist owns its own boundary. One primary owner must be identified for the overall request; the other owner supports a distinct boundary and never duplicates the same responsibility.

### Precedence Examples

**Security review of a WordPress plugin**

```text
Primary Owner: WordPress Manager (38_WORDPRESS/WORDPRESS-MANAGER.md)
Route: REVIEW-CODE Skill → 38_WORDPRESS/ROLES/SECURITY-ENGINEER.md
Supporting: 16_AGENTS/AGENT-SECURITY.md only if explicitly called by the WordPress route
Reason: The request is WordPress-specific; the WordPress Security Engineer role, not the general Security Agent, owns the finding.
```

**Performance review of a WordPress theme**

```text
Primary Owner: WordPress Manager (38_WORDPRESS/WORDPRESS-MANAGER.md)
Route: OPTIMIZE-PERFORMANCE Skill → 38_WORDPRESS/ROLES/PERFORMANCE-ENGINEER.md
Supporting: 16_AGENTS/AGENT-PERFORMANCE.md only if explicitly called by the WordPress route
Reason: The request is WordPress-specific and its primary objective is performance improvement, so `38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md`'s "Performance Work" rule selects OPTIMIZE-PERFORMANCE regardless of whether the target is a plugin or a theme; CREATE-THEME governs building a new theme, not reviewing an existing one's performance. The WordPress Performance Engineer role owns the finding.
```

**Generic PHP security review unrelated to WordPress**

```text
Primary Owner: Security Agent (16_AGENTS/AGENT-SECURITY.md)
Route: Primary Routing Table → Audit security → Security Agent
Reason: No WordPress domain trigger is present; the WordPress Manager is not invoked and general Agent routing applies unchanged.
```

**WordPress plugin integrating with an external API**

```text
Primary Owner: WordPress Manager (38_WORDPRESS/WORDPRESS-MANAGER.md)
Route: CREATE-PLUGIN Skill, supporting CREATE-REST-ENDPOINT Skill
Supporting Boundary Owner: 26_INTEGRATIONS / Integration Agent for the external service's own auth, contract, and failure-mode requirements
Reason: The deliverable is a WordPress plugin, so the WordPress Manager owns the overall implementation boundary; the Integration Agent owns only the external-API boundary, not the plugin itself.
```

**WordPress deployment request**

```text
Primary Owner: WordPress Manager (38_WORDPRESS/WORDPRESS-MANAGER.md)
Route: REVIEW-CODE Skill (per 38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md's "Code Inspection" → "assess release readiness" example) → Role Routing Matrix → 38_WORDPRESS/ROLES/RELEASE-ENGINEER.md for WordPress production readiness
Supporting: 16_AGENTS/AGENT-RELEASE.md, invoked explicitly by the WordPress Manager, to perform the actual release action execution once readiness is approved
Reason: A standalone "prepare for deployment" request with no accompanying feature or fix work is a readiness assessment, not new implementation, so it routes as REVIEW-CODE; Release Engineer is the conditional role that owns WordPress production readiness within that review. The general Release Agent supports execution because it is the only implemented release-action mechanism, not a competing owner.
```

---

## Selection Rules

1. Use one primary workflow based on the requested outcome.
2. Add supporting workflows only for distinct quality, safety, lifecycle, recovery, or domain needs.
3. Route each task to one owning agent and record every handoff.
4. Increase reasoning depth and test coverage with risk, irreversibility, production impact, interface impact, and user-data exposure.
5. Load WordPress references only when the request is WordPress-specific.
6. Load Security when permissions, secrets, destructive changes, authentication, authorization, or external systems are involved.
7. Load Testing before claiming a project-changing task is complete.
8. If no route fits, return to goal clarification or create a governed capability proposal; do not improvise an undocumented workflow.

---

## Risk Escalation

Escalate routing depth when the request involves:

- production systems,
- database writes,
- destructive file operations,
- authentication or authorization,
- secrets or credentials,
- deployment,
- dependency upgrades,
- schema changes,
- external APIs,
- payment or order data,
- personal or sensitive data,
- irreversible actions,
- or incomplete previous work.

Escalation may add Security, Governance, Resilience, Observability, Testing, or manual approval requirements.

---

## Universal Operating Loop

```text
Receive → Understand → Classify → Route → Load Context → Reason → Plan → Execute → Validate → Report → Remember
```

---

## Rule

> The Capability Router must select capabilities from the current architecture, not from stale layer names or copied domain assumptions.
