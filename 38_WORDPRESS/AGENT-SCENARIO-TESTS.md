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
Evidence:
Gap:
```

Result must be one of `PASS`, `PARTIAL`, `FAIL`, or `NOT EXECUTABLE`. This suite verifies documentation and routing traceability through the authoritative control chain (`38_WORDPRESS/WORDPRESS-MANAGER.md` → `38_WORDPRESS/PIPELINE.md` → `38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md` → selected Skill → `33_WORDPRESS_ROLES/ROLE-MANAGER.md` → `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md` → required knowledge → security and standards gates → testing requirements → completion criteria). A `PASS` proves the route, roles, knowledge, gates, and completion criteria are explicitly traceable in the repository — by itself it does not prove that code generated under that route would actually run correctly in a live WordPress environment. Six scenarios, WP-SCENARIO-001, WP-SCENARIO-002, WP-SCENARIO-003, WP-SCENARIO-006, WP-SCENARIO-009, and WP-SCENARIO-010, have additionally been runtime-validated against a live WordPress installation; see the "Runtime Evidence" section below. Those results are bounded to each scenario's specific request and do not extend runtime-validated status to any other scenario in this suite. See `38_WORDPRESS/AGENT-READINESS-REPORT.md`'s Runtime Execution Readiness category for the full distinction between traceability and execution.

---

### Scenario 1 — Create Plugin

```text
Scenario ID: WP-SCENARIO-001
User Request: Create a WordPress plugin with an admin settings page and frontend shortcode.
Expected Primary Skill: CREATE-PLUGIN
Expected Supporting Skills: CREATE-SHORTCODE, CREATE-TESTS, WRITE-DOCUMENTATION
Expected Required Roles: Project Architect, Plugin Architect, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer
Expected Conditional Roles: CSS Engineer, JavaScript Engineer, Release Engineer
Expected Validation Gates: Security, QA, Documentation
Expected Reports: Skill Selection Decision, Role Routing Decision, Plugin Architecture Specification, PHP Implementation Report, Security Review Report, QA Report, Documentation Report
Pass Criteria: Agent selects CREATE-PLUGIN as primary and does not jump directly to code generation.
Result: PASS
Evidence:
  - 38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md, "New Plugin" section — selects CREATE-PLUGIN for plugin-scale requests; lists CREATE-SHORTCODE, CREATE-TESTS, WRITE-DOCUMENTATION as possible supporting Skills.
  - 38_WORDPRESS/SKILLS/CREATE-PLUGIN.md, Stage 2 "Knowledge Selection" — Settings API is listed and required when the request involves a settings page.
  - 38_WORDPRESS/SKILLS/CREATE-SHORTCODE.md, "Security Gates" and "Testing Gates" sections — cover the shortcode component directly.
  - 33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md, "Route 1 — CREATE-PLUGIN" and "Route 5 — CREATE-SHORTCODE" — Required/Conditional Roles match the scenario's expected lists exactly.
  - 38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md, Knowledge Mapping Examples — "Create a settings page" row.
  - 38_WORDPRESS/SKILLS/CREATE-PLUGIN.md, Stage 9 (Security Validation, blocking) and Stage 11 (QA Validation) — security and QA gates present.
  - 38_WORDPRESS/SKILLS/CREATE-PLUGIN.md, "Validation Commands" and "Completion Criteria" sections — concrete PHP syntax/PHPUnit/PHPCS/WP-CLI commands and an explicit completion list.
Gap: None.
```

---

### Scenario 2 — Debug Plugin

```text
Scenario ID: WP-SCENARIO-002
User Request: My plugin crashes when I activate it.
Expected Primary Skill: DEBUG-PLUGIN
Expected Supporting Skills: None initially
Expected Required Roles: Role Manager, Responsible Implementation Engineer, QA Engineer
Expected Conditional Roles: Security Engineer if the fix affects security boundaries, Documentation Engineer if behavior changes
Expected Validation Gates: Reproduction, Cause Confirmation, Fix Validation, QA, Regression
Expected Reports: Debug Final Report, QA Report
Pass Criteria: Agent identifies symptom first, confirms cause before applying a fix, and does not turn debugging into refactoring.
Result: PASS
Evidence:
  - 38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md, "Existing Defect" section — "plugin activation causes a fatal error" is a listed example routing to DEBUG-PLUGIN.
  - 38_WORDPRESS/SKILLS/DEBUG-PLUGIN.md, Stage 1 (Defect Triage/reproduction), Stage 3 (Root Cause Analysis), Stage 4 (Fix Implementation), Stage 7 (Fix Verification/QA and regression) — each gate named in the scenario is a distinct workflow stage.
  - 33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md, "Route 10 — DEBUG-PLUGIN" — Required Roles (Role Manager, responsible Implementation Engineer, QA Engineer) and Conditional Roles (Security Engineer when security boundaries involved, Documentation Engineer when behavior/config changes) match exactly.
  - 38_WORDPRESS/SKILLS/DEBUG-PLUGIN.md, "Validation Commands" section — WP-CLI `--debug` activation reproduction, PHP syntax check, focused/full PHPUnit, `debug.log` tail, activation test; directly applicable to an activation-crash report.
  - 38_WORDPRESS/SKILLS/DEBUG-PLUGIN.md, "Completion Criteria" — root cause identified, fix passes Security/Performance/QA gates before release.
Gap: None. The scenario's gate names (Reproduction, Cause Confirmation) are workflow stages rather than named validation gates in the Skill file; this is a wording difference, not a missing control.
```

---

### Scenario 3 — Review Code

```text
Scenario ID: WP-SCENARIO-003
User Request: Review this plugin and tell me if it is production-ready.
Expected Primary Skill: REVIEW-CODE
Expected Supporting Skills: None unless findings recommend them
Expected Required Roles: Relevant Implementation Engineer, Security Engineer, QA Engineer
Expected Conditional Roles: Performance Engineer, Documentation Engineer, Architect roles
Expected Validation Gates: Scope, Evidence, Security Review, QA/Test Gap Review
Expected Reports: WordPress Code Review Report, Security Review Report
Pass Criteria: Agent produces evidence-based findings and does not silently modify code.
Result: PASS
Evidence:
  - 38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md, "Code Inspection" section — "assess release readiness" is a listed example routing to REVIEW-CODE.
  - 38_WORDPRESS/SKILLS/REVIEW-CODE.md, "Validation Requirements" — scope, files, standards, security/performance/accessibility/compatibility/maintainability, blocking-vs-recommendation separation, and final status are all required.
  - 33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md, "Route 8 — REVIEW-CODE" — Required Roles and Conditional Roles match the scenario's expected lists exactly.
  - 38_WORDPRESS/STANDARDS/CODE-REVIEW-STANDARD.md — Review Areas, Manual Review Checklist, Severity Levels; Agent Rule #1 correctly cites the Code Review stage in `38_WORDPRESS/PIPELINE.md`.
  - 38_WORDPRESS/SKILLS/REVIEW-CODE.md, "Completion Criteria" — review is complete only when scope, findings, risk, fixes, gates, and final status are recorded.
Gap: None currently. (Historical note: `CODE-REVIEW-STANDARD.md` previously cited the deprecated `AGENT-PIPELINE.md`; this was corrected to `PIPELINE.md` in an earlier documentation pass and is confirmed current as of this trace.)
```

---

### Scenario 4 — Refactor Plugin

```text
Scenario ID: WP-SCENARIO-004
User Request: Split this large plugin class into smaller services without changing behavior.
Expected Primary Skill: REFACTOR-CODE
Expected Supporting Skills: CREATE-TESTS when regression coverage is missing
Expected Required Roles: Responsible Implementation Engineer, Security Engineer, QA Engineer
Expected Conditional Roles: Plugin Architect, Documentation Engineer, Release Engineer
Expected Validation Gates: Behavior Baseline, Architecture Impact, Compatibility, Security, QA, Regression
Expected Reports: Refactor Final Report, Compatibility Record, QA Report
Pass Criteria: Agent baselines behavior before changing structure.
Result: PASS
Evidence:
  - 38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md, "Routing Examples" → "Example 6 — Refactoring" — this exact request ("Split this 4,000-line plugin class into smaller services without changing behavior") is a documented routing example with Primary Skill REFACTOR-CODE and Supporting Skill CREATE-TESTS.
  - 38_WORDPRESS/SKILLS/REFACTOR-CODE.md, "Validation Requirements" — intended behavior documented before changes, scope limited, public APIs preserved, regression checks required.
  - 33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md, "Route 9 — REFACTOR-CODE" — Required Roles and Conditional Roles match the scenario's expected lists exactly.
  - 38_WORDPRESS/SKILLS/REFACTOR-CODE.md, "Completion Criteria" — complete only when behavior preservation is verified and regression checks pass.
