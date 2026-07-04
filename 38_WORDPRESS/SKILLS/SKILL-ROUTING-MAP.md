# SquirrelForge WordPress Skill Routing Map

## Purpose

The Skill Routing Map determines which WordPress Skill must handle a request before Role routing begins.

It converts analyzed user intent into a controlled Skill selection decision.

The Skill Routing Map prevents:

- selecting the wrong workflow
- jumping directly from user request to code generation
- mixing debugging with refactoring
- mixing refactoring with migration
- optimizing without measurement
- reviewing code while silently changing it
- creating tests without defined expected behavior
- documenting behavior that has not been validated

---

## Position in the WordPress Pipeline

```text
User Request
↓
Intent Analysis
↓
Knowledge Selection
↓
Requirements Definition
↓
Skill Routing Map
↓
Skill Selection
↓
Architecture Planning when required
↓
Role Manager
↓
Role Routing Matrix
↓
Implementation or Review Workflow
↓
Validation Gates
↓
Final Result
```

---

## Required References

The Skill Routing Map must coordinate with:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/WORDPRESS-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/SKILLS/`
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`
- `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`

---

## Available Skills

| Skill | Primary Purpose |
|---|---|
| `CREATE-PLUGIN.md` | Create a new WordPress plugin or major plugin feature system. |
| `CREATE-THEME.md` | Create a WordPress theme, child theme, block theme, classic theme, or hybrid theme. |
| `CREATE-BLOCK.md` | Create a custom block or Block Editor extension. |
| `CREATE-REST-ENDPOINT.md` | Create or modify a WordPress REST API endpoint. |
| `CREATE-SHORTCODE.md` | Create a WordPress shortcode. |
| `CREATE-WIDGET.md` | Create a WordPress widget or sidebar feature. |
| `MIGRATE-PLUGIN.md` | Perform controlled plugin architecture, data, API, or compatibility migration. |
| `REVIEW-CODE.md` | Inspect existing code and produce evidence-based findings. |
| `REFACTOR-CODE.md` | Restructure code while preserving approved external behavior. |
| `DEBUG-PLUGIN.md` | Diagnose and fix a reproducible plugin defect. |
| `OPTIMIZE-PERFORMANCE.md` | Measure, identify, and improve confirmed performance bottlenecks. |
| `CREATE-TESTS.md` | Design and implement meaningful regression protection. |
| `WRITE-DOCUMENTATION.md` | Create or update documentation based on actual validated behavior. |

---

## Skill Selection Decision

Every Skill selection must produce:

```text
WordPress Skill Selection Decision

User Request:
Analyzed Intent:
Primary Objective:
Project Type:
Existing or New Project:
Affected Component:
Requested Deliverable:
Known Constraints:
Known Risks:
Candidate Skills:
Selected Primary Skill:
Supporting Skills:
Rejected Skills:
Selection Reason:
Required Architecture Review:
Required Information Before Execution:
Skill Routing Status:
```

### Skill Routing Status

| Status | Meaning |
|---|---|
| Ready | The correct primary Skill has been selected. |
| Ready with Supporting Skills | One primary Skill and required supporting Skills are identified. |
| Needs More Information | The correct Skill cannot be selected reliably. |
| Ambiguous | Multiple Skills remain equally plausible. |
| Blocked | A prerequisite prevents Skill execution. |

Execution must not begin when Skill Routing Status is `Needs More Information`, `Ambiguous`, or `Blocked`.

---

## Primary Skill Rule

Every request must have one primary Skill.

Supporting Skills may be invoked when required.

Example:

```text
Primary Skill:
CREATE-PLUGIN

Supporting Skills:
CREATE-REST-ENDPOINT
CREATE-BLOCK
CREATE-TESTS
WRITE-DOCUMENTATION
```

The primary Skill owns the overall lifecycle.

Supporting Skills execute bounded specialized workflows within that lifecycle.

---

## Routing Decision Tree

### New Plugin

If the user requests a new plugin or a new plugin-scale feature system, select `CREATE-PLUGIN.md`.

Examples:

- build a membership plugin
- create an event-management plugin
- create a plugin with administration settings and REST endpoints
- create a WooCommerce extension
- create a plugin containing blocks, cron, and database tables

Supporting Skills may include:

- `CREATE-BLOCK.md`
- `CREATE-REST-ENDPOINT.md`
- `CREATE-SHORTCODE.md`
- `CREATE-WIDGET.md`
- `CREATE-TESTS.md`
- `WRITE-DOCUMENTATION.md`

### New Theme

If the user requests a complete theme, select `CREATE-THEME.md`.

Examples:

- create a block theme
- create a classic theme
- create a hybrid theme
- create a child theme
- convert an approved design system into a theme

Supporting Skills may include `CREATE-BLOCK.md`, `CREATE-TESTS.md`, and `WRITE-DOCUMENTATION.md`.

Persistent business functionality must not be placed in the theme merely because the theme request exposed the need.

### New Block

