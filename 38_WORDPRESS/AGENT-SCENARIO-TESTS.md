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

Result must be one of `PASS`, `PARTIAL`, `FAIL`, or `NOT EXECUTABLE`. This suite verifies documentation and routing traceability through the authoritative control chain (`38_WORDPRESS/WORDPRESS-MANAGER.md` → `38_WORDPRESS/PIPELINE.md` → `38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md` → selected Skill → `33_WORDPRESS_ROLES/ROLE-MANAGER.md` → `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md` → required knowledge → security and standards gates → testing requirements → completion criteria). A `PASS` proves the route, roles, knowledge, gates, and completion criteria are explicitly traceable in the repository — by itself it does not prove that code generated under that route would actually run correctly in a live WordPress environment. One scenario, WP-SCENARIO-001, has additionally been runtime-validated once against a live WordPress installation; see the "Runtime Evidence" section below. That result is bounded to WP-SCENARIO-001's specific request and does not extend runtime-validated status to any other scenario in this suite. See `38_WORDPRESS/AGENT-READINESS-REPORT.md`'s Runtime Execution Readiness category for the full distinction between traceability and execution.

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

Documentation/routing traceability (recorded per scenario above) is distinct from runtime execution evidence. This section records the first, and so far only, scenario that has actually been run against a live WordPress installation. It supplements WP-SCENARIO-001's traceability result above — it does not replace that result, and it does not extend runtime-validated status to any other scenario in this suite.

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
  any other Skill or any of the remaining 13 documentation scenarios in
  this suite.
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
Overall Scenario Status: All 14 defined scenarios pass documentation/routing traceability, including the 6 scenario classes (Custom Post Type + taxonomy, Settings API on an existing plugin, a WordPress-specific security review, a WordPress theme performance review, a plugin integrating with an external API, and a WordPress deployment request) that were previously absent from this suite. This status covers routing readiness for all 14 scenarios. One of the 14, WP-SCENARIO-001, has additionally been runtime-validated once against a live WordPress installation (see "Runtime Evidence" above); the remaining 13 scenarios have not been executed against a live WordPress environment. See 38_WORDPRESS/AGENT-READINESS-REPORT.md for the full readiness breakdown, including the Runtime Execution Readiness category.
```

---

## Rule

The WordPress Agent must pass scenario tests before it can be considered operational for real WordPress development work.

A passed scenario in this suite demonstrates routing and documentation traceability. It does not demonstrate that the Agent's output has been executed or verified in a live WordPress environment.

WordPress agent scenarios must validate routing, role handoffs, safety gates, and final outputs before readiness is accepted.