Gap: None.
```

---

### Scenario 5 — Performance Optimization

```text
Scenario ID: WP-SCENARIO-005
User Request: This admin screen performs 400 database queries and loads slowly.
Expected Primary Skill: OPTIMIZE-PERFORMANCE
Expected Supporting Skills: None initially
Expected Required Roles: Performance Engineer, Responsible Implementation Engineer, QA Engineer
Expected Conditional Roles: Database Engineer, Security Engineer, Documentation Engineer
Expected Validation Gates: Baseline, Bottleneck Confirmation, Remeasurement, QA
Expected Reports: Performance Baseline, Performance Optimization Plan, Performance Result, QA Report
Pass Criteria: Agent measures or records measurement limitations before claiming improvement.
Result: PASS
Evidence:
  - 38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md, "Routing Examples" → "Example 5 — Measured Database Problem" — the same request pattern (admin screen, excessive queries, slow load) is a documented routing example resolving to OPTIMIZE-PERFORMANCE.
  - 38_WORDPRESS/SKILLS/OPTIMIZE-PERFORMANCE.md, Stage 3 (Baseline Measurement, includes "query count" and a "Limitations:" field), Stage 4 (Bottleneck Identification), Stage 8 (Performance Revalidation, marks non-equivalent comparisons explicitly).
  - 33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md, "Route 11 — OPTIMIZE-PERFORMANCE" — Required Roles and Conditional Roles match the scenario's expected lists.
  - 38_WORDPRESS/SKILLS/OPTIMIZE-PERFORMANCE.md, "Completion Criteria" — baseline, bottleneck, optimization plan, revalidation, and QA are all required before completion.
Gap: None for this scenario's own routing. Coverage note: this scenario tests plugin/admin-screen performance; no existing scenario exercises a WordPress *theme* performance review specifically (see readiness report Testing-Guidance Readiness section).
```

---

### Scenario 6 — Plugin Migration

```text
Scenario ID: WP-SCENARIO-006
User Request: Move this plugin from options storage into custom database tables.
Expected Primary Skill: MIGRATE-PLUGIN
Expected Supporting Skills: CREATE-TESTS, WRITE-DOCUMENTATION
Expected Required Roles: Project Architect, Plugin Architect, Database Engineer, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer, Release Engineer
Expected Conditional Roles: Performance Engineer
Expected Validation Gates: Current-State, Migration Strategy, Security, Migration QA, Documentation, Release
Expected Reports: Current-State Record, Migration Strategy, Database Engineering Report, Migration Verification Record, Release Readiness Report
Pass Criteria: Agent defines source state, target state, failure behavior, and rollback limitations.
Result: PASS (after fix — see Gap)
Evidence:
  - 38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md, "Migration" section — "move options into custom tables" is a listed example routing to MIGRATE-PLUGIN.
  - 38_WORDPRESS/SKILLS/MIGRATE-PLUGIN.md, Stage 1 (Current-State Assessment), Stage 5 (Migration Strategy, includes Rollback Strategy and Recovery Strategy fields), Stage 11 (Security), Stage 13 (Migration QA), Stage 14 (Documentation), Stage 15 (Release Review).
  - 33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md, "Route 7 — MIGRATE-PLUGIN" and "Conditional Role Trigger Matrix" (Database Engineer required for custom tables) — Required and Conditional Roles match the scenario's expected list.
  - 38_WORDPRESS/SKILLS/MIGRATE-PLUGIN.md, "Completion Criteria" — current state, target architecture, strategy, role routing, data preservation, security, QA, documentation, and release review are all required.
Gap: FOUND AND FIXED. Before this pass, 38_WORDPRESS/SKILLS/MIGRATE-PLUGIN.md contained an orphaned duplicate draft appended after its closing "## Rule" (a second, conflicting "### Workflow" with a different Stage 1-8 sequence and a second "## Rule" block), making the file's authoritative ending ambiguous. Fixed by removing the orphaned tail and folding its one non-redundant point (never delete prior data until verifiably migrated) into the single closing Rule. Before: file ended with two competing "## Rule" sections. After: file ends with one "## Rule" section (confirmed by re-reading the file after the edit). Rerun of this scenario after the fix: PASS.
```

---

### Scenario 7 — Create REST Endpoint

```text
Scenario ID: WP-SCENARIO-007
User Request: Add a REST endpoint that returns private member records to authorized admins.
Expected Primary Skill: CREATE-REST-ENDPOINT
Expected Supporting Skills: None unless part of larger plugin
Expected Required Roles: REST Engineer, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer
Expected Conditional Roles: Database Engineer, Performance Engineer
Expected Validation Gates: API Contract, Security, QA, Documentation
Expected Reports: REST Route Specification, REST Engineering Report, Security Review Report, QA Report
Pass Criteria: Agent requires permission callback, validation, sanitization, and sensitive-data review.
Result: PASS (after fix — see Gap)
Evidence:
  - 38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md, "New REST Endpoint" section — routes REST-route requests to CREATE-REST-ENDPOINT.
  - 38_WORDPRESS/SKILLS/CREATE-REST-ENDPOINT.md, Stage 4 (Security Validation, blocking gate, "focusing on the permission_callback, argument validation, and sanitization") and Rule #1 ("Mandatory permission_callback") — directly covers "private member records to authorized admins."
  - 38_WORDPRESS/SECURITY-VALIDATOR.md, "REST API Validation" — "Endpoints without permission callbacks fail validation."
  - 33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md, "Route 4 — CREATE-REST-ENDPOINT" — Required and Conditional Roles match the scenario's expected lists exactly.
  - 38_WORDPRESS/SKILLS/CREATE-REST-ENDPOINT.md, "Validation Commands" — authenticated request example (env-var credential placeholders) and an unauthenticated request specifically to verify 401/403 rejection.
Gap: FOUND AND FIXED. Before this pass, 38_WORDPRESS/SKILLS/CREATE-REST-ENDPOINT.md had no explicit "Completion Criteria" section, unlike its sibling Skills (CREATE-PLUGIN, DEBUG-PLUGIN, MIGRATE-PLUGIN, REFACTOR-CODE, REVIEW-CODE all have one), leaving completion criteria only implicit in its Rule list and Final Report instruction. Fixed by adding a concise "Completion Criteria" section listing the same conditions already implied by Stages 1-7 and the Rule list. Before: no "## Completion Criteria" heading present. After: section present, confirmed by re-reading the file after the edit. Rerun of this scenario after the fix: PASS.
```

---

### Scenario 8 — Create Theme

```text
Scenario ID: WP-SCENARIO-008
User Request: Create a block theme for a small business website.
Expected Primary Skill: CREATE-THEME
Expected Supporting Skills: CREATE-TESTS, WRITE-DOCUMENTATION
Expected Required Roles: Project Architect, Theme Architect, PHP Engineer, CSS Engineer, Security Engineer, Performance Engineer, QA Engineer, Documentation Engineer
Expected Conditional Roles: Block Engineer, JavaScript Engineer, Release Engineer
Expected Validation Gates: Architecture, Security, Performance, QA, Documentation
Expected Reports: Theme Architecture Specification, CSS Implementation Report, Security Review Report, Performance Review Report, QA Report
Pass Criteria: Agent routes theme work through Theme Architect and does not place persistent business logic in the theme.
Result: PASS
Evidence:
  - 38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md, "New Theme" section — "create a block theme" is a listed example routing to CREATE-THEME.
  - 38_WORDPRESS/SKILLS/CREATE-THEME.md, "Pipeline Mapping" table — maps every `38_WORDPRESS/PIPELINE.md` stage one-to-one to a responsible role, theme activity, and required output/gate, explicitly marking Security Validation and Standards Validation as BLOCKING GATEs and Final Approval as the FINAL GATE.
  - 38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md, Knowledge Mapping Examples — "Build a block theme" row lists THEME-HANDBOOK.md, BLOCK-EDITOR.md, CODING-STANDARDS.md, ACCESSIBILITY.md, all of which exist.
  - 33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md, "Route 2 — CREATE-THEME" — Required and Conditional Roles match the scenario's expected lists exactly; Block Engineer is correctly triggered for a block theme.
  - 38_WORDPRESS/SKILLS/CREATE-THEME.md, "Theme Boundary Rules" — "Themes must not own critical business logic or durable data behavior that must survive a theme change" matches the Pass Criteria verbatim.
  - 38_WORDPRESS/SKILLS/CREATE-THEME.md, "Completion Criteria" — 12-item checklist covering roles, architecture, implementation, accessibility, security, standards, performance, testing, documentation, and release.