If the primary deliverable is a custom block or Block Editor extension, select `CREATE-BLOCK.md`.

If the block is one component of a larger plugin or theme project, the parent project Skill remains primary.

```text
Primary Skill:
CREATE-PLUGIN

Supporting Skill:
CREATE-BLOCK
```

### New REST Endpoint

If the primary deliverable is a REST route or API contract, select `CREATE-REST-ENDPOINT.md`.

If REST work belongs to a larger plugin or block project, the parent Skill remains primary.

### New Shortcode

If the primary deliverable is a shortcode, select `CREATE-SHORTCODE.md`.

If the shortcode belongs to a larger plugin project, use `CREATE-PLUGIN` as primary and `CREATE-SHORTCODE` as supporting.

### New Widget

If the primary deliverable is a widget or sidebar feature, select `CREATE-WIDGET.md`.

If the widget belongs to a larger plugin or theme project, the parent project Skill remains primary.

### Existing Defect

If the user reports broken behavior and wants it fixed, select `DEBUG-PLUGIN.md`.

Examples:

- plugin activation causes a fatal error
- settings do not save
- REST endpoint returns an unexpected error
- block crashes the editor
- cron job does not run
- AJAX request fails

The debugging workflow must confirm the cause before changing code.

### Code Inspection

If the user wants findings, risks, or an assessment without implementation changes, select `REVIEW-CODE.md`.

Examples:

- review this plugin
- audit this code
- inspect security
- identify technical debt
- assess release readiness
- review architecture

A review must not silently become implementation work.

### Internal Restructuring

If the user wants code organization improved while preserving behavior, select `REFACTOR-CODE.md`.

Examples:

- split a large class
- reduce duplication
- introduce services or repositories
- reorganize hooks
- improve testability
- simplify internal architecture

If public contracts, stored data, or major compatibility behavior must change, evaluate `MIGRATE-PLUGIN.md` instead.

### Migration

If the task changes architecture, stored data, compatibility contracts, API versions, or major implementation models, select `MIGRATE-PLUGIN.md`.

Examples:

- move options into custom tables
- migrate legacy architecture
- migrate shortcode content to blocks
- replace a public API contract
- migrate schema versions
- replace a major external dependency

Migration requires source state, target state, transition strategy, failure behavior, and recovery analysis.

### Performance Work

If the primary objective is performance improvement, select `OPTIMIZE-PERFORMANCE.md`.

Examples:

- reduce slow queries
- improve REST latency
- reduce administration load time
- optimize cron workloads
- reduce repeated AJAX requests
- reduce Block Editor lag

Optimization must begin with measurement whenever practical.

### Test Creation

If the primary deliverable is a test suite, test plan, or regression protection, select `CREATE-TESTS.md`.

If tests are required as part of another Skill, that parent Skill remains primary.

### Documentation

If the primary deliverable is documentation, select `WRITE-DOCUMENTATION.md`.

Documentation must be based on actual implementation evidence.

---

## Ambiguous Intent Rules

### Debug vs Refactor

Use `DEBUG-PLUGIN` when the primary objective is to correct broken behavior.

Use `REFACTOR-CODE` when behavior works but internal structure should improve.

For “This plugin is broken and the code is messy,” route first to `DEBUG-PLUGIN`. After the defect is confirmed and fixed, recommend `REFACTOR-CODE` as a separate controlled workflow when appropriate.

### Refactor vs Migration

Use `REFACTOR-CODE` when external behavior and contracts remain stable.

Use `MIGRATE-PLUGIN` when the task changes stored data structures, database schema, public APIs, REST contracts, block or shortcode compatibility, major architecture boundaries, or major dependency models.

### Review vs Debug

Use `REVIEW-CODE` when the user wants analysis and findings.

Use `DEBUG-PLUGIN` when the user wants a specific failure diagnosed and corrected.

A review may recommend debugging but must not silently transition into it.

### Review vs Refactor

Use `REVIEW-CODE` when the deliverable is findings.

Use `REFACTOR-CODE` when the deliverable is changed code with preserved behavior.

### Debug vs Optimize Performance

Use `DEBUG-PLUGIN` when expected functionality fails.

Use `OPTIMIZE-PERFORMANCE` when functionality works but measurable performance is unacceptable.

If severe slowness causes operational failure, debugging may be used first to determine whether a defect exists.

### Create Plugin vs Supporting Skill

Use `CREATE-PLUGIN` as primary when the request describes a plugin-scale product. Use specialized Skills as supporting workflows for individual components.

```text
Request:
Create a directory plugin with custom tables, REST endpoints, blocks, and frontend search.

Primary Skill:
CREATE-PLUGIN

Supporting Skills:
CREATE-REST-ENDPOINT
CREATE-BLOCK
CREATE-TESTS
WRITE-DOCUMENTATION
```

---

## Multi-Skill Execution

When multiple Skills are required, define:

