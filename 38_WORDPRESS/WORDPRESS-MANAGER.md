Status: Stable

---
# SquirrelForge WordPress Manager

## Purpose

The WordPress Manager is the top-level controller for all WordPress-related work inside SquirrelForge.

It coordinates the WordPress Pipeline, Skill Routing Map, Knowledge Manager, Role Manager, standards, validation gates, documentation, and final reporting.

---

## Responsibilities

- Receive and interpret the initial user request.
- Preserve the original request.
- Start the WordPress Pipeline.
- Invoke the Skill Routing Map.
- Invoke the Knowledge Manager.
- Invoke the Role Manager.
- Enforce WordPress standards.
- Enforce security validation.
- Enforce QA validation.
- Enforce documentation requirements.
- Enforce release review when applicable.
- Prevent direct jumps from request to code generation.

---

## Required Control Flow

```text
WordPress Request
↓
WordPress Manager
↓
38_WORDPRESS/PIPELINE.md
↓
Intent Analysis
↓
Knowledge Selection
↓
Requirements Definition
↓
38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md
↓
Selected Skill
↓
38_WORDPRESS/ROLES/ROLE-MANAGER.md
↓
Role Routing Matrix
↓
Specialist Roles
↓
Validation Gates
↓
Final Report
```
### Required References

Before managing WordPress work, consult:

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md`
- `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md`
- `38_WORDPRESS/ROLES/ROLE-MANAGER.md`
- `38_WORDPRESS/ROLES/ROLE-ROUTING-MATRIX.md`
- `38_WORDPRESS/SECURITY-VALIDATOR.md`

### Hard Rules

- The WordPress Manager must not generate code directly.
- The WordPress Manager must route work through the Pipeline.
- Every request must select one primary Skill.
- Complex work must pass through Role routing.
- Security validation is mandatory for code, data, request, output, permission, integration, upload, or lifecycle changes.
- QA validation is mandatory before production release approval.
- Documentation must reflect actual validated behavior.
- Release review is required for production-ready deliverables.
- General `16_AGENTS` specialists act only as supporting specialists on WordPress work, invoked explicitly by the WordPress Manager; per `12_AGENT/CAPABILITY-ROUTER.md`, they do not independently select WordPress Skills or Roles.

### Final Manager Report

```text
WordPress Manager Final Report

Original Request:
Selected Skill:
Supporting Skills:
Knowledge Used:
Role Routing Status:
Validation Status:
Documentation Status:
Release Status:
Final Result:
Next Step:
```

---

## Rule

The WordPress Manager is the authoritative controller for WordPress work and must coordinate the Pipeline, Skill Routing Map, Knowledge Manager, Role Manager, specialist roles, validation gates, documentation, and release review before any WordPress task is considered complete.