Gap: None. This is the most thoroughly cross-referenced Skill of the eight traced (explicit Pipeline Mapping table).
```

---

### Scenario 9 — Add a Custom Post Type and Taxonomy

```text
Scenario ID: WP-SCENARIO-009
User Request: Add a custom post type for testimonials with a custom taxonomy.
Expected Primary Skill: CREATE-PLUGIN
Expected Supporting Skills: CREATE-TESTS, WRITE-DOCUMENTATION
Expected Required Roles: Project Architect, Plugin Architect, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer
Expected Conditional Roles: CSS Engineer, JavaScript Engineer, Release Engineer
Expected Validation Gates: Security, QA, Documentation
Expected Reports: Skill Selection Decision, Role Routing Decision, PHP Implementation Report, Security Review Report, QA Report, Documentation Report
Pass Criteria: Agent selects CREATE-PLUGIN regardless of new-or-existing project, and required knowledge includes Custom Post Types and Taxonomies.
Result: PASS
Evidence:
  - 38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md, "Custom Post Types, Taxonomies, and Settings Pages" section — this exact request is the section's first listed example, resolving to CREATE-PLUGIN as primary regardless of new-or-existing project.
  - 38_WORDPRESS/SKILLS/CREATE-PLUGIN.md, Stage 2 "Knowledge Selection" — Custom Post Types and Taxonomies are listed and required when their concept is present in the request.
  - 33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md, "Route 1 — CREATE-PLUGIN" — Required/Conditional Roles match the scenario's expected lists exactly.
  - 38_WORDPRESS/KNOWLEDGE/CUSTOM-POST-TYPES.md and 38_WORDPRESS/KNOWLEDGE/TAXONOMIES.md — both exist.
  - 38_WORDPRESS/SKILLS/CREATE-PLUGIN.md, Stage 9 (Security, blocking), Stage 11 (QA), "Completion Criteria" section.
Gap: None.
```

---

### Scenario 10 — Add a Settings API Page to an Existing Plugin

```text
Scenario ID: WP-SCENARIO-010
User Request: Add a Settings API options page to an existing plugin.
Expected Primary Skill: CREATE-PLUGIN
Expected Supporting Skills: CREATE-TESTS, WRITE-DOCUMENTATION
Expected Required Roles: Project Architect, Plugin Architect, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer
Expected Conditional Roles: CSS Engineer, JavaScript Engineer, Release Engineer
Expected Validation Gates: Security, QA, Documentation
Expected Reports: Skill Selection Decision, Role Routing Decision, PHP Implementation Report, Security Review Report, QA Report, Documentation Report
Pass Criteria: Agent selects CREATE-PLUGIN for the existing project (recorded via the Existing or New Project field) and required knowledge includes Settings API.
Result: PASS
Evidence:
  - 38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md, "Custom Post Types, Taxonomies, and Settings Pages" section — this exact request is the section's second listed example; explicitly states CREATE-PLUGIN's Trigger Conditions cover "build plugin functionality" without restricting to new projects, so no separate Skill is needed.
  - 38_WORDPRESS/SKILLS/CREATE-PLUGIN.md, Stage 2 "Knowledge Selection" — Settings API is listed and required when a Settings API options page is involved.
  - 33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md, "Route 1 — CREATE-PLUGIN" — Required/Conditional Roles match the scenario's expected lists exactly.
  - 38_WORDPRESS/KNOWLEDGE/SETTINGS-API.md — exists.
  - 38_WORDPRESS/KNOWLEDGE/SECURITY.md, "Nonces" section — directly applicable to a settings-save form.
  - 38_WORDPRESS/STANDARDS/TESTING-STANDARD.md, Plugin Testing Checklist — "settings persist" item.
Gap: None.
```

---

### Scenario 11 — WordPress Plugin Security Review

```text
Scenario ID: WP-SCENARIO-011
User Request: Perform a security review of this WordPress plugin.
Expected Primary Skill: REVIEW-CODE
Expected Supporting Skills: None unless findings recommend them
Expected Required Roles: Role Manager, relevant Implementation Engineer, Security Engineer, QA Engineer
Expected Conditional Roles: Performance Engineer, Documentation Engineer, Architect roles
Expected Validation Gates: Scope, Evidence, Security Review, QA/Test Gap Review
Expected Reports: WordPress Code Review Report, Security Review Report
Pass Criteria: The request follows WordPress routing precedence (not the general Security Agent) and Security Engineer is a required, not merely conditional, role.
Result: PASS
Evidence:
  - 12_AGENT/CAPABILITY-ROUTER.md, "Domain Precedence Rule" → "Precedence Examples" → "Security review of a WordPress plugin" — Primary Owner WordPress Manager, Route REVIEW-CODE → 33_WORDPRESS_ROLES/SECURITY-ENGINEER.md; the general 16_AGENTS/AGENT-SECURITY.md is supporting-only and only if explicitly called.
  - 38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md, "Code Inspection" section — evidence-based assessment requests route to REVIEW-CODE.
  - 33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md, "Route 8 — REVIEW-CODE" — Security Engineer is a Required Role (not conditional).
  - 38_WORDPRESS/SECURITY-VALIDATOR.md and 38_WORDPRESS/STANDARDS/CODE-REVIEW-STANDARD.md, "Review Areas" #1 (Security) — full security checklist available to the review.
  - 38_WORDPRESS/SKILLS/REVIEW-CODE.md, "Completion Criteria" section.
Gap: None.
```

---

### Scenario 12 — WordPress Theme Performance Review

```text
Scenario ID: WP-SCENARIO-012
User Request: Perform a performance review of this WordPress theme.
Expected Primary Skill: OPTIMIZE-PERFORMANCE
Expected Supporting Skills: None initially
Expected Required Roles: Role Manager, Performance Engineer, responsible Implementation Engineer, Performance Engineer Revalidation, QA Engineer
Expected Conditional Roles: CSS Engineer as implementation owner, Theme Architect or Plugin Architect when structural/template changes are needed, Security Engineer, Documentation Engineer, Release Engineer
Expected Validation Gates: Baseline, Bottleneck Confirmation, Remeasurement, QA
Expected Reports: Performance Baseline, Performance Optimization Plan, Performance Result, QA Report
Pass Criteria: The request follows WordPress routing precedence and resolves to OPTIMIZE-PERFORMANCE (not CREATE-THEME) with Theme and Performance roles both available and measurement gates present.
Result: PASS (after fix — see Gap)
Evidence:
  - 38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md, "Performance Work" section — "the primary objective is performance improvement" selects OPTIMIZE-PERFORMANCE regardless of subject (plugin or theme).
  - 12_AGENT/CAPABILITY-ROUTER.md, "Precedence Examples" → "Performance review of a WordPress theme" — corrected in this pass to route through OPTIMIZE-PERFORMANCE rather than CREATE-THEME.
  - 38_WORDPRESS/SKILLS/OPTIMIZE-PERFORMANCE.md, Stage 2 "Role Routing" — now explicitly adds "Theme Architect or Plugin Architect when the optimization requires template, block, or structural boundary changes"; Required References now include `38_WORDPRESS/KNOWLEDGE/THEME-HANDBOOK.md` when the target is a theme.
  - 33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md, "Route 11 — OPTIMIZE-PERFORMANCE" — Conditional Roles now include Theme Architect/Plugin Architect.
  - 38_WORDPRESS/SKILLS/OPTIMIZE-PERFORMANCE.md, Stage 3 (Baseline Measurement), Stage 4 (Bottleneck Identification), Stage 8 (Performance Revalidation) — measurement gates present.
