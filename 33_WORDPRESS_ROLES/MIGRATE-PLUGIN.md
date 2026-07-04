# SquirrelForge WordPress Migrate Plugin Skill

## Purpose

This Skill defines the controlled workflow for migrating an existing WordPress plugin from one architecture, storage model, API contract, dependency model, or major implementation structure to another.

Migration work must preserve approved data, compatibility, security boundaries, operational behavior, and rollback safety.

---

## Trigger Conditions

Use this Skill when the request is to:

- migrate plugin data
- upgrade a database schema
- change option structures
- move from one storage format to another
- migrate an existing plugin architecture
- replace legacy plugin structure
- move from options or metadata to custom tables
- change database schema
- replace a legacy API
- introduce a new service architecture
- migrate shortcode functionality to blocks
- replace an integration dependency
- perform a major compatibility transition

Do not use this Skill when the task is only to:

- create a new plugin
- debug a plugin
- refactor code without data changes

Use the appropriate specialized Skill instead.

Use:

`32_WORDPRESS/SKILLS/REFACTOR-CODE.md`

for behavior-preserving internal code restructuring.

---

## Required References

Before execution, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/WORDPRESS-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/WORDPRESS-CORE.md`
- `38_WORDPRESS/KNOWLEDGE/PLUGIN-HANDBOOK.md`
- `38_WORDPRESS/KNOWLEDGE/DATABASE.md`
- `38_WORDPRESS/KNOWLEDGE/REST-API.md` when API contracts change
- `38_WORDPRESS/KNOWLEDGE/BLOCK-EDITOR.md` when block compatibility changes
- `38_WORDPRESS/KNOWLEDGE/CRON.md` when scheduled work changes
- `38_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `38_WORDPRESS/KNOWLEDGE/PERFORMANCE.md`
- `38_WORDPRESS/STANDARDS/PLUGIN-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/DOCUMENTATION-STANDARD.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`
- `38_WORDPRESS/ROLES/ROLE-MANAGER.md`
- `38_WORDPRESS/ROLES/ROLE-ROUTING-MATRIX.md`
- `38_WORDPRESS/ROLES/PROJECT-ARCHITECT.md`
- `38_WORDPRESS/ROLES/PLUGIN-ARCHITECT.md`
- `38_WORDPRESS/ROLES/DATABASE-ENGINEER.md` when applicable
- `38_WORDPRESS/ROLES/SECURITY-ENGINEER.md`
- `38_WORDPRESS/ROLES/PERFORMANCE-ENGINEER.md` when applicable
- `38_WORDPRESS/ROLES/QA-ENGINEER.md`
- `38_WORDPRESS/ROLES/DOCUMENTATION-ENGINEER.md`
- `38_WORDPRESS/ROLES/RELEASE-ENGINEER.md`
- All relevant implementation and validation roles.

---

## Required Input

```text
Plugin Migration Request

Project:
Plugin Name:
Current Version:
Target Version:
Current Architecture:
Target Architecture:
Purpose of Migration:
Data to Migrate:
Database Changes:
Settings Changes:
API Changes:
REST Changes:
Shortcode Changes:
Block Changes:
Cron Changes:
Dependency Changes:
Compatibility Requirements:
Supported Upgrade Sources:
Rollback Requirements:
Downtime Constraints:
Performance Constraints:
Known Risks:
```

Critical migration requirements must not be invented.

### Workflow

#### Stage 1 — Current-State Assessment

Inspect and document the existing plugin.

Produce:

```text
Plugin Migration Current-State Record

Plugin:
Version:
Architecture:
Bootstrap:
Services:
Hooks:
Data Stores:
Database Schema:
Options:
Metadata:
REST Routes:
AJAX Actions:
Shortcodes:
Blocks:
Cron Events:
External Integrations:
Public Hooks:
Compatibility Contracts:
Known Technical Debt:
Known Risks:
```

Migration planning must be based on the actual current state.

#### Stage 2 — Migration Requirements

Define:

```text
Plugin Migration Requirements

Migration Purpose:

Target State:

Data Preservation Requirements:

Compatibility Requirements:

API Compatibility Requirements:

REST Compatibility Requirements:

Shortcode Compatibility Requirements:

Block Compatibility Requirements:

Cron Continuity Requirements:

Security Requirements:

Performance Requirements:

Rollback Requirements:

Acceptance Criteria:

Out of Scope:

Missing Information:
```

If critical information is missing:

`Status: Needs More Information`

#### Stage 3 — Knowledge Selection

Use:

`38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`

Select required knowledge based on migration scope.

Produce:

```text
Knowledge Selection

Task:
Required Knowledge:
Optional Knowledge:
Reason:
```

#### Stage 4 — Target Architecture

Use:

`38_WORDPRESS/ROLES/PROJECT-ARCHITECT.md`
`38_WORDPRESS/ROLES/PLUGIN-ARCHITECT.md`

Produce:

`Approved Migration Architecture Plan`

and:

`Approved Target Plugin Architecture Specification`

Define:

