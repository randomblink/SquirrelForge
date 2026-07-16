Status: Stable

---
# SquirrelForge WordPress Agent Boot Sequence

## Purpose

This document defines the mandatory initialization sequence SquirrelForge must complete before accepting or executing WordPress development work.

The Boot Sequence verifies that the WordPress Agent has access to its controller, pipeline, Skill routing, knowledge system, standards, specialist roles, validation gates, and execution contracts.

---

## Boot Principle

The Agent must verify its operating system before beginning WordPress work.

```text
Boot
↓
Verify Controller
↓
Verify Pipeline
↓
Verify Skills
↓
Verify Knowledge
↓
Verify Standards
↓
Verify Roles
↓
Verify Validation
↓
Verify Execution Contracts
↓
Declare Readiness
↓
Accept Work
Stage 1 — Load WordPress Manager

Load:

38_WORDPRESS/WORDPRESS-MANAGER.md

Verify:

top-level WordPress control exists
direct request-to-code execution is prohibited
Pipeline routing is required
Skill selection is required
Knowledge selection is required
Role routing is required
validation gates are required

Failure status:

BOOT FAILURE: WORDPRESS MANAGER UNAVAILABLE
Stage 2 — Load Pipeline

Load:

38_WORDPRESS/PIPELINE.md

Verify that the Pipeline defines:

request intake
intent analysis
requirements definition
knowledge selection
Skill selection
architecture planning when required
Role routing
implementation
security validation
performance validation when required
QA
documentation
release review when applicable
final reporting

Failure status:

BOOT FAILURE: WORDPRESS PIPELINE INCOMPLETE
Stage 3 — Load Agent Operating Mode

Load:

38_WORDPRESS/AGENT-OPERATING-MODE.md

Verify:

Agent states are defined
evidence rules are defined
existing-project inspection rules are defined
code generation rules are defined
gate failure routing is defined
Skill transition behavior is defined
architecture change behavior is defined
completion rules are defined

Failure status:

BOOT FAILURE: OPERATING MODE UNAVAILABLE
Stage 4 — Load Execution Contract

Load:

38_WORDPRESS/AGENT-EXECUTION-CONTRACT.md

Verify:

request contract exists
intent contract exists
requirements contract exists
knowledge contract exists
Skill contract exists
architecture contract exists
Role routing contract exists
execution plan contract exists
file change contract exists
implementation record exists
handoff contract exists
validation contracts exist
failure and remediation contracts exist
completion contract exists

Failure status:

BOOT FAILURE: EXECUTION CONTRACT INCOMPLETE
Stage 5 — Load Skill Routing

Load:

38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md

Verify that every supported Skill has an explicit routing rule:

CREATE-PLUGIN
CREATE-THEME
CREATE-BLOCK
CREATE-REST-ENDPOINT
CREATE-SHORTCODE
CREATE-WIDGET
MIGRATE-PLUGIN
REVIEW-CODE
REFACTOR-CODE
DEBUG-PLUGIN
OPTIMIZE-PERFORMANCE
CREATE-TESTS
WRITE-DOCUMENTATION

Verify:

one primary Skill is required
supporting Skills are allowed
ambiguous intent rules exist
Skill transitions are explicit
Skill boundaries are enforced

Failure status:

BOOT FAILURE: SKILL ROUTING INCOMPLETE
Stage 6 — Verify Skill Files

Verify existence and readability of:

38_WORDPRESS/SKILLS/CREATE-PLUGIN.md
38_WORDPRESS/SKILLS/CREATE-THEME.md
38_WORDPRESS/SKILLS/CREATE-BLOCK.md
38_WORDPRESS/SKILLS/CREATE-REST-ENDPOINT.md
38_WORDPRESS/SKILLS/CREATE-SHORTCODE.md
38_WORDPRESS/SKILLS/CREATE-WIDGET.md
38_WORDPRESS/SKILLS/MIGRATE-PLUGIN.md
38_WORDPRESS/SKILLS/REVIEW-CODE.md
38_WORDPRESS/SKILLS/REFACTOR-CODE.md
38_WORDPRESS/SKILLS/DEBUG-PLUGIN.md
38_WORDPRESS/SKILLS/OPTIMIZE-PERFORMANCE.md
38_WORDPRESS/SKILLS/CREATE-TESTS.md
38_WORDPRESS/SKILLS/WRITE-DOCUMENTATION.md

Failure status:

BOOT FAILURE: REQUIRED SKILL FILE MISSING

Record the missing file.

Stage 7 — Load Knowledge Manager

Load:

38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md

Verify:

task-based knowledge selection exists
required and optional knowledge can be distinguished
knowledge gaps can be recorded
applicable standards can be identified

Failure status:

BOOT FAILURE: KNOWLEDGE MANAGER UNAVAILABLE
Stage 8 — Verify Knowledge Domains

Verify required knowledge documents for supported WordPress work.

Expected domains include applicable documents for:

WordPress Core
Plugin Development
Theme Development
Block Editor
REST API
Database
Security
Performance
Accessibility
Cron
Media
WooCommerce when supported

A missing optional domain does not necessarily block all Agent operation.

Instead record:

BOOT CONDITION: KNOWLEDGE DOMAIN UNAVAILABLE

and identify the affected work type.

Stage 9 — Verify Standards

Verify the WordPress standards directory:

38_WORDPRESS/STANDARDS/

Expected standards include applicable documents for:

architecture
PHP
JavaScript
CSS
naming
testing
documentation
plugin development
theme development

Record missing standards.

If a missing standard materially affects the requested task:

TASK BLOCKED: REQUIRED STANDARD UNAVAILABLE
Stage 10 — Load Role Manager

Load:

38_WORDPRESS/ROLES/ROLE-MANAGER.md

Verify:

selected Skill is accepted as input
complexity classification exists
required roles can be selected
conditional roles can be selected
validation gates can be selected
expected reports can be selected
routing status is produced

Failure status:

BOOT FAILURE: ROLE MANAGER UNAVAILABLE
Stage 11 — Load Role Routing Matrix

Load:

38_WORDPRESS/ROLES/ROLE-ROUTING-MATRIX.md

Verify explicit routes for:

CREATE-PLUGIN
CREATE-THEME
CREATE-BLOCK
CREATE-REST-ENDPOINT
CREATE-SHORTCODE
CREATE-WIDGET
MIGRATE-PLUGIN
REVIEW-CODE
REFACTOR-CODE
DEBUG-PLUGIN
OPTIMIZE-PERFORMANCE
CREATE-TESTS
WRITE-DOCUMENTATION

Verify:

required roles are identified
conditional role triggers exist
validation gates exist
expected reports exist
supporting Skill coordination exists
duplicate-role handling exists
gate consolidation rules exist
failure routing exists

Failure status:

BOOT FAILURE: ROLE ROUTING MATRIX INCOMPLETE
Stage 12 — Verify Specialist Roles

Verify existence and readability of applicable Role documents:

38_WORDPRESS/ROLES/PROJECT-ARCHITECT.md
38_WORDPRESS/ROLES/PLUGIN-ARCHITECT.md
38_WORDPRESS/ROLES/THEME-ARCHITECT.md
38_WORDPRESS/ROLES/PHP-ENGINEER.md
38_WORDPRESS/ROLES/JAVASCRIPT-ENGINEER.md
38_WORDPRESS/ROLES/CSS-ENGINEER.md
38_WORDPRESS/ROLES/DATABASE-ENGINEER.md
38_WORDPRESS/ROLES/REST-ENGINEER.md
38_WORDPRESS/ROLES/BLOCK-ENGINEER.md
38_WORDPRESS/ROLES/SECURITY-ENGINEER.md
38_WORDPRESS/ROLES/PERFORMANCE-ENGINEER.md
38_WORDPRESS/ROLES/QA-ENGINEER.md
38_WORDPRESS/ROLES/DOCUMENTATION-ENGINEER.md
38_WORDPRESS/ROLES/RELEASE-ENGINEER.md

Failure status:

BOOT FAILURE: REQUIRED ROLE FILE MISSING

A missing role may block only affected workflows if the Agent can prove the role is not required for the current task.

Stage 13 — Load Security Validator

Load:

38_WORDPRESS/SECURITY-VALIDATOR.md

Verify coverage for applicable:

authentication
authorization
capabilities
nonces
validation
sanitization
escaping
SQL safety
REST permissions
AJAX permissions
uploads
private data
secrets
external integrations
error exposure
lifecycle behavior

Failure status:

BOOT FAILURE: SECURITY VALIDATOR UNAVAILABLE

Security-sensitive work must not proceed without security validation capability.

Stage 14 — Verify QA Capability

Load:

38_WORDPRESS/ROLES/QA-ENGINEER.md

Verify that QA can evaluate applicable:

functional behavior
negative behavior
permissions
persistence
REST behavior
AJAX behavior
shortcode behavior
block behavior
cron behavior
integrations
accessibility
compatibility
migration behavior
regressions

Failure status:

BOOT FAILURE: QA CAPABILITY UNAVAILABLE

Production-ready work must not proceed without QA capability.

Stage 15 — Verify Performance Capability

Load:

38_WORDPRESS/ROLES/PERFORMANCE-ENGINEER.md

Verify:

baseline measurement
bottleneck identification
measurement method recording
optimization validation
remeasurement
comparison limitations

If unavailable:

BOOT CONDITION: PERFORMANCE VALIDATION UNAVAILABLE

Performance-sensitive tasks become blocked until the capability is restored.

Stage 16 — Verify Documentation Capability

Load:

38_WORDPRESS/ROLES/DOCUMENTATION-ENGINEER.md

Verify ability to document applicable:

README
readme.txt
hooks
filters
REST endpoints
shortcodes
blocks
settings
database structures
migrations
testing
upgrades
changelog
release notes

If unavailable:

BOOT CONDITION: DOCUMENTATION CAPABILITY UNAVAILABLE

Tasks requiring documentation cannot be marked complete.

Stage 17 — Verify Release Capability

Load:

38_WORDPRESS/ROLES/RELEASE-ENGINEER.md

Verify ability to evaluate:

required reports
validation status
version consistency
package integrity
installation behavior
upgrade behavior
migration behavior
rollback limitations
artifact integrity
known risks

If unavailable:

BOOT CONDITION: RELEASE REVIEW UNAVAILABLE

Production-ready release approval must not be issued.

Stage 18 — Run Readiness Checklist

Run:

38_WORDPRESS/AGENT-READINESS-CHECKLIST.md

Produce:

WordPress Agent Readiness Result

Core Readiness:
Control Flow Readiness:
Skill Readiness:
Knowledge Readiness:
Standards Readiness:
Role Readiness:
Validation Readiness:
Documentation Readiness:
Release Readiness:

Blocking Failures:

Operating Conditions:

Agent Verdict:

Agent Verdict:

Ready
Ready with Conditions
Not Ready
Stage 19 — Declare Operating Capability

If verdict is:

Ready

declare:

WORDPRESS AGENT READY

If verdict is:

Ready with Conditions

declare:

WORDPRESS AGENT READY WITH CONDITIONS

and list affected capabilities.

If verdict is:

Not Ready

declare:

WORDPRESS AGENT NOT READY

The Agent must not claim unrestricted WordPress Agent capability.

Task-Level Capability Check

Even after successful boot, each task must verify that the required capability is available.

Produce:

Task Capability Check

Task:
Primary Skill:
Supporting Skills:
Required Knowledge:
Required Standards:
Required Roles:
Required Validation Gates:
Missing Capabilities:
Task Capability Status:

Task Capability Status:

Ready
Ready with Conditions
Blocked
Boot Failure Record

When boot fails, produce:

Boot Failure Record

Stage:
Component:
Expected:
Observed:
Impact:
Affected Skills:
Affected Roles:
Required Fix:
Boot Status:
Boot Condition Record

For non-global limitations:

Boot Condition Record

Component:
Condition:
Affected Capability:
Affected Skills:
Workaround:
Risk:
Status:

A workaround must not bypass required safety or validation gates.

Boot Report

Produce:

SquirrelForge WordPress Agent Boot Report

Boot Time:

WordPress Manager:
Pipeline:
Operating Mode:
Execution Contract:
Skill Routing:
Skill Files:
Knowledge Manager:
Knowledge Domains:
Standards:
Role Manager:
Role Routing Matrix:
Specialist Roles:
Security Validator:
QA Capability:
Performance Capability:
Documentation Capability:
Release Capability:
Readiness Checklist:

Blocking Failures:

Operating Conditions:

Agent Verdict:

Next Required Action:
Reboot Conditions

The WordPress Agent should rerun the Boot Sequence when:

the Skill Routing Map changes
a Skill file is added or removed
the Pipeline changes
the WordPress Manager changes
the Knowledge Manager changes
required standards change materially
Role Manager changes
Role Routing Matrix changes
specialist roles are added or removed
validation rules change
execution contracts change
a prior Boot Failure is repaired
Rule

The SquirrelForge WordPress Agent Boot Sequence is the mandatory initialization and readiness verification process for WordPress Agent operation.

The Agent must verify controllers, workflows, Skills, knowledge, standards, roles, validation capabilities, documentation capability, release capability, and execution contracts before claiming readiness or beginning unrestricted WordPress development work.

## Rule

The WordPress Agent must complete boot checks before routing or executing WordPress work.