Gap: FOUND AND FIXED. Before this pass, `12_AGENT/CAPABILITY-ROUTER.md`'s own precedence example routed a WordPress theme performance review through CREATE-THEME + PERFORMANCE-ENGINEER.md, directly contradicting `SKILL-ROUTING-MAP.md`'s general "Performance Work" rule (which selects OPTIMIZE-PERFORMANCE for any performance-improvement objective) and the already-passing WP-SCENARIO-005 pattern. Neither `OPTIMIZE-PERFORMANCE.md` nor `ROLE-ROUTING-MATRIX.md` Route 11 mentioned a Theme Architect role or theme-specific knowledge at all. Fixed by (1) correcting the CAPABILITY-ROUTER.md example to route through OPTIMIZE-PERFORMANCE, (2) adding "Theme Architect or Plugin Architect when the optimization requires template, block, or structural boundary changes" to both `OPTIMIZE-PERFORMANCE.md`'s Stage 2 and `ROLE-ROUTING-MATRIX.md` Route 11's Conditional Roles, and (3) adding `THEME-HANDBOOK.md` as a conditional required reference in `OPTIMIZE-PERFORMANCE.md` when the target is a theme. Rerun after fix: PASS.
```

---

### Scenario 13 — WordPress Plugin Integrating with an External API

```text
Scenario ID: WP-SCENARIO-013
User Request: Build a WordPress plugin that integrates with an external API.
Expected Primary Skill: CREATE-PLUGIN
Expected Supporting Skills: CREATE-REST-ENDPOINT when the plugin also exposes its own REST route, CREATE-TESTS, WRITE-DOCUMENTATION
Expected Required Roles: Project Architect, Plugin Architect, PHP Engineer, Security Engineer, QA Engineer, Documentation Engineer
Expected Conditional Roles: REST Engineer, Release Engineer
Expected Validation Gates: Security, QA, Documentation
Expected Reports: Skill Selection Decision, Role Routing Decision, PHP Implementation Report, Security Review Report, QA Report, Documentation Report
Pass Criteria: One primary WordPress owner is identified; explicit API/security boundaries, credential handling, error handling, and validation requirements are all traceable.
Result: PASS
Evidence:
  - 12_AGENT/CAPABILITY-ROUTER.md, "Precedence Examples" → "WordPress plugin integrating with an external API" — Primary Owner WordPress Manager, Route CREATE-PLUGIN (supporting CREATE-REST-ENDPOINT), Supporting Boundary Owner 26_INTEGRATIONS/Integration Agent for the external service's own auth/contract/failure-mode requirements only — one primary owner, no duplicate ownership of the plugin itself.
  - 38_WORDPRESS/KNOWLEDGE/PLUGIN-HANDBOOK.md, "Performance Considerations" ("Use Transients for Caching... external API calls") and "Best Practices" ("Error Handling: Use the WP_Error class").
  - 38_WORDPRESS/SECURITY-VALIDATOR.md, "API Credentials" section — secrets must come from options, environment variables, or secure configuration, never hardcoded.
  - 38_WORDPRESS/KNOWLEDGE/SECURITY.md, Core Principle — "Never trust any data... whether from users, third-party APIs, or even the database."
  - 33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md, "Route 1 — CREATE-PLUGIN" Conditional Roles — REST Engineer "for REST routes, API contracts, authenticated operations, or public operations."
Gap: None. Minor observation, not blocking: no single dedicated "external API integration" knowledge file exists; coverage is distributed across PLUGIN-HANDBOOK.md, SECURITY.md, and SECURITY-VALIDATOR.md.
```

---

### Scenario 14 — WordPress Plugin Deployment Request

```text
Scenario ID: WP-SCENARIO-014
User Request: Prepare this WordPress plugin for deployment.
Expected Primary Skill: REVIEW-CODE
Expected Supporting Skills: None
Expected Required Roles: Role Manager, relevant Implementation Engineer, Security Engineer, QA Engineer, Release Engineer
Expected Conditional Roles: Performance Engineer, Documentation Engineer, Architect roles
Expected Validation Gates: Scope, Evidence, Security Review, QA/Test Gap Review, Release Readiness
Expected Reports: WordPress Code Review Report, Security Review Report, Release Readiness Report
Pass Criteria: The request routes through the WordPress release path (REVIEW-CODE → Release Engineer), not through the general Release Agent as primary owner.
Result: PASS (after fix — see Gap)
Evidence:
  - 38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md, "Code Inspection" section — "assess release readiness" is a listed example routing to REVIEW-CODE.
  - 12_AGENT/CAPABILITY-ROUTER.md, "Precedence Examples" → "WordPress deployment request" — corrected in this pass to name REVIEW-CODE explicitly as the triggering Skill before Role Routing Matrix selects Release Engineer; the general 16_AGENTS/AGENT-RELEASE.md is supporting-only, invoked to perform actual release-action execution once WordPress readiness is approved.
  - 33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md, "Route 8 — REVIEW-CODE" — Conditional Roles now include "Release Engineer when the review's objective is release or deployment readiness."
  - 33_WORDPRESS_ROLES/RELEASE-ENGINEER.md — "Required Approval Gates" table, "Final Release Decisions" (GO/CONDITIONAL GO/NO-GO/HOLD), and "Handoff" section ("approved release status returns to the WordPress Role Manager and WordPress Manager").
Gap: FOUND AND FIXED. Before this pass, `12_AGENT/CAPABILITY-ROUTER.md`'s own precedence example for a WordPress deployment request named the Role Routing Matrix and Release Engineer directly without naming which Skill selects them first, breaking the required Skill-before-Role sequencing in the authoritative control chain. `ROLE-ROUTING-MATRIX.md` Route 8 (REVIEW-CODE) also did not list Release Engineer as a role at all, even though "assess release readiness" is one of REVIEW-CODE's own documented trigger examples. Fixed by naming REVIEW-CODE explicitly as the triggering Skill in the CAPABILITY-ROUTER.md example, and adding "Release Engineer when the review's objective is release or deployment readiness" to Route 8's Conditional Roles. Rerun after fix: PASS.
```

---

## Runtime Evidence

Documentation/routing traceability (recorded per scenario above) is distinct from runtime execution evidence. This section records the six scenarios, so far, that have actually been run against a live WordPress installation. It supplements WP-SCENARIO-001's, WP-SCENARIO-002's, WP-SCENARIO-003's, WP-SCENARIO-006's, WP-SCENARIO-009's, and WP-SCENARIO-010's traceability results above — it does not replace any of those results, and it does not extend runtime-validated status to any other scenario in this suite.

### Runtime Validation — WP-SCENARIO-001 (Create a WordPress plugin with an administrator Settings API page and frontend shortcode)

```text
Scenario Reference: WP-SCENARIO-001
Runtime Target: Hospital WordPress installation ($HOME/Local Sites/hospital/app/public)
Plugin Path: wp-content/plugins/squirrelforge-runtime-validation
Plugin Created As: standalone plugin

PHP Syntax Lint: PASS (all plugin PHP files)
Focused PHPUnit: PASS
Full Plugin PHPUnit Suite: PASS — 11 tests, 20 assertions
Plugin Activation: PASS — activated successfully, appeared active
Setting Registration: PASS — setting registered through the Settings API
Shortcode Reads Saved Value: PASS — saved setting value was retrieved by the shortcode
Output Escaping: PASS — shortcode output escaped unexpected stored HTML
Plugin Deactivation: PASS — deactivated successfully
Plugin Reactivation: PASS — reactivated successfully
Corrected-Run PHP Errors: None captured
WP-CLI Availability: Unavailable
Runtime Method Used: a safe, direct wp-load.php PHP runtime script, in place of WP-CLI
Debug Log: Unavailable — WP_DEBUG_LOG was not enabled on the target installation
Repository Boundary: CSHD, WordPress core, themes, and other plugins were untouched

Validation-Harness Issue (disclosed):
  The first harness run fired admin_init without first loading the real
  admin template functions, so add_settings_section() was undefined and
  that run failed. The harness was corrected to load the same wp-admin
  include (wp-admin/includes/template.php) that WordPress itself
  guarantees is loaded before admin_init ever fires in a real request.
  The corrected run passed. This was a defect in the validation harness,
  not in the plugin or in any SquirrelForge routing document, and is not
  counted as a plugin or routing defect.

