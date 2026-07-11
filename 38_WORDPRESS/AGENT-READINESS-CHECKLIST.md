Status: Stable

---
# SquirrelForge WordPress Agent Readiness Checklist

## Purpose

Verifies that the WordPress Agent has the managers, routing rules, skills, roles, knowledge, standards, and validation gates required for controlled operation.

## Checklist

- WordPress Manager exists.
- Pipeline exists.
- Skill routing map exists.
- Role Manager exists.
- Role routing matrix exists.
- Required Skills exist.
- Required Roles exist.
- Required Knowledge documents exist.
- Required Standards documents exist.
- Security Validator exists.
- QA and release gates are defined.
- Agent boot sequence exists.
- Agent execution contract exists.

## Verification

All items above were confirmed present during the scenario-tracing passes recorded in `38_WORDPRESS/AGENT-SCENARIO-TESTS.md` (2026-07-10 and 2026-07-11): all 14 defined scenarios resolved through the WordPress Manager, Pipeline, Skill Routing Map, Role Manager, and Role Routing Matrix with exact evidence citations. This confirms the checklist items exist and are linked; for 10 of the 14 scenarios it does not confirm they produce working WordPress output, since those scenarios were not run against a live WordPress environment. Four scenarios, WP-SCENARIO-001, WP-SCENARIO-006, WP-SCENARIO-009, and WP-SCENARIO-010, have additionally been run against a live WordPress installation and all four passed — including live custom post type and taxonomy registration, REST exposure, rewrite behavior, and post/term persistence for WP-SCENARIO-009; live Settings API registration, administrator capability enforcement, save-time sanitization, an update lifecycle (changing an already-saved value), and separately-verified attribute/HTML output escaping for WP-SCENARIO-010; and, for WP-SCENARIO-006, the first runtime evidence for a Skill other than CREATE-PLUGIN — live custom-table schema creation, exhaustive source-to-target fidelity, insert/update idempotency, partial-batch resume, a controlled structured-failure path, and database-state rollback with source-option preservation (multisite migration behavior: NOT EXECUTABLE IN THIS ENVIRONMENT — single-site installation); see `38_WORDPRESS/AGENT-SCENARIO-TESTS.md`'s "Runtime Evidence" section. Broad runtime coverage remains incomplete: 11 of 13 Skills and 10 of 14 scenarios are still runtime-unevaluated. See `38_WORDPRESS/AGENT-READINESS-REPORT.md` for the full, category-separated readiness decision.

## Rule

The WordPress Agent is not ready for autonomous execution until required managers, skills, roles, knowledge sources, standards, and validation gates are present and linked.
