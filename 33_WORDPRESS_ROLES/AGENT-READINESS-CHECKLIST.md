Status: Stable

---
# SquirrelForge WordPress Agent Readiness Checklist

## Purpose

This checklist determines whether the SquirrelForge WordPress Layer is ready to operate as a controlled WordPress development Agent.

---

## Core Readiness

- [ ] `WORDPRESS-MANAGER.md` exists.
- [ ] `PIPELINE.md` exists.
- [ ] `SKILLS/SKILL-ROUTING-MAP.md` exists.
- [ ] `KNOWLEDGE/KNOWLEDGE-MANAGER.md` exists.
- [ ] `33_WORDPRESS_ROLES/ROLE-MANAGER.md` exists.
- [ ] `33_WORDPRESS_ROLES/ROLE-ROUTING-MATRIX.md` exists.
- [ ] `SECURITY-VALIDATOR.md` exists.
- [ ] `STANDARDS/` exists.
- [ ] `SKILLS/` exists.
- [ ] `KNOWLEDGE/` exists.

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

- [ ] `CREATE-PLUGIN.md`
- [ ] `CREATE-THEME.md`
- [ ] `CREATE-BLOCK.md`
- [ ] `CREATE-REST-ENDPOINT.md`
- [ ] `CREATE-SHORTCODE.md`
- [ ] `CREATE-WIDGET.md`
- [ ] `MIGRATE-PLUGIN.md`
- [ ] `REVIEW-CODE.md`
- [ ] `REFACTOR-CODE.md`
- [ ] `DEBUG-PLUGIN.md`
- [ ] `OPTIMIZE-PERFORMANCE.md`
- [ ] `CREATE-TESTS.md`
- [ ] `WRITE-DOCUMENTATION.md`

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