Scope of This Evidence:
  This runtime result applies only to WP-SCENARIO-001's specific request
  (CREATE-PLUGIN with a Settings API page and a shortcode), executed once
  against one WordPress installation. It does not runtime-validate
  CREATE-PLUGIN for other request shapes, and it does not runtime-validate
  any other Skill or any of the remaining 12 documentation scenarios in
  this suite.
```

### Runtime Validation — WP-SCENARIO-009 (Add a Custom Post Type and Taxonomy)

```text
Scenario Reference: WP-SCENARIO-009
Runtime Target: Hospital WordPress installation ($HOME/Local Sites/hospital/app/public)
Plugin Path: wp-content/plugins/squirrelforge-cpt-taxonomy-validation
Plugin Created As: standalone plugin, no runtime dependency on Composer or another plugin

PHP Syntax Lint: PASS (all three authored plugin PHP files)
Focused PHPUnit: PASS — 2 tests, 6 assertions
Full Plugin PHPUnit Suite: PASS — 10 tests, 27 assertions
SquirrelForge Full Suite: PASS — 146 tests, 338 assertions (unaffected)

Live Environment: Hospital WordPress installation; WP-CLI unavailable; execution
  performed through fresh PHP processes bootstrapping wp-load.php, using Local's
  site-matched PHP binary and database socket; strict runtime error capture
  enabled throughout; no debug.log was created (WP_DEBUG_LOG not enabled).

Activation and Registration: PASS
  - Activation completed without a fatal error; plugin became active.
  - Rewrite rules were flushed by the activation routine.
  - `sfctv_testimonial` registered successfully on a fresh bootstrap.
  - `sfctv_category` registered successfully on a fresh bootstrap.
  - Both were absent after deactivation, confirmed on a fresh bootstrap.
  - Both returned after reactivation, confirmed on another fresh bootstrap.

Custom Post Type Evidence:
  - public: true; show_ui: true; has_archive: true; show_in_rest: true
  - REST base: sfctv_testimonial
  - capability type: standard post capabilities (no custom capability introduced);
    edit_posts capability resolved to the core edit_posts capability
  - supports: title, editor, thumbnail
  - rewrite slug: testimonials

Taxonomy Evidence:
  - taxonomy: sfctv_category
  - hierarchical: true; show_in_rest: true
  - attached only to: sfctv_testimonial
  - rewrite slug: testimonial-category

REST Evidence: Built-in WordPress REST routes were confirmed for both the CPT and
  the taxonomy, including their collection and single-item routes. No custom REST
  endpoint was required or created; built-in exposure via show_in_rest was
  sufficient for this scenario's scope.

Rewrite and Permalink Evidence:
  - 23 rewrite rules matched the testimonial CPT pattern
  - 5 rewrite rules matched the testimonial taxonomy pattern
  - active site permalink structure: /%postname%/
  - testimonial URLs use the /testimonials/ structure
  - taxonomy URLs use the /testimonial-category/ structure
  - rewrite rules were not flushed during ordinary init execution (flushing is
    isolated to the activation and deactivation hooks only)

Persistence Evidence: Runtime validation created exactly one testimonial post,
  one taxonomy term, and one term assignment connecting them. Verified: post
  type, title, and content were correct and retrievable; the term existed in the
  expected taxonomy; the assignment was confirmed through both
  wp_get_object_terms() and get_the_terms(); the assignment remained intact
  through the deactivation/reactivation cycle; unrelated core `post` and
  `category` counts were unchanged before and after (proving isolation from
  core content types).

Deactivation and Reactivation Evidence:
  - Deactivation removed the plugin's runtime registration, confirmed on a
    fresh bootstrap. Deactivation did not delete the stored post row — a
    direct database query confirmed the row remained present with its
    `sfctv_testimonial` post_type value intact even while the type was
    unregistered. Deactivation must not be read as deleting content.
  - Reactivation restored both registrations on another fresh bootstrap, and
    the stored term assignment remained intact. Repeated activation and
    rewrite-rule flushing completed without any fatal error.

Cleanup and Boundaries:
  - All temporary post and term data created during validation was deleted;
    final counts confirmed zero remaining validation-CPT posts and zero
    remaining validation-taxonomy terms.
  - Zero PHP warnings, notices, deprecations, or errors were captured at any
    step.
  - CSHD remained clean and unchanged (`git status --short` / `git diff --check`
    both clean before and after).
  - The existing squirrelforge-runtime-validation plugin (WP-SCENARIO-001)
    remained untouched and active throughout.
  - All other Hospital plugins remained untouched.
  - The SquirrelForge repository remained unchanged during runtime execution;
    no routing, Skill, Role, knowledge, or scenario-definition file was
    modified as part of running this scenario.

Scope of This Evidence:
  This runtime result applies only to WP-SCENARIO-009's specific request
  (CREATE-PLUGIN registering a custom post type and an attached hierarchical
  taxonomy), executed once against one WordPress installation. It does not
  runtime-validate CREATE-PLUGIN for other request shapes beyond this,
  WP-SCENARIO-001, and WP-SCENARIO-010, and it does not runtime-validate any
  other Skill or any of the remaining 11 documentation scenarios in this
  suite.
```

### Runtime Validation — WP-SCENARIO-010 (Add a Settings API Page to an Existing Plugin)

```text
Scenario Reference: WP-SCENARIO-010
Runtime Target: Hospital WordPress installation ($HOME/Local Sites/hospital/app/public)
Plugin Path: wp-content/plugins/squirrelforge-settings-api-validation
Plugin Created As: standalone plugin representing the "add a Settings API page to
  an existing plugin" request shape. This scenario's own request describes adding
  a Settings API page to an existing plugin; the runtime validation used an
  isolated, standalone validation plugin rather than modifying a real existing
  plugin (CSHD or otherwise), consistent with the repository-boundary
  restrictions and the precedent set by WP-SCENARIO-001 and WP-SCENARIO-009.
  This validates the Settings API request shape itself, not the act of
  modifying a specific production plugin.

PHP Syntax Lint: PASS (all three authored plugin PHP files)
Focused PHPUnit: PASS — 1 test, 2 assertions
Full Plugin PHPUnit Suite: PASS — 17 tests, 28 assertions
SquirrelForge Full Suite: PASS — 146 tests, 338 assertions (unaffected)

Live Environment: Hospital WordPress installation; WP-CLI unavailable; execution
  performed through fresh PHP processes bootstrapping wp-load.php, using Local's
  site-matched PHP binary and database socket; strict runtime error capture
  enabled throughout; no debug.log was created (WP_DEBUG_LOG not enabled).

Settings Registration Evidence:
  - register_setting() registration confirmed on a fresh bootstrap
  - settings group: sfsav_settings_group
  - sanitization callback attached
  - settings section registered (add_settings_section)
  - settings field registered (add_settings_field)
  - settings page rendered successfully under an authenticated administrator
    context
  - page access is protected by the manage_options capability

Capability Behavior (disclosed harness note, not a plugin defect):
  The first rendering attempt correctly reached a real wp_die() when the
  page's manage_options capability check ran against the CLI bootstrap's
  unauthenticated default context. This was a gap in the validation harness'
  own context, not a defect in the plugin: the settings page enforcing its
  capability check against an unauthenticated request is the correct,
  intended behavior. The harness was corrected by looking up an existing
  administrator through a read-only get_users() query and calling
  wp_set_current_user() to give that one process an in-memory authenticated
  context only -- no user account, option, or other persistent data was
  modified by this step. The corrected render then completed successfully.
  This is positive evidence that the manage_options gate is enforced, not a
  runtime failure.

Sanitization Evidence (a save-time control, distinct from output escaping):
  The live sanitize_callback executed on every save. sanitize_text_field()
  removed a submitted <script>...</script> payload entirely (this is bounded
  evidence for this plugin's text-field sanitization path; it is not a
  general claim that sanitize_text_field() is a universal sanitizer for
  every context). Because the result was empty after stripping, this
  plugin's own sanitize_value() then returned its defined default per its
  documented empty-value fallback behavior. No unsafe script value was
  persisted through the normal Settings API save path.

