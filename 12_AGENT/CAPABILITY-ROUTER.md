# SquirrelForge Capability Router

Version: 1.0.0
Status: Stable
Owner: Engine Maintainers
Depends On: Skill Catalog, Workflow Selector, Agent API
Used By: Bootstrap and Task Router
Last Updated: 2026-07-01

## Routing Table

| Request | Primary Skill | Primary Workflow | Lead Agent | Required Verification |
|---|---|---|---|---|
| Plan a project | Project Planner | Feature Development | Architect / Planner | Architecture and project checklist |
| Build a plugin | Plugin Developer | Plugin Development | Developer | Unit, integration, security, smoke |
| Build a theme | Theme Developer | Theme Development | Developer | Accessibility, system, smoke |
| Add a feature | Relevant implementation skill | Feature Development | Developer | Risk-based unit and integration tests |
| Fix a defect | Bug Fixer | Bug Fix | Developer | Reproduction and regression test |
| Review code | Code Reviewer | Code Review | Reviewer | Review checklist and validation |
| Audit security | Security Auditor | Security Review | Security Agent | Security findings and retest evidence |
| Improve performance | Performance Optimizer | Performance Optimization | Performance Agent | Before/after measurement and regression |
| Review accessibility | Accessibility Reviewer | Accessibility Review | Reviewer | Accessibility evidence and retest |
| Add tests | Testing | Testing | Developer / Reviewer | Test report |
| Write documentation | Documentation Writer | Documentation | Documentation Agent | Accuracy, links, metadata, accessibility |
| Prepare release | Deployment Assistant | Release | Release Agent | Smoke, regression, quality gates, rollback readiness |

## Selection Rules

1. Use one primary workflow based on the requested outcome.
2. Add supporting workflows only for distinct quality or lifecycle needs.
3. Route each task to one owning agent and record every handoff.
4. Increase reasoning depth and test coverage with risk, irreversibility, and interface impact.
5. If no route fits, return to goal clarification or create a governed capability proposal; do not improvise an undocumented workflow.

## Universal Operating Loop

```text
Receive → Understand → Route → Reason → Plan → Execute → Validate → Report → Remember
```