```text
Multi-Skill Execution Plan

Primary Skill:
Supporting Skills:
Execution Order:
Shared Architecture:
Shared Requirements:
Shared Validation Gates:
Shared Reports:
Parent Skill Owner:
Completion Rule:
```

The parent Skill owns final completion status. Supporting Skills must return their reports to the parent workflow.

### Multi-Skill Sequence Rule

Supporting Skills execute according to dependency order.

```text
CREATE-PLUGIN
↓
CREATE-REST-ENDPOINT
↓
CREATE-BLOCK
↓
CREATE-TESTS
↓
WRITE-DOCUMENTATION
↓
Parent Skill Validation Gates
↓
Release Review
```

The exact sequence must follow architecture and dependency requirements.

---

## Skill Transition Contract

When one Skill recommends another Skill, use:

```text
Skill Transition

From Skill:
To Skill:
Project:
Reason:
Evidence:
Work Completed:
Inputs Available:
Open Risks:
Blocking Issues:
Required Next Action:
```

A Skill transition must preserve context and evidence.

---

## Skill Boundary Rule

A Skill must remain within its defined responsibility.

For example, `REVIEW-CODE` may identify defects but must not silently become `DEBUG-PLUGIN`, `REFACTOR-CODE`, or `MIGRATE-PLUGIN`.

Similarly, `DEBUG-PLUGIN` must not silently become a large architecture rewrite.

Explicit Skill transition is required.

---

## Information Sufficiency Check

Before final Skill selection, verify:

```text
Information Sufficiency Check

Primary Objective Known:
Project Type Known:
Existing or New Known:
Affected Component Known:
Requested Deliverable Known:
Critical Constraints Known:
Required Compatibility Known:
Known Risks Recorded:
Information Status:
```

If missing information could materially change Skill selection, set:

```text
Skill Routing Status: Needs More Information
```

---

## Routing Examples

### Example 1 — Plugin Creation

```text
Request:
Create a plugin that manages private member records.

Primary Skill:
CREATE-PLUGIN

Supporting Skills:
CREATE-TESTS
WRITE-DOCUMENTATION

Reason:
The request is a new plugin-scale system.
```

### Example 2 — Broken Plugin

```text
Request:
The plugin settings save successfully but disappear after reload.

Primary Skill:
DEBUG-PLUGIN

Reason:
The primary objective is diagnosis and correction of broken behavior.
```

### Example 3 — Large Legacy Rewrite

```text
Request:
Move a legacy plugin from procedural files and options storage into services and custom tables.

Primary Skill:
MIGRATE-PLUGIN

Supporting Skills:
CREATE-TESTS
WRITE-DOCUMENTATION

Reason:
The request changes architecture and persistent storage.
```

### Example 4 — Code Quality Assessment

```text
Request:
Review this plugin and tell me whether it is production-ready.

Primary Skill:
REVIEW-CODE

Reason:
The requested deliverable is an evidence-based assessment.
```

### Example 5 — Measured Database Problem

```text
Request:
This admin screen performs 480 queries and takes six seconds to load.

Primary Skill:
OPTIMIZE-PERFORMANCE

Reason:
The primary objective is improvement of a measured performance problem.
```

### Example 6 — Refactoring

```text
Request:
Split this 4,000-line plugin class into smaller services without changing behavior.

Primary Skill:
REFACTOR-CODE

Supporting Skill:
CREATE-TESTS

Reason:
The goal is internal restructuring with behavior preservation.
```

---

## Hard Rules

1. Every WordPress task must select one primary Skill.
2. Supporting Skills must remain subordinate to the parent Skill lifecycle.
3. Skill selection must occur before Role routing.
4. Role routing must not replace Skill selection.
5. A Skill must not silently expand into another Skill.
6. Ambiguous intent must be resolved before implementation begins.
7. New plugin-scale systems must use `CREATE-PLUGIN.md` as the primary Skill.
8. New theme-scale systems must use `CREATE-THEME.md` as the primary Skill.
9. Debugging must confirm cause before repair.
10. Performance optimization must measure before and after significant changes whenever practical.
11. Migration must define source state, target state, transition strategy, and recovery behavior.
12. Review findings must remain evidence-based.
13. Documentation must describe actual validated behavior.
14. Test creation must be based on requirements, contracts, behavior, and risk.

---

## Final Skill Selection Output

Every routing operation must end with:

```text
WordPress Skill Selection Decision

User Request:
Analyzed Intent:
Primary Objective:
Project Type:
Existing or New Project:
Affected Component:
Requested Deliverable:
Candidate Skills:
Selected Primary Skill:
Supporting Skills:
Rejected Skills:
Selection Reason:
Required Architecture Review:
Required Information Before Execution:
Skill Routing Status:
```

## Rule

The SquirrelForge WordPress Skill Routing Map is the authoritative decision layer between user intent and WordPress Skill execution.

It must select one primary Skill, identify supporting Skills when required, preserve Skill boundaries, require explicit transitions between workflows, and pass the selected Skill into the Role Manager before implementation or validation work begins.