Persistence and Update-Lifecycle Evidence:
  - the option value persisted and get_option() retrieved it correctly
  - an existing saved value was updated from a generic "Value A" to a
    generic "Value B", confirmed overwritten
  - the replacement value ("Value B") remained correct across a fresh
    WordPress bootstrap
  - this update lifecycle -- changing an already-saved value, not just
    creating one -- is the principal additional runtime behavior this
    scenario demonstrates beyond WP-SCENARIO-001

Output Escaping Evidence (an output-time control, distinct from sanitization):
  Rendering was tested using a raw value written directly to the database
  (bypassing the normal save path's sanitize callback) so the output layer
  could be verified on its own terms. The payload contained script markup,
  an ampersand, and quotation marks. Confirmed:
  - the field input escaped its value with esc_attr()
  - the page preview escaped its value with esc_html()
  - the raw markup was not emitted as executable HTML in either location
  - the value was restored to a benign state immediately after the check

Activation, Deactivation, and Reactivation Evidence:
  - activation completed without a fatal error
  - activation seeds a default value via add_option() (a no-op when the
    option already exists)
  - activation did not overwrite an existing saved value
  - deactivation removed the setting's Settings API registration, confirmed
    on a fresh bootstrap
  - deactivation did not delete the stored option value
  - reactivation restored the registration, confirmed on another fresh
    bootstrap, and preserved the existing value unchanged

Cleanup and Boundaries:
  - the validation option was explicitly deleted; zero remaining
    scenario-created option data was confirmed afterward
  - zero PHP warnings, notices, deprecations, or errors were captured on the
    corrected runs
  - no debug.log was created
  - CSHD remained clean and unchanged (git status --short / git diff --check
    both clean before and after)
  - the WP-SCENARIO-001 validation plugin remained untouched
  - the WP-SCENARIO-009 validation plugin remained untouched
  - all other Hospital plugins remained untouched
  - the SquirrelForge repository remained unchanged and at HEAD abf7ef0
    during runtime execution; no routing, Skill, Role, knowledge, or
    scenario-definition file was modified as part of running this scenario

Scope of This Evidence:
  This runtime result applies only to WP-SCENARIO-010's specific request
  (a Settings API options page, including its update lifecycle), executed
  once against one WordPress installation using a standalone validation
  plugin. It does not runtime-validate modifying an actual existing
  production plugin, it does not runtime-validate CREATE-PLUGIN for other
  request shapes beyond this, WP-SCENARIO-001, and WP-SCENARIO-009, and it
  does not runtime-validate any other Skill or any of the remaining 11
  documentation scenarios in this suite. It does not establish that all
  settings pages, all sanitization contexts, or all capability models are
  broadly proven.
```

### Runtime Validation — WP-SCENARIO-006 (Plugin Migration)

```text
Scenario Reference: WP-SCENARIO-006
Runtime Target: Hospital WordPress installation ($HOME/Local Sites/hospital/app/public)
Plugin Path: wp-content/plugins/squirrelforge-migration-validation
Plugin Created As: standalone plugin representing the "move plugin data from
  options storage into a custom database table" request shape.

Primary Skill: MIGRATE-PLUGIN. This is the first bounded runtime execution of
  a primary Skill other than CREATE-PLUGIN; WP-SCENARIO-001, WP-SCENARIO-009,
  and WP-SCENARIO-010 all runtime-validated CREATE-PLUGIN.

PHP Syntax Lint: PASS (all four authored plugin PHP files)
Focused PHPUnit: PASS — 3 tests, 6 assertions
Full Plugin PHPUnit Suite: PASS — 20 tests, 51 assertions
SquirrelForge Full Suite: PASS — 146 tests, 338 assertions (unaffected)

Pre-Execution Implementation Defect (disclosed):
  Automated testing found a real dead-code logic defect in verify_migration()
  before live execution began: a blanket row-count comparison short-circuited
  ahead of the per-record "missing target row" check and made the final
  "unexpected target row" check mathematically unreachable whenever the
  count check passed. This was a genuine defect in the validation plugin's
  implementation, not a test-authoring mistake, and it was corrected rather
  than hidden or accommodated through weakened tests -- the count check was
  replaced with independent set-difference checks for missing and unexpected
  identifiers. Focused verification tests passed after the correction (3
  tests, 6 assertions), and the full plugin suite passed after the
  correction (20 tests, 51 assertions). Live execution used only the
  corrected implementation. No readiness or routing defect was involved.
  This was a pre-execution implementation defect, not a live runtime
  failure.

Live Environment: Hospital WordPress installation; single-site; MySQL 8.4.0;
  live table prefix wp_; utf8mb4 charset and live database collation;
  WP-CLI unavailable; execution performed through fresh PHP processes
  bootstrapping wp-load.php, using Local's site-matched PHP binary and
  database socket; strict runtime error capture enabled throughout; no
  debug.log was created (WP_DEBUG_LOG not enabled).

Source State:
  The bounded legacy source used option sfmig_legacy_records holding five
  source records with fields legacy_id, label, value, and created_at. Test
  data included ordinary text, punctuation and quotation marks, Unicode and
  emoji, a value longer than 1,400 characters, distinct identifiers, and
  distinct timestamps. No production secrets or personal data were used.

Target State:
  Custom table {$wpdb->prefix}sfmig_records with columns id, legacy_id,
  label, value, created_at, and migrated_at. id is the primary key;
  legacy_id carries a unique key. Schema was created with dbDelta(); table
  naming used $wpdb->prefix; charset and collation used
  $wpdb->get_charset_collate().

Clean-Install Path: PASS
  Activation with no legacy option present created the schema, created zero
  target rows, did not invent a legacy option, and did not falsely mark a
  migration complete.

Upgrade Path: PASS
  The legacy option was staged before activation of the upgrade path.
  Activation then created/confirmed the schema, migrated all five records,
  reached migration status complete, stored database version 1.0.0, and
  left the source option intact.

Exhaustive Fidelity Evidence:
  All five source records were compared exhaustively, not sampled, across
  legacy_id, label, value, and created_at. All five source identifiers
  appeared exactly once in the target; no unexpected target identifiers
  existed; row count matched the source count; no duplicate legacy_id
  values existed; no values were lost, truncated, duplicated, or
  reinterpreted. A serialized representation of the complete source option
  was captured before migration and re-compared after every later sequence;
  it remained unchanged throughout migration testing. The literal hash
  value is not recorded here as it is not needed to demonstrate this
  result.

Database Write Behavior:
  Implementation used a prepared lookup by legacy_id, $wpdb->insert() for
  new rows, $wpdb->update() for existing rows, and checked both the
  database return value and $wpdb->last_error on every write. Raw
  INSERT ... ON DUPLICATE KEY UPDATE was not used.

Idempotency: PASS
  Re-running migration after completion did not add rows, did not
  duplicate identifiers, did not alter source data, did not change verified
  target values, and remained safely complete.

Partial Batch and Resume: PASS
  A validation-only batch limit migrated three of five records; migration
  status remained in_progress; the source stayed unchanged. A subsequent
  unlimited run migrated the remaining two records; existing rows were not
  duplicated; all five records then passed exhaustive verification; status
  reached complete. This proves resumability from a controlled batch
  boundary, not recovery from every possible crash.

Controlled Failure: PASS
  One scenario-owned malformed source record was introduced with a missing
  required value field. Migration returned a structured WP_Error
  identifying the malformed record and the missing field; migration status
  changed to failed; no false complete state was recorded; the five
  already-valid rows stayed unchanged; source data was restored; recovery
  returned cleanly to complete.

Migration States Observed: not_started, in_progress, complete, failed.
  Partial batches remained in_progress; successful verification reached
  complete; duplicate execution remained safely complete; malformed input
  produced failed; failure was not silently overwritten.

Rollback: PASS
  Rollback dropped the custom table, reset migration status to
  not_started, removed the database-version option, left the original
  legacy source option unchanged, and did not affect unrelated tables or
  options. This validates database-state rollback while the source remains
  available; it does not prove reversal of production traffic, deployed
  application code, or every production upgrade condition.

Cleanup and Boundaries:
  - the target table was removed
  - sfmig_legacy_records was removed during final cleanup
  - all scenario-owned sfmig_* metadata options were removed
  - zero scenario-owned database artifacts remained afterward
  - the temporary harness file was removed
  - baseline wp_options count immediately before execution was 293; final
    count was 292; direct inspection proved zero scenario-owned sfmig_*
    options remained; the one-row difference was attributed to unrelated
    WordPress transient/background option churn observed live during the
    session and was not treated as scenario residue (a global options-row
    count alone is not a reliable cleanup test; the absence check above is
    the actual evidence)
  - zero PHP warnings, notices, deprecations, or errors were captured at
    any step
  - CSHD remained clean and unchanged (git status --short / git diff
    --check both clean before and after)
  - the WP-SCENARIO-001, WP-SCENARIO-009, and WP-SCENARIO-010 validation
    plugins remained untouched
  - all other Hospital plugins remained untouched
  - the SquirrelForge repository remained unchanged during runtime
    execution; no routing, Skill, Role, knowledge, or scenario-definition
    file was modified as part of running this scenario

Security and Database Review:
  - direct-access guard present
  - no hardcoded table prefix ($wpdb->prefix used throughout)
  - no public migration endpoint; no AJAX handler; no REST route; no
    $_GET, $_POST, or $_REQUEST migration trigger
  - the validation batch limit existed only as an internal PHP argument
  - no user-controlled SQL identifiers; reads used prepared SQL; writes
    used WordPress database APIs
  - destructive operations targeted only the scenario-owned table and
    options
  - migration never deleted the legacy source option

Multisite: NOT EXECUTABLE IN THIS ENVIRONMENT — single-site installation.
  Multisite-specific table-prefix and network migration behavior was not
  runtime exercised. This is a scoped environment limitation and does not
  change the overall scenario classification from PASS.

Scope of This Evidence:
  This runtime result applies only to WP-SCENARIO-006's specific request
  (moving plugin data from options storage into a custom database table,
  including clean-install, upgrade, duplicate-run, partial-batch/resume,
  controlled-failure, and rollback behavior), executed once against one
  WordPress installation using a standalone validation plugin. It is the
  first runtime evidence for MIGRATE-PLUGIN and does not runtime-validate
  MIGRATE-PLUGIN for other request shapes, does not runtime-validate any
  other Skill, and does not runtime-validate any of the remaining 10
  documentation scenarios in this suite. It does not prove that arbitrary
  production migrations are safe, that large data volumes have been
  performance tested, that concurrent migration execution has been tested,
  that every MySQL or WordPress version is supported, that multisite
  migrations are proven, that production traffic cutover is proven, that
  destructive source cleanup is proven safe, or that rollback of deployed
  application code is proven.
```

### Runtime Validation — WP-SCENARIO-002 (Debug Plugin)

```text
Scenario Reference: WP-SCENARIO-002
Runtime Target: Hospital WordPress installation ($HOME/Local Sites/hospital/app/public)
Plugin Path: wp-content/plugins/squirrelforge-debug-validation
Plugin Created As: standalone validation plugin, intentionally shipped in a
  defective activation state first, then corrected in place -- the first
  runtime scenario in this suite whose evidence is a debugging cycle
  (reproduce, diagnose, fix, regression-test) rather than building working
  output from a clean start.

Primary Skill: DEBUG-PLUGIN. This is the first bounded runtime execution of
  this Skill; it demonstrates that SquirrelForge can diagnose and correct
  existing defective WordPress code, distinct from WP-SCENARIO-001,
  WP-SCENARIO-009, and WP-SCENARIO-010 (CREATE-PLUGIN) and WP-SCENARIO-006
  (MIGRATE-PLUGIN).

PHP Syntax Lint: PASS (all three authored plugin PHP files, before and after
  the fix)
Pre-Fix Targeted Regression Test: FAILED as expected -- 1 test, 1 assertion,
  1 error (the intentional defect, confirmed reproducing in the automated
  suite before any correction was applied)
Post-Fix Targeted Regression Test: PASS -- 1 test, 2 assertions
Full Plugin PHPUnit Suite (post-fix): PASS -- 10 tests, 14 assertions
SquirrelForge Full Suite: PASS -- 146 tests, 338 assertions (unaffected)

Live Environment: Hospital WordPress installation; single-site; WP-CLI
  unavailable; execution performed through fresh PHP processes bootstrapping
  wp-load.php, using Local's site-matched PHP binary and database socket;
  strict runtime error capture (error_reporting(E_ALL), a custom error
  handler, and a try/catch around every activation attempt with a
  shutdown-function fallback) enabled throughout; no debug.log was created
  or fabricated (WP_DEBUG_LOG not enabled).

Intentional Defect: The activation callback read a scenario-owned option
  with get_option() and, without validating its type, passed the result
  directly to an array-only operation. On a fresh installation the option
  is absent and get_option() returns false, so activation threw.

Disclosed Adjustment To The Planned Defect Shape (not a live runtime
  failure; found and handled before finalizing the defect): the originally
  planned direct array-offset dereference ($config['mode'] on a false
  value) was tested directly first and found to only emit a PHP warning in
  PHP 8 (return value null), not a thrown error. An equivalent array
  operation, array_key_exists() against the same false value, was
  confirmed by direct test to throw a genuine uncaught TypeError
  deterministically, and was used instead so the scenario's requirement of
  a real, deterministic PHP 8 error was actually satisfied rather than
  assumed.

Pre-Fix Reproduction Evidence:
  - activation was attempted via activate_plugin() inside a try/catch on a
    fresh bootstrap with no scenario-owned option present
  - a TypeError was caught: "array_key_exists(): Argument #2 ($array) must
    be of type array, false given"
  - file and line pinpointed the exact defective statement in the plugin's
    own activation callback
  - a full stack trace was captured showing propagation through
    WP_Hook->do_action() and activate_plugin()
  - the plugin did not remain active after the failed attempt
  - the Hospital site remained reachable and operational in a subsequent
    fresh process (confirmed via get_bloginfo())
  - the active-plugins list was confirmed unchanged from before the attempt
  - zero unrelated PHP warnings, notices, or deprecations were captured

Diagnosis:
  - Symptom: activation throws when the callback treats a non-array option
    value as an array.
  - Root Cause: the callback assumed get_option() always returns a valid
    configuration array, but a fresh installation returns false, and the
    callback never validated the returned type before using it as an array.
  - Rejected symptom-masking alternatives: error suppression, a broad
    try/catch that silently ignores the failure, disabling the activation
    routine, the @ operator, and blind unchecked casting were all rejected
    in favor of explicit type validation and a bounded default.

Smallest Safe Fix: the defective line was replaced with an explicit
  is_array() check and a bounded 'safe' default, applied only inside the
  one affected method -- no other method, file, or behavior was changed.

Post-Fix Runtime Verification (all against the live installation):
  - Missing option: activation succeeded, no throwable, default mode
    'safe' applied, zero captured PHP issues
  - Malformed option (string): activation succeeded, default mode applied,
    zero captured PHP issues
  - Malformed option (boolean false, the exact original crash shape):
    activation succeeded, default mode applied, zero captured PHP issues --
    directly disproving recurrence of the original defect
  - Valid option (a configured mode value): activation succeeded, the
    configured value was used and confirmed unchanged on a subsequent
    fresh-process read
  - Deactivation: succeeded; plugin became inactive; scenario-owned option
    data was left untouched by deactivation itself (no deactivation hook is
    registered, by design)
  - Reactivation: repeated across three full reactivate/deactivate cycles;
    every reactivation succeeded, the configured value remained correct,
    and the original TypeError never recurred

Cleanup and Boundaries:
  - all scenario-owned options (the configuration option and the resolved-
    mode option) were deleted; a direct query confirmed zero sfdbg_*
    options remained afterward
  - the temporary harness script was deleted from the scratchpad directory
  - the corrected plugin remains installed but was deliberately left
    inactive, to satisfy the explicit requirement that zero scenario-owned
    options remain afterward (reactivating once more would have recreated
    the resolved-mode option as harmless-but-real state)
  - zero PHP warnings, notices, deprecations, or errors were captured on
    any post-fix run
  - CSHD remained clean and unchanged (git status --short / git diff
    --check both clean before and after)
  - the WP-SCENARIO-001, WP-SCENARIO-006, WP-SCENARIO-009, and
    WP-SCENARIO-010 validation plugins remained untouched
  - all other Hospital plugins remained untouched
  - the SquirrelForge repository remained unchanged during runtime
    execution; no routing, Skill, Role, knowledge, or scenario-definition
    file was modified as part of running this scenario

Security and Boundary Review:
  - direct-access guard present
  - no public debugging trigger; no AJAX handler; no REST route; no
    $_GET, $_POST, or $_REQUEST usage anywhere in the plugin
  - no database-table work, no raw SQL
  - no external network access
  - no destructive operations; the plugin touches only its own two options

Scope of This Evidence:
  This runtime result applies only to WP-SCENARIO-002's specific request (an
  activation-time crash caused by an unvalidated non-array option value,
  diagnosed and corrected on a standalone validation plugin), executed once
  against one WordPress installation. It is the first runtime evidence for
  DEBUG-PLUGIN and does not runtime-validate DEBUG-PLUGIN for other defect
  shapes (e.g. a runtime-only defect outside activation, a defect spanning
  multiple files, a database-related defect, a JavaScript defect), and does
  not runtime-validate any other Skill or any of the remaining 9
  documentation scenarios in this suite. It does not prove that every class
  of WordPress plugin defect is diagnosable this way, that defects requiring
  WP-CLI or a browser-driven reproduction are covered, or that multi-file or
  multi-plugin-conflict debugging is proven.
