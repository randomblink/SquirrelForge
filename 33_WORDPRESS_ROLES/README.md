# SquirrelForge WordPress Roles Layer

## Purpose

This directory defines the specialist roles used by SquirrelForge to plan, implement, validate, test, document, and release WordPress plugins, themes, blocks, APIs, migrations, and supporting systems.

The WordPress Roles Layer does not select the primary Skill. Skill selection occurs in:

`38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md`

After Skill selection, the Role Manager uses the Role Routing Matrix to assign the correct specialist roles and validation gates.

---

## Position in the WordPress System

```text
WordPress Request
↓
38_WORDPRESS/WORDPRESS-MANAGER.md
↓
38_WORDPRESS/PIPELINE.md
↓
38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md
↓
Selected Primary Skill
↓
33_WORDPRESS_ROLES/ROLE-MANAGER.md
↓
33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md
↓
Specialist Roles
↓
Independent Validation Gates
↓
Documentation
↓
Release Review
↓
Final Result
```

---

## Core Routing Components

| Component | Responsibility |
|---|---|
| `ROLE-MANAGER.md` | Receives the selected Skill and produces the WordPress Role Routing Decision. |
| `ROLE-ROUTING-MATRIX.md` | Maps WordPress task types and Skills to required and conditional specialist roles. |

---

## Architecture Roles

| Role | Responsibility |
|---|---|
| `PROJECT-ARCHITECT.md` | Defines project boundaries, component ownership, dependencies, data ownership, and system-level architecture. |
| `PLUGIN-ARCHITECT.md` | Defines plugin structure, lifecycle, services, hooks, data boundaries, and implementation architecture. |
| `THEME-ARCHITECT.md` | Defines theme structure, templates, template parts, theme supports, design systems, blocks, patterns, and presentation architecture. |

Architecture roles define approved structure.

Implementation roles must not silently redesign approved architecture.

---

## Engineering Roles

| Role | Responsibility |
|---|---|
| `PHP-ENGINEER.md` | Implements PHP application logic, hooks, services, controllers, lifecycle behavior, and WordPress integrations. |
| `JAVASCRIPT-ENGINEER.md` | Implements browser and editor interactions, REST clients, AJAX clients, state handling, and accessible interaction behavior. |
| `CSS-ENGINEER.md` | Implements maintainable presentation, responsive behavior, editor/frontend styling, and interaction states. |
| `DATABASE-ENGINEER.md` | Designs and implements schemas, repositories, queries, migrations, indexes, and data lifecycle behavior. |
| `REST-ENGINEER.md` | Designs and implements REST routes, contracts, permissions, validation, responses, and compatibility behavior. |
| `BLOCK-ENGINEER.md` | Designs and implements Block Editor components, metadata, attributes, rendering models, variations, styles, transforms, and compatibility behavior. |

Engineering roles implement approved assignments.

Each Engineering role must:

- remain within assigned scope
- follow approved architecture
- follow applicable WordPress standards
- perform self-review
- produce an implementation report
- hand work to the next role through the Handoff Contract

Engineer self-review does not replace independent validation.

---

## Validation Roles

| Role | Responsibility |
|---|---|
| `SECURITY-ENGINEER.md` | Independently reviews authentication, authorization, validation, sanitization, escaping, SQL safety, REST permissions, AJAX security, uploads, integrations, secrets, and error exposure. |
| `PERFORMANCE-ENGINEER.md` | Measures and reviews query cost, execution cost, REST latency, AJAX frequency, cron workloads, assets, JavaScript cost, CSS delivery, and Block Editor performance. |

Validation roles must review actual implementation evidence.

A validation role must not mark work as passed solely because the implementation role reports that it is correct.

---

## QA Role

| Role | Responsibility |
|---|---|
| `QA-ENGINEER.md` | Performs independent functional, negative, permission, integration, accessibility, compatibility, migration, and regression testing. |

QA validation is separate from:

- developer self-testing
- automated unit tests
- implementation reports
- security review
- performance review

A project is not production-ready merely because the code compiles or automated tests pass.

---

## Documentation Role

| Role | Responsibility |
|---|---|
| `DOCUMENTATION-ENGINEER.md` | Creates and maintains accurate project, API, hook, block, shortcode, migration, testing, upgrade, and release documentation. |

Documentation must describe actual implementation and validated behavior.

Documentation must not invent:

- functionality
- compatibility
- test results
- security guarantees
- migration behavior
- rollback support

---

## Release Role

| Role | Responsibility |
|---|---|
| `RELEASE-ENGINEER.md` | Verifies release readiness, package integrity, version consistency, required validation reports, installation behavior, upgrade behavior, migration behavior, rollback planning, and artifact integrity. |

The Release Engineer does not replace the Security Engineer, Performance Engineer, QA Engineer, or Documentation Engineer.

