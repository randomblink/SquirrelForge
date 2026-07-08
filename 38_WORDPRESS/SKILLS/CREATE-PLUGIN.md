Status: Stable

---
# SquirrelForge WordPress Create Plugin Skill

## Purpose

This Skill defines the controlled workflow for creating a WordPress plugin.

It coordinates requirements, knowledge selection, architecture, role routing, specialist implementation, independent validation, QA, documentation, and release review.

---

## Trigger Conditions

Use this Skill when the request is to:

- create a new WordPress plugin
- build plugin functionality
- scaffold a production plugin
- convert functionality into a plugin
- create a reusable WordPress feature package

Do not use this Skill when the task is only:

- debugging an existing plugin
- reviewing existing plugin code
- refactoring an existing plugin
- migrating an existing plugin

Use the appropriate specialized Skill instead.

---

## Required References

Before execution, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/WORDPRESS-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/KNOWLEDGE/WORDPRESS-CORE.md`
- `38_WORDPRESS/KNOWLEDGE/PLUGIN-HANDBOOK.md`
- `38_WORDPRESS/KNOWLEDGE/SECURITY.md`
- `38_WORDPRESS/STANDARDS/PLUGIN-STANDARD.md`
- `38_WORDPRESS/STANDARDS/ARCHITECTURE-STANDARD.md`
- `38_WORDPRESS/STANDARDS/NAMING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/PHP-STANDARD.md`
- `38_WORDPRESS/STANDARDS/TESTING-STANDARD.md`
- `38_WORDPRESS/STANDARDS/DOCUMENTATION-STANDARD.md`
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`
- `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`
- `33_WORDPRESS_ROLES/PROJECT-ARCHITECT.md`
- `33_WORDPRESS_ROLES/PLUGIN-ARCHITECT.md`

Additional references must be selected according to project requirements.

---

## Required Input

```text
Plugin Creation Request

Plugin Name:
Purpose:
Primary Users:
Required Features:
Admin Features:
Frontend Features:
Data Requirements:
REST Requirements:
AJAX Requirements:
Block Requirements:
Cron Requirements:
External Integrations:
Accessibility Requirements:
Performance Requirements:
Compatibility Requirements:
Distribution Target:
Known Constraints:
```

Missing fields may be resolved during requirements definition.

Critical requirements must not be invented.

---

## Workflow

### Stage 1 — Requirements Definition

Convert the request into:

```text
Plugin Requirements

Purpose:
Functional Requirements:
Admin Requirements:
Frontend Requirements:
Data Requirements:
Security Requirements:
REST Requirements:
AJAX Requirements:
Block Requirements:
Cron Requirements:
External Integration Requirements:
Accessibility Requirements:
Performance Requirements:
Compatibility Requirements:
Distribution Requirements:
Acceptance Criteria:
Out of Scope:
Missing Information:
```

If critical information is missing:

```text
Status: Needs More Information
```

Do not proceed to architecture until blocking requirements are resolved.

### Stage 2 — Knowledge Selection

Use:

`38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`

Select required knowledge domains.

Possible selections include:

- WordPress Core
- Plugin Handbook
- Security
- Custom Post Types
- Database
- REST API
- Block Editor
- Cron
- Media
- Performance
- Accessibility
- WooCommerce

Produce:

```text
Knowledge Selection

Task:
Required Knowledge:
Optional Knowledge:
Reason:
```

### Stage 3 — Project Architecture

Use:

`33_WORDPRESS_ROLES/PROJECT-ARCHITECT.md`

Produce:

`Approved Project Architecture Plan`

The plan must establish:

- project boundaries
- plugin responsibility
- persistent functionality ownership
- data ownership
- external dependencies
- security boundaries
- performance boundaries
- compatibility requirements
- required specialist domains

### Stage 4 — Plugin Architecture

Use:

`33_WORDPRESS_ROLES/PLUGIN-ARCHITECT.md`

Produce:

`Approved Plugin Architecture Specification`

The specification must define applicable items:

- plugin identity
- file structure
- bootstrap architecture
- lifecycle behavior
- hook architecture
- service architecture
- controller architecture
- repository architecture
- admin architecture
- frontend architecture
- REST architecture
- AJAX architecture
- block architecture
- cron architecture
- data architecture
- asset architecture
- security boundaries
- performance risks
- testing requirements
- documentation requirements

Implementation must not begin before architecture is approved.

### Stage 5 — Role Routing

Use:

- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`
- `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`

The standard plugin route is:

```text
Project Architect
↓
Role Manager
↓
Plugin Architect
↓
PHP Engineer
↓
Database Engineer when required
↓
REST Engineer when required
↓
Block Engineer when required
↓
JavaScript Engineer when required
↓
CSS Engineer when required
↓
Security Engineer
↓
Performance Engineer when required
↓
QA Engineer
↓
Documentation Engineer
↓
Release Engineer
```

Produce:

```text
WordPress Role Routing Decision

Task:
Selected Skill: CREATE-PLUGIN
Project Type: Plugin
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

Implementation may proceed only when Routing Status is `Ready`, or when all blocking conditions attached to `Ready with Conditions` have been resolved.

### Stage 6 — Implementation Planning

Create assignments for every selected implementation role.

Possible assignments:

- PHP Engineering Assignment
- Database Engineering Assignment
- REST Engineering Assignment
- Block Engineering Assignment
- JavaScript Engineering Assignment
- CSS Engineering Assignment

Each assignment must identify:

- project
- component
- purpose
- approved architecture
- files to create
- files to modify
- required interfaces
- dependencies
- security requirements
- performance constraints
- compatibility requirements
- testing requirements
- open risks

