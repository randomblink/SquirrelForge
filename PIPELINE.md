# SquirrelForge WordPress Agent Pipeline

## Purpose

This document defines the required workflow SquirrelForge must follow when handling WordPress development tasks for plugins, themes, blocks, admin tools, REST APIs, and related systems.

The pipeline coordinates intent analysis, knowledge selection, requirements, Skill selection, architecture, Role routing, specialist implementation, independent validation, testing, documentation, and release approval.

---

## Pipeline Flow

```text
User Request
↓
38_WORDPRESS/WORDPRESS-MANAGER.md
↓
[PIPELINE START]
↓
Intent Analysis
↓
Knowledge Selection
↓
Requirements Definition
↓
38_WORDPRESS/SKILLS/SKILL-ROUTING-MAP.md
↓
Architecture Planning
↓
38_WORDPRESS/ROLES/ROLE-MANAGER.md
↓
Implementation Planning
↓
Specialist Implementation
↓
Security Validation
↓
Performance Validation
↓
QA Validation
↓
Documentation Update
↓
Release Review
↓
Final Report
[PIPELINE END]
```
### Required Stages
| Stage | Responsibility |
|---|---|
| Intent Analysis | Determine what the user wants built, changed, reviewed, or fixed. |
| Knowledge Selection | Use the Knowledge Manager to select relevant WordPress references. |
| Requirements Builder | Convert the request into clear acceptance criteria. |
| Architecture Planning | Choose plugin, theme, block, REST, AJAX, cron, or admin architecture. |
| Implementation Planning | Define files, classes, hooks, assets, and tests. |
| Code Generation | Generate code according to SquirrelForge standards. |
| Security Validation | Check sanitization, escaping, nonces, permissions, SQL, uploads, and secrets. |
| Standards Validation | Check naming, architecture, PHP, CSS, JS, and documentation standards. |
| Testing Plan | Define manual and automated verification steps. |
| Code Review | Identify defects, risks, missing pieces, and refactoring needs. |
| Refactoring | Apply safe, automated improvements to code based on review findings. |
| Documentation Update | Update README, changelog, hook registry, and usage notes. |
| Final Approval | Approve only when security, structure, and testing gates pass. |
### Hard Rule

SquirrelForge must not skip Security Validation or Standards Validation for any WordPress code task.

### Approval States
| State | Meaning |
|---|---|
| Approved | Ready to apply or ship. |
| Approved with Warnings | Usable, but documented issues remain. |
| Blocked | Must not ship. |
| Needs More Information | Missing requirements prevent a safe plan. |
### Final Report Format

Every WordPress task should end with:

```text
Task:
Result:
Files Changed:
References Consulted:
Validation:
Testing:
Risks:
Next Step:
```
## Rule

The WordPress Agent Pipeline is the required lifecycle for all WordPress development work handled by SquirrelForge.