- current architecture
- target architecture
- transition boundaries
- compatibility layers
- data ownership
- migration sequence
- rollback boundaries
- removal schedule for legacy components

Implementation must not begin until the target architecture and transition strategy are approved.

#### Stage 5 — Migration Strategy

Produce:

```text
Plugin Migration Strategy

Source Version:
Target Version:

Pre-Migration Checks:

Migration Trigger:

Migration Phases:

Data Transformations:

Schema Changes:

Compatibility Layer:

Legacy Behavior Retained:

Legacy Behavior Deprecated:

Legacy Behavior Removed:

Batching Strategy:

Locking Strategy:

Retry Strategy:

Failure Detection:

Recovery Strategy:

Rollback Strategy:

Verification Strategy:
```

Large migrations should be resumable when practical.

#### Stage 6 — Role Routing

Use:

`38_WORDPRESS/ROLES/ROLE-MANAGER.md`
`38_WORDPRESS/ROLES/ROLE-ROUTING-MATRIX.md`

Standard migration route:

1. Project Architect
2. ↓
3. Role Manager
4. ↓
5. Plugin Architect
6. ↓
7. Database Engineer when persistent data changes
8. ↓
9. PHP Engineer
10. ↓
11. REST Engineer when API contracts change
12. ↓
13. Block Engineer when block compatibility changes
14. ↓
15. JavaScript Engineer when client behavior changes
16. ↓
17. CSS Engineer when presentation changes
18. ↓
19. Security Engineer
20. ↓
21. Performance Engineer when required
22. ↓
23. QA Engineer
24. ↓
25. Documentation Engineer
26. ↓
27. Release Engineer

Produce:

```text
WordPress Role Routing Decision

Task:
Selected Skill: MIGRATE-PLUGIN
Project Type: Plugin Migration
Complexity:
Required Roles:
Optional Roles:
Role Sequence:
Required Gates:
Conditional Gates:
Expected Reports:
Known Risks:
Routing Status:
```

Migration implementation must not begin until routing is ready.

#### Stage 7 — Migration Implementation Planning

Create assignments for each selected implementation role.

Assignments must identify:

- source state
- target state
- migration sequence
- files to create
- files to modify
- data transformations
- compatibility requirements
- failure behavior
- retry behavior
- rollback behavior
- security requirements
- performance constraints
- testing requirements

#### Stage 8 — Database Migration Implementation

Use `Database Engineer` when persistent data changes.

Review and implement applicable:

- schema creation
- schema alteration
- data transformation
- data copying
- data normalization
- index creation
- option migration
- metadata migration
- batch processing
- migration state tracking
- retry safety
- rollback limitations

Required output:

`Database Engineering Report`

Migration logic must be idempotent or explicitly protected against unsafe repeated execution.

#### Stage 9 — Application Migration Implementation

Use `PHP Engineer` for:

- bootstrap transition
- service transition
- hook transition
- compatibility adapters
- legacy API bridges
- migration triggers
- version checks
- migration state coordination
- cleanup behavior

Required output:

`PHP Implementation Report`

Legacy behavior must not be removed before compatibility and migration requirements are satisfied.

#### Stage 10 — Conditional Specialist Migration

Use applicable specialist roles for:

- REST contract migration
- block migration
- JavaScript client migration
- CSS or markup migration
- cron migration
- external integration migration

Each selected role must produce its normal implementation report.

#### Stage 11 — Security Validation

Use:

`38_WORDPRESS/SECURITY-VALIDATOR.md`
`38_WORDPRESS/ROLES/SECURITY-ENGINEER.md`

Review:

- migration authorization
- migration trigger exposure
- data validation
- data transformation safety
- SQL safety
- sensitive-data handling
- temporary migration data
- logs
- errors
- rollback behavior
- cleanup behavior

Required output:

`Security Review Report`

Critical and High security findings block migration release.

#### Stage 12 — Performance Validation

Use `38_WORDPRESS/ROLES/PERFORMANCE-ENGINEER.md` when migration cost or runtime behavior is significant.

Review:

- migration duration
- batch size
- memory use
- query volume
- locking impact
- request timeout risk
- cron workload
- retry cost
- frontend impact
- admin impact

Required output:

`Performance Review Report`

Large migration workloads must not be assumed safe without measurement or bounded execution planning.

#### Stage 13 — Migration QA

Use `38_WORDPRESS/ROLES/QA-ENGINEER.md`.

Test applicable paths:

- clean installation of target version
- upgrade from each supported source version
- repeated migration execution
- interrupted migration
- resumed migration
- failed migration behavior
- retry behavior
- preserved settings
- preserved user data
- preserved public APIs
- REST compatibility
- shortcode compatibility
- block compatibility
- cron continuity
- dependency failure
- rollback behavior where supported
- regression behavior

Required output:

`QA Report`

Migration release must stop when `Final QA Status` is:

- `Fail`
- `Blocked`
- `Needs More Information`

#### Stage 14 — Migration Documentation

Use `38_WORDPRESS/ROLES/DOCUMENTATION-ENGINEER.md`.

