Status: Stable

---
# SquirrelForge WordPress Refactor Code Skill

## Purpose

Defines the workflow for refactoring existing WordPress code without changing intended behavior.

## Required References

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/STANDARDS/REFACTORING-STANDARD.md`
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`
- `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`

## Output

This Skill must produce:

- refactor scope;
- behavior-preservation statement;
- selected implementation role;
- structural change summary;
- affected files;
- regression validation result;
- risk assessment;
- and handoff or completion status.

## Validation Requirements

A refactor is valid only when:

- intended behavior is documented before changes;
- the refactor scope is limited and clear;
- public APIs and user-facing behavior are preserved unless explicitly approved;
- regression checks pass;
- security, performance, accessibility, and compatibility risks are reviewed;
- the reason for structural change is documented;
- and follow-up owners are identified when needed.

## Handoff Rules

- Role selection routes through `33_WORDPRESS_ROLES/ROLE-MANAGER.md`.
- Security-sensitive refactors route to `33_WORDPRESS_ROLES/SECURITY-ENGINEER.md`.
- Performance-sensitive refactors route to `33_WORDPRESS_ROLES/PERFORMANCE-ENGINEER.md`.
- Documentation-impacting refactors route to `33_WORDPRESS_ROLES/DOCUMENTATION-ENGINEER.md`.
- Implementation ownership routes to the responsible specialist role.

## Completion Criteria

This Skill is complete only when behavior preservation is verified, regression checks pass, structural changes are documented, and remaining risks or follow-ups are recorded.

## Rule

A refactor must preserve intended behavior, pass regression checks, and document the reason for structural change.