Release Review verifies that required gates have been completed and that the release artifact matches the validated artifact.

---

## Standard Routes

### Standard Plugin Route

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

### Standard Theme Route

```text
Project Architect
↓
Role Manager
↓
Theme Architect
↓
PHP Engineer
↓
Block Engineer when required
↓
JavaScript Engineer when required
↓
CSS Engineer
↓
Security Engineer
↓
Performance Engineer
↓
QA Engineer
↓
Documentation Engineer
↓
Release Engineer
```

### Standard Review Route

```text
Role Manager
↓
Relevant Architect when architecture is in scope
↓
Relevant Implementation Engineer
↓
Security Engineer
↓
Performance Engineer when required
↓
QA Engineer
↓
Documentation Engineer when documentation impact exists
```

### Standard Debug Route

```text
Role Manager
↓
Responsible Implementation Engineer
↓
Additional Specialist Engineer when required
↓
Security Engineer when security boundaries are affected
↓
Performance Engineer when performance-related
↓
QA Engineer
↓
Documentation Engineer when behavior changes
```

### Standard Performance Route

```text
Role Manager
↓
Performance Engineer
↓
Responsible Implementation Engineer
↓
Performance Engineer Revalidation
↓
Security Engineer when security controls are affected
↓
QA Engineer
↓
Documentation Engineer when operational behavior changes
↓
Release Engineer when part of a release
```

---

## Role Routing Decision

Before complex implementation begins, the Role Manager must produce:

```text
WordPress Role Routing Decision

Task:
Selected Skill:
Project Type:
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

### Routing Status

| Status | Meaning |
|---|---|
| Ready | Required roles and gates are defined. |
| Ready with Conditions | Routing is defined but listed conditions must be resolved. |
| Needs More Information | Reliable role selection is not possible. |
| Blocked | A critical issue prevents execution. |

Implementation must not begin when Routing Status is `Needs More Information` or `Blocked`.

For `Ready with Conditions`, all implementation-blocking conditions must be resolved before implementation begins.

---

## Required Handoff Contract

Every role-to-role transition must use:

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

The receiving role must reject an incomplete handoff when missing information prevents reliable execution.

---

## Gate Failure Routing

When a validation gate fails:

```text
Gate Failure
↓
Record Finding or Defect
↓
Identify Responsible Owner
↓
Return Work to Responsible Role
↓
Apply Fix
↓
Independent Revalidation
↓
Regression Testing
↓
Resume Workflow
```

A failed gate must not be bypassed.

---

## Architecture Change Routing

If implementation reveals that approved architecture must change:

```text
Implementation Role
↓
Role Manager
↓
Specialist Architect
↓
Project Architect when project boundaries change
↓
Architecture Revision
↓
Role Routing Review
↓
Implementation Resumes
```

Engineers must not silently redesign the project during implementation.

---

## Independence Rules

The following are not equivalent:

```text
Engineer Self-Review
≠
Security Review

Developer Manual Testing
≠
QA Validation

Automated Tests
≠
Complete QA Validation

Optimization Implementation
≠
Performance Revalidation

Completed Code
≠
Release Approval
```

---

## Required Reports

Depending on the route, roles may produce:

- Approved Project Architecture Plan
- Approved Plugin Architecture Specification
- Approved Theme Architecture Specification
- PHP Implementation Report
- JavaScript Implementation Report
- CSS Implementation Report
- Database Engineering Report
- REST Engineering Report
- Block Engineering Report
- Security Review Report
- Performance Review Report
- QA Report
- Documentation Report
- Release Readiness Report

The Role Manager must identify expected reports before implementation begins.

---

## Role Boundary Rules

1. Architects define approved structure but do not replace implementation validation.
2. Engineers implement assigned work but do not approve their own independent validation gates.
3. Security Engineer reviews security independently.
4. Performance Engineer validates performance claims through evidence and measurement when practical.
5. QA Engineer validates behavior independently.
6. Documentation Engineer documents actual behavior and evidence.
7. Release Engineer verifies release readiness but does not replace earlier validation roles.
8. Role transitions must preserve context through the Handoff Contract.
9. Failed gates must return work to the responsible owner.
10. Architecture changes must be formally reviewed before implementation continues.

---

## Completion Rule

The WordPress Roles Layer has completed its responsibility for a task only when:

- the Role Manager produced a routing decision
- required roles completed their assignments
- expected reports exist
- required validation gates passed
- failed gates were remediated and independently revalidated
- QA completed required validation
- documentation was completed when required
- release review completed when applicable
- final status was returned to the parent WordPress Skill

---

## Rule

The SquirrelForge WordPress Roles Layer is the specialist execution and validation system for WordPress work. It must receive a selected Skill, route the task through the correct roles, enforce role boundaries, preserve independent validation, require complete handoffs, and return validated results to the parent WordPress workflow.