Document:

- supported source versions
- target version
- automatic migration steps
- manual migration steps
- expected migration duration when known
- compatibility changes
- deprecated behavior
- removed behavior
- backup requirements
- rollback limitations
- verification steps
- known risks

Required output:

`Documentation Report`

#### Stage 15 — Release Review

Use `38_WORDPRESS/ROLES/RELEASE-ENGINEER.md`.

Verify:

- source-version coverage
- target architecture
- migration strategy
- implementation reports
- security status
- performance status
- QA status
- documentation status
- version consistency
- package integrity
- upgrade behavior
- rollback planning
- migration recovery behavior

Required output:

`Release Readiness Report`

Final decision:

- `GO`
- `CONDITIONAL GO`
- `NO-GO`
- `HOLD`

### Migration Verification Record

Produce:

```text
Migration Verification Record

Source Version:
Target Version:
Test Environment:
Pre-Migration Data State:
Migration Result:
Post-Migration Data State:
Settings Preserved:
User Data Preserved:
API Compatibility:
REST Compatibility:
Shortcode Compatibility:
Block Compatibility:
Cron Continuity:
Regression Result:
Rollback Result:
Final Status:
```

### Failure Routing

When migration fails:

```text
Migration Failure
↓
Stop Unsafe Progression
↓
Record Migration State
↓
Identify Failure Owner
↓
Apply Fix
↓
Restore or Resume According to Strategy
↓
Re-run Migration Validation
↓
Run Regression Tests
↓
Resume Release Review
```

Migration failures must not be hidden or treated as successful completion.

### Plugin Migration Final Report

Produce:

```text
Plugin Migration Final Report

Project:
Plugin:
Source Version:
Target Version:

Current-State Assessment:

Migration Requirements Status:

Knowledge Used:

Target Architecture Status:

Migration Strategy Status:

Role Routing Status:

Roles Used:

Files Created:

Files Modified:

Data Migrated:

Schema Changes:

Compatibility Status:

Security Status:

Performance Status:

QA Status:

Documentation Status:

Release Status:

Known Limitations:

Residual Risks:

Rollback Limitations:

Final Result:

Next Step:
```

### Completion Criteria

The `Migrate Plugin` Skill is complete only when:

- current state is documented
- migration requirements are defined
- target architecture is approved
- migration strategy is defined
- role routing is complete
- migration implementation is complete
- required data is preserved
- compatibility requirements are satisfied
- security validation passed
- performance validation passed or was not required
- migration QA passed
- documentation is complete
- release review passed

## Rule

The Migrate Plugin Skill must treat migration as a controlled transition between known source and target states. It must preserve required data and compatibility, define failure and recovery behavior, validate supported upgrade paths, and never perform destructive migration work without explicit rollback or recovery analysis.
From Format:
To Format:
Migration Trigger:
Expected Data Volume:
Known Constraints:
```

### Workflow

#### Stage 1 — Architecture Planning

Use `Project Architect` to define the high-level migration strategy and impact. Use `Plugin Architect` and `Database Engineer` to design the detailed migration path, data transformation, schema changes, and trigger logic.

#### Stage 2 — Role Routing

Use `Role Manager` and `ROLE-ROUTING-MATRIX.md` to produce the `WordPress Role Routing Decision`.

#### Stage 3 — Implementation

Use `PHP Engineer` and `Database Engineer` to write the migration code, ensuring idempotency, data integrity, and batch processing for large datasets.

#### Stage 4 — Security Validation

Use `Security Engineer` to audit the migration code for SQL injection, data exposure, and privilege escalation risks. This is a **blocking gate**.

#### Stage 5 — Performance Validation

Use `Performance Engineer` to profile the migration for performance bottlenecks, especially with large datasets. This is a **blocking gate**.

#### Stage 6 — QA Validation

Use `QA Engineer` to execute a test plan to verify data integrity, successful migration, and rollback behavior. This is a **blocking gate**.

#### Stage 7 — Documentation

Use `Documentation Engineer` to update `CHANGELOG.md`, `README.md`, and create migration-specific documentation with rollback instructions.

#### Stage 8 — Release Preparation

Use `Release Engineer` to verify all gates passed and package the final release, paying special attention to upgrade notices and versioning.

### Migration Final Report

Produce a final report summarizing the status of all stages.

---

## Rule

1.  **Prioritize Data Integrity**: The agent must never generate a migration that deletes old data until it has been successfully and verifiably moved to the new location.
2.  **Be Idempotent**: The migration logic must be wrapped in checks (e.g., `if ( get_option('my_plugin_version') < '2.0.0' )`) to prevent it from running more than once.
3.  **Work in Batches**: For large datasets, the agent must design the migration to run in smaller, time-limited batches to avoid server timeouts. This can be done using the Action Scheduler or custom cron tasks.
4.  **Plan for Failure**: The agent must assume the migration could fail halfway through and design it to be resumable or safely reversible.
5.  **Validate After**: All migrations must be validated by Security, Performance (if applicable), and QA roles.