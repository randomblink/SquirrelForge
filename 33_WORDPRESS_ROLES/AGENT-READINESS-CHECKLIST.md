Status: Stable

---
# SquirrelForge WordPress Agent Readiness Checklist

## Purpose

This checklist determines whether the SquirrelForge WordPress Layer is ready to operate as a controlled WordPress development Agent.

---

## Core Readiness

- [ ] `38_WORDPRESS/WORDPRESS-MANAGER.md` exists.
- [ ] `38_WORDPRESS/PIPELINE.md` exists.
- [ ] `38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md` exists.
- [ ] `38_WORDPRESS/KNOWLEDGE/KNOWLEDGE-MANAGER.md` exists.
- [ ] `33_WORDPRESS_ROLES/ROLE-MANAGER.md` exists.
- [ ] `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md` exists.
- [ ] `38_WORDPRESS/SECURITY-VALIDATOR.md` exists.
- [ ] `38_WORDPRESS/STANDARDS/` exists.
- [ ] `38_WORDPRESS/SKILLS/` exists.
- [ ] `38_WORDPRESS/KNOWLEDGE/` exists.

---

## Control Flow Readiness

- [ ] WordPress requests route through the WordPress Manager.
- [ ] The Pipeline prevents direct request-to-code generation.
- [ ] Skill selection happens before Role routing.
- [ ] Knowledge selection happens before planning.
- [ ] Role routing happens before implementation.
- [ ] Specialist roles have defined handoffs.
- [ ] Failed gates return work to the responsible role.
- [ ] Final reports include validation status.

---

## Skill Readiness

- [ ] `38_WORDPRESS/SKILLS/CREATE-PLUGIN.md`
- [ ] `38_WORDPRESS/SKILLS/CREATE-THEME.md`
- [ ] `38_WORDPRESS/SKILLS/CREATE-BLOCK.md`
- [ ] `38_WORDPRESS/SKILLS/CREATE-REST-ENDPOINT.md`
- [ ] `38_WORDPRESS/SKILLS/CREATE-WIDGET.md`
- [ ] `38_WORDPRESS/SKILLS/REVIEW-CODE.md`
- [ ] `38_WORDPRESS/SKILLS/REFACTOR-CODE.md`
- [ ] `38_WORDPRESS/SKILLS/DEBUG-PLUGIN.md`
- [ ] `38_WORDPRESS/SKILLS/OPTIMIZE-PERFORMANCE.md`
- [ ] `38_WORDPRESS/SKILLS/CREATE-TESTS.md`
- [ ] `38_WORDPRESS/SKILLS/WRITE-DOCUMENTATION.md`

---

## Validation Readiness

- [ ] Security validation is mandatory for code work.
- [ ] QA validation is independent from engineer self-review.
- [ ] Performance validation is required when performance-sensitive work exists.
- [ ] Documentation must match validated behavior.
- [ ] Release review is required for production-ready deliverables.

---

## Agent Verdict

Use one:

```text
Ready
Ready with Conditions
Not Ready
```
## Rule

SquirrelForge is not ready to operate as a WordPress Agent until this checklist passes or all remaining gaps are explicitly documented.
