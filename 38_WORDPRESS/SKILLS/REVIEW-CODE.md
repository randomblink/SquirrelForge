Status: Stable

---
# SquirrelForge WordPress Review Code Skill

## Purpose

Defines the workflow for reviewing existing WordPress code for correctness, security, maintainability, compatibility, accessibility, performance, and standards compliance.

## Required References

- `38_WORDPRESS/PIPELINE.md`
- `38_WORDPRESS/STANDARDS/CODE-REVIEW-STANDARD.md`
- `33_WORDPRESS_ROLES/ROLE-MANAGER.md`
- `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md`

## Output

This Skill must produce:

- code review scope;
- selected review roles;
- blocking findings;
- non-blocking recommendations;
- risk assessment;
- required validation gates;
- and final review status.

## Validation Requirements

A code review is valid only when:

- review scope is defined;
- changed or reviewed files are identified;
- relevant WordPress standards are applied;
- security, performance, accessibility, compatibility, and maintainability are considered;
- blocking issues are separated from recommendations;
- required follow-up owners are identified;
- and final review status is recorded.

## Handoff Rules

- Role selection routes through `33_WORDPRESS_ROLES/ROLE-MANAGER.md`.
- Security findings route to `33_WORDPRESS_ROLES/SECURITY-ENGINEER.md`.
- Performance findings route to `33_WORDPRESS_ROLES/PERFORMANCE-ENGINEER.md`.
- Documentation findings route to `33_WORDPRESS_ROLES/DOCUMENTATION-ENGINEER.md`.
- Implementation findings route to the responsible specialist role.

## Completion Criteria

This Skill is complete only when the review scope, findings, risk level, required fixes, validation gates, and final review status are recorded.

## Rule

A code review must identify blocking issues, required fixes, risk level, and final review status before handoff.