### Stage 7 — Plugin Foundation Implementation

The PHP Engineer implements the approved plugin foundation.

Typical responsibilities include:

- main plugin file
- bootstrap behavior
- constants
- service initialization
- hook registration
- activation behavior
- deactivation behavior
- uninstall behavior
- localization setup
- shared utilities when approved

The PHP Engineer must produce:

`PHP Implementation Report`

### Stage 8 — Conditional Specialist Implementation

Invoke selected specialist roles according to the Role Routing Decision.

#### Database Work

Use Database Engineer when the plugin requires:

- custom tables
- complex persistence
- high-volume operational data
- specialized queries
- schema migrations

Required output:

`Database Engineering Report`

#### REST Work

Use REST Engineer when the plugin exposes or consumes REST behavior requiring an API contract.

Required output:

`REST Engineering Report`

#### Block Work

Use Block Engineer when the plugin contains custom blocks or Block Editor extensions.

Required output:

`Block Engineering Report`

#### JavaScript Work

Use JavaScript Engineer when the plugin contains:

- admin interactions
- frontend interactions
- REST clients
- AJAX clients
- editor interactions

Required output:

`JavaScript Implementation Report`

#### CSS Work

Use CSS Engineer when the plugin requires:

- admin styles
- frontend component styles
- block styles
- editor styles
- responsive presentation

Required output:

`CSS Implementation Report`

### Stage 9 — Security Validation

Use:

- `38_WORDPRESS/SECURITY-VALIDATOR.md`
- `33_WORDPRESS_ROLES/SECURITY-ENGINEER.md`

Review the actual implementation.

Required output:

`Security Review Report`

The Skill must stop when:

```text
Final Security Status: Fail
```

or:

```text
Final Security Status: Needs More Information
```

Security failures return to the responsible implementation role.

After remediation, Security Engineer must independently revalidate the fix.

### Stage 10 — Performance Validation

Use Performance Engineer when required by the Role Routing Decision.

Review applicable areas:

- query count
- repeated queries
- autoloaded options
- REST response size
- AJAX request frequency
- cron workloads
- external API calls
- PHP execution cost
- JavaScript bundle cost
- CSS delivery
- block editor cost

Required output:

`Performance Review Report`

Performance changes must be remeasured whenever practical.

### Stage 11 — QA Validation

Use:

`33_WORDPRESS_ROLES/QA-ENGINEER.md`

QA must test applicable behavior including:

- clean installation
- activation
- repeated activation when relevant
- deactivation
- reactivation
- admin features
- frontend features
- settings persistence
- REST endpoints
- AJAX actions
- blocks
- cron
- database persistence
- migration behavior
- permission failures
- invalid input
- accessibility
- compatibility
- regression behavior

Required output:

`QA Report`

The Skill must stop when Final QA Status is:

- `Fail`
- `Blocked`
- `Needs More Information`

Defects return to the responsible Engineer and must be retested.

### Stage 12 — Documentation

Use:

`33_WORDPRESS_ROLES/DOCUMENTATION-ENGINEER.md`

Update applicable documentation:

- `README.md`
- `readme.txt`
- `CHANGELOG.md`
- `HOOKS.md`
- REST API documentation
- shortcode documentation
- block documentation
- settings documentation
- database documentation
- migration documentation
- testing documentation

Required output:

`Documentation Report`

Documentation must describe actual validated behavior.

### Stage 13 — Release Review

Use:

`33_WORDPRESS_ROLES/RELEASE-ENGINEER.md`

Verify:

- architecture status
- implementation reports
- security status
- performance status
- QA status
- documentation status
- version consistency
- package contents
- secret scan
- clean installation
- upgrade behavior
- migration behavior
- rollback planning
- release artifact integrity

Required output:

`Release Readiness Report`

The final decision must be one of:

- `GO`
- `CONDITIONAL GO`
- `NO-GO`
- `HOLD`

---

## Required Handoff Contract

Every role transition must use:

```text
Role Handoff

From Role:
To Role:
Project:
Task:
Input:
Work Completed:
Output:
Validation Performed:
Open Risks:
Blocking Issues:
Required Next Action:
```

Incomplete handoffs must be rejected.

---

## Failure Routing

When a gate fails:

```text
Gate Failure
↓
Identify Responsible Engineer
↓
Return Finding or Defect
↓
Apply Fix
↓
Independent Revalidation
↓
Regression Testing
↓
Resume Skill Workflow
```

A failed gate must not be skipped.

---

## Plugin Creation Final Report

Produce:

```text
Plugin Creation Final Report

Plugin:
Purpose:
Requirements Status:
Knowledge Used:
Project Architecture Status:
Plugin Architecture Status:
Role Routing Status:
Roles Used:
Files Created:
Files Modified:
Implementation Reports:
Security Status:
Performance Status:
QA Status:
Documentation Status:
Release Status:
Known Limitations:
Residual Risks:
Final Result:
Next Step:
```

---

## Completion Criteria

The Create Plugin Skill is complete only when:

- requirements are defined
- knowledge is selected
- architecture is approved
- role routing is complete
- required implementation work is complete
- implementation reports exist
- security validation passed
- performance validation passed or was not required
- QA validation passed
- documentation is complete
- release review passed when production release is intended

---

## Rule

The Create Plugin Skill must use the WordPress Pipeline, Role Manager, Role Routing Matrix, specialist engineering roles, independent validation gates, QA, documentation, and release review as one controlled workflow. It must not jump directly from a plugin request to code generation.