```

### Runtime Validation — WP-SCENARIO-003 (Review Code)

```text
Scenario Reference: WP-SCENARIO-003
Runtime Target: Hospital WordPress installation ($HOME/Local Sites/hospital/app/public)
Plugin Path: wp-content/plugins/squirrelforge-review-fixture
Plugin Created As: a deliberately flawed but runnable single-file fixture plugin
  (56 lines) containing exactly five deterministic, independently-locatable
  seeded issues -- not a plugin intended to demonstrate correct behavior, but
  a fixed inspection target.

Primary Skill: REVIEW-CODE. This is the first bounded runtime execution of
  this Skill; unlike every prior runtime scenario, the objective was not to
  build or fix working code but to prove SquirrelForge can accurately inspect
  existing code and report evidence-based findings without modifying it.

Pre-Review Environment Check: the fixture was activated against the live
  installation in a fresh wp-load.php process; activation completed with no
  throwable, confirming the plugin loads before review (a pre-review
  environment check, not part of the review itself).

Review Process (locked before any runtime validation):
  A structured Code Review Report was produced by static inspection of the
  fixture's actual source, citing exact file and line for every finding,
  before any live validation step was run. The five findings were:
    - F-1 (Critical): permission_callback set to __return_true on a
      data-writing REST route (line 23)
    - F-2 (Warning): unsanitized REST request data passed directly to
      update_option() (lines 29-31)
    - F-3 (Critical): the stored value echoed with no escaping (line 53)
    - F-4 (Critical): a destructive admin-post action gated by
      current_user_can('read') instead of an admin-level capability
      (line 39)
    - F-5 (Notice): a single camelCase variable name against an otherwise
      snake_case file (line 50) -- correctly classified as non-blocking,
      not inflated to a defect
  No-Invented-Findings Statement: the review identified exactly the five
  seeded deterministic findings. No additional blocking defects were
  reported, and no additional non-blocking recommendations were reported.
  Final Review Status (the review's own verdict on the fixture, distinct
  from this scenario's PASS classification below): NOT APPROVED FOR
  PRODUCTION -- three Critical and one Warning finding require correction.

Independent Runtime Validation of Locked Findings (performed only after the
  review above was locked, and explicitly kept separate from the review
  itself -- this validates the findings, it is not part of how they were
  produced):
  - F-1/F-2: an unauthenticated WP_REST_Request POST to /sfrev/v1/notes
    succeeded (HTTP 200, {"saved":true}) and stored the literal payload
    <script>alert(1)</script> verbatim in the option -- confirming both the
    unauthenticated-write and missing-sanitization findings live.
  - F-3: the render function emitted the stored value with no escaping,
    producing <div class="sfrev-notes-panel"><script>alert(1)</script></div>
    -- confirming the unescaped-output finding live.
  - F-4: a freshly created Subscriber-level user (confirmed to hold 'read'
    but not 'manage_options') successfully passed the plugin's own
    capability gate and deleted the option -- confirming the wrong-capability
    finding is genuinely exploitable, not merely textually incorrect.

Quantitative Metrics:
  Seeded deterministic findings: 5
  Correctly identified: 5
  False positives: 0
  False negatives: 0
  Blocking findings correctly classified: Yes (3 Critical + 1 Warning)
  Non-blocking findings correctly classified: Yes (1 Notice)
  Critical findings independently validated live: 3 of 3
  Source modified during review: No

Non-Modification Proof: SHA-256 of the fixture's source file was recorded
  before the review (67803c2b8926f505c67bf411f4a7ea1056cd99935fca27b7e8a645e1
  b1c906f6) and re-verified identical after the review and all runtime
  validation steps completed.

Live Environment: Hospital WordPress installation; single-site; WP-CLI
  unavailable; execution performed through fresh PHP processes bootstrapping
  wp-load.php, using Local's site-matched PHP binary and database socket;
  strict runtime error capture enabled throughout; zero PHP warnings,
  notices, deprecations, or errors were captured at any step.

Cleanup and Boundaries:
  - the scenario-owned option (sfrev_notes) and the temporary Subscriber-
    level check user created for F-4 validation were both removed; a direct
    query confirmed zero sfrev_* options remained afterward
  - the fixture plugin was deactivated; the plugin file itself remains
    installed (harmless, single-file, no runtime side effects when inactive)
  - the temporary harness script was deleted from the scratchpad directory
  - CSHD remained clean and unchanged (git status --short / git diff
    --check both clean before and after)
  - the WP-SCENARIO-001, WP-SCENARIO-002, WP-SCENARIO-006, WP-SCENARIO-009,
    and WP-SCENARIO-010 validation plugins remained untouched
  - all other Hospital plugins remained untouched
  - the SquirrelForge repository remained unchanged during runtime
    execution; no routing, Skill, Role, knowledge, or scenario-definition
    file was modified as part of running this scenario

Scope of This Evidence:
  This runtime result applies only to WP-SCENARIO-003's specific request (a
  full-plugin code review producing evidence-based, severity-ranked findings
  without modifying the reviewed code), executed once against one
  deliberately-seeded fixture. It is the first runtime evidence for
  REVIEW-CODE and does not runtime-validate REVIEW-CODE for other review
  shapes (e.g. a multi-file plugin, a theme, JavaScript/block code, a review
  that also spans performance or accessibility concerns), and does not
  runtime-validate any other Skill or any of the remaining 8 documentation
  scenarios in this suite. It does not prove that every class of WordPress
  defect is detectable this way, that reviews of larger or unfamiliar
  codebases are proven, or that this reflects the full range of findings a
  production review might need to surface.
