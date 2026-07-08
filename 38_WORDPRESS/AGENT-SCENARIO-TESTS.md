Status: Stable

---
# SquirrelForge WordPress Agent Scenario Tests

## Purpose

This document defines controlled test requests used to verify that the WordPress Agent can correctly interpret intent, select Skills, route Roles, enforce validation gates, and produce final reports.

---

## Test Format

Each scenario must record:

```text
Scenario ID:
User Request:
Expected Primary Skill:
Expected Supporting Skills:
Expected Required Roles:
Expected Conditional Roles:
Expected Validation Gates:
Expected Reports:
Pass Criteria:
Result:
Scenario 1 — Create Plugin
Scenario ID: WP-SCENARIO-001
User Request: Create a WordPress plugin with an admin settings page and frontend shortcode.
Expected Primary Skill: CREATE-PLUGIN
Expected Supporting Skills: CREATE-SHORTCODE, CREATE-TESTS, WRITE-DOCUMENTATION
Expected Required Roles: Project Architect, Plugin Architect, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer
Expected Conditional Roles: CSS Engineer, JavaScript Engineer, Release Engineer
Expected Validation Gates: Security, QA, Documentation
Expected Reports: Skill Selection Decision, Role Routing Decision, Plugin Architecture Specification, PHP Implementation Report, Security Review Report, QA Report, Documentation Report
Pass Criteria: Agent selects CREATE-PLUGIN as primary and does not jump directly to code generation.
Result:
Scenario 2 — Debug Plugin
Scenario ID: WP-SCENARIO-002
User Request: My plugin crashes when I activate it.
Expected Primary Skill: DEBUG-PLUGIN
Expected Supporting Skills: None initially
Expected Required Roles: Role Manager, Responsible Implementation Engineer, QA Engineer
Expected Conditional Roles: Security Engineer if the fix affects security boundaries, Documentation Engineer if behavior changes
Expected Validation Gates: Reproduction, Cause Confirmation, Fix Validation, QA, Regression
Expected Reports: Debug Final Report, QA Report
Pass Criteria: Agent identifies symptom first, confirms cause before applying a fix, and does not turn debugging into refactoring.
Result:
Scenario 3 — Review Code
Scenario ID: WP-SCENARIO-003
User Request: Review this plugin and tell me if it is production-ready.
Expected Primary Skill: REVIEW-CODE
Expected Supporting Skills: None unless findings recommend them
Expected Required Roles: Relevant Implementation Engineer, Security Engineer, QA Engineer
Expected Conditional Roles: Performance Engineer, Documentation Engineer, Architect roles
Expected Validation Gates: Scope, Evidence, Security Review, QA/Test Gap Review
Expected Reports: WordPress Code Review Report, Security Review Report
Pass Criteria: Agent produces evidence-based findings and does not silently modify code.
Result:
Scenario 4 — Refactor Plugin
Scenario ID: WP-SCENARIO-004
User Request: Split this large plugin class into smaller services without changing behavior.
Expected Primary Skill: REFACTOR-CODE
Expected Supporting Skills: CREATE-TESTS when regression coverage is missing
Expected Required Roles: Responsible Implementation Engineer, Security Engineer, QA Engineer
Expected Conditional Roles: Plugin Architect, Documentation Engineer, Release Engineer
Expected Validation Gates: Behavior Baseline, Architecture Impact, Compatibility, Security, QA, Regression
Expected Reports: Refactor Final Report, Compatibility Record, QA Report
Pass Criteria: Agent baselines behavior before changing structure.
Result:
Scenario 5 — Performance Optimization
Scenario ID: WP-SCENARIO-005
User Request: This admin screen performs 400 database queries and loads slowly.
Expected Primary Skill: OPTIMIZE-PERFORMANCE
Expected Supporting Skills: None initially
Expected Required Roles: Performance Engineer, Responsible Implementation Engineer, QA Engineer
Expected Conditional Roles: Database Engineer, Security Engineer, Documentation Engineer
Expected Validation Gates: Baseline, Bottleneck Confirmation, Remeasurement, QA
Expected Reports: Performance Baseline, Performance Optimization Plan, Performance Result, QA Report
Pass Criteria: Agent measures or records measurement limitations before claiming improvement.
Result:
Scenario 6 — Plugin Migration
Scenario ID: WP-SCENARIO-006
User Request: Move this plugin from options storage into custom database tables.
Expected Primary Skill: MIGRATE-PLUGIN
Expected Supporting Skills: CREATE-TESTS, WRITE-DOCUMENTATION
Expected Required Roles: Project Architect, Plugin Architect, Database Engineer, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer, Release Engineer
Expected Conditional Roles: Performance Engineer
Expected Validation Gates: Current-State, Migration Strategy, Security, Migration QA, Documentation, Release
Expected Reports: Current-State Record, Migration Strategy, Database Engineering Report, Migration Verification Record, Release Readiness Report
Pass Criteria: Agent defines source state, target state, failure behavior, and rollback limitations.
Result:
Scenario 7 — Create REST Endpoint
Scenario ID: WP-SCENARIO-007
User Request: Add a REST endpoint that returns private member records to authorized admins.
Expected Primary Skill: CREATE-REST-ENDPOINT
Expected Supporting Skills: None unless part of larger plugin
Expected Required Roles: REST Engineer, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer
Expected Conditional Roles: Database Engineer, Performance Engineer
Expected Validation Gates: API Contract, Security, QA, Documentation
Expected Reports: REST Route Specification, REST Engineering Report, Security Review Report, QA Report
Pass Criteria: Agent requires permission callback, validation, sanitization, and sensitive-data review.
Result:
Scenario 8 — Create Theme
Scenario ID: WP-SCENARIO-008
User Request: Create a block theme for a small business website.
Expected Primary Skill: CREATE-THEME
Expected Supporting Skills: CREATE-TESTS, WRITE-DOCUMENTATION
Expected Required Roles: Project Architect, Theme Architect, PHP Engineer, CSS Engineer, Security Engineer, Performance Engineer, QA Engineer, Documentation Engineer
Expected Conditional Roles: Block Engineer, JavaScript Engineer, Release Engineer
Expected Validation Gates: Architecture, Security, Performance, QA, Documentation
Expected Reports: Theme Architecture Specification, CSS Implementation Report, Security Review Report, Performance Review Report, QA Report
Pass Criteria: Agent routes theme work through Theme Architect and does not place persistent business logic in the theme.
Result:
Scenario Test Summary
Scenarios Run:
Scenarios Passed:
Scenarios Failed:
Blocked Scenarios:
Routing Errors:
Missing Skills:
Missing Roles:
Missing Validation Gates:
Overall Scenario Status:
Rule

The WordPress Agent must pass scenario tests before it can be considered operational for real WordPress development work.

## Rule

WordPress agent scenarios must validate routing, role handoffs, safety gates, and final outputs before readiness is accepted.