```

---

## Scenario Test Summary

```text
Scenarios Run: 14
Scenarios Passed: 14 (10 passed directly; 4 passed after a small, scenario-exposed documentation fix — see Scenario 6, Scenario 7, Scenario 12, and Scenario 14 Gap fields)
Scenarios Failed: 0
Blocked Scenarios: 0
Routing Errors: 0 (1 pre-existing routing contradiction found and fixed — see Scenario 12)
Missing Skills: 0
Missing Roles: 0
Missing Validation Gates: 0
Overall Scenario Status: All 14 defined scenarios pass documentation/routing traceability, including the 6 scenario classes (Custom Post Type + taxonomy, Settings API on an existing plugin, a WordPress-specific security review, a WordPress theme performance review, a plugin integrating with an external API, and a WordPress deployment request) that were previously absent from this suite. This status covers routing readiness for all 14 scenarios. Six of the 14, WP-SCENARIO-001, WP-SCENARIO-002, WP-SCENARIO-003, WP-SCENARIO-006, WP-SCENARIO-009, and WP-SCENARIO-010, have additionally been runtime-validated against a live WordPress installation (see "Runtime Evidence" above); the remaining 8 scenarios have not been executed against a live WordPress environment. See 38_WORDPRESS/AGENT-READINESS-REPORT.md for the full readiness breakdown, including the Runtime Execution Readiness category.
```

---

## Rule

The WordPress Agent must pass scenario tests before it can be considered operational for real WordPress development work.

A passed scenario in this suite demonstrates routing and documentation traceability. It does not demonstrate that the Agent's output has been executed or verified in a live WordPress environment.

WordPress agent scenarios must validate routing, role handoffs, safety gates, and final outputs before readiness is accepted.